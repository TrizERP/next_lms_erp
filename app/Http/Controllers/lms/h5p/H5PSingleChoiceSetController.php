<?php

namespace App\Http\Controllers\lms\h5p;

use App\Models\lms\h5p\H5pSingleChoiceOption;
use App\Models\lms\h5p\H5pSingleChoiceQuestion;
use App\Models\lms\h5p\H5pSingleChoiceSet;
use App\Services\lms\H5P\H5PSingleChoiceSetBuilder;
use App\Services\lms\H5P\H5PSingleChoiceSetPackageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * H5P Single Choice Set (H5P.SingleChoiceSet).
 *
 * A two-level type -- a set, its questions, their options -- so it writes
 * children in two passes: questions first, then each question's options
 * against the id the insert just produced. There are no refs to resolve the
 * way a Course Presentation needs, because nothing in a single choice set
 * points at anything else in it.
 *
 * THE INVARIANT THIS CONTROLLER EXISTS TO PROTECT: EXACTLY ONE CORRECT OPTION
 * PER QUESTION. Laravel's rule set cannot express "exactly one element of this
 * nested array has a flag set", so it is checked explicitly in
 * `assertOneCorrectAnswer()` and raised as a normal validation error naming
 * the question -- not as a publish blocker. A set saved with two right answers
 * would be a set whose export is silently wrong (the builder writes the FIRST
 * correct option to `answers[0]` and demotes the other to a distractor), and
 * an author would have no way to see that had happened. It is refused at the
 * door instead.
 *
 * The publish check then re-tests the same invariant against the stored rows,
 * because a row can also arrive by import, by duplicate, or by a future
 * caller, and publish is the last gate before a class sees it.
 */
class H5PSingleChoiceSetController extends H5PContentTypeController
{
    public function __construct(
        private readonly H5PSingleChoiceSetBuilder $builder,
        private readonly H5PSingleChoiceSetPackageService $packages
    ) {
    }

    protected function modelClass(): string
    {
        return H5pSingleChoiceSet::class;
    }

    protected function registryCode(): string
    {
        return 'single_choice_set';
    }

    protected function routePrefix(): string
    {
        return 'h5p_single_choice_set';
    }

    protected function payloadKey(): string
    {
        return 'singleChoiceSet';
    }

    protected function listKey(): string
    {
        return 'singleChoiceSetLists';
    }

    protected function label(): string
    {
        return 'Single choice set';
    }

    protected function relations(): array
    {
        return ['questions.options'];
    }

    /**
     * Both child tables, deepest first.
     *
     * The base class derives this from relations() by stripping nested paths,
     * which would give `questions` alone and leave every option behind as an
     * orphan on each update. The flat `options()` relation on the set -- the
     * reason the options table carries a denormalised `set_id` -- is what
     * makes the deeper sweep one query.
     */
    protected function childRelations(): array
    {
        return ['options', 'questions'];
    }

    protected function viewPath(): string
    {
        return 'lms/h5p/singlechoiceset';
    }

    /** No media. The `media` endpoint is not routed for this type. */
    protected function mediaRoles(): array
    {
        return [];
    }

