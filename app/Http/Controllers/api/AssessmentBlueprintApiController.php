<?php

namespace App\Http\Controllers\api;

use App\Domain\Exam\AssessmentBlueprint;
use App\Domain\Exam\AssessmentBlueprintPresets;
use App\Domain\Exam\HpcBlueprint;
use App\Domain\Exam\HpcBlueprintPresets;
use App\Services\Evaluation\HpcVocabularyService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Assessment blueprints for the LMS Exam module.
 *
 * A blueprint is the DESIGN of a paper — how many marks to which chapter, how
 * many questions of which type, what share is easy — settled before any
 * question is picked. It is not the layout; that is
 * QuestionPaperTemplateApiController, which shares the word and nothing else.
 *
 * Reference blueprints (Delhi DoE, CBSE) are served from
 * AssessmentBlueprintPresets rather than seeded into the table, exactly as the
 * question-paper template presets are: the published documents change once a
 * year, and code is easier to correct than 60 schools' worth of rows. A school
 * uses one by cloning it, which writes a row they own and can change.
 */
class AssessmentBlueprintApiController extends Controller
{
    public function __construct(private readonly HpcVocabularyService $vocabulary)
    {
    }

    /**
     * The school whose HPC option lists apply to this request.
     *
     * Held for the length of one request so present()/presentPreset(), which are
     * called per row, do not each re-read the same four tiny lists.
     */
    private ?int $vocabularyTenantId = null;

    /** @var array<string,array<string,string>>|null */
    private ?array $resolvedVocabulary = null;

    /** A marks-based paper design: sections, question types, chapter weightage. */
    public const KIND_REGULAR = 'regular';

    /** A Holistic Progress Card design: competencies and proficiency, no marks. */
    public const KIND_HPC = 'hpc';

    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');

        if (! $tenantId) {
            return $this->failure('sub_institute_id is required.', 422);
        }

        $this->useVocabularyOf($tenantId);

        $query = DB::table('assessment_blueprint as b')
            ->leftJoin('standard as st', 'st.id', '=', 'b.standard_id')
            ->leftJoin('subject as sub', 'sub.id', '=', 'b.subject_id')
            ->where('b.sub_institute_id', $tenantId);

        if ($syear = (int) $request->input('syear')) {
            // A blueprint with no year on it is a design the school keeps
            // across sessions, so it stays visible rather than disappearing
            // every April.
            $query->where(function ($inner) use ($syear) {
                $inner->where('b.syear', $syear)->orWhereNull('b.syear');
            });
        }

        // The tab filters client-side so switching category is instant, but a
        // caller that only wants one kind can say so.
        $kindFilter = $this->kind($request->input('kind', ''));

        if ($request->filled('kind')) {
            $query->where('b.kind', $kindFilter);
        }

        $rows = $query
            ->orderByDesc('b.id')
            ->limit(300)
            ->get(['b.*', 'st.name as standard_name', 'sub.subject_name']);

        $presets = [];

        foreach (AssessmentBlueprintPresets::all() as $preset) {
            $presets[] = $this->presentPreset($preset, self::KIND_REGULAR);
        }

        foreach (HpcBlueprintPresets::all() as $preset) {
            $presets[] = $this->presentPreset($preset, self::KIND_HPC);
        }

