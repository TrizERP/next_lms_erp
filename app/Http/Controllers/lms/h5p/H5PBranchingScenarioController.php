<?php

namespace App\Http\Controllers\lms\h5p;

use App\Models\lms\chapterModel;
use App\Models\lms\h5p\H5pBranchingNode;
use App\Models\lms\h5p\H5pBranchingScenario;
use App\Services\ContentGenerationService;
use App\Services\lms\H5P\BranchingPayload;
use App\Services\lms\H5P\H5PBranchingScenarioBuilder;
use App\Services\lms\H5P\H5PBranchingScenarioPackageService;
use App\Services\lms\H5P\HtmlLite;
use App\Services\StudyDeck\Branching\BranchingScenarioService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * H5P Branching Scenario (H5P.BranchingScenario).
 *
 * A decision tree: screens of content, decisions, endings. Everything in the
 * save/publish/duplicate/package cycle is inherited; this class says what is
 * specific to a tree.
 *
 * REFS, NOT IDS, ON THE WAY IN. A node is addressed by a client-side `ref`,
 * and a save runs in two passes, exactly as a Course Presentation's does:
 *
 *   1. insert every node, building ref -> real id;
 *   2. write each node's `next_node_id` and each choice's `next_node_id` with
 *      those ids.
 *
 * So a brand new node can lead to another brand new node in the same save --
 * the normal case when a scenario is generated, or authored from scratch.
 *
 * A REFERENCE TO A NODE THAT IS NOT IN THE PAYLOAD IS REFUSED, never clamped.
 * A branch into nothing is a dead end a learner cannot get out of, and quietly
 * dropping it would leave a choice that does nothing. What a DRAFT may still be
 * is unfinished: a screen with no "next" yet is saved, and the graph check
 * (BranchingPayload::analyse) is what stops it being PUBLISHED.
 *
 * GENERATION IS NOT SAVING. `generate()` returns a validated draft payload and a
 * report, and writes nothing. Saving it is the ordinary `store()`, which makes a
 * draft; publishing is the ordinary `publish()`, which runs the graph check. A
 * generated scenario therefore goes through exactly the same two human steps as
 * one a teacher typed.
 */
class H5PBranchingScenarioController extends H5PContentTypeController
{
    /** Most nodes a scenario may have. A tree larger than this is not one a learner finishes. */
    private const MAX_NODES = 60;

    public function __construct(
        private readonly H5PBranchingScenarioBuilder $builder,
        private readonly H5PBranchingScenarioPackageService $packages
    ) {
    }

    protected function modelClass(): string
    {
        return H5pBranchingScenario::class;
    }

    protected function registryCode(): string
    {
        return 'branching_scenario';
    }

    protected function routePrefix(): string
    {
        return 'h5p_branching_scenario';
    }

    protected function payloadKey(): string
    {
        return 'branchingScenario';
    }

    protected function listKey(): string
    {
        return 'branchingScenarioLists';
    }

    protected function label(): string
    {
        return 'Branching scenario';
    }

    protected function relations(): array
    {
        return ['nodes'];
    }

    protected function viewPath(): string
    {
        return 'lms/h5p/branchingscenario';
    }

    protected function mediaRoles(): array
    {
        return [
            'start_image' => 'image',
            'image' => 'image',
            'feedback_image' => 'image',
        ];
    }

    protected function authoringDefaults(): array
    {
        return [
            'scoring_option' => 'static-end-score',
            'pass_percentage' => 60,
            'enable_backwards_navigation' => true,
            'randomize_alternatives' => false,
            'scoring_options' => H5pBranchingScenario::SCORING_OPTIONS,
            'node_types' => H5pBranchingNode::TYPES,
            'purposes' => H5pBranchingNode::PURPOSES,
            'qualities' => H5pBranchingNode::QUALITIES,
            'max_nodes' => self::MAX_NODES,
        ];
    }

    // -----------------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------------

    protected function saveRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',

            'start_title' => 'nullable|string|max:255',
            'start_subtitle' => 'nullable|string|max:5000',
            'start_image' => 'nullable|string|max:2048',
            'start_image_alt' => 'nullable|string|max:255',

            'learning_objectives' => 'nullable|array|max:8',
            'learning_objectives.*' => 'nullable|string|max:300',