    protected function authoringDefaults(): array
    {
        return [
            'auto_continue' => true,
            'timeout_correct_ms' => 2000,
            'timeout_wrong_ms' => 3000,
            'sound_effects' => false,
            'enable_retry' => true,
            'enable_show_solution' => true,
            'randomize_questions' => false,
            'randomize_answers' => true,
            'points_per_question' => 1,
            'pass_percentage' => 60,
            'show_progress' => true,
            'max_questions' => H5pSingleChoiceSet::MAX_QUESTIONS,
            'min_options' => H5pSingleChoiceSet::MIN_OPTIONS,
            'max_options' => H5pSingleChoiceSet::MAX_OPTIONS,
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
            'task_description' => 'nullable|string|max:2000',

            'auto_continue' => 'nullable|boolean',
            // 0 to 10 seconds. Zero is "no pause", which is what an author
            // pairing this with auto-continue for a speed round wants.
            'timeout_correct_ms' => 'nullable|integer|min:0|max:10000',
            'timeout_wrong_ms' => 'nullable|integer|min:0|max:10000',
            'sound_effects' => 'nullable|boolean',

            'enable_retry' => 'nullable|boolean',
            'enable_show_solution' => 'nullable|boolean',
            'randomize_questions' => 'nullable|boolean',
            'randomize_answers' => 'nullable|boolean',

            'points_per_question' => 'nullable|integer|min:1|max:100',
            'pass_percentage' => 'nullable|integer|min:0|max:100',
            'show_progress' => 'nullable|boolean',

            'feedback_bands' => 'nullable|array',
            'feedback_bands.*.from' => 'required|integer|min:0|max:100',
            'feedback_bands.*.to' => 'required|integer|min:0|max:100',
            'feedback_bands.*.feedback' => 'nullable|string|max:500',

            'questions' => 'required|array|min:1|max:' . H5pSingleChoiceSet::MAX_QUESTIONS,
            'questions.*.question_text' => 'required|string|max:5000',
            'questions.*.feedback_correct' => 'nullable|string|max:1000',
            'questions.*.feedback_incorrect' => 'nullable|string|max:1000',
            'questions.*.explanation' => 'nullable|string|max:2000',

            'questions.*.options' => 'required|array|min:' . H5pSingleChoiceSet::MIN_OPTIONS
                . '|max:' . H5pSingleChoiceSet::MAX_OPTIONS,
            'questions.*.options.*.option_text' => 'required|string|max:1000',
            'questions.*.options.*.is_correct' => 'nullable|boolean',
            'questions.*.options.*.feedback' => 'nullable|string|max:1000',
        ];
    }

    public function store(Request $request)
    {
        $this->assertOneCorrectAnswer($request);

        return parent::store($request);
    }

    public function update(Request $request, $id)
    {
        $this->assertOneCorrectAnswer($request);

        return parent::update($request, $id);
    }