        return $this->success([
            'blueprints' => $rows->map(fn ($row) => $this->present($row))->all(),
            'presets' => $presets,
            'options' => $this->options(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $this->useVocabularyOf($tenantId);
        $row = $this->find($id, $tenantId);

        if (! $row) {
            return $this->failure('Blueprint not found.', 404);
        }

        return $this->success(['blueprint' => $this->present($row)]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->save($request, $id);
    }

    /**
     * Copies a reference (or one of the school's own blueprints) into a new,
     * editable row.
     *
     * `parent_id` is set only when cloning another row of ours; a preset has no
     * id to point at, so `preset_key` carries that link instead. Either way the
     * copy records where it came from, which is what makes "how does our paper
     * differ from the board's" answerable a year later.
     */
    public function clone(Request $request): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');

        if (! $tenantId) {
            return $this->failure('sub_institute_id is required.', 422);
        }

        $this->useVocabularyOf($tenantId);

        $presetKey = trim((string) $request->input('preset_key'));
        $sourceId = (int) $request->input('blueprint_id');

        if ($presetKey !== '') {
            $preset = AssessmentBlueprintPresets::find($presetKey);
            $kind = self::KIND_REGULAR;

            if (! $preset) {
                $preset = HpcBlueprintPresets::find($presetKey);
                $kind = self::KIND_HPC;
            }

            if (! $preset) {
                return $this->failure('That reference blueprint no longer exists.', 404);
            }

            $source = [
                'kind' => $kind,
                'stage' => $preset['stage'] ?? null,
                'name' => $preset['name'],
                'description' => $preset['description'],
                'academic_year' => $preset['academic_year'],
                'board' => $preset['board'],
                'class_band' => $preset['class_band'],
                'assessment_type' => $preset['assessment_type'],
                'standard_id' => null,
                'subject_id' => null,
                'subject_label' => $preset['subject_label'],
                // An HPC preset carries neither: having no marks is the point.
                'total_marks' => $preset['total_marks'] ?? 0,
                'duration_minutes' => $preset['duration_minutes'] ?? null,
                'preset_key' => $preset['preset_key'],
                'parent_id' => null,
                'source' => $preset['source'],
                'source_url' => $preset['source_url'],
                'definition' => $preset['definition'],
            ];
        } elseif ($sourceId > 0) {
            $row = $this->find($sourceId, $tenantId);

            if (! $row) {
                return $this->failure('Blueprint not found.', 404);
            }

            $source = [
                'kind' => $this->kind($row->kind ?? self::KIND_REGULAR),
                'stage' => $row->stage ?? null,
                'name' => $row->name . ' (copy)',
                'description' => $row->description,
                'academic_year' => $row->academic_year,
                'board' => $row->board,
                'class_band' => $row->class_band,
                'assessment_type' => $row->assessment_type,
                'standard_id' => $row->standard_id,
                'subject_id' => $row->subject_id,
                'subject_label' => $row->subject_label,
                'total_marks' => $row->total_marks,
                'duration_minutes' => $row->duration_minutes,
                'preset_key' => $row->preset_key,
                'parent_id' => (int) $row->id,
                'source' => $row->source,
                'source_url' => $row->source_url,
                'definition' => json_decode((string) $row->definition, true),
            ];
        } else {
            return $this->failure('Choose a reference blueprint or one of your own to copy.', 422);
        }

        $name = trim((string) $request->input('name'));
        $definition = $this->normalizeDefinition($source['kind'], $source['definition']);

        $id = DB::table('assessment_blueprint')->insertGetId([
            'sub_institute_id' => $tenantId,
            'kind' => $source['kind'],
            'stage' => $source['stage'],
            'syear' => (int) $request->input('syear') ?: null,
            'name' => mb_substr($name !== '' ? $name : $source['name'], 0, 191),
            'description' => $source['description'] ? mb_substr((string) $source['description'], 0, 500) : null,
            'academic_year' => $source['academic_year'],
            'board' => $source['board'],
            'class_band' => $source['class_band'],
            'assessment_type' => $source['assessment_type'],
            'standard_id' => $source['standard_id'],
            'subject_id' => $source['subject_id'],
            'subject_label' => $source['subject_label'],
            'total_marks' => (float) $source['total_marks'],
            'duration_minutes' => $source['duration_minutes'],
            'is_reference' => false,
            'preset_key' => $source['preset_key'],
            'parent_id' => $source['parent_id'],
            'version' => 1,
            'source' => $source['source'],
            'source_url' => $source['source_url'],
            'status' => 'Draft',
            'definition' => json_encode($definition),
            'created_by' => (int) $request->input('user_id') ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->show($request, $id);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $row = $this->find($id, $tenantId);

        if (! $row) {
            return $this->failure('Blueprint not found.', 404);
        }

        DB::table('assessment_blueprint')->where('id', $id)->delete();

        return $this->success([], 'Blueprint removed.');
    }

    /**
     * The chapters a content-weightage row can point at.
     *
     * Without this a coordinator types chapter names by hand and the weightings
     * never join back to anything — which is the difference between a blueprint
     * that produces a report and one that produces a PDF.
     */
    public function chapters(Request $request): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');

        if (! $tenantId) {
            return $this->failure('sub_institute_id is required.', 422);
        }

        $query = DB::table('chapter_master')
            ->where('sub_institute_id', $tenantId)
            ->where(function ($inner) {
                $inner->whereNull('show_hide')->orWhere('show_hide', '!=', 0);
            });

        foreach (['standard_id', 'subject_id', 'grade_id'] as $field) {
            if ($value = (int) $request->input($field)) {
                $query->where($field, $value);
            }
        }

        if ($syear = (int) $request->input('syear')) {
            $query->where(function ($inner) use ($syear) {
                $inner->where('syear', $syear)->orWhereNull('syear');
            });
        }

        $rows = $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(400)
            ->get(['id', 'chapter_name', 'no_of_periods']);

        return $this->success([
            'chapters' => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->chapter_name,
                'periods' => $row->no_of_periods === null ? null : (int) $row->no_of_periods,
            ])->all(),
        ]);
    }

    // -- School HPC options --------------------------------------------------

    /**
     * One school's HPC option lists, with the published defaults alongside.
     *
     * The defaults are sent too so the settings screen can show what a list
     * would revert to, and offer "start from the standard list" without
     * hardcoding NCERT's vocabulary in the frontend.
     */
    public function hpcOptions(Request $request): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');

        if (! $tenantId) {
            return $this->failure('sub_institute_id is required.', 422);
        }

        $defaults = [];

        foreach (HpcVocabularyService::publishedDefaults() as $type => $map) {
            $defaults[$type] = self::pairs($map);
        }

        return $this->success([
            'options' => $this->vocabulary->forTenant($tenantId),
            'defaults' => $defaults,
            'customised_types' => $this->vocabulary->customisedTypes($tenantId),
            'types' => array_keys(HpcVocabularyService::TYPES),
        ]);
    }

