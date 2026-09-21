<?php

namespace App\Http\Controllers\lms\h5p;

use App\Models\lms\h5p\H5pTrueFalse;
use App\Models\lms\h5p\H5pTrueFalseQuestion;
use App\Services\lms\H5P\H5PTrueFalseBuilder;
use App\Services\lms\H5P\H5PTrueFalsePackageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * H5P True/False (H5P.TrueFalse).
 *
 * A one-level type: an item and its pool of statements. Simpler than Single
 * Choice Set because a true/false question has no options table -- the two
 * answers are the type.
 *
 * WHAT IS WORTH READING HERE IS THE PUBLISH CHECK, and specifically the
 * all-true / all-false warning it raises. A pool whose every answer is the
 * same is not a syntax error and will run; it is a pool a class can score full
 * marks on by pressing the same button ten times, which makes the result
 * meaningless and is a mistake a teacher makes by writing statements they know
 * to be true and forgetting to write any they know to be false. Publish is the
 * right place to catch it, because a draft is allowed to be half-written.
 *
 * `questions_to_ask` IS CLAMPED, NOT REFUSED, against the pool. See
 * H5pTrueFalse::questionsPerAttempt(): an author who deletes four questions
 * from a pool of twenty has not thereby broken a published activity, and
 * refusing the save would leave them unable to fix anything else either.
 */
class H5PTrueFalseController extends H5PContentTypeController
{
    public function __construct(
        private readonly H5PTrueFalseBuilder $builder,
        private readonly H5PTrueFalsePackageService $packages
    ) {
    }

    protected function modelClass(): string
    {
        return H5pTrueFalse::class;
    }

    protected function registryCode(): string
    {
        return 'true_false';
    }

    protected function routePrefix(): string
    {
        return 'h5p_true_false';
    }

    protected function payloadKey(): string
    {
        return 'trueFalse';
    }

    protected function listKey(): string
    {
        return 'trueFalseLists';
    }

    protected function label(): string
    {
        return 'True or false';
    }

    protected function relations(): array
    {
        return ['questions'];
    }

    protected function viewPath(): string
    {
        return 'lms/h5p/truefalse';
    }

    /** One image per statement, through the shared upload endpoint. */
    protected function mediaRoles(): array
    {
        return ['image' => 'image'];
    }

    protected function authoringDefaults(): array
    {
        return [
            'enable_retry' => true,
            'enable_show_solution' => true,
            'enable_check_button' => true,
            'auto_check' => false,
            'confirm_check_dialog' => false,
            'confirm_retry_dialog' => false,
            'randomize_questions' => false,
            'questions_to_ask' => 0,
            'points_per_question' => 1,
            'pass_percentage' => 60,
            'show_progress' => true,
            'max_questions' => H5pTrueFalse::MAX_QUESTIONS,
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

            'enable_retry' => 'nullable|boolean',
            'enable_show_solution' => 'nullable|boolean',
            'enable_check_button' => 'nullable|boolean',
            'auto_check' => 'nullable|boolean',
            'confirm_check_dialog' => 'nullable|boolean',
            'confirm_retry_dialog' => 'nullable|boolean',

            'randomize_questions' => 'nullable|boolean',
            'questions_to_ask' => 'nullable|integer|min:0|max:' . H5pTrueFalse::MAX_QUESTIONS,

            'points_per_question' => 'nullable|integer|min:1|max:100',
            'pass_percentage' => 'nullable|integer|min:0|max:100',
            'show_progress' => 'nullable|boolean',

            'feedback_bands' => 'nullable|array',
            'feedback_bands.*.from' => 'required|integer|min:0|max:100',
            'feedback_bands.*.to' => 'required|integer|min:0|max:100',
            'feedback_bands.*.feedback' => 'nullable|string|max:500',

            'questions' => 'required|array|min:1|max:' . H5pTrueFalse::MAX_QUESTIONS,
            'questions.*.question_text' => 'required|string|max:5000',
            // `required` and not `nullable`: an omitted answer would default
            // to true, which is a statement marked correct by accident rather
            // than by decision. The editor always sends it.
            'questions.*.correct_answer' => 'required|boolean',
            'questions.*.feedback_correct' => 'nullable|string|max:1000',
            'questions.*.feedback_incorrect' => 'nullable|string|max:1000',
            'questions.*.explanation' => 'nullable|string|max:2000',
            'questions.*.media_image' => 'nullable|string|max:2048',
            'questions.*.media_alt' => 'nullable|string|max:255',
        ];
    }

