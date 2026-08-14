<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Services\PAL\Content\PalVocabulary;
use App\Services\PAL\ContentModel\ContentMetadataDeriver;
use App\Services\PAL\ContentModel\ContentModelAuthoringService;
use App\Services\PAL\ContentModel\ContentModelCoverageService;
use App\Services\PAL\ContentModel\ContentModelEnrichmentService;
use App\Services\PAL\ContentModel\ContentModelProjector;
use App\Services\PAL\ContentModel\MisconceptionProjector;
use App\Services\PAL\ContentModel\SemanticSourceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * New PAL → Content Model API.
 *
 * Serves the PAL V4 Content Intelligence Layer projected live out of
 * `semantic_intelligence`. Nothing in the response is stored content: the
 * four-type model, the Bloom ladder, the misconception library and the 30+
 * field metadata schema are computed per request from the extracted chapter
 * data, with the authoring overlay merged on top.
 *
 * Mounted under the same `pal.auth` middleware as the rest of /api/pal/*.
 * Content rows have no learner for the middleware to scope through, so tenancy
 * is resolved here from the caller's own token — the same rule
 * PalContentIntelligenceController uses, so the two agree.
 *
 * Envelope: {success: true, data: …} / {success: false, message: …}.
 */
class NewPalContentModelController extends Controller
{
    public function __construct(
        protected SemanticSourceRepository $source,
        protected ContentModelProjector $projector,
        protected MisconceptionProjector $misconceptions,
        protected ContentMetadataDeriver $deriver,
        protected ContentModelAuthoringService $authoring,
        protected ContentModelEnrichmentService $enrichment,
        protected ContentModelCoverageService $coverage
    ) {}

    // ══════════════════════════════════════════════════════════════════════
    // Vocabulary + coverage
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/pal/new/content-model/vocabulary
     *
     * Everything the authoring UI needs to render its selectors. The closed
     * vocabularies come from the same registry the existing Content
     * Intelligence layer validates against, so a value that renders here is a
     * value the write path will accept.
     */
    public function vocabulary(Request $request): JsonResponse
    {
        return $this->ok([
            'closed_vocabulary' => PalVocabulary::all(),
            'variant_blueprint' => config('pal_content_model.variant_blueprint'),
            'metadata_field_groups' => config('pal_content_model.metadata_field_groups'),
            'mandatory_fields' => config('pal_content_model.mandatory_fields'),
            'llm_only_fields' => config('pal_content_model.llm_only_fields'),
            'framework_vocabulary' => $this->enrichment->frameworkVocabulary(),
            'dok_levels' => config('pal_content_model.dok_levels'),
            'ladder' => config('pal_content_model.ladder'),
            'node_prefixes' => config('pal_content_model.node_prefixes'),
            'ai' => [
                'available' => $this->enrichment->available(),
                'unavailable_reason' => $this->enrichment->unavailableReason(),
            ],
        ]);
    }

