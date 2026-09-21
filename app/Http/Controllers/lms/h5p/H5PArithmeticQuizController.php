<?php

namespace App\Http\Controllers\lms\h5p;

use App\Models\lms\h5p\H5pArithmeticQuiz;
use App\Services\lms\H5P\H5PArithmeticQuizBuilder;
use App\Services\lms\H5P\H5PArithmeticQuizPackageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * H5P Arithmetic Quiz (H5P.ArithmeticQuiz).
 *
 * The simplest controller in the family, because the type has no children and
 * no media: an item is a rule set, and the questions are generated per attempt
 * from it. `syncChildren`, `duplicateChildren` and `media` therefore have
 * nothing to do, and say so rather than being quietly absent.
 */
class H5PArithmeticQuizController extends H5PContentTypeController
{
    public function __construct(
        private readonly H5PArithmeticQuizBuilder $builder,
        private readonly H5PArithmeticQuizPackageService $packages
    ) {
    }

    protected function modelClass(): string
    {
        return H5pArithmeticQuiz::class;
    }

    protected function registryCode(): string
    {
        return 'arithmetic_quiz';
    }

    protected function routePrefix(): string
    {
        return 'h5p_arithmetic_quiz';
    }

    protected function payloadKey(): string
    {
        return 'arithmeticQuiz';
    }

    protected function listKey(): string
    {
        return 'arithmeticQuizLists';
    }

    protected function label(): string
    {
        return 'Arithmetic quiz';
    }

    /** No children to load. */
    protected function relations(): array
    {
        return [];
    }

    protected function viewPath(): string
    {
        return 'lms/h5p/arithmeticquiz';
    }

    /** No media. The `media` endpoint is not routed for this type. */
    protected function mediaRoles(): array
    {
        return [];
    }

    protected function authoringDefaults(): array
    {
        return [
            'operations' => ['addition'],
            'difficulty_level' => 1,
            'max_questions' => 20,
            'enable_timer' => true,
            'time_limit_seconds' => 0,
            'points_per_question' => 1,
            'pass_percentage' => 60,
            'enable_retry' => true,
            'max_attempts' => 0,
            'show_intro' => true,
            'available_operations' => H5pArithmeticQuiz::OPERATIONS,
            // Sent so the editor can show "numbers 1–10" beside each level
            // rather than hard-coding a second copy of the ranges.
            'difficulty_ranges' => H5pArithmeticQuiz::DIFFICULTY_RANGES,
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
            'intro_text' => 'nullable|string|max:1000',
            'show_intro' => 'nullable|boolean',

            'operations' => 'required|array|min:1',
            'operations.*' => 'in:' . implode(',', H5pArithmeticQuiz::OPERATIONS),

            'difficulty_level' => 'required|integer|min:1|max:3',

            // 100 is the ceiling because the generator draws without repeating
            // a question, and the easy level's operand range cannot produce
            // many more distinct sums than that.
            'max_questions' => 'required|integer|min:1|max:100',

            'enable_timer' => 'nullable|boolean',
            'time_limit_seconds' => 'nullable|integer|min:0|max:7200',

            'points_per_question' => 'nullable|integer|min:1|max:100',
            'pass_percentage' => 'nullable|integer|min:0|max:100',

            'enable_retry' => 'nullable|boolean',
            'max_attempts' => 'nullable|integer|min:0|max:100',

            'feedback_bands' => 'nullable|array',
            'feedback_bands.*.from' => 'required|integer|min:0|max:100',
            'feedback_bands.*.to' => 'required|integer|min:0|max:100',
            'feedback_bands.*.feedback' => 'nullable|string|max:500',
        ];
    }

    protected function attributesFrom(array $data, Request $request): array
    {
        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'intro_text' => $data['intro_text'] ?? null,
            'show_intro' => $data['show_intro'] ?? true,
            // Deduplicated and re-indexed: a list that arrives as
            // ["addition","addition"] would otherwise weight addition twice in
            // the generator's uniform draw.
            'operations' => array_values(array_unique($data['operations'])),
            'difficulty_level' => $data['difficulty_level'],
            'max_questions' => $data['max_questions'],
            'enable_timer' => $data['enable_timer'] ?? true,
            'time_limit_seconds' => $data['time_limit_seconds'] ?? 0,
            'points_per_question' => $data['points_per_question'] ?? 1,
            'pass_percentage' => $data['pass_percentage'] ?? 60,
            'enable_retry' => $data['enable_retry'] ?? true,
            'max_attempts' => $data['max_attempts'] ?? 0,
            'feedback_bands' => $data['feedback_bands'] ?? null,
        ];
    }

    // -----------------------------------------------------------------------
    // Children -- there are none
    // -----------------------------------------------------------------------

    protected function syncChildren(Model $item, array $data, int|string|null $subInstituteId, int|string|null $userId): void
    {
        // Nothing to write. The questions are generated per attempt from the
        // columns above; see the migration.
    }

    protected function duplicateChildren(Model $original, Model $copy, int|string|null $subInstituteId, int|string|null $userId): void
    {
        // Nothing to copy, for the same reason.
    }

    protected function duplicableColumns(): array
    {
        return [
            'description', 'intro_text', 'show_intro', 'operations', 'difficulty_level',
            'max_questions', 'enable_timer', 'time_limit_seconds', 'points_per_question',
            'pass_percentage', 'enable_retry', 'max_attempts', 'feedback_bands',
            'standard_id', 'subject_id', 'chapter_id', 'syear',
        ];
    }

    // -----------------------------------------------------------------------
    // Publish
    // -----------------------------------------------------------------------

    protected function publishBlocker(Model $item): ?string
    {
        if ($item->operations === null || $item->operations === []) {
            return 'Choose at least one operation before publishing.';
        }

        if ((int) $item->max_questions < 1) {
            return 'Set how many questions the quiz asks before publishing.';
        }

        /*
         * Division at the hardest level draws a divisor and a quotient from
         * 5-100 and multiplies them, so the dividend can reach 10,000. That is
         * a valid drill for a senior class and a wall for a junior one, and
         * the author is the only one who knows which they have -- so this is a
         * warning shaped as a blocker only when it is certainly wrong, which
         * it is not. Nothing is blocked here; the editor shows a worked
         * example at the chosen level instead, which tells them more than a
         * sentence would.
         */

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
        return H5pArithmeticQuiz::create($parsed['quiz'] + [
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
    }
}