            'scoring_option' => 'nullable|in:' . implode(',', H5pBranchingScenario::SCORING_OPTIONS),
            'pass_percentage' => 'nullable|integer|min:0|max:100',
            'enable_backwards_navigation' => 'nullable|boolean',
            'randomize_alternatives' => 'nullable|boolean',

            // Where a generated scenario came from. Narrowed to a fixed set of
            // keys in generationFrom(); nothing else is stored.
            'generation' => 'nullable|array',

            'nodes' => 'required|array|min:1|max:' . self::MAX_NODES,
            'nodes.*.ref' => 'required|string|max:64',
            'nodes.*.node_type' => 'required|in:' . implode(',', H5pBranchingNode::TYPES),
            'nodes.*.purpose' => 'nullable|string|max:32',
            'nodes.*.title' => 'nullable|string|max:255',
            'nodes.*.show_title' => 'nullable|boolean',
            'nodes.*.body' => 'nullable|string|max:30000',
            'nodes.*.media_path' => 'nullable|string|max:2048',
            'nodes.*.media_alt' => 'nullable|string|max:255',
            'nodes.*.proceed_button_text' => 'nullable|string|max:50',
            'nodes.*.next_ref' => 'nullable|string|max:64',
            'nodes.*.score' => 'nullable|integer|min:0|max:1000',
            'nodes.*.concept_id' => 'nullable|integer',
            'nodes.*.source_question_id' => 'nullable|integer',