    /** GET /api/pal/new/content-model/coverage */
    public function coverage(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'Coverage reporting is not available to students.')) {
            return $denied;
        }

        return $this->ok($this->coverage->estate($this->tenantFor($request)));
    }

    // ══════════════════════════════════════════════════════════════════════
    // Chapters — the extracted source
    // ══════════════════════════════════════════════════════════════════════

    /** GET /api/pal/new/content-model/chapters */
    public function chapters(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $tenant = $this->tenantFor($request);

        $chapters = $this->source->listChapters($tenant, [
            'subject' => $request->query('subject'),
            'standard' => $request->query('standard'),
            'chapter_id' => $request->query('chapter_id'),
            'search' => $request->query('search'),
        ]);

        return $this->ok([
            'sub_institute_id' => $tenant,
            'count' => count($chapters),
            'facets' => $this->source->facets($tenant),
            'chapters' => $chapters,
        ]);
    }

    /**
     * GET /api/pal/new/content-model/chapters/{semanticId}
     *
     * The chapter's scored Content Model: every concept with its four-type
     * counts, plus the seven requirement scores.
     */
    public function chapter(Request $request, int $semanticId): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $data = $this->coverage->chapter($semanticId, $this->tenantFor($request));
        if ($data === null) {
            return $this->fail('That extracted chapter was not found, or belongs to another institute.', 404);
        }

        return $this->ok($data);
    }

    /**
     * GET /api/pal/new/content-model/chapters/{semanticId}/concepts/{conceptSlug}
     *
     * The complete four-type model for one concept, overrides merged in.
     */
    public function concept(Request $request, int $semanticId, string $conceptSlug): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $tenant = $this->tenantFor($request);
        $projected = $this->projector->projectConcept($semanticId, $conceptSlug, $tenant);

        if ($projected === null) {
            return $this->fail('That concept was not found in the extracted chapter.', 404);
        }

        $overrides = $this->authoring->overridesForChapter($semanticId, $tenant ?? 0);
        $projected = $this->mergeConcept($projected, $overrides);

        return $this->ok($projected);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Individual node read / write
    // ══════════════════════════════════════════════════════════════════════

    /** GET /api/pal/new/content-model/nodes/{nodeKey} */
    public function node(Request $request, string $nodeKey): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $tenant = $this->tenantFor($request);
        $found = $this->findNode($nodeKey, $tenant);

        if ($found === null) {
            return $this->fail("No projected node matches '{$nodeKey}'. It may belong to a chapter that has been re-extracted.", 404);
        }

        $node = $found['node'];
        $metadata = $node['metadata'] ?? [];

        return $this->ok([
            'node' => $node,
            'concept' => $found['concept_header'],
            'chapter' => $found['chapter'],
            'provenance' => $this->deriver->provenance($metadata),
            'llm_candidates' => $this->deriver->llmCandidates($metadata),
            'revisions' => $this->authoring->revisions($nodeKey, $tenant ?? 0, 20),
            'allowed_transitions' => $this->allowedTransitions($node['quality_status'] ?? 'draft'),
        ]);
    }

    /**
     * POST /api/pal/new/content-model/nodes/{nodeKey}
     *
     * Save an authored edit. Always creates a revision (spec §9.1).
     */
    public function saveNode(Request $request, string $nodeKey): JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');
        if ($denied = $this->denyStudents($request, 'Students may not author content.')) {
            return $denied;
        }

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('Cannot resolve a single institute for this write. Undecidable tenancy is rejected.', 422);
        }

        if ($this->findNode($nodeKey, $tenant) === null) {
            return $this->fail("No projected node matches '{$nodeKey}'.", 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|nullable|string|max:512',
            'body' => 'sometimes|nullable|string',
            'media_url' => 'sometimes|nullable|string|max:2000',
            'metadata' => 'sometimes|array',
            'language_variants' => 'sometimes|array',
            'note' => 'sometimes|nullable|string|max:2000',
        ]);

        try {
            $saved = $this->authoring->save($nodeKey, $tenant, $validated, (int) $auth['user_id']);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $refreshed = $this->findNode($nodeKey, $tenant);

        return $this->ok([
            'override' => $saved,
            'node' => $refreshed['node'] ?? null,
            'revisions' => $this->authoring->revisions($nodeKey, $tenant, 20),
        ]);
    }

    /** POST /api/pal/new/content-model/nodes/{nodeKey}/transition */
    public function transitionNode(Request $request, string $nodeKey): JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');
        if ($denied = $this->denyStudents($request, 'Students may not review content.')) {
            return $denied;
        }

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('Cannot resolve a single institute for this write.', 422);
        }

        $validated = $request->validate([
            'to_status' => 'required|string',
            'note' => 'nullable|string|max:2000',
        ]);

        $found = $this->findNode($nodeKey, $tenant);
        if ($found === null) {
            return $this->fail("No projected node matches '{$nodeKey}'.", 404);
        }

        try {
            $saved = $this->authoring->transition(
                $nodeKey,
                $tenant,
                $validated['to_status'],
                (int) $auth['user_id'],
                'human',
                $validated['note'] ?? null,
                $found['node']
            );
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok(['override' => $saved, 'allowed_transitions' => $this->allowedTransitions($saved['quality_status'] ?? 'draft')]);
    }

    /** POST /api/pal/new/content-model/nodes/bulk-transition */
    public function bulkTransition(Request $request): JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');
        if ($denied = $this->denyStudents($request, 'Students may not review content.')) {
            return $denied;
        }

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('Cannot resolve a single institute for this write.', 422);
        }

        $validated = $request->validate([
            'node_keys' => 'required|array|min:1|max:200',
            'node_keys.*' => 'string',
            'to_status' => 'required|string',
            'note' => 'nullable|string|max:2000',
        ]);

        return $this->ok($this->authoring->bulkTransition(
            $validated['node_keys'],
            $tenant,
            $validated['to_status'],
            (int) $auth['user_id'],
            $validated['note'] ?? null
        ));
    }

    // ══════════════════════════════════════════════════════════════════════
    // Version control (spec §9.1)
    // ══════════════════════════════════════════════════════════════════════

    /** GET /api/pal/new/content-model/nodes/{nodeKey}/revisions */
    public function revisions(Request $request, string $nodeKey): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        return $this->ok([
            'node_key' => $nodeKey,
            'revisions' => $this->authoring->revisions($nodeKey, $this->tenantFor($request) ?? 0, 100),
        ]);
    }

    /** POST /api/pal/new/content-model/nodes/{nodeKey}/restore */
    public function restore(Request $request, string $nodeKey): JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');
        if ($denied = $this->denyStudents($request, 'Students may not author content.')) {
            return $denied;
        }

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('Cannot resolve a single institute for this write.', 422);
        }

        $validated = $request->validate(['version' => 'required|integer|min:1']);

        try {
            $saved = $this->authoring->restore($nodeKey, $tenant, (int) $validated['version'], (int) $auth['user_id']);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok(['override' => $saved, 'revisions' => $this->authoring->revisions($nodeKey, $tenant, 20)]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Review queue (the authoring console's work list)
    // ══════════════════════════════════════════════════════════════════════

    /** GET /api/pal/new/content-model/review-queue */
    public function reviewQueue(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'Students may not review content.')) {
            return $denied;
        }

        $tenant = $this->tenantFor($request) ?? 0;

        return $this->ok([
            'items' => $this->authoring->reviewQueue($tenant, [
                'status' => $request->query('status'),
                'content_type' => $request->query('content_type'),
                'semantic_id' => $request->query('semantic_id'),
                'tagged_by' => $request->query('tagged_by'),
            ], (int) $request->query('limit', 100)),
            'pipeline' => $this->authoring->pipelineCounts($tenant, $request->filled('semantic_id') ? (int) $request->query('semantic_id') : null),
            'quality_transitions' => config('pal_content.quality_transitions'),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Misconception library (Type 3, chapter-wide)
    // ══════════════════════════════════════════════════════════════════════

    /** GET /api/pal/new/content-model/chapters/{semanticId}/misconceptions */
    public function chapterMisconceptions(Request $request, int $semanticId): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $tenant = $this->tenantFor($request);
        $loaded = $this->source->conceptsFor($semanticId, $tenant);

        if ($loaded['header'] === null) {
            return $this->fail('That extracted chapter was not found.', 404);
        }

        $entries = [];
        $violations = [];
        $bySeverity = [];

        foreach ($loaded['concepts'] as $concept) {
            $projected = $this->misconceptions->project($semanticId, $concept, $loaded['header']);
            foreach ($projected['entries'] as $entry) {
                $entries[] = $entry;
                $bySeverity[$entry['severity']] = ($bySeverity[$entry['severity']] ?? 0) + 1;
                if (! $entry['c6_ok']) {
                    $violations[] = ['tag' => $entry['tag'], 'concept' => $entry['concept_name'], 'reason' => $entry['c6_reason']];
                }
            }
        }

        if ($request->filled('severity')) {
            $severity = (string) $request->query('severity');
            $entries = array_values(array_filter($entries, fn ($e) => $e['severity'] === $severity));
        }
        if ($request->filled('concept')) {
            $slug = (string) $request->query('concept');
            $entries = array_values(array_filter($entries, fn ($e) => $e['concept_slug'] === $slug));
        }

        return $this->ok([
            'semantic_id' => $semanticId,
            'header' => $loaded['header'],
            'total' => count($entries),
            'servable' => count(array_filter($entries, fn ($e) => $e['c6_ok'])),
            'by_severity' => $bySeverity,
            'c6_pass' => $violations === [],
            'c6_violations' => $violations,
            'corrective_formats' => config('pal_content.corrective_formats'),
            'entries' => $entries,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Bloom ladder (Type 2, per concept)
    // ══════════════════════════════════════════════════════════════════════

    /** GET /api/pal/new/content-model/chapters/{semanticId}/concepts/{conceptSlug}/ladder */
    public function ladder(Request $request, int $semanticId, string $conceptSlug): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $tenant = $this->tenantFor($request);
        $loaded = $this->source->conceptsFor($semanticId, $tenant);

        if ($loaded['header'] === null) {
            return $this->fail('That extracted chapter was not found.', 404);
        }

        foreach ($loaded['concepts'] as $concept) {
            if ($concept['slug'] !== $conceptSlug) {
                continue;
            }

            return $this->ok($this->projector->practiceLadder($semanticId, $concept, $loaded['header'], $loaded['chapter']));
        }

        return $this->fail('That concept was not found in the extracted chapter.', 404);
    }

    // ══════════════════════════════════════════════════════════════════════
    // LLM enrichment — only where the database cannot answer
    // ══════════════════════════════════════════════════════════════════════

    /**
     * POST /api/pal/new/content-model/nodes/{nodeKey}/enrich
     *
     * kind: cultural_context | framework_tags | variant_draft
     *
     * What comes back is a PROPOSAL. It is returned for review and, when
     * `apply` is set, written as a draft override tagged `ai` — never approved.
     */
    public function enrich(Request $request, string $nodeKey): JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');
        if ($denied = $this->denyStudents($request, 'Students may not run content enrichment.')) {
            return $denied;
        }

        $validated = $request->validate([
            'kind' => 'required|string|in:cultural_context,framework_tags,variant_draft',
            'fields' => 'sometimes|array',
            'fields.*' => 'string',
            'apply' => 'sometimes|boolean',
        ]);

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('Cannot resolve a single institute for this request.', 422);
        }

        $found = $this->findNode($nodeKey, $tenant);
        if ($found === null) {
            return $this->fail("No projected node matches '{$nodeKey}'.", 404);
        }

        $node = $found['node'];
        $context = $this->llmContext($found);
        $userId = (int) $auth['user_id'];

        switch ($validated['kind']) {
            case 'cultural_context':
                // The cheap pass first — most nodes never reach the provider.
                $lexicon = $this->enrichment->classifyCulturalContext((string) $context['text']);
                if (! $lexicon['needs_llm'] && $lexicon['context'] !== null) {
                    $result = [
                        'ok' => true,
                        'data' => [
                            'cultural_context' => $lexicon['context'],
                            'reason' => 'Matched the cultural lexicon on: '
                                . implode(', ', array_merge(...array_values($lexicon['matched'] ?: [[]]))),
                            'confidence' => null,
                        ],
                        'method' => $lexicon['method'],
                        'cached' => false,
                    ];
                } else {
                    $result = $this->enrichment->llmCulturalContext($nodeKey, $context, $tenant, $userId);
                    $result['method'] = 'llm';
                    $result['lexicon'] = $lexicon;
                }
                $patch = isset($result['data']['cultural_context'])
                    ? ['cultural_context' => $result['data']['cultural_context']]
                    : [];
                break;

            case 'framework_tags':
                $fields = $validated['fields'] ?? $this->deriver->llmCandidates($node['metadata'] ?? []);
                $result = $this->enrichment->llmFrameworkTags($nodeKey, $context, $fields, $tenant, $userId);
                $patch = $result['data']['tags'] ?? [];
                break;

            default: // variant_draft
                $result = $this->enrichment->llmVariantDraft($nodeKey, $context, $tenant, $userId);
                $patch = [];
                break;
        }

        if (! ($result['ok'] ?? false)) {
            return $this->fail($result['error'] ?? 'Enrichment failed.', 422);
        }

        $applied = false;
        if (! empty($validated['apply'])) {
            $payload = [];
            if ($patch !== []) {
                $payload['metadata'] = $patch;
            }
            if ($validated['kind'] === 'variant_draft') {
                $payload['title'] = $result['data']['title'] ?? null;
                $payload['body'] = $result['data']['body'] ?? null;
                if (! empty($result['data']['h5p_type'])) {
                    $payload['metadata'] = array_merge($payload['metadata'] ?? [], ['h5p_type' => $result['data']['h5p_type']]);
                }
            }

            if ($payload !== []) {
                $payload['note'] = 'AI proposal applied as a draft (' . $validated['kind'] . ').';
                try {
                    // actorType 'ai' — the service refuses to touch approved
                    // content and stamps tagged_by accordingly (CONTENT LAW C5).
                    $this->authoring->save($nodeKey, $tenant, $payload, $userId, 'ai');
                    $applied = true;
                } catch (InvalidArgumentException $e) {
                    return $this->fail('The proposal was generated but could not be saved: ' . $e->getMessage(), 422);
                }
            }
        }

        return $this->ok([
            'node_key' => $nodeKey,
            'kind' => $validated['kind'],
            'proposal' => $result['data'] ?? [],
            'method' => $result['method'] ?? 'llm',
            'lexicon' => $result['lexicon'] ?? null,
            'cached' => $result['cached'] ?? false,
            'model' => $result['model'] ?? null,
            'applied' => $applied,
            'tagged_by' => config('pal_content_model.llm.forced_tagged_by', 'ai'),
            'quality_status' => config('pal_content_model.llm.forced_status', 'draft'),
        ]);
    }

    /**
     * POST /api/pal/new/content-model/nodes/{nodeKey}/translate
     *
     * Adds one of the 9 registered language variants to a node (spec §2.2).
     */
    public function translate(Request $request, string $nodeKey): JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');
        if ($denied = $this->denyStudents($request, 'Students may not author content.')) {
            return $denied;
        }

        $validated = $request->validate([
            'language' => 'required|string|size:2',
            'apply' => 'sometimes|boolean',
        ]);

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('Cannot resolve a single institute for this request.', 422);
        }

        $found = $this->findNode($nodeKey, $tenant);
        if ($found === null) {
            return $this->fail("No projected node matches '{$nodeKey}'.", 404);
        }

        $node = $found['node'];
        $result = $this->enrichment->llmTranslate($nodeKey, [
            'concept_name' => $found['concept_header']['name'] ?? null,
            'subject' => $found['chapter']['header']['subject_name'] ?? null,
            'grade' => $found['chapter']['header']['standard'] ?? null,
            'title' => $node['title'] ?? '',
            'body' => $node['body'] ?? ($node['prompt'] ?? ''),
        ], $validated['language'], $tenant, (int) $auth['user_id']);

        if (! ($result['ok'] ?? false)) {
            return $this->fail($result['error'] ?? 'Translation failed.', 422);
        }

        $applied = false;
        if (! empty($validated['apply'])) {
            try {
                $this->authoring->save($nodeKey, $tenant, [
                    'language_variants' => [
                        $validated['language'] => [
                            'title' => $result['data']['title'] ?? null,
                            'body' => $result['data']['body'] ?? '',
                            'source' => 'llm',
                        ],
                    ],
                    'note' => 'Machine translation into ' . $validated['language'] . ' stored as a draft variant.',
                ], (int) $auth['user_id'], 'ai');
                $applied = true;
            } catch (InvalidArgumentException $e) {
                return $this->fail('The translation was generated but could not be saved: ' . $e->getMessage(), 422);
            }
        }

        return $this->ok([
            'node_key' => $nodeKey,
            'language' => $validated['language'],
            'translation' => $result['data'] ?? [],
            'cached' => $result['cached'] ?? false,
            'model' => $result['model'] ?? null,
            'applied' => $applied,
            'languages' => config('pal_content.languages'),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Internals
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Re-project the chapter a node key names and pull the one node out of it,
     * with its override merged.
     *
     * Node keys are derived rather than stored, so this is how a node is
     * "loaded": the projection is the source of truth, and a key that no longer
     * resolves means the extraction changed under it — which the caller should
     * be told, not silently given an empty node.
     *
     * @return array{node:array, concept_header:array, chapter:array}|null
     */
    protected function findNode(string $nodeKey, ?int $tenant): ?array
    {
        $parsed = $this->projector->parseNodeKey($nodeKey);
        if ($parsed === null) {
            return null;
        }

        $projected = $this->projector->projectConcept($parsed['semantic_id'], $parsed['concept_slug'], $tenant);
        if ($projected === null) {
            return null;
        }

        $overrides = $this->authoring->overridesForChapter($parsed['semantic_id'], $tenant ?? 0);
        $override = $overrides[$nodeKey] ?? null;

        foreach ($this->allNodes($projected) as $node) {
            if (($node['node_key'] ?? null) === $nodeKey) {
                return [
                    'node' => $this->authoring->merge($node, $override),
                    'concept_header' => $projected['concept'],
                    'chapter' => $projected['chapter'],
                ];
            }
        }

        return null;
    }

    /** Every node a projected concept contains, across all four types. */
    protected function allNodes(array $projected): array
    {
        $nodes = $projected['type_1_concept_learning']['variants'] ?? [];

        foreach ($projected['type_2_practice']['levels'] ?? [] as $level) {
            $nodes = array_merge($nodes, $level['items'] ?? []);
        }

        foreach ($projected['type_3_misconceptions']['entries'] ?? [] as $entry) {
            $nodes[] = $entry;
            $nodes = array_merge($nodes, $entry['correctives'] ?? []);
        }

        return array_merge($nodes, $projected['type_4_assessment']['items'] ?? []);
    }

    /** Merge overrides into every node of a projected concept, in place. */
    protected function mergeConcept(array $projected, array $overrides): array
    {
        foreach ($projected['type_1_concept_learning']['variants'] as $i => $node) {
            $projected['type_1_concept_learning']['variants'][$i] =
                $this->authoring->merge($node, $overrides[$node['node_key']] ?? null);
        }

        foreach ($projected['type_2_practice']['levels'] as $l => $level) {
            foreach ($level['items'] as $i => $node) {
                $projected['type_2_practice']['levels'][$l]['items'][$i] =
                    $this->authoring->merge($node, $overrides[$node['node_key']] ?? null);
            }
        }

        foreach ($projected['type_3_misconceptions']['entries'] as $e => $entry) {
            $projected['type_3_misconceptions']['entries'][$e] =
                $this->authoring->merge($entry, $overrides[$entry['node_key']] ?? null);

            foreach ($entry['correctives'] as $c => $corrective) {
                $projected['type_3_misconceptions']['entries'][$e]['correctives'][$c] =
                    $this->authoring->merge($corrective, $overrides[$corrective['node_key']] ?? null);
            }
        }

        foreach ($projected['type_4_assessment']['items'] as $i => $node) {
            $projected['type_4_assessment']['items'][$i] =
                $this->authoring->merge($node, $overrides[$node['node_key']] ?? null);
        }

        return $projected;
    }

    /** What the model is shown when it is asked to enrich this node. */
    protected function llmContext(array $found): array
    {
        $node = $found['node'];
        $concept = $found['concept_header'];
        $header = $found['chapter']['header'] ?? [];

        $text = trim(implode("\n\n", array_filter([
            $concept['definition'] ?? null,
            $node['body'] ?? null,
            $node['prompt'] ?? null,
        ])));

        return [
            'concept_name' => $concept['name'] ?? null,
            'definition' => $concept['definition'] ?? null,
            'subject' => $header['subject_name'] ?? null,
            'grade' => $header['standard'] ?? null,
            'bloom_level' => $node['metadata']['bloom_level'] ?? null,
            'skills' => $node['metadata']['skills'] ?? [],
            'cultural_context' => $node['metadata']['cultural_context'] ?? null,
            'variant_number' => $node['variant_number'] ?? null,
            'evidence' => array_values(array_map(
                fn ($i) => (string) ($i['text'] ?? ''),
                array_filter($node['source_items'] ?? [], fn ($i) => ($i['kind'] ?? '') === 'evidence')
            )),
            'misconceptions' => $node['misconception_tags'] ?? [],
            'text' => $text,
        ];
    }

    protected function allowedTransitions(string $from): array
    {
        return config('pal_content.quality_transitions')[$from] ?? [];
    }

    // ── Tenancy (mirrors PalContentIntelligenceController) ───────────────────

    protected function tenantFor(Request $request): ?int
    {
        $auth = $request->attributes->get('pal_auth');

        if ((int) ($auth['is_admin'] ?? 0) === 2) {
            return $request->filled('sub_institute_id') ? (int) $request->get('sub_institute_id') : null;
        }

        $sub = (string) ($auth['sub_institute_id'] ?? '');
        if (str_contains($sub, ',')) {
            $sub = trim(explode(',', $sub)[0]);
        }

        return $sub === '' ? null : (int) $sub;
    }

    /** A write must name exactly one institute; an ambiguous CSV is rejected. */
    protected function writeTenantFor(Request $request): ?int
    {
        $auth = $request->attributes->get('pal_auth');

        if ((int) ($auth['is_admin'] ?? 0) === 2 && $request->filled('sub_institute_id')) {
            return (int) $request->get('sub_institute_id');
        }

        $sub = (string) ($auth['sub_institute_id'] ?? '');
        if ($sub === '' || str_contains($sub, ',')) {
            return null;
        }

        $id = (int) $sub;
        if ($id === 0 && (int) ($auth['is_admin'] ?? 0) !== 2) {
            return null;
        }

        return $id;
    }

    protected function denyStudents(Request $request, string $message = 'The Content Model workspace is not available to students.'): ?JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');

        return ! empty($auth['is_student']) ? $this->fail($message, 403) : null;
    }

    protected function ok(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }

    protected function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