    /**
     * Replaces one school's list for one option type.
     *
     * Scoped to the caller's own school by construction -- the tenant comes
     * from the session parameters every other endpoint here uses, never from
     * the body -- so one school editing its assessors cannot reach another's.
     */
    public function saveHpcOptions(Request $request): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $type = trim((string) $request->input('option_type'));

        if (! $tenantId) {
            return $this->failure('sub_institute_id is required.', 422);
        }

        if (! isset(HpcVocabularyService::TYPES[$type])) {
            return $this->failure('Unknown option type.', 422);
        }

        $options = $request->input('options');

        if (! is_array($options)) {
            return $this->failure('Send the full list of options for this type.', 422);
        }

        // An empty list would leave the school with nothing to choose from and
        // read as "reset" to anyone looking at the table later. Say so instead.
        if ($options === []) {
            return $this->failure('A list needs at least one option. Reset it to the standard list instead.', 422);
        }

        $saved = $this->vocabulary->replace(
            $tenantId,
            $type,
            $options,
            (int) $request->input('user_id') ?: null
        );

        return $this->success(
            ['option_type' => $type, 'options' => $saved],
            'Options saved for your school.'
        );
    }

    /** Drops this school's list for one type, so it follows the standard again. */
    public function resetHpcOptions(Request $request): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $type = trim((string) $request->input('option_type'));

        if (! $tenantId) {
            return $this->failure('sub_institute_id is required.', 422);
        }

        if (! isset(HpcVocabularyService::TYPES[$type])) {
            return $this->failure('Unknown option type.', 422);
        }

        $this->vocabulary->resetToDefault($tenantId, $type);

        return $this->success(
            ['option_type' => $type, 'options' => $this->vocabulary->forTenant($tenantId)[$type] ?? []],
            'Reset to the standard list.'
        );
    }

    // -- Plumbing -----------------------------------------------------------

    private function save(Request $request, ?int $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');

        if (! $tenantId) {
            return $this->failure('sub_institute_id is required.', 422);
        }

        $this->useVocabularyOf($tenantId);

        $name = trim((string) $request->input('name'));

        if ($name === '') {
            return $this->failure('Give this blueprint a name.', 422);
        }

        // On an update the kind is whatever the row already is -- a blueprint
        // does not change category, and letting a stray request flip one would
        // reinterpret its whole definition as a different shape.
        $existing = $id !== null ? $this->find($id, $tenantId) : null;
        $kind = $existing ? $this->kind($existing->kind ?? self::KIND_REGULAR) : $this->kind($request->input('kind', self::KIND_REGULAR));

        $definition = $this->normalizeDefinition($kind, $request->input('definition'));
        $status = (string) $request->input('status', 'Draft');

        if ($id !== null && ! $existing) {
            return $this->failure('Blueprint not found.', 404);
        }

        $stage = mb_substr(trim((string) $request->input('stage')), 0, 40)
            ?: ($kind === self::KIND_HPC ? (string) ($definition['stage'] ?? '') : '');

        $payload = [
            'stage' => $stage ?: null,
            'name' => mb_substr($name, 0, 191),
            'description' => mb_substr(trim((string) $request->input('description')), 0, 500) ?: null,
            'academic_year' => mb_substr(trim((string) $request->input('academic_year')), 0, 20) ?: null,
            'board' => mb_substr(trim((string) $request->input('board')), 0, 60) ?: null,
            'class_band' => mb_substr(trim((string) $request->input('class_band')), 0, 40) ?: null,
            'assessment_type' => mb_substr(trim((string) $request->input('assessment_type')), 0, 60) ?: null,
            'standard_id' => (int) $request->input('standard_id') ?: null,
            'subject_id' => (int) $request->input('subject_id') ?: null,
            'subject_label' => mb_substr(trim((string) $request->input('subject_label')), 0, 120) ?: null,
            // Forced to 0 on an HPC rather than trusted from the request:
            // having no marks is the defining property of one, and a stray
            // total would show up on a card that must not carry a score.
            'total_marks' => $kind === self::KIND_HPC ? 0 : max(0, (float) $request->input('total_marks')),
            'duration_minutes' => (int) $request->input('duration_minutes') ?: null,
            'status' => in_array($status, AssessmentBlueprint::STATUSES, true) ? $status : 'Draft',
            'definition' => json_encode($definition),
            'updated_by' => (int) $request->input('user_id') ?: null,
            'updated_at' => now(),
        ];

        if ($id === null) {
            $payload += [
                'sub_institute_id' => $tenantId,
                'kind' => $kind,
                'syear' => (int) $request->input('syear') ?: null,
                'is_reference' => false,
                'preset_key' => mb_substr(trim((string) $request->input('preset_key')), 0, 80) ?: null,
                'parent_id' => (int) $request->input('parent_id') ?: null,
                'version' => 1,
                'source' => mb_substr(trim((string) $request->input('source')), 0, 150) ?: 'School-custom',
                'source_url' => mb_substr(trim((string) $request->input('source_url')), 0, 500) ?: null,
                'created_by' => (int) $request->input('user_id') ?: null,
                'created_at' => now(),
            ];

            $id = DB::table('assessment_blueprint')->insertGetId($payload);
        } else {
            // `version` counts edits, so a school can tell that the design their
            // Term 1 paper was built from is not the one on screen today.
            DB::table('assessment_blueprint')->where('id', $id)->update($payload + [
                'version' => DB::raw('version + 1'),
            ]);
        }

        return $this->show($request, $id);
    }

    private function find(int $id, int $tenantId): ?object
    {
        if (! $tenantId) {
            return null;
        }

        return DB::table('assessment_blueprint')
            ->where('id', $id)
            ->where('sub_institute_id', $tenantId)
            ->first();
    }

    private function present(object $row): array
    {
        $kind = $this->kind($row->kind ?? self::KIND_REGULAR);
        $definition = $this->normalizeDefinition($kind, $row->definition ?? null);
        $totalMarks = (float) $row->total_marks;

        return [
            'id' => (int) $row->id,
            'kind' => $kind,
            'stage' => (string) ($row->stage ?? ''),
            'is_preset' => false,
            'preset_key' => $row->preset_key ? (string) $row->preset_key : null,
            'parent_id' => $row->parent_id ? (int) $row->parent_id : null,
            'version' => (int) $row->version,
            'name' => (string) $row->name,
            'description' => (string) ($row->description ?? ''),
            'academic_year' => (string) ($row->academic_year ?? ''),
            'board' => (string) ($row->board ?? ''),
            'class_band' => (string) ($row->class_band ?? ''),
            'assessment_type' => (string) ($row->assessment_type ?? ''),
            'standard_id' => $row->standard_id ? (int) $row->standard_id : null,
            'standard_name' => (string) ($row->standard_name ?? ''),
            'subject_id' => $row->subject_id ? (int) $row->subject_id : null,
            'subject_name' => (string) ($row->subject_name ?? ''),
            'subject_label' => (string) ($row->subject_label ?? ''),
            'total_marks' => $totalMarks,
            'duration_minutes' => $row->duration_minutes ? (int) $row->duration_minutes : null,
            'source' => (string) ($row->source ?? ''),
            'source_url' => (string) ($row->source_url ?? ''),
            'status' => (string) $row->status,
            'definition' => $definition,
            'section_marks' => $kind === self::KIND_REGULAR
                ? AssessmentBlueprint::marksFromSections($definition)
                : 0.0,
            'warnings' => $this->validateDefinition($kind, $definition, $totalMarks),
            'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
        ];
    }

    /** A reference, in the same shape as a saved row so one list renders both. */
    private function presentPreset(array $preset, string $kind): array
    {
        $definition = $this->normalizeDefinition($kind, $preset['definition']);
        $totalMarks = (float) ($preset['total_marks'] ?? 0);

        return [
            'id' => null,
            'kind' => $kind,
            'stage' => (string) ($preset['stage'] ?? ''),
            'is_preset' => true,
            'preset_key' => $preset['preset_key'],
            'parent_id' => null,
            'version' => 1,
            'name' => $preset['name'],
            'description' => $preset['description'],
            'academic_year' => $preset['academic_year'],
            'board' => $preset['board'],
            'class_band' => $preset['class_band'],
            'assessment_type' => $preset['assessment_type'],
            'standard_id' => null,
            'standard_name' => '',
            'subject_id' => null,
            'subject_name' => '',
            'subject_label' => $preset['subject_label'],
            'total_marks' => $totalMarks,
            'duration_minutes' => $preset['duration_minutes'] ?? null,
            'source' => $preset['source'],
            'source_url' => $preset['source_url'],
            'status' => 'Active',
            'definition' => $definition,
            'section_marks' => $kind === self::KIND_REGULAR
                ? AssessmentBlueprint::marksFromSections($definition)
                : 0.0,
            'warnings' => $this->validateDefinition($kind, $definition, $totalMarks),
            'updated_at' => null,
        ];
    }

    /** Only ever `regular` or `hpc`; anything else is treated as marks-based. */
    private function kind(mixed $value): string
    {
        return trim((string) $value) === self::KIND_HPC ? self::KIND_HPC : self::KIND_REGULAR;
    }

    private function normalizeDefinition(string $kind, mixed $definition): array
    {
        return $kind === self::KIND_HPC
            ? HpcBlueprint::normalize($definition, $this->resolvedVocabulary)
            : AssessmentBlueprint::normalize($definition);
    }

    /**
     * Loads this school's HPC option lists once per request.
     *
     * A reference blueprint is normalised against them too, so a school that
     * has retired an option does not see it ticked on a published design it is
     * about to copy.
     */
    private function useVocabularyOf(int $tenantId): void
    {
        if ($this->vocabularyTenantId === $tenantId) {
            return;
        }

        $this->vocabularyTenantId = $tenantId;
        $this->resolvedVocabulary = $this->vocabulary->codeMapsFor($tenantId);
    }

    /**
     * @return array<int,string>
     */
    private function validateDefinition(string $kind, array $definition, float $totalMarks): array
    {
        return $kind === self::KIND_HPC
            ? HpcBlueprint::validate($definition)
            : AssessmentBlueprint::validate($definition, $totalMarks);
    }

    private function options(): array
    {
        $questionTypes = [];

        foreach (AssessmentBlueprint::QUESTION_TYPES as $code => $label) {
            $questionTypes[] = ['code' => $code, 'label' => $label];
        }

        return [
            'question_types' => $questionTypes,
            'assessment_types' => AssessmentBlueprint::ASSESSMENT_TYPES,
            'boards' => AssessmentBlueprint::BOARDS,
            'statuses' => AssessmentBlueprint::STATUSES,
            'defaults' => AssessmentBlueprint::defaults(),
            // The HPC half of the vocabulary. Sent as {code,label} pairs
            // throughout so the editor never has to hold its own copy of a
            // published list and drift from it -- and drawn from THIS SCHOOL's
            // lists, so a school that added a "Grandparent" assessor sees it in
            // the picker and a school that has not sees the NCERT list.
            'hpc' => [
                'stages' => HpcBlueprint::STAGES,
                'assessors' => $this->vocabularyPairs(HpcVocabularyService::TYPE_ASSESSOR),
                'activity_approaches' => $this->vocabularyPairs(HpcVocabularyService::TYPE_ACTIVITY_APPROACH),
                'evidence_modes' => $this->vocabularyPairs(HpcVocabularyService::TYPE_EVIDENCE_MODE),
                'part_a_elements' => $this->vocabularyPairs(HpcVocabularyService::TYPE_PART_A_ELEMENT),
                'strengths' => HpcBlueprint::STRENGTHS,
                'barriers' => HpcBlueprint::BARRIERS,
                'defaults' => HpcBlueprint::defaults(),
                // Which lists this school has taken over, so the settings screen
                // can show "customised" against them.
                'customised_types' => $this->vocabularyTenantId
                    ? $this->vocabulary->customisedTypes($this->vocabularyTenantId)
                    : [],
            ],
        ];
    }

    /** One option type as {code,label} pairs, from the school's own list. */
    private function vocabularyPairs(string $type): array
    {
        return self::pairs($this->resolvedVocabulary[$type] ?? HpcVocabularyService::publishedDefaults()[$type] ?? []);
    }

    /** @param array<string,string> $map */
    private static function pairs(array $map): array
    {
        $pairs = [];

        foreach ($map as $code => $label) {
            $pairs[] = ['code' => $code, 'label' => $label];
        }

        return $pairs;
    }

    private function success(array $data, string $message = 'SUCCESS'): JsonResponse
    {
        return response()->json(['status_code' => 1, 'message' => $message, 'data' => $data]);
    }

    private function failure(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json(['status_code' => 0, 'message' => $message, 'errors' => $errors], $status);
    }
}
