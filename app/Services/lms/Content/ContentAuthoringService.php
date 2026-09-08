<?php

namespace App\Services\lms\Content;

use App\Models\lms\ContentAuthoringAudit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * One authoring capability, reused across Classroom Resource, Teacher Workspace and
 * Question Bank — tracker row 3 / Decision #36.
 *
 * > "Generate (AI) and Upload capabilities are built once ... and reused with a
 * >  content-type parameter across Classroom Resource, Teacher Resource, and Question
 * >  Bank."
 *
 * The three existing implementations differ in almost every respect — different providers,
 * different validation, different storage paths, different response envelopes, different
 * error handling. What they have in COMMON is exactly four steps, and this class is those
 * four steps:
 *
 *      1. resolve what kind of thing is being authored   (AuthoringTypeRegistry)
 *      2. produce the artefact                            (gateway OR upload service)
 *      3. persist the content row
 *      4. record who owns it                              (ContentProvenanceService)
 *
 * Steps 3 and 4 run in ONE transaction. That is the invariant this class exists to hold:
 * content without provenance is exactly the state the estate is already in — 96,479 rows
 * that had to be classified retroactively by a backfill, and 9 that could not be.
 *
 * OWNERSHIP IS DERIVED, NEVER ACCEPTED FROM THE CLIENT.
 * ContentProvenanceService enforces a strict XOR: platform ownership requires the platform
 * tenant, and the platform tenant requires platform ownership. Tenant 1 holds 16,379 of
 * the 31,385 content_master rows, so a client-supplied "teacher" ownership on tenant 1
 * would throw on a large fraction of real traffic. deriveOwnership() is the only writer.
 */
class ContentAuthoringService
{
    public function __construct(
        private AuthoringTypeRegistry $registry,
        private ContentGenerationGateway $gateway,
        private ContentUploadService $uploads,
        private ContentProvenanceService $provenance,
        private LmsContentVocabulary $vocabulary
    ) {
    }

    /**
     * Author one piece of content.
     *
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>  $actor   normalised identity from the lms.auth middleware
     * @return array<string,mixed>
     */
    public function author(array $input, array $actor, ?UploadedFile $file = null): array
    {
        $type = (string) $input['content_type'];
        $mode = (string) $input['mode'];

        if (! $this->registry->supportsMode($type, $mode)) {
            throw new RuntimeException(sprintf(
                'Content type "%s" does not support mode "%s". Supported: %s.',
                $type,
                $mode,
                implode(', ', (array) ($this->registry->get($type)['modes'] ?? []))
            ));
        }

        $tenantId = (int) ($actor['sub_institute_id'] ?? 0);

        if ($tenantId <= 0) {
            throw new RuntimeException('The authoring request has no resolvable tenant.');
        }

        $audit = $this->openAudit($input, $actor, $type, $mode, $tenantId);

        // An identical in-flight or completed request returns the original result rather
        // than billing a second provider call. QUEUE_CONNECTION is `sync`, so generation
        // runs inline and a user watching a slow spinner will click again.
        if ($audit->status === 'succeeded') {
            return [
                'idempotent_replay' => true,
                'audit_id'          => $audit->id,
                'entity_type'       => $audit->entity_type,
                'entity_ids'        => $audit->entity_ids ?? [],
            ];
        }

        try {
            $artefact = $mode === 'generate'
                ? $this->generate($type, $input)
                : $this->upload($type, $tenantId, $input, $file);

            $result = DB::transaction(function () use ($type, $mode, $input, $actor, $tenantId, $artefact) {
                return $this->persist($type, $mode, $input, $actor, $tenantId, $artefact);
            });

            $audit->update([
                'status'        => 'succeeded',
                'entity_type'   => $this->registry->entityType($type),
                'entity_ids'    => $result['entity_ids'],
                'provider'      => $artefact['provider'] ?? null,
                'model'         => $artefact['model'] ?? null,
                'input_tokens'  => $artefact['input_tokens'] ?? null,
                'output_tokens' => $artefact['output_tokens'] ?? null,
                'latency_ms'    => $artefact['latency_ms'] ?? null,
            ]);

            return $result + ['audit_id' => $audit->id, 'idempotent_replay' => false];
        } catch (Throwable $e) {
            $audit->update(['status' => 'failed', 'error' => $e->getMessage()]);

            // Object storage is not transactional: if the file landed but the row did not,
            // remove it. Where removal fails, log the key so a sweeper can reconcile —
            // an orphan is not a reason to fail the user's request twice.
            if (! empty($artefact['upload']['path'])) {
                if (! $this->uploads->forget($artefact['upload']['path'])) {
                    Log::channel('daily')->warning('lms.authoring: orphaned upload', [
                        'path' => $artefact['upload']['path'],
                        'audit_id' => $audit->id,
                    ]);
                }
            }

            throw $e;
        }
    }