    /**
     * Refuse a save where any question does not have exactly one right answer.
     *
     * Raised as a validation error keyed to the offending question, so the
     * editor can put the message beside it rather than in a banner at the top
     * of a form that is thirty questions long. See the class header for why
     * this is a save-time refusal and not a publish-time one.
     */
    private function assertOneCorrectAnswer(Request $request): void
    {
        $errors = [];

        foreach (array_values((array) $request->input('questions', [])) as $index => $question) {
            $options = array_values((array) ($question['options'] ?? []));

            $correct = 0;
            foreach ($options as $option) {
                if (filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $correct++;
                }
            }

            if ($correct === 1) {
                continue;
            }

            $errors['questions.' . $index . '.options'] = [
                $correct === 0
                    ? sprintf('Question %d has no correct answer. Mark exactly one option correct.', $index + 1)
                    : sprintf('Question %d has %d correct answers. A single choice question has exactly one.', $index + 1, $correct),
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function attributesFrom(array $data, Request $request): array
    {
        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'task_description' => $data['task_description'] ?? null,
            'auto_continue' => $data['auto_continue'] ?? true,
            'timeout_correct_ms' => $data['timeout_correct_ms'] ?? 2000,
            'timeout_wrong_ms' => $data['timeout_wrong_ms'] ?? 3000,
            'sound_effects' => $data['sound_effects'] ?? false,
            'enable_retry' => $data['enable_retry'] ?? true,
            'enable_show_solution' => $data['enable_show_solution'] ?? true,
            'randomize_questions' => $data['randomize_questions'] ?? false,
            'randomize_answers' => $data['randomize_answers'] ?? true,
            'points_per_question' => $data['points_per_question'] ?? 1,
            'pass_percentage' => $data['pass_percentage'] ?? 60,
            'show_progress' => $data['show_progress'] ?? true,
            'feedback_bands' => $data['feedback_bands'] ?? null,
        ];
    }

    // -----------------------------------------------------------------------
    // Children
    // -----------------------------------------------------------------------

    protected function syncChildren(Model $item, array $data, int|string|null $subInstituteId, int|string|null $userId): void
    {
        $audit = [
            'sub_institute_id' => $subInstituteId,
            'created_by' => $userId,
            'created_at' => now(),
        ];

        foreach (array_values($data['questions']) as $order => $question) {
            $row = H5pSingleChoiceQuestion::create([
                'set_id' => $item->id,
                'question_text' => $question['question_text'],
                'feedback_correct' => $this->nullable($question['feedback_correct'] ?? null),
                'feedback_incorrect' => $this->nullable($question['feedback_incorrect'] ?? null),
                'explanation' => $this->nullable($question['explanation'] ?? null),
                'sort_order' => $order,
            ] + $audit);

            foreach (array_values((array) $question['options']) as $optionOrder => $option) {
                H5pSingleChoiceOption::create([
                    'question_id' => $row->id,
                    // Denormalised, and written here rather than derived on
                    // read, so the flat `options()` relation is correct from
                    // the moment the row exists.
                    'set_id' => $item->id,
                    'option_text' => $option['option_text'],
                    'is_correct' => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'feedback' => $this->nullable($option['feedback'] ?? null),
                    'sort_order' => $optionOrder,
                ] + $audit);
            }
        }
    }

    protected function duplicableColumns(): array
    {
        return [
            'description', 'task_description', 'auto_continue', 'timeout_correct_ms',
            'timeout_wrong_ms', 'sound_effects', 'enable_retry', 'enable_show_solution',
            'randomize_questions', 'randomize_answers', 'points_per_question',
            'pass_percentage', 'show_progress', 'feedback_bands',
            'standard_id', 'subject_id', 'chapter_id', 'syear',
        ];
    }

    protected function duplicateChildren(Model $original, Model $copy, int|string|null $subInstituteId, int|string|null $userId): void
    {
        $audit = ['sub_institute_id' => $subInstituteId, 'created_by' => $userId, 'created_at' => now()];

        foreach ($original->questions as $question) {
            $row = H5pSingleChoiceQuestion::create($question->only([
                'question_text', 'feedback_correct', 'feedback_incorrect', 'explanation', 'sort_order',
            ]) + ['set_id' => $copy->id] + $audit);

            foreach ($question->options as $option) {
                H5pSingleChoiceOption::create($option->only([
                    'option_text', 'is_correct', 'feedback', 'sort_order',
                ]) + ['question_id' => $row->id, 'set_id' => $copy->id] + $audit);
            }
        }
    }

    // -----------------------------------------------------------------------
    // Publish
    // -----------------------------------------------------------------------

    protected function publishBlocker(Model $item): ?string
    {
        $item->load('questions.options');

        if ($item->questions->isEmpty()) {
            return 'Add at least one question before publishing.';
        }

        foreach ($item->questions as $question) {
            $position = (int) $question->sort_order + 1;

            if (trim(strip_tags((string) $question->question_text)) === '') {
                return sprintf('Question %d has no text. Write the question before publishing.', $position);
            }

            $options = $question->options;

            if ($options->count() < H5pSingleChoiceSet::MIN_OPTIONS) {
                return sprintf(
                    'Question %d ("%s") has fewer than %d answer options.',
                    $position,
                    $question->plainQuestion(40),
                    H5pSingleChoiceSet::MIN_OPTIONS
                );
            }

            $blank = $options->first(fn (H5pSingleChoiceOption $option) => trim(strip_tags((string) $option->option_text)) === '');
            if ($blank !== null) {
                return sprintf('Question %d has a blank answer option. Fill it in or remove it.', $position);
            }

            // The same invariant the save path refuses. Re-checked because a
            // row can also arrive by import or duplicate, and publish is the
            // last gate before a class sees it.
            $correct = $question->correctCount();
            if ($correct !== 1) {
                return $correct === 0
                    ? sprintf('Question %d ("%s") has no correct answer.', $position, $question->plainQuestion(40))
                    : sprintf('Question %d ("%s") has %d correct answers. Exactly one is allowed.', $position, $question->plainQuestion(40), $correct);
            }
        }

        return null;
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
        $set = H5pSingleChoiceSet::create($parsed['set'] + [
            'title' => $parsed['title'],
            'description' => '',
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
            'chapter_id' => $request->chapter_id,
            'sub_institute_id' => $subInstituteId,
            'syear' => $request->input('syear'),
            // An imported set always lands as a draft. The teacher checks it
            // against this chapter before students see it.
            'status' => 'draft',
            'library' => $this->libraryVersionString(),
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        $audit = ['sub_institute_id' => $subInstituteId, 'created_by' => $userId, 'created_at' => now()];

        foreach ($parsed['questions'] as $question) {
            $options = $question['options'];
            unset($question['options']);

            $row = H5pSingleChoiceQuestion::create($question + ['set_id' => $set->id] + $audit);

            foreach ($options as $option) {
                H5pSingleChoiceOption::create($option + [
                    'question_id' => $row->id,
                    'set_id' => $set->id,
                ] + $audit);
            }
        }

        return $set;
    }

    /** "" and null both mean "nothing authored"; the column holds null. */
    private function nullable(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
