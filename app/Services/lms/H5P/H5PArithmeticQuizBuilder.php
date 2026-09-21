<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pArithmeticQuiz;

/**
 * Translates between h5p_arithmetic_quiz rows and H5P.ArithmeticQuiz params.
 *
 * THE ONE PLACE THIS TYPE DOES NOT LINE UP WITH H5P.
 *
 * H5P.ArithmeticQuiz takes a SINGLE `arithmeticType` -- addition, subtraction,
 * multiplication or division -- and has no way to say "multiplication and
 * division". This product stores a LIST, because "the two operations we did
 * this week" is a real drill and a single value cannot express it.
 *
 * So the export does both:
 *
 *   - `arithmeticType` is the first operation in the list, which is what a
 *     stock H5P host will actually run; and
 *   - `eduerpGenerator` carries the full list, the difficulty and the limits,
 *     which this importer reads back exactly.
 *
 * A multi-operation quiz therefore exports as a narrower quiz in a foreign
 * host rather than as a broken one, and the package service raises an export
 * warning saying so. That is the honest trade; the alternative is exporting a
 * field H5P will ignore and telling nobody.
 *
 * WHERE THE QUESTIONS ARE. Nowhere. They are generated per attempt from these
 * rules -- see the migration. This builder describes the rules; it never draws
 * a number.
 */
class H5PArithmeticQuizBuilder
{
    use ConvertsToH5PParams;

    private const REGISTRY_CODE = 'arithmetic_quiz';

    /** This schema's operation names -> H5P's `arithmeticType` values. */
    private const H5P_TYPES = [
        'addition' => 'addition',
        'subtraction' => 'subtraction',
        'multiplication' => 'multiplication',
        'division' => 'division',
    ];

    // -----------------------------------------------------------------------
    // Build
    // -----------------------------------------------------------------------

    /**
     * Build the H5P.ArithmeticQuiz `params` object for one quiz.
     *
     * @return array<string,mixed>
     */
    public function build(H5pArithmeticQuiz $quiz): array
    {
        $operations = $quiz->activeOperations();
        $range = $quiz->difficultyRange();

        $params = [
            'intro' => (string) ($quiz->intro_text ?? ''),
            'quizType' => 'arithmetic',
            // The first operation only -- see the class header.
            'arithmeticType' => self::H5P_TYPES[$operations[0]] ?? 'addition',
            'maxQuestions' => max(1, (int) $quiz->max_questions),
            'UI' => [
                'score' => 'You got @score of @total possible points.',
                'time' => 'Time: @time',
                'resultPageHeader' => 'Finished!',
                'intro' => 'Test your arithmetic skills.',
                'go' => 'Go!',
                'startButton' => 'Start quiz',
                'retryButton' => 'Retry',
                'correctText' => 'Correct',
                'incorrectText' => 'Incorrect. Correct answer was :num',
                'durationLabel' => 'Duration in hours, minutes and seconds.',
                'humanizedQuestion' => 'What does :arithmetic equal?',
            ],
            /*
             * The full rule set, in this product's own terms.
             *
             * This is what lib/h5p/arithmetic-quiz.ts consumes to generate a
             * paper and what parse() reads back, so a package exported here
             * and re-imported here produces exactly the same quiz.
             */
            'eduerpGenerator' => [
                'operations' => $operations,
                'difficultyLevel' => (int) $quiz->difficulty_level,
                'operandMin' => (int) $range['min'],
                'operandMax' => (int) $range['max'],
                'maxQuestions' => max(1, (int) $quiz->max_questions),
                'pointsPerQuestion' => max(1, (int) $quiz->points_per_question),
                'passPercentage' => max(0, min(100, (int) $quiz->pass_percentage)),
                'enableTimer' => (bool) $quiz->enable_timer,
                'timeLimitSeconds' => max(0, (int) $quiz->time_limit_seconds),
                'enableRetry' => (bool) $quiz->enable_retry,
                'maxAttempts' => max(0, (int) $quiz->max_attempts),
                'showIntro' => (bool) $quiz->show_intro,
            ],
            'overallFeedback' => $this->feedbackBands($quiz->feedback_bands),
        ];

        return $this->mergePreservedKeys($params, $quiz->content_json);
    }