    protected function attributesFrom(array $data, Request $request): array
    {
        $pool = count((array) ($data['questions'] ?? []));

        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'task_description' => $data['task_description'] ?? null,
            'enable_retry' => $data['enable_retry'] ?? true,
            'enable_show_solution' => $data['enable_show_solution'] ?? true,
            'enable_check_button' => $data['enable_check_button'] ?? true,
            'auto_check' => $data['auto_check'] ?? false,
            'confirm_check_dialog' => $data['confirm_check_dialog'] ?? false,
            'confirm_retry_dialog' => $data['confirm_retry_dialog'] ?? false,
            'randomize_questions' => $data['randomize_questions'] ?? false,
            // Clamped against the pool being saved in the same request rather
            // than against the pool on disk, which is about to be replaced.
            // See the class header for why this clamps instead of refusing.
            'questions_to_ask' => min(max(0, (int) ($data['questions_to_ask'] ?? 0)), $pool),
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
            $image = $this->nullable($question['media_image'] ?? null);

            H5pTrueFalseQuestion::create([
                'true_false_id' => $item->id,
                'question_text' => $question['question_text'],
                'correct_answer' => filter_var($question['correct_answer'], FILTER_VALIDATE_BOOLEAN),
                'feedback_correct' => $this->nullable($question['feedback_correct'] ?? null),
                'feedback_incorrect' => $this->nullable($question['feedback_incorrect'] ?? null),
                'explanation' => $this->nullable($question['explanation'] ?? null),
                'media_image' => $image,
                // Alt text without an image is a description of nothing, and
                // would survive into an export as an empty H5P.Image node.
                'media_alt' => $image !== null ? $this->nullable($question['media_alt'] ?? null) : null,
                'sort_order' => $order,
            ] + $audit);
        }
    }

    protected function duplicableColumns(): array
    {
        return [
            'description', 'task_description', 'enable_retry', 'enable_show_solution',
            'enable_check_button', 'auto_check', 'confirm_check_dialog', 'confirm_retry_dialog',
            'randomize_questions', 'questions_to_ask', 'points_per_question',
            'pass_percentage', 'show_progress', 'feedback_bands',
            'standard_id', 'subject_id', 'chapter_id', 'syear',
        ];
    }

    protected function duplicateChildren(Model $original, Model $copy, int|string|null $subInstituteId, int|string|null $userId): void
    {
        foreach ($original->questions as $question) {
            H5pTrueFalseQuestion::create($question->only([
                'question_text', 'correct_answer', 'feedback_correct', 'feedback_incorrect',
                'explanation', 'media_image', 'media_alt', 'sort_order',
            ]) + [
                'true_false_id' => $copy->id,
                'sub_institute_id' => $subInstituteId,
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        }
    }

    // -----------------------------------------------------------------------
    // Publish
    // -----------------------------------------------------------------------

    protected function publishBlocker(Model $item): ?string
    {
        $item->load('questions');

        if ($item->questions->isEmpty()) {
            return 'Add at least one statement before publishing.';
        }

        foreach ($item->questions as $question) {
            if (trim(strip_tags((string) $question->question_text)) === '') {
                return sprintf(
                    'Statement %d has no text. Write it before publishing.',
                    (int) $question->sort_order + 1
                );
            }

            // An image with no description is unusable by a learner using a
            // screen reader, and on this type the image is often the question
            // itself. Publish is the right place to insist.
            if (trim((string) $question->media_image) !== '' && trim((string) $question->media_alt) === '') {
                return sprintf(
                    'Statement %d has a picture with no description. Describe it so it can be read by a screen reader.',
                    (int) $question->sort_order + 1
                );
            }
        }

        // See the class header: a pool that is all one answer is answerable
        // without reading it.
        if ($item->questions->count() > 1) {
            $trueCount = $item->questions->filter(fn (H5pTrueFalseQuestion $q) => (bool) $q->correct_answer)->count();

            if ($trueCount === 0 || $trueCount === $item->questions->count()) {
                return sprintf(
                    'Every statement in this activity is %s, so it can be answered without reading it. Mix in some that are %s.',
                    $trueCount === 0 ? 'false' : 'true',
                    $trueCount === 0 ? 'true' : 'false'
                );
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
        $item = H5pTrueFalse::create($parsed['item'] + [
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

        $audit = ['sub_institute_id' => $subInstituteId, 'created_by' => $userId, 'created_at' => now()];

        foreach ($parsed['questions'] as $question) {
            H5pTrueFalseQuestion::create($question + ['true_false_id' => $item->id] + $audit);
        }

        return $item;
    }

    /** "" and null both mean "nothing authored"; the column holds null. */
    private function nullable(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
