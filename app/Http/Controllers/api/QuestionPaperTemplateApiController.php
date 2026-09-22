<?php

namespace App\Http\Controllers\api;

use App\Domain\Exam\QuestionPaperTemplateBlueprint;
use App\Domain\Exam\QuestionPaperTemplatePresets;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Question paper templates for the LMS Exam module.
 *
 * A template is a reusable *layout* -- it says how a paper is laid out, never
 * what is on it. The school, exam, questions and marks are resolved at render
 * time from the selected question paper, so one template works for every
 * school. See QuestionPaperTemplateBlueprint for the stored shape.
 *
 * Templates are rows in the existing per-school `template_master` table under
 * `module_name = 'Question Paper'`; the blueprint JSON lives in `html_content`.
 * Reusing that table keeps the feature free of a schema change and inherits the
 * tenant scoping (`sub_institute_id`) that the rest of that table already has.
 *
 * `paper()` is the read side: it returns one question paper with its questions,
 * question type names and options already joined, which is what the renderer
 * needs and what `/api/question-paper/{id}` does not provide. That controller
 * is deliberately left untouched so the existing Exam screens cannot regress.
 */
class QuestionPaperTemplateApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');

        if (! $tenantId) {
            return $this->failure('sub_institute_id is required.', 422);
        }

        $rows = DB::table('template_master')
            ->where('module_name', QuestionPaperTemplateBlueprint::MODULE_NAME)
            ->where('sub_institute_id', $tenantId)
            ->where(function ($query) {
                $query->where('status', 1)->orWhereNull('status');
            })
            ->orderByDesc('id')
            ->get();

        $templates = $rows->map(fn ($row) => $this->presentRow($row))->values()->all();

        $presets = array_map(fn ($preset) => [
            'id' => null,
            'preset_key' => $preset['key'],
            'name' => $preset['name'],
            'description' => $preset['description'],
            'is_preset' => true,
            'blueprint' => QuestionPaperTemplateBlueprint::normalize($preset['blueprint']),
            'created_on' => null,
            'created_by' => null,
        ], QuestionPaperTemplatePresets::all());

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => [
                'templates' => $templates,
                'presets' => $presets,
                'options' => [
                    'placeholders' => $this->placeholderList(),
                    'source_modes' => QuestionPaperTemplateBlueprint::SOURCE_MODES,
                    'question_types' => $this->questionTypes(),
                    'defaults' => QuestionPaperTemplateBlueprint::defaults(),
                ],
            ],
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $row = $this->findRow((int) $id, $tenantId);

        if (! $row) {
            return $this->failure('Template not found.', 404);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $this->presentRow($row),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, $id): JsonResponse
    {
        return $this->save($request, (int) $id);
    }

    /**
     * Archive rather than erase: `status = 0` drops the template out of every
     * list while leaving papers already printed from it traceable.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');

        if (! $tenantId) {
            return $this->failure('sub_institute_id is required.', 422);
        }

        $affected = DB::table('template_master')
            ->where('id', (int) $id)
            ->where('sub_institute_id', $tenantId)
            ->where('module_name', QuestionPaperTemplateBlueprint::MODULE_NAME)
            ->update(['status' => 0]);

        if (! $affected) {
            return $this->failure('Template not found.', 404);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Template removed successfully',
            'data' => [],
        ]);
    }

    /**
     * One question paper, shaped for rendering: header fields, then every
     * question with its type name, marks and options resolved.
     */
    public function paper(Request $request, $paperId): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');

        if (! $tenantId) {
            return $this->failure('sub_institute_id is required.', 422);
        }

        $paper = DB::table('question_paper')
            ->leftJoin('standard', 'standard.id', '=', 'question_paper.standard_id')
            ->leftJoin('academic_section', 'academic_section.id', '=', 'question_paper.grade_id')
            ->leftJoin('sub_std_map as ssm', function ($join) use ($tenantId) {
                $join->on('ssm.subject_id', '=', 'question_paper.subject_id')
                    ->on('ssm.standard_id', '=', 'question_paper.standard_id')
                    ->where('ssm.sub_institute_id', $tenantId);
            })
            ->leftJoin('subject', 'subject.id', '=', 'question_paper.subject_id')
            ->where('question_paper.id', (int) $paperId)
            ->whereIn('question_paper.sub_institute_id', $this->readableTenantIds($tenantId))
            ->select(
                'question_paper.*',
                'standard.name as standard_name',
                'academic_section.title as grade_name',
                DB::raw('COALESCE(ssm.display_name, subject.subject_name) as subject_name')
            )
            ->first();

        if (! $paper) {
            return $this->failure('Question paper not found.', 404);
        }

        $questionIds = collect(explode(',', (string) $paper->question_ids))
            ->map(fn ($value) => (int) trim($value))
            ->filter()
            ->values();

        $questions = [];

        if ($questionIds->isNotEmpty()) {
            // Only the columns a student is meant to read. `description`,
            // `concept`, `hint_text` and `learning_outcome` are the authoring
            // side of the row -- the generator writes a teacher-facing
            // rationale into `description` (Bloom level, ability reference, the
            // misconception each distractor targets) -- and none of that
            // belongs on a printed exam paper, so it is never sent.
            $query = DB::table('lms_question_master as q')
                ->leftJoin('question_type_master as qt', 'qt.id', '=', 'q.question_type_id')
                ->leftJoin('chapter_master as ch', 'ch.id', '=', 'q.chapter_id')
                ->whereIn('q.id', $questionIds)
                ->select(
                    'q.id',
                    'q.question_type_id',
                    'q.question_title',
                    'q.points',
                    'q.multiple_answer',
                    'q.chapter_id',
                    'qt.question_type',
                    'ch.chapter_name'
                );

            // The catalogue form this question was ingested under, so a section
            // pointed at a `question_type_catalog` code collects it. Joined
            // only where both tables exist; an install without the extraction
            // sidecar still renders on the grading type alone.
            if ($this->hasQuestionTypeCatalogue()) {
                $query
                    ->leftJoin('lms_question_extraction as qx', 'qx.question_id', '=', 'q.id')
                    ->leftJoin('question_type_catalog as qtc', 'qtc.code', '=', 'qx.question_type_code')
                    ->addSelect(
                        'qx.question_type_code as question_type_code',
                        'qtc.label as question_type_label'
                    );
            }

            $rows = $query->get()->keyBy('id');

            $options = DB::table('answer_master')
                ->whereIn('question_id', $questionIds)
                ->orderBy('id')
                ->get(['id', 'question_id', 'answer', 'correct_answer'])
                ->groupBy('question_id');

            $figures = $this->figuresFor($questionIds->all());

            // Keep the order the paper was built in -- `question_ids` is the
            // teacher's chosen sequence and whereIn() does not preserve it.
            foreach ($questionIds as $questionId) {
                $row = $rows->get($questionId);

                if (! $row) {
                    continue;
                }

                $questions[] = [
                    'id' => (int) $row->id,
                    'question_type_id' => (int) $row->question_type_id,
                    'question_type' => (string) ($row->question_type ?? ''),
                    'question_type_code' => trim((string) ($row->question_type_code ?? '')),
                    'question_type_label' => trim((string) ($row->question_type_label ?? '')),
                    'question_title' => (string) ($row->question_title ?? ''),
                    'points' => (float) ($row->points ?? 0),
                    'multiple_answer' => (int) ($row->multiple_answer ?? 0),
                    'chapter_id' => $row->chapter_id === null ? null : (int) $row->chapter_id,
                    'chapter_name' => (string) ($row->chapter_name ?? ''),
                    'figures' => $figures[$questionId] ?? [],
                    'options' => collect($options->get($questionId, []))
                        ->map(fn ($option) => [
                            'id' => (int) $option->id,
                            'text' => (string) ($option->answer ?? ''),
                            'is_correct' => (int) ($option->correct_answer ?? 0) === 1,
                        ])
                        ->values()
                        ->all(),
                ];
            }
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => [
                'paper' => [
                    'id' => (int) $paper->id,
                    'paper_name' => (string) ($paper->paper_name ?? ''),
                    'paper_desc' => (string) ($paper->paper_desc ?? ''),
                    'exam_type' => (string) ($paper->exam_type ?? ''),
                    'grade_id' => (int) ($paper->grade_id ?? 0),
                    'grade_name' => (string) ($paper->grade_name ?? ''),
                    'standard_id' => (int) ($paper->standard_id ?? 0),
                    'standard_name' => (string) ($paper->standard_name ?? ''),
                    'subject_id' => (int) ($paper->subject_id ?? 0),
                    'subject_name' => (string) ($paper->subject_name ?? ''),
                    'syear' => (string) ($paper->syear ?? ''),
                    'open_date' => (string) ($paper->open_date ?? ''),
                    'close_date' => (string) ($paper->close_date ?? ''),
                    'timelimit_enable' => (int) ($paper->timelimit_enable ?? 0),
                    'time_allowed' => (int) ($paper->time_allowed ?? 0),
                    'total_ques' => (int) ($paper->total_ques ?? 0),
                    'total_marks' => (float) ($paper->total_marks ?? 0),
                ],
                'questions' => $questions,
            ],
        ]);
    }

    private function save(Request $request, ?int $id): JsonResponse
    {
        $isUpdate = $id !== null;
        $tenantId = (int) $request->input('sub_institute_id');

        if (! $tenantId) {
            return $this->failure('sub_institute_id is required.', 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'blueprint' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors()->toArray());
        }

        $name = trim((string) $request->input('name'));

        $duplicate = DB::table('template_master')
            ->where('module_name', QuestionPaperTemplateBlueprint::MODULE_NAME)
            ->where('sub_institute_id', $tenantId)
            ->where('title', $name)
            ->where('status', 1)
            ->when($id, fn ($query) => $query->where('id', '<>', $id))
            ->exists();

        if ($duplicate) {
            return $this->failure('A template with this name already exists.', 422);
        }

        $payload = json_encode([
            'name' => $name,
            'description' => trim((string) $request->input('description', '')),
            'preset_key' => trim((string) $request->input('preset_key', '')) ?: null,
            'blueprint' => QuestionPaperTemplateBlueprint::normalize($request->input('blueprint')),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $values = [
            'module_name' => QuestionPaperTemplateBlueprint::MODULE_NAME,
            'title' => $name,
            'html_content' => $payload,
            'status' => 1,
            'sub_institute_id' => $tenantId,
            'created_by' => (int) $request->input('user_id'),
        ];

        if ($id) {
            $affected = DB::table('template_master')
                ->where('id', $id)
                ->where('sub_institute_id', $tenantId)
                ->where('module_name', QuestionPaperTemplateBlueprint::MODULE_NAME)
                ->update($values);

            if (! $affected && ! $this->findRow($id, $tenantId)) {
                return $this->failure('Template not found.', 404);
            }
        } else {
            $values['created_on'] = now();
            $id = (int) DB::table('template_master')->insertGetId($values);
        }

        $row = $this->findRow($id, $tenantId);

        return response()->json([
            'status_code' => 1,
            'message' => $isUpdate ? 'Template updated successfully' : 'Template saved successfully',
            'data' => $row ? $this->presentRow($row) : null,
        ]);
    }

    private function findRow(int $id, int $tenantId): ?object
    {
        if (! $id || ! $tenantId) {
            return null;
        }

        return DB::table('template_master')
            ->where('id', $id)
            ->where('sub_institute_id', $tenantId)
            ->where('module_name', QuestionPaperTemplateBlueprint::MODULE_NAME)
            ->first();
    }

    private function presentRow(object $row): array
    {
        $stored = json_decode((string) $row->html_content, true);
        $stored = is_array($stored) ? $stored : [];

        return [
            'id' => (int) $row->id,
            'preset_key' => $stored['preset_key'] ?? null,
            'name' => (string) ($stored['name'] ?? $row->title ?? ''),
            'description' => (string) ($stored['description'] ?? ''),
            'is_preset' => false,
            'blueprint' => QuestionPaperTemplateBlueprint::normalize($stored['blueprint'] ?? null),
            'created_on' => $row->created_on ?? null,
            'created_by' => $row->created_by === null ? null : (int) $row->created_by,
        ];
    }

    /**
     * The diagrams, maps and figures attached to these questions, keyed by
     * question id and in the order the extraction recorded them.
     *
     * A figure is exam content -- on a map-reading or circuit question the
     * image *is* the question -- so the paper is wrong without it. Read from
     * `lms_question_asset`, the same source the question bank card renders
     * from, so the figure a teacher saw when picking the question is the one
     * that prints.
     *
     * Only what the paper needs: the url, the intrinsic size (so the sheet can
     * hold the aspect ratio) and the alt text. The extraction metadata stored
     * alongside -- `ocr_text`, `asset_sha256`, `source_page` -- is authoring
     * data and stays off the paper.
     *
     * A question with no figure gets an empty list and nothing is printed in
     * its place; a placeholder box on a real exam paper reads as a printing
     * fault.
     */
    private function figuresFor(array $questionIds): array
    {
        if ($questionIds === [] || ! Schema::hasTable('lms_question_asset')) {
            return [];
        }

        $byQuestion = [];

        $rows = DB::table('lms_question_asset')
            ->whereIn('question_id', $questionIds)
            ->orderBy('ordinal')
            ->get(['question_id', 'stored_url', 'width', 'height', 'alt_text']);

        foreach ($rows as $row) {
            $url = trim((string) ($row->stored_url ?? ''));

            if ($url === '') {
                continue;
            }

            $byQuestion[(int) $row->question_id][] = [
                'url' => $url,
                'width' => $row->width !== null ? (int) $row->width : null,
                'height' => $row->height !== null ? (int) $row->height : null,
                'caption' => trim((string) ($row->alt_text ?? '')),
            ];
        }

        return $byQuestion;
    }

    /**
     * Whether a question's catalogue form can be resolved at all: the
     * catalogue itself plus the extraction sidecar that ties a question to a
     * code. Cached per request -- `paper()` would otherwise ask twice.
     */
    private function hasQuestionTypeCatalogue(): bool
    {
        static $has = null;

        if ($has === null) {
            $has = Schema::hasTable('question_type_catalog')
                && Schema::hasTable('lms_question_extraction');
        }

        return $has;
    }

    /**
     * The question forms a section can be pointed at -- so a template says
     * "put the multiple-choice questions in Section A" rather than the
     * frontend shipping a hardcoded list.
     *
     * question_type_catalog is the authority: it is the rich vocabulary the
     * extraction pipeline classifies against (MCQ, assertion-reason, case
     * study, plus whatever a publisher invented) and it carries the `label` a
     * school has configured for each `code`. The editor shows those labels and
     * stores the codes, and `paper()` resolves the same code onto every
     * question, which is what makes the two sides agree.
     *
     * question_type_master remains the fallback for an install whose catalogue
     * has not been populated yet -- those eight grading rows are better than an
     * empty dropdown, and the renderer matches either spelling.
     */
    private function questionTypes(): array
    {
        if (Schema::hasTable('question_type_catalog')) {
            $catalogue = DB::table('question_type_catalog')
                ->where('status', 1)
                // Standard forms first, then alphabetical -- the same order the
                // question bank's own filter uses.
                ->orderByDesc('is_standard')
                ->orderBy('label')
                ->get(['id', 'code', 'label'])
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'code' => trim((string) $row->code),
                    'label' => trim((string) $row->label) ?: trim((string) $row->code),
                ])
                ->filter(fn ($row) => $row['code'] !== '')
                ->unique('code')
                ->values()
                ->all();

            if ($catalogue !== []) {
                return $catalogue;
            }
        }

        return DB::table('question_type_master')
            ->where('status', 1)
            ->orderBy('question_type')
            ->get(['id', 'question_type'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => trim((string) $row->question_type),
                'label' => trim((string) $row->question_type),
            ])
            ->filter(fn ($row) => $row['code'] !== '')
            ->unique('code')
            ->values()
            ->all();
    }

    /**
     * Schools on shared LMS content read tenant 1's rows alongside their own --
     * the same rule ApiQuestionPaperController::index() applies to papers.
     */
    private function readableTenantIds(int $tenantId): array
    {
        $isLms = DB::table('school_setup')->where('Id', $tenantId)->value('is_Lms');

        return $isLms === 'Y' ? array_values(array_unique([$tenantId, 1])) : [$tenantId];
    }

    private function placeholderList(): array
    {
        $rows = [];

        foreach (QuestionPaperTemplateBlueprint::PLACEHOLDERS as $token => $description) {
            $rows[] = ['token' => '{{'.$token.'}}', 'description' => $description];
        }

        return $rows;
    }

    private function failure(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'status_code' => 0,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