    /** @return array<string,mixed> */
    public function vocabulary(): array
    {
        return [
            'authoring_types' => $this->registry->vocabulary(),
            'ownership'       => $this->vocabulary->ownershipKeys(),
            'modes'           => ['generate', 'upload'],
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    private function generate(string $type, array $input): array
    {
        $provider = $this->registry->provider($type);

        if ($provider === null) {
            throw new RuntimeException("No generator is registered for content type \"{$type}\".");
        }

        return $this->gateway->generate($provider, $input);
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    private function upload(string $type, int $tenantId, array $input, ?UploadedFile $file): array
    {
        if ($file === null) {
            throw new RuntimeException('An upload request must include a file.');
        }

        return [
            'provider' => null,
            'model'    => null,
            'upload'   => $this->uploads->store($file, $type, $tenantId, $input['chapter_id'] ?? null),
        ];
    }

    /**
     * Write the content row and its provenance row, together.
     *
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>  $actor
     * @param  array<string,mixed>  $artefact
     * @return array<string,mixed>
     */
    private function persist(string $type, string $mode, array $input, array $actor, int $tenantId, array $artefact): array
    {
        $entityType = $this->registry->entityType($type);

        // Question generation persists through QuestionGenerationService, which owns a
        // dedup index this class must not duplicate. It returns ids; we record ownership.
        if ($entityType === 'question') {
            $ids = $this->questionIds($artefact['output'] ?? null);

            if ($ids !== []) {
                $this->provenance->recordMany(array_map(fn ($id) => [
                    'entity_type'            => 'question',
                    'entity_id'              => $id,
                    'owner_sub_institute_id' => $tenantId,
                    'ownership'              => $this->deriveOwnership($tenantId),
                    'authored_by_user_id'    => $actor['user_id'] ?? null,
                    'authoring_mode'         => 'generate',
                    'generation_source'      => $artefact['model'] ?? null,
                    'visibility'             => $this->visibilityFor($tenantId),
                ], $ids));
            }

            return ['entity_type' => 'question', 'entity_ids' => $ids];
        }

        $upload = $artefact['upload'] ?? null;

        $row = [
            'chapter_id'       => $input['chapter_id'] ?? null,
            'subject_id'       => $input['subject_id'] ?? null,
            'standard_id'      => $input['standard_id'] ?? null,
            'concept_id'       => $input['concept_id'] ?? null,
            'title'            => $input['title'] ?? ($this->registry->get($type)['label'] ?? 'Untitled content'),
            'description'      => is_string($artefact['output'] ?? null) ? $artefact['output'] : ($input['description'] ?? null),
            'content_category' => $this->registry->category($type),
            'file_type'        => $upload['extension'] ?? ($mode === 'generate' ? 'pdf' : null),
            'file_size'        => $upload['bytes'] ?? null,
            'filename'         => $upload['filename'] ?? null,
            'url'              => $upload['url'] ?? ($input['url'] ?? null),
            'sub_institute_id' => $tenantId,
            'syear'            => $input['syear'] ?? null,
            'created_by'       => $actor['user_id'] ?? null,
            // The existing free-text column. Provenance is the new truth; this keeps its
            // established literals so ApiLmsCourseController::applyContentSourceFilter
            // (which matches on 'Gamma AI') is unaffected.
            'source'           => $mode === 'generate' ? ($artefact['provider'] === 'gamma' ? 'Gamma AI' : 'Gemini AI') : 'Uploaded',
        ];

        $contentId = (int) DB::table('content_master')->insertGetId(array_filter(
            $row,
            static fn ($v) => $v !== null
        ));

        $this->provenance->record('content', $contentId, $tenantId, [
            'ownership'              => $this->deriveOwnership($tenantId),
            'authored_by_user_id'    => $actor['user_id'] ?? null,
            'authored_by_profile'    => $actor['user_profile_name'] ?? null,
            'authoring_mode'         => $mode,
            'generation_source'      => $row['source'],
            // The overlay pointer, written forward for the first time here. The backfill
            // deliberately leaves it NULL because a historical derivation cannot be
            // reconstructed after the fact.
            'derived_from_entity_id' => $input['derived_from_entity_id'] ?? null,
            'visibility'             => $this->visibilityFor($tenantId),
        ]);

        return ['entity_type' => 'content', 'entity_ids' => [$contentId]];
    }

    /**
     * Ownership is a function of the tenant, never of client input.
     *
     * ContentProvenanceService enforces platform-tenant XOR platform-ownership. Deriving
     * it here is what keeps that invariant from throwing on the 52% of content that lives
     * on the platform tenant.
     */
    private function deriveOwnership(int $tenantId): string
    {
        return $this->vocabulary->isPlatformTenant($tenantId) ? 'platform' : 'school';
    }

    private function visibilityFor(int $tenantId): string
    {
        return $this->vocabulary->isPlatformTenant($tenantId) ? 'global' : 'tenant';
    }

    /**
     * Pull created question ids out of whatever QuestionGenerationService returned.
     *
     * Tolerant on purpose — that service is a black box to this class, and a shape change
     * there must degrade to "no provenance recorded" rather than a failed generation.
     *
     * @return list<int>
     */
    private function questionIds(mixed $output): array
    {
        if (! is_array($output)) {
            return [];
        }

        foreach (['question_ids', 'ids', 'saved_ids'] as $key) {
            if (isset($output[$key]) && is_array($output[$key])) {
                return array_values(array_filter(array_map('intval', $output[$key])));
            }
        }

        if (isset($output['questions']) && is_array($output['questions'])) {
            return array_values(array_filter(array_map(
                static fn ($q) => is_array($q) && isset($q['id']) ? (int) $q['id'] : 0,
                $output['questions']
            )));
        }

        return [];
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>  $actor
     */
    private function openAudit(array $input, array $actor, string $type, string $mode, int $tenantId): ContentAuthoringAudit
    {
        $key = $input['idempotency_key'] ?? hash('sha256', json_encode([
            $actor['user_id'] ?? null, $tenantId, $type, $mode,
            $input['chapter_id'] ?? null, $input['concept_id'] ?? null,
            $input['prompt'] ?? null, $input['title'] ?? null,
        ]));

        return ContentAuthoringAudit::firstOrCreate(
            ['idempotency_key' => (string) $key],
            [
                'actor_user_id'    => $actor['user_id'] ?? null,
                'sub_institute_id' => $tenantId,
                'module'           => 'lms_content',
                'authoring_type'   => $type,
                'mode'             => $mode,
                'chapter_id'       => $input['chapter_id'] ?? null,
                'concept_id'       => $input['concept_id'] ?? null,
                'status'           => 'pending',
            ]
        );
    }
}
