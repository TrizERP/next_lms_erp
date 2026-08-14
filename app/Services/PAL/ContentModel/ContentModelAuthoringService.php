<?php

namespace App\Services\PAL\ContentModel;

use App\Services\PAL\Content\PalVocabulary;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The authoring interface's write path (spec §9.1) and the overlay merge.
 *
 * The Content Model is projected from `semantic_intelligence` on every read, so
 * an author's edit cannot be a row that replaces the projection — it is a PATCH
 * layered over it. That gives three properties the spec asks for and a straight
 * copy would not:
 *
 *   - re-running the extractor refreshes every node immediately, and an author's
 *     edits survive it because they were never a copy of the source in the first
 *     place;
 *   - "what did a human change" is answerable by looking at the overlay alone;
 *   - version control (§9.1 "every save creates a revision, rollback in 2
 *     clicks") is a snapshot of a small patch, not of a whole content tree.
 *
 * CONTENT LAW C4/C5 are enforced here, not in the controller: only `approved`
 * content is servable, only a human may write a human_only status, and every
 * status change is stamped and logged.
 */
class ContentModelAuthoringService
{
    public function __construct(
        protected ContentModelProjector $projector,
        protected ContentMetadataDeriver $deriver
    ) {}

    // ── Read ─────────────────────────────────────────────────────────────────

    /** The override row for one node, or null. */
    public function override(string $nodeKey, int $tenant): ?array
    {
        $row = DB::table('pal_cm_node_overrides')
            ->where('node_key', $nodeKey)
            ->where('sub_institute_id', $tenant)
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * Overrides for a whole chapter, keyed by node_key — one query instead of
     * one per node, which matters because a chapter projects hundreds of nodes.
     *
     * @return array<string,array>
     */
    public function overridesForChapter(int $semanticId, int $tenant): array
    {
        $rows = DB::table('pal_cm_node_overrides')
            ->where('sub_institute_id', $tenant)
            ->where('semantic_id', $semanticId)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->node_key] = $this->hydrate($row);
        }

        return $out;
    }

    /**
     * Merge an override over a projected node.
     *
     * The projection always wins for anything the override does not mention, so
     * an edit to one field never freezes the other thirty against future
     * extractions.
     */
    public function merge(array $node, ?array $override): array
    {
        $node['has_override'] = $override !== null;

        if ($override === null) {
            $node['quality_status'] = $node['quality_status'] ?? 'draft';
            $node['servable'] = PalVocabulary::isServable($node['quality_status']);

            return $node;
        }

        foreach (['title', 'body', 'media_url'] as $field) {
            if ($override[$field] !== null && $override[$field] !== '') {
                $node[$field] = $override[$field];
                $node['overridden_fields'][] = $field;
            }
        }

        if (! empty($override['metadata'])) {
            $node['metadata'] = array_merge($node['metadata'] ?? [], $override['metadata']);
            $node['overridden_fields'] = array_merge($node['overridden_fields'] ?? [], array_keys($override['metadata']));
        }

        if (! empty($override['language_variants'])) {
            $node['language_variants'] = $override['language_variants'];
            $available = array_values(array_unique(array_merge(
                [$node['metadata']['language'] ?? config('pal_content.default_language', 'en')],
                array_keys($override['language_variants'])
            )));
            $node['metadata']['language_variants_available'] = $available;
        }

        $node['quality_status'] = $override['quality_status'];
        $node['metadata']['quality_status'] = $override['quality_status'];
        $node['tagged_by'] = $override['tagged_by'];
        $node['metadata']['tagged_by'] = $override['tagged_by'];
        $node['metadata']['reviewed_by'] = $override['reviewed_by'];
        $node['metadata']['reviewed_at'] = $override['reviewed_at'];
        $node['metadata']['version'] = (string) $override['version'];
        $node['version'] = $override['version'];
        $node['servable'] = PalVocabulary::isServable($override['quality_status']);
        $node['overridden_fields'] = array_values(array_unique($node['overridden_fields'] ?? []));

        // Recompute after the merge — a human edit changes both.
        $contentType = (string) ($node['content_type'] ?? 'concept');
        $node['missing_mandatory'] = $this->deriver->missingMandatory($contentType, $node['metadata']);
        $node['completeness'] = $this->deriver->completeness($node['metadata']);

        return $node;
    }

    // ── Write ────────────────────────────────────────────────────────────────

    /**
     * Save an author's edit. Always creates a revision.
     *
     * @param  array  $payload  title|body|media_url|metadata|language_variants
     * @throws InvalidArgumentException on a vocabulary or transition violation
     */
    public function save(string $nodeKey, int $tenant, array $payload, int $userId, string $actorType = 'human'): array
    {
        $parsed = $this->projector->parseNodeKey($nodeKey);
        if ($parsed === null) {
            throw new InvalidArgumentException("'{$nodeKey}' is not a valid node key.");
        }

        $existing = DB::table('pal_cm_node_overrides')
            ->where('node_key', $nodeKey)
            ->where('sub_institute_id', $tenant)
            ->first();

        // CONTENT LAW C5: a batch/AI writer may not touch approved content, and
        // may not write a status only a human may write.
        if ($actorType !== 'human' && $existing !== null && PalVocabulary::isServable($existing->quality_status)) {
            throw new InvalidArgumentException('This node is approved. A machine writer may not modify approved content.');
        }

        $metadata = $this->cleanMetadata($payload['metadata'] ?? []);
        if ($metadata !== []) {
            $errors = PalVocabulary::validate($metadata);
            if ($errors !== []) {
                throw new InvalidArgumentException(implode(' ', $errors));
            }
        }

        $variants = $this->cleanLanguageVariants($payload['language_variants'] ?? []);

        $changed = [];
        $update = [
            'semantic_id' => $parsed['semantic_id'],
            'concept_slug' => $parsed['concept_slug'],
            'content_type' => $parsed['type'],
            'updated_by' => $userId,
            'tagged_by' => $actorType === 'human' ? 'human' : 'ai',
            'updated_at' => now(),
        ];

        foreach (['title', 'body', 'media_url'] as $field) {
            if (array_key_exists($field, $payload)) {
                $value = $payload[$field];
                $update[$field] = ($value === '' ? null : $value);
                $changed[] = $field;
            }
        }

        if ($metadata !== []) {
            // Merge rather than replace, so a form that posts one field does not
            // silently erase the thirty it did not render.
            $previous = $existing !== null ? (json_decode((string) $existing->metadata, true) ?: []) : [];
            $update['metadata'] = json_encode(array_merge($previous, $metadata), JSON_UNESCAPED_UNICODE);
            $changed = array_merge($changed, array_keys($metadata));
        }

        if ($variants !== []) {
            $previous = $existing !== null ? (json_decode((string) $existing->language_variants, true) ?: []) : [];
            $update['language_variants'] = json_encode(array_merge($previous, $variants), JSON_UNESCAPED_UNICODE);
            $changed = array_merge($changed, array_map(fn ($l) => "language:{$l}", array_keys($variants)));
        }

        if (array_key_exists('confidence', $payload) && is_numeric($payload['confidence'])) {
            $update['confidence'] = (float) $payload['confidence'];
        }

        if ($existing === null) {
            $update['node_key'] = $nodeKey;
            $update['sub_institute_id'] = $tenant;
            $update['quality_status'] = 'draft';
            $update['version'] = 1;
            $update['created_by'] = $userId;
            $update['created_at'] = now();

            DB::table('pal_cm_node_overrides')->insert($update);
            $version = 1;
        } else {
            $version = (int) $existing->version + 1;
            $update['version'] = $version;

            // An edit to approved content sends it back for review — the
            // approval was of the previous text, not of this one.
            if (PalVocabulary::isServable($existing->quality_status) && $changed !== []) {
                $update['quality_status'] = 'reviewed';
                $update['reviewed_by'] = null;
                $update['reviewed_at'] = null;
                $changed[] = 'quality_status';
            }

            DB::table('pal_cm_node_overrides')
                ->where('id', $existing->id)
                ->update($update);
        }

        $saved = $this->override($nodeKey, $tenant);

        $this->recordRevision(
            $nodeKey,
            $tenant,
            $version,
            $saved ?? [],
            array_values(array_unique($changed)),
            $existing->quality_status ?? null,
            $saved['quality_status'] ?? 'draft',
            $actorType,
            $userId,
            $payload['note'] ?? null
        );

        return $saved ?? [];
    }

    /**
     * Move a node through the QA pipeline (spec §7.1).
     *
     * A node with mandatory metadata still missing cannot be approved — that is
     * the whole point of the mandatory list, and letting it through here would
     * put an untagged item in front of a learner.
     */
    public function transition(
        string $nodeKey,
        int $tenant,
        string $toStatus,
        int $userId,
        string $actorType,
        ?string $note,
        array $projectedNode = []
    ): array {
        if (! PalVocabulary::isQualityStatus($toStatus)) {
            throw new InvalidArgumentException("'{$toStatus}' is not a registered quality status.");
        }

        if ($actorType !== 'human' && ! PalVocabulary::isMachineWritable($toStatus)) {
            throw new InvalidArgumentException("Only a person may set the status '{$toStatus}'.");
        }

        $existing = DB::table('pal_cm_node_overrides')
            ->where('node_key', $nodeKey)
            ->where('sub_institute_id', $tenant)
            ->first();

        $from = $existing->quality_status ?? null;

        // A node with no override yet is implicitly a fresh draft.
        if ($existing === null && $toStatus !== 'draft' && ! PalVocabulary::canTransition('draft', $toStatus)) {
            throw new InvalidArgumentException("A new node cannot move straight to '{$toStatus}'.");
        }
        if ($existing !== null && ! PalVocabulary::canTransition($from, $toStatus)) {
            throw new InvalidArgumentException("'{$from}' cannot move to '{$toStatus}'.");
        }

        if (PalVocabulary::isServable($toStatus) && $projectedNode !== []) {
            $merged = $this->merge($projectedNode, $existing === null ? null : $this->hydrate($existing));
            $missing = $merged['missing_mandatory'] ?? [];
            if ($missing !== []) {
                throw new InvalidArgumentException(
                    'Cannot approve: these mandatory fields are still empty — ' . implode(', ', $missing) . '.'
                );
            }
            if (($merged['gap'] ?? false) === true) {
                throw new InvalidArgumentException('Cannot approve: this node has no content behind it yet.');
            }
        }

        $parsed = $this->projector->parseNodeKey($nodeKey);
        if ($parsed === null) {
            throw new InvalidArgumentException("'{$nodeKey}' is not a valid node key.");
        }

        $stamp = [
            'quality_status' => $toStatus,
            'review_note' => $note,
            'updated_by' => $userId,
            'updated_at' => now(),
        ];
        // Only a real review stamps a reviewer.
        if (in_array($toStatus, ['reviewed', 'pedagogy_reviewed', 'approved'], true)) {
            $stamp['reviewed_by'] = $userId;
            $stamp['reviewed_at'] = now();
        }

        if ($existing === null) {
            DB::table('pal_cm_node_overrides')->insert($stamp + [
                'node_key' => $nodeKey,
                'sub_institute_id' => $tenant,
                'semantic_id' => $parsed['semantic_id'],
                'concept_slug' => $parsed['concept_slug'],
                'content_type' => $parsed['type'],
                'tagged_by' => $actorType === 'human' ? 'human' : 'ai',
                'version' => 1,
                'created_by' => $userId,
                'created_at' => now(),
            ]);
            $version = 1;
        } else {
            $version = (int) $existing->version + 1;
            DB::table('pal_cm_node_overrides')->where('id', $existing->id)->update($stamp + ['version' => $version]);
        }

        $saved = $this->override($nodeKey, $tenant) ?? [];

        $this->recordRevision($nodeKey, $tenant, $version, $saved, ['quality_status'], $from, $toStatus, $actorType, $userId, $note);

        return $saved;
    }

    /**
     * Apply the same transition to many nodes. One illegal move does not sink
     * the batch — it comes back in `failed` with the reason, so the reviewer
     * sees exactly which row was refused and why.
     */
    public function bulkTransition(array $nodeKeys, int $tenant, string $toStatus, int $userId, ?string $note): array
    {
        $ok = [];
        $failed = [];

        foreach ($nodeKeys as $nodeKey) {
            try {
                $this->transition((string) $nodeKey, $tenant, $toStatus, $userId, 'human', $note);
                $ok[] = $nodeKey;
            } catch (\Throwable $e) {
                $failed[] = ['node_key' => $nodeKey, 'error' => $e->getMessage()];
            }
        }

        return [
            'updated' => $ok,
            'failed' => $failed,
            'ok_count' => count($ok),
            'fail_count' => count($failed),
        ];
    }

    // ── Version control (spec §9.1) ──────────────────────────────────────────

    public function revisions(string $nodeKey, int $tenant, int $limit = 50): array
    {
        return DB::table('pal_cm_node_revisions')
            ->where('node_key', $nodeKey)
            ->where('sub_institute_id', $tenant)
            ->orderByDesc('version')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'version' => (int) $row->version,
                    'changed_fields' => json_decode((string) $row->changed_fields, true) ?: [],
                    'from_status' => $row->from_status,
                    'to_status' => $row->to_status,
                    'actor_type' => $row->actor_type,
                    'actor_id' => $row->actor_id !== null ? (int) $row->actor_id : null,
                    'note' => $row->note,
                    'created_at' => $row->created_at,
                    'snapshot' => json_decode((string) $row->snapshot, true) ?: [],
                ];
            })
            ->all();
    }

    /**
     * Roll back to a previous revision. The restore is itself a new revision,
     * so the history is append-only and a rollback can be rolled back.
     */
    public function restore(string $nodeKey, int $tenant, int $version, int $userId): array
    {
        $row = DB::table('pal_cm_node_revisions')
            ->where('node_key', $nodeKey)
            ->where('sub_institute_id', $tenant)
            ->where('version', $version)
            ->first();

        if ($row === null) {
            throw new InvalidArgumentException("Version {$version} does not exist for this node.");
        }

        $snapshot = json_decode((string) $row->snapshot, true) ?: [];

        return $this->save($nodeKey, $tenant, [
            'title' => $snapshot['title'] ?? null,
            'body' => $snapshot['body'] ?? null,
            'media_url' => $snapshot['media_url'] ?? null,
            'metadata' => $snapshot['metadata'] ?? [],
            'language_variants' => $snapshot['language_variants'] ?? [],
            'note' => "Restored from version {$version}.",
        ], $userId, 'human');
    }

    protected function recordRevision(
        string $nodeKey,
        int $tenant,
        int $version,
        array $snapshot,
        array $changedFields,
        ?string $fromStatus,
        string $toStatus,
        string $actorType,
        int $userId,
        ?string $note
    ): void {
        DB::table('pal_cm_node_revisions')->updateOrInsert(
            ['node_key' => $nodeKey, 'sub_institute_id' => $tenant, 'version' => $version],
            [
                'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'changed_fields' => json_encode(array_values($changedFields), JSON_UNESCAPED_UNICODE),
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_type' => $actorType,
                'actor_id' => $userId,
                'note' => $note,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    // ── Review queue ─────────────────────────────────────────────────────────

    /**
     * Everything a human has touched or the AI has proposed, filtered by status.
     * This is the authoring console's work list; nodes with no override are not
     * here because there is nothing to review about a pure projection until
     * somebody acts on it.
     */
    public function reviewQueue(int $tenant, array $filters = [], int $limit = 100): array
    {
        $query = DB::table('pal_cm_node_overrides')
            ->where('sub_institute_id', $tenant);

        if (! empty($filters['status'])) {
            $query->where('quality_status', $filters['status']);
        }
        if (! empty($filters['content_type'])) {
            $query->where('content_type', $filters['content_type']);
        }
        if (! empty($filters['semantic_id'])) {
            $query->where('semantic_id', (int) $filters['semantic_id']);
        }
        if (! empty($filters['tagged_by'])) {
            $query->where('tagged_by', $filters['tagged_by']);
        }

        return $query
            // Least-confident first: that is where a reviewer's time is worth most.
            ->orderByRaw('confidence IS NULL, confidence ASC')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->hydrate($row))
            ->all();
    }

    /** Status counts for this tenant, for the pipeline strip. */
    public function pipelineCounts(int $tenant, ?int $semanticId = null): array
    {
        $rows = DB::table('pal_cm_node_overrides')
            ->select('quality_status', DB::raw('COUNT(*) as n'))
            ->where('sub_institute_id', $tenant)
            ->when($semanticId !== null, fn ($q) => $q->where('semantic_id', $semanticId))
            ->groupBy('quality_status')
            ->get();

        $counts = array_fill_keys(array_keys(config('pal_content.quality_statuses', [])), 0);
        foreach ($rows as $row) {
            $counts[$row->quality_status] = (int) $row->n;
        }

        return $counts;
    }

    // ── Internals ────────────────────────────────────────────────────────────

    protected function hydrate(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'node_key' => $row->node_key,
            'sub_institute_id' => (int) $row->sub_institute_id,
            'semantic_id' => $row->semantic_id !== null ? (int) $row->semantic_id : null,
            'concept_slug' => $row->concept_slug,
            'content_type' => $row->content_type,
            'title' => $row->title,
            'body' => $row->body,
            'media_url' => $row->media_url,
            'metadata' => json_decode((string) $row->metadata, true) ?: [],
            'language_variants' => json_decode((string) $row->language_variants, true) ?: [],
            'quality_status' => $row->quality_status,
            'tagged_by' => $row->tagged_by,
            'confidence' => $row->confidence !== null ? (float) $row->confidence : null,
            'version' => (int) $row->version,
            'created_by' => $row->created_by !== null ? (int) $row->created_by : null,
            'updated_by' => $row->updated_by !== null ? (int) $row->updated_by : null,
            'reviewed_by' => $row->reviewed_by !== null ? (int) $row->reviewed_by : null,
            'reviewed_at' => $row->reviewed_at,
            'review_note' => $row->review_note,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Drop keys that are not part of the metadata schema, and normalise the
     * couple of fields that arrive as JSON strings from a form post.
     */
    protected function cleanMetadata(array $metadata): array
    {
        $allowed = [];
        foreach (config('pal_content_model.metadata_field_groups', []) as $fields) {
            foreach ($fields as $field) {
                $allowed[$field] = true;
            }
        }

        // Never writable through the authoring form — they are stamped by the
        // service, and letting a client set them would forge an approval.
        unset($allowed['node_key'], $allowed['reviewed_by'], $allowed['reviewed_at'], $allowed['quality_status'], $allowed['tagged_by']);

        $clean = [];
        foreach ($metadata as $key => $value) {
            if (! isset($allowed[$key])) {
                continue;
            }
            if (is_string($value) && str_starts_with(trim($value), '[')) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }
            $clean[$key] = $value === '' ? null : $value;
        }

        return $clean;
    }

    protected function cleanLanguageVariants(array $variants): array
    {
        $languages = config('pal_content.languages', []);

        $clean = [];
        foreach ($variants as $language => $payload) {
            if (! in_array($language, $languages, true) || ! is_array($payload)) {
                continue;
            }
            $body = $payload['body'] ?? null;
            if (! is_string($body) || trim($body) === '') {
                continue;
            }
            $clean[$language] = [
                'title' => is_string($payload['title'] ?? null) ? $payload['title'] : null,
                'body' => $body,
                'source' => in_array($payload['source'] ?? null, ['human', 'llm'], true) ? $payload['source'] : 'human',
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        return $clean;
    }
}