            'nodes.*.options' => 'nullable|array|max:6',
            'nodes.*.options.*.text' => 'nullable|string|max:500',
            'nodes.*.options.*.next_ref' => 'nullable|string|max:64',
            'nodes.*.options.*.quality' => 'nullable|in:' . implode(',', H5pBranchingNode::QUALITIES),
            'nodes.*.options.*.feedback_title' => 'nullable|string|max:255',
            'nodes.*.options.*.feedback_text' => 'nullable|string|max:5000',
            'nodes.*.options.*.feedback_image' => 'nullable|string|max:2048',
            'nodes.*.options.*.feedback_image_alt' => 'nullable|string|max:255',
        ];
    }

    protected function attributesFrom(array $data, Request $request): array
    {
        $attributes = [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'start_title' => $data['start_title'] ?? null,
            'start_subtitle' => isset($data['start_subtitle']) ? HtmlLite::clean($data['start_subtitle']) : null,
            'start_image' => $data['start_image'] ?? null,
            'start_image_alt' => $data['start_image_alt'] ?? null,
            'learning_objectives' => array_values(array_filter(
                array_map(fn ($o) => trim((string) $o), (array) ($data['learning_objectives'] ?? [])),
                fn ($o) => $o !== ''
            )),
            'scoring_option' => $data['scoring_option'] ?? 'static-end-score',
            'pass_percentage' => $data['pass_percentage'] ?? 60,
            'enable_backwards_navigation' => $data['enable_backwards_navigation'] ?? true,
            'randomize_alternatives' => $data['randomize_alternatives'] ?? false,
        ];

        // Absent means "leave it alone", so an edit from the editor, which
        // never sends provenance, does not erase where a scenario came from.
        if (array_key_exists('generation', $data)) {
            $attributes['generation'] = $this->generationFrom($data['generation']);
        }

        return $attributes;
    }

    /**
     * The provenance of a generated scenario, narrowed to what the generator
     * writes. A client-supplied blob is not stored as it arrived.
     *
     * @param  mixed  $generation
     * @return array<string,mixed>|null
     */
    private function generationFrom(mixed $generation): ?array
    {
        if (! is_array($generation) || $generation === []) {
            return null;
        }

        $ids = fn ($list) => array_values(array_unique(array_filter(array_map('intval', (array) $list), fn ($id) => $id > 0)));

        return [
            'generator' => mb_substr((string) ($generation['generator'] ?? 'ai'), 0, 32),
            'model' => isset($generation['model']) ? mb_substr((string) $generation['model'], 0, 64) : null,
            'generated_at' => isset($generation['generated_at']) ? mb_substr((string) $generation['generated_at'], 0, 40) : now()->toIso8601String(),
            'topic_ids' => $ids($generation['topic_ids'] ?? []),
            'concept_ids' => $ids($generation['concept_ids'] ?? []),
            'question_ids' => $ids($generation['question_ids'] ?? []),
            'focus' => isset($generation['focus']) ? mb_substr((string) $generation['focus'], 0, 400) : null,
        ];
    }

    // -----------------------------------------------------------------------
    // Children
    // -----------------------------------------------------------------------

    protected function syncChildren(Model $item, array $data, int|string|null $subInstituteId, int|string|null $userId): void
    {
        $this->writeNodes($item, array_values($data['nodes']), $subInstituteId, $userId);
    }

    /**
     * Write a scenario's nodes from payload nodes (refs, not ids).
     *
     * Used by save AND by package import, which produces the same shape.
     *
     * @param  list<array<string,mixed>>  $nodes
     */
    private function writeNodes(Model $item, array $nodes, int|string|null $subInstituteId, int|string|null $userId): void
    {
        $audit = [
            'sub_institute_id' => $subInstituteId,
            'created_by' => $userId,
            'created_at' => now(),
        ];

        $refs = [];
        foreach ($nodes as $node) {
            $ref = (string) $node['ref'];
            if (isset($refs[$ref])) {
                throw ValidationException::withMessages(['nodes' => 'Two steps share the same reference (' . $ref . ').']);
            }
            $refs[$ref] = true;
        }

        // Pass 1: the rows, without their edges.
        $idByRef = [];
        foreach ($nodes as $index => $node) {
            $type = (string) $node['node_type'];
            $row = H5pBranchingNode::create([
                'scenario_id' => $item->id,
                'node_index' => $index,
                'node_type' => $type,
                'purpose' => $this->purposeFrom($node['purpose'] ?? null),
                'title' => $node['title'] ?? null,
                'show_title' => $node['show_title'] ?? true,
                'body' => isset($node['body']) ? HtmlLite::clean($node['body']) : null,
                'media_path' => $node['media_path'] ?? null,
                'media_alt' => $node['media_alt'] ?? null,
                'proceed_button_text' => $node['proceed_button_text'] ?? null,
                'next_node_id' => null,
                'options' => null,
                // Only an ending is worth anything; a score sent on another
                // kind of node must not inflate the maximum.
                'score' => $type === H5pBranchingNode::ENDING ? max(0, (int) ($node['score'] ?? 0)) : 0,
                'concept_id' => $node['concept_id'] ?? null,
                'source_question_id' => $node['source_question_id'] ?? null,
            ] + $audit);

            $idByRef[(string) $node['ref']] = $row->id;
        }

        // Pass 2: the edges, now that every ref has an id.
        foreach ($nodes as $index => $node) {
            $type = (string) $node['node_type'];
            $id = $idByRef[(string) $node['ref']];
            $name = BranchingPayload::label($node, $index);

            if ($type === H5pBranchingNode::TEXT) {
                $next = $this->resolve($node['next_ref'] ?? null, $idByRef, $name);
                if ($next !== null) {
                    H5pBranchingNode::where('id', $id)->update(['next_node_id' => $next]);
                }
            } elseif ($type === H5pBranchingNode::BRANCHING_QUESTION) {
                $options = [];
                foreach (array_values((array) ($node['options'] ?? [])) as $option) {
                    $options[] = [
                        'text' => trim((string) ($option['text'] ?? '')),
                        'next_node_id' => $this->resolve($option['next_ref'] ?? null, $idByRef, $name),
                        'quality' => $option['quality'] ?? null,
                        'feedback_title' => (string) ($option['feedback_title'] ?? ''),
                        'feedback_text' => HtmlLite::clean($option['feedback_text'] ?? ''),
                        'feedback_image' => (string) ($option['feedback_image'] ?? ''),
                        'feedback_image_alt' => (string) ($option['feedback_image_alt'] ?? ''),
                    ];
                }
                H5pBranchingNode::where('id', $id)->update(['options' => json_encode($options, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    /**
     * @param  array<string,int>  $idByRef
     *
     * @throws ValidationException when the ref names a node that is not in the payload
     */
    private function resolve(mixed $ref, array $idByRef, string $from): ?int
    {
        $ref = $ref === null ? '' : trim((string) $ref);
        if ($ref === '') {
            return null;
        }
        if (! isset($idByRef[$ref])) {
            throw ValidationException::withMessages([
                'nodes' => $from . ' leads to a step that is not in this scenario.',
            ]);
        }

        return $idByRef[$ref];
    }

    private function purposeFrom(mixed $purpose): ?string
    {
        $purpose = is_string($purpose) ? trim($purpose) : '';

        return $purpose !== '' && in_array($purpose, H5pBranchingNode::PURPOSES, true) ? $purpose : null;
    }

    protected function duplicableColumns(): array
    {
        return [
            'description', 'start_title', 'start_subtitle', 'start_image', 'start_image_alt',
            'learning_objectives', 'scoring_option', 'pass_percentage',
            'enable_backwards_navigation', 'randomize_alternatives', 'generation',
            'standard_id', 'subject_id', 'chapter_id', 'syear',
        ];
    }

    protected function duplicateChildren(Model $original, Model $copy, int|string|null $subInstituteId, int|string|null $userId): void
    {
        $original->loadMissing('nodes');

        $audit = ['sub_institute_id' => $subInstituteId, 'created_by' => $userId, 'created_at' => now()];

        // Edges are ids in the ORIGINAL scenario and have to be remapped to the
        // copy's, or a duplicate would branch into the one it came from.
        $idMap = [];
        foreach ($original->nodes as $node) {
            $row = H5pBranchingNode::create($node->only([
                'node_index', 'node_type', 'purpose', 'title', 'show_title', 'body', 'media_path',
                'media_alt', 'proceed_button_text', 'score', 'concept_id', 'source_question_id',
            ]) + ['scenario_id' => $copy->id, 'next_node_id' => null, 'options' => null] + $audit);

            $idMap[(int) $node->id] = $row->id;
        }

        foreach ($original->nodes as $node) {
            $updates = [];

            if ($node->next_node_id !== null && isset($idMap[(int) $node->next_node_id])) {
                $updates['next_node_id'] = $idMap[(int) $node->next_node_id];
            }

            if (is_array($node->options) && $node->options !== []) {
                $updates['options'] = json_encode(array_map(function ($option) use ($idMap) {
                    if (is_array($option) && isset($option['next_node_id'])) {
                        $option['next_node_id'] = $idMap[(int) $option['next_node_id']] ?? null;
                    }

                    return $option;
                }, $node->options), JSON_UNESCAPED_UNICODE);
            }

            if ($updates !== []) {
                H5pBranchingNode::where('id', $idMap[(int) $node->id])->update($updates);
            }
        }
    }

    // -----------------------------------------------------------------------
    // Publish
    // -----------------------------------------------------------------------

    protected function publishBlocker(Model $item): ?string
    {
        $item->load('nodes');

        $report = BranchingPayload::analyse(BranchingPayload::fromRows($item->nodes), true);
        if ($report['errors'] === []) {
            return null;
        }

        $first = $report['errors'][0]['message'];
        $more = count($report['errors']) - 1;

        // The first problem, in words, and how many more there are. Fixing the
        // first often fixes the rest (an unreachable node is usually one
        // missing link), so listing all of them would bury the one to start on.
        return $more > 0 ? $first . ' (' . $more . ' more ' . ($more === 1 ? 'problem' : 'problems') . ' to fix.)' : $first;
    }

    // -----------------------------------------------------------------------
    // Params and packages
    // -----------------------------------------------------------------------

    protected function buildParams(Model $item): array
    {
        return $this->builder->build($item);
    }

    protected function exportPackage(Model $item): array
    {
        return $this->packages->export($item);
    }

    protected function parsePackage(UploadedFile $file, int|string|null $subInstituteId): array
    {
        return $this->packages->import($file, $subInstituteId);
    }

    protected function createFromImport(array $parsed, Request $request, int|string|null $subInstituteId, int|string|null $userId): Model
    {
        $scenario = H5pBranchingScenario::create($parsed['scenario'] + [
            'title' => $parsed['title'],
            'description' => '',
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
            'chapter_id' => $request->chapter_id,
            'sub_institute_id' => $subInstituteId,
            'syear' => $request->input('syear'),
            'status' => 'draft',
            'library' => $this->libraryVersionString(),
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        $this->writeNodes($scenario, array_values($parsed['nodes']), $subInstituteId, $userId);

        return $scenario;
    }

    // -----------------------------------------------------------------------
    // Generation
    // -----------------------------------------------------------------------

    /**
     * What a teacher can build a scenario from: the chapter's topics and
     * concepts, with how well each suits a decision-based lesson and how many
     * bank questions could be reused.
     *
     * Read-only. Nothing here calls a model.
     */
    public function sources(Request $request)
    {
        $type = $request->input('type');
        $data = $request->validate(['chapter_id' => 'required|integer']);

        $chapter = $this->chapterFor((int) $data['chapter_id'], $this->tenant($request));
        if ($chapter === null) {
            return $this->fail($type, 'That chapter could not be found for this school.', 404);
        }

        try {
            $sources = BranchingScenarioService::make(null)->sources((int) $chapter->id, (int) $this->tenant($request));
        } catch (\Throwable $e) {
            report($e);

            return $this->fail($type, 'The chapter\'s topics and concepts could not be loaded.', 500);
        }

        $content = app(ContentGenerationService::class);
        $enabled = $content->handles($chapter->id);

        return response()->json([
            'status' => true,
            'sources' => $sources,
            'generation' => [
                'enabled' => $enabled,
                'reason' => $enabled ? null : 'AI generation is not switched on for this chapter.',
            ],
        ]);
    }

    /**
     * Generate a scenario for a chapter, for REVIEW. Stores nothing.
     *
     * The response is the draft in exactly the shape the editor saves, plus a
     * report (what was checked, what it found, what the scenario covers). The
     * caller shows it, and saving it is a separate, ordinary store().
     */
    public function generate(Request $request)
    {
        $type = $request->input('type');

        // Generation spends the school's model budget and its output reaches
        // students once published; it is a teacher's act.
        if ($this->isStudent($request)) {
            return $this->fail($type, 'Only teachers can generate a scenario.', 403);
        }

        $data = $request->validate([
            'standard_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'chapter_id' => 'required|integer',
            'topic_ids' => 'nullable|array|max:50',
            'topic_ids.*' => 'integer|min:1',
            'concept_ids' => 'nullable|array|max:60',
            'concept_ids.*' => 'integer|min:1',
            'focus' => 'nullable|string|max:400',
            'length' => 'nullable|in:short,standard,extended',
            'use_questions' => 'nullable|boolean',
        ]);

        $tenant = $this->tenant($request);
        $chapter = $this->chapterFor((int) $data['chapter_id'], $tenant);
        if ($chapter === null) {
            return $this->fail($type, 'That chapter could not be found for this school.', 404);
        }

        // Scenarios already written for this chapter. The generator is told to
        // take a different situation, and its validator rejects a repeat.
        $existing = H5pBranchingScenario::where('sub_institute_id', $tenant)
            ->where('chapter_id', $chapter->id)
            ->get(['id', 'title', 'generation'])
            ->map(fn (H5pBranchingScenario $s) => [
                'title' => (string) $s->title,
                'concept_ids' => (array) ($s->generation['concept_ids'] ?? []),
            ])->all();

        $result = app(ContentGenerationService::class)->generateBranchingScenario([
            'chapter' => $chapter,
            'sub_institute_id' => (int) $tenant,
            'topic_ids' => array_map('intval', (array) ($data['topic_ids'] ?? [])),
            'concept_ids' => array_map('intval', (array) ($data['concept_ids'] ?? [])),
            'focus' => $data['focus'] ?? null,
            'length' => $data['length'] ?? 'standard',
            'use_questions' => $data['use_questions'] ?? true,
            'existing' => $existing,
        ]);

        $body = $result['body'] + ['status' => $result['http'] < 400];

        // Recorded even though nothing is stored: a generation spends the
        // school's model budget, and who asked for one is worth knowing.
        \App\Models\AuditLog::record([
            'module' => 'lms',
            'action' => 'branching_scenario_generated',
            'entity_type' => 'chapter_master',
            'entity_id' => $chapter->id,
            'new_values' => ['concept_ids' => $data['concept_ids'] ?? [], 'ok' => $body['status']],
        ]);

        return response()->json($body, $result['http']);
    }

    /** The chapter, if it is this school's. Mirrors IntelligenceContentGenerationApiController. */
    private function chapterFor(int $chapterId, int|string|null $tenant): ?chapterModel
    {
        $chapter = chapterModel::where('id', $chapterId)->first();

        return $chapter !== null && (int) $chapter->sub_institute_id === (int) $tenant ? $chapter : null;
    }
}