    /**
     * Why this quiz will be narrower in a foreign host than it is here, or
     * null when it will not be.
     *
     * Returned rather than logged so the export endpoint can put it in front
     * of the author at the moment they download the file.
     */
    public function exportCaveat(H5pArithmeticQuiz $quiz): ?string
    {
        $operations = $quiz->activeOperations();
        if (count($operations) < 2) {
            return null;
        }

        return sprintf(
            'This quiz draws from %d operations. H5P.ArithmeticQuiz supports one, so a host outside this ERP will run it as %s only. Re-imported here it keeps all %d.',
            count($operations),
            $operations[0],
            count($operations)
        );
    }

    // -----------------------------------------------------------------------
    // Parse
    // -----------------------------------------------------------------------

    /**
     * Read an H5P.ArithmeticQuiz params object into a row payload.
     *
     * @param  array<string,mixed>  $params
     * @return array{quiz: array<string,mixed>}
     */
    public function parse(array $params): array
    {
        $generator = (array) ($params['eduerpGenerator'] ?? []);
        $bands = (array) ($params['overallFeedback'] ?? []);

        // Prefer this product's own rule set; fall back to what a stock H5P
        // package can say, which is one operation and a question count.
        $operations = array_values(array_intersect(
            array_map('strval', (array) ($generator['operations'] ?? [])),
            H5pArithmeticQuiz::OPERATIONS
        ));

        if ($operations === []) {
            $fallback = (string) ($params['arithmeticType'] ?? 'addition');
            $operations = [in_array($fallback, H5pArithmeticQuiz::OPERATIONS, true) ? $fallback : 'addition'];
        }

        $level = (int) ($generator['difficultyLevel'] ?? 0);
        if (! isset(H5pArithmeticQuiz::DIFFICULTY_RANGES[$level])) {
            // A stock package has no difficulty. Infer it from the operand
            // range if one came through, and otherwise open at the easiest
            // level -- which a teacher will raise, rather than a hard level a
            // class will fail before anyone notices.
            $level = $this->levelForRange((int) ($generator['operandMax'] ?? 0));
        }

        $quiz = [
            'intro_text' => (string) ($params['intro'] ?? '') ?: null,
            'show_intro' => (bool) ($generator['showIntro'] ?? true),
            'operations' => $operations,
            'difficulty_level' => $level,
            'max_questions' => max(1, (int) ($generator['maxQuestions'] ?? $params['maxQuestions'] ?? 20)),
            'points_per_question' => max(1, (int) ($generator['pointsPerQuestion'] ?? 1)),
            'pass_percentage' => (int) ($generator['passPercentage'] ?? $this->passFromFeedback($bands, 60)),
            'enable_timer' => (bool) ($generator['enableTimer'] ?? true),
            'time_limit_seconds' => max(0, (int) ($generator['timeLimitSeconds'] ?? 0)),
            'enable_retry' => (bool) ($generator['enableRetry'] ?? true),
            'max_attempts' => max(0, (int) ($generator['maxAttempts'] ?? 0)),
            'feedback_bands' => $this->feedbackBands($bands),
        ];

        return ['quiz' => $quiz];
    }

    /** The lowest level whose range covers this operand ceiling. */
    private function levelForRange(int $operandMax): int
    {
        if ($operandMax <= 0) {
            return 1;
        }

        foreach (H5pArithmeticQuiz::DIFFICULTY_RANGES as $level => $range) {
            if ($operandMax <= (int) $range['max']) {
                return $level;
            }
        }

        return array_key_last(H5pArithmeticQuiz::DIFFICULTY_RANGES);
    }
}
