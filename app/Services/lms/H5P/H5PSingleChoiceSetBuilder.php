<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pSingleChoiceQuestion;
use App\Models\lms\h5p\H5pSingleChoiceSet;

/**
 * Translates between h5p_single_choice_set rows and H5P.SingleChoiceSet params.
 *
 * THE ONE THING TO GET RIGHT: THE CORRECT ANSWER IS POSITION, NOT A FLAG.
 *
 * H5P.SingleChoiceSet stores a question as `{question, answers: [...]}` and
 * declares that `answers[0]` IS the correct one. There is no `correct` key
 * anywhere in the format -- the player shuffles the list at run time and
 * remembers where index 0 went.
 *
 * This schema stores an `is_correct` flag instead, because a flag survives an
 * author reordering their options and a position does not. The conversion
 * therefore happens exactly twice -- here on build, here on parse -- and
 * nowhere else. Every bug this type can have that reaches a classroom without
 * looking broken is a bug in those two functions, which is why they are the
 * most heavily tested thing in the file.
 *
 * WHAT H5P CANNOT HOLD, and which therefore travels in `eduerpSet`:
 *
 *   - per-question feedback and per-option feedback. The library has one
 *     `overallFeedback` for the whole set and nothing per question.
 *   - randomised question order and randomised answer order.
 *   - points per question. H5P scores one point per question, always.
 *   - the task description shown above the first question.
 *
 * All of it is re-read by parse(), so a package exported here and re-imported
 * here is the same set. A stock host runs the narrower activity and the
 * package service raises a warning saying which fields it will not honour.
 */
class H5PSingleChoiceSetBuilder
{
    use ConvertsToH5PParams;

    private const REGISTRY_CODE = 'single_choice_set';

    // -----------------------------------------------------------------------
    // Build
    // -----------------------------------------------------------------------

    /**
     * Build the H5P.SingleChoiceSet `params` object for one set.
     *
     * @return array<string,mixed>
     */
    public function build(H5pSingleChoiceSet $set): array
    {
        $questions = $set->relationLoaded('questions') ? $set->questions : $set->questions()->with('options')->get();

        $choices = [];
        $extras = [];

        foreach ($questions as $question) {
            $options = $question->relationLoaded('options') ? $question->options : $question->options()->get();

            /*
             * The correct option first, then the rest in author order.
             *
             * `usort` is deliberately NOT used: it is not stable in every PHP
             * version this deploys on, and an unstable sort here would
             * reorder a learner's distractors between two exports of the same
             * set for no reason. Partitioning is stable by construction.
             */
            $correct = [];
            $rest = [];
            foreach ($options as $option) {
                if ((bool) $option->is_correct && $correct === []) {
                    $correct[] = $option;
                } else {
                    $rest[] = $option;
                }
            }
            $ordered = array_merge($correct, $rest);

            $choices[] = [
                'subContentId' => $this->subContentId('single_choice_question', (int) $question->id),
                'question' => $this->html($question->question_text),
                'answers' => array_map(fn ($option) => $this->html($option->option_text), $ordered),
            ];

            $extras[] = [
                'questionId' => (int) $question->id,
                'feedbackCorrect' => $question->feedback_correct,
                'feedbackIncorrect' => $question->feedback_incorrect,
                'explanation' => $question->explanation,
                // Indexed to match `answers` above, so a re-import can put
                // each message back on the option it belongs to even though
                // the flag itself is gone from the H5P side.
                'answerFeedback' => array_map(fn ($option) => (string) ($option->feedback ?? ''), $ordered),
                // Always 0, because of the ordering above. Written out anyway
                // so that a package hand-edited elsewhere, or produced by a
                // future version that stops reordering, still imports right.
                'correctIndex' => 0,
            ];
        }

        $params = [
            'choices' => $choices,
            'behaviour' => [
                'timeoutCorrect' => max(0, (int) $set->timeout_correct_ms),
                'timeoutWrong' => max(0, (int) $set->timeout_wrong_ms),
                'soundEffectsEnabled' => (bool) $set->sound_effects,
                'enableRetry' => (bool) $set->enable_retry,
                'enableSolutionsButton' => (bool) $set->enable_show_solution,
                'passPercentage' => max(0, min(100, (int) $set->pass_percentage)),
                'autoContinue' => (bool) $set->auto_continue,
            ],
            'l10n' => $this->l10n(),
            'overallFeedback' => $this->feedbackBands($set->feedback_bands),

            // Everything H5P has no field for. See the class header.
            'eduerpSet' => [
                'taskDescription' => (string) ($set->task_description ?? ''),
                'randomizeQuestions' => (bool) $set->randomize_questions,
                'randomizeAnswers' => (bool) $set->randomize_answers,
                'pointsPerQuestion' => max(1, (int) $set->points_per_question),
                'passPercentage' => max(0, min(100, (int) $set->pass_percentage)),
                'showProgress' => (bool) $set->show_progress,
                'questions' => $extras,
            ],
        ];

        return $this->mergePreservedKeys($params, $set->content_json);
    }

    /**
     * What a stock H5P host will not honour about this set, or null when
     * there is nothing to say.
     *
     * Returned rather than logged so the export endpoint can put it in front
     * of the author at the moment they download the file -- which is the only
     * moment it matters and the only moment they can act on it.
     */
    public function exportCaveat(H5pSingleChoiceSet $set): ?string
    {
        $questions = $set->relationLoaded('questions') ? $set->questions : $set->questions()->with('options')->get();

        $lost = [];

        $hasPerQuestionFeedback = $questions->contains(function (H5pSingleChoiceQuestion $question) {
            $options = $question->relationLoaded('options') ? $question->options : $question->options()->get();

            return trim((string) $question->feedback_correct) !== ''
                || trim((string) $question->feedback_incorrect) !== ''
                || trim((string) $question->explanation) !== ''
                || $options->contains(fn ($option) => trim((string) $option->feedback) !== '');
        });

        if ($hasPerQuestionFeedback) {
            $lost[] = 'per-question and per-option feedback';
        }
        if ($set->randomize_questions || $set->randomize_answers) {
            $lost[] = 'randomised order';
        }
        if ((int) $set->points_per_question !== 1) {
            $lost[] = 'points per question';
        }

        if ($lost === []) {
            return null;
        }

        return sprintf(
            'H5P.SingleChoiceSet has no field for %s, so a host outside this ERP will run this set without %s. Re-imported here it keeps all of it.',
            $this->humanList($lost),
            count($lost) === 1 ? 'it' : 'them'
        );
    }

    // -----------------------------------------------------------------------
    // Parse
    // -----------------------------------------------------------------------

    /**
     * Read an H5P.SingleChoiceSet params object into row payloads.
     *
     * A question whose `answers` list is too short to be a question is
     * DROPPED and reported, not repaired: a single-choice question with one
     * option has no choice in it, and inventing a distractor would put words
     * in an author's mouth that a class would then be marked against.
     *
     * @param  array<string,mixed>  $params
     * @return array{set: array<string,mixed>, questions: list<array<string,mixed>>, warnings: list<string>}
     */
    public function parse(array $params): array
    {
        $extension = (array) ($params['eduerpSet'] ?? []);
        $behaviour = (array) ($params['behaviour'] ?? []);
        $bands = (array) ($params['overallFeedback'] ?? []);
        $extras = array_values((array) ($extension['questions'] ?? []));

        $warnings = [];
        $questions = [];

        foreach (array_values((array) ($params['choices'] ?? [])) as $index => $choice) {
            if (! is_array($choice)) {
                continue;
            }

            $answers = array_values(array_filter(
                array_map(fn ($answer) => trim((string) $answer), (array) ($choice['answers'] ?? [])),
                fn (string $answer) => $answer !== ''
            ));

            if (count($answers) < H5pSingleChoiceSet::MIN_OPTIONS) {
                $warnings[] = sprintf(
                    'Question %d was skipped: it has fewer than %d answer options.',
                    $index + 1,
                    H5pSingleChoiceSet::MIN_OPTIONS
                );

                continue;
            }

            $extra = (array) ($extras[$index] ?? []);
            $answerFeedback = array_values((array) ($extra['answerFeedback'] ?? []));
            // The format's rule, and the extension's override for a package
            // that was hand-edited. Clamped, because an index past the end
            // would leave the question with no correct answer at all.
            $correctIndex = (int) ($extra['correctIndex'] ?? 0);
            if ($correctIndex < 0 || $correctIndex >= count($answers)) {
                $correctIndex = 0;
            }

            $options = [];
            foreach ($answers as $position => $answer) {
                $options[] = [
                    'option_text' => $answer,
                    'is_correct' => $position === $correctIndex,
                    'feedback' => $this->nullableString($answerFeedback[$position] ?? null),
                    'sort_order' => $position,
                ];
            }

            $questions[] = [
                'question_text' => (string) ($choice['question'] ?? ''),
                'feedback_correct' => $this->nullableString($extra['feedbackCorrect'] ?? null),
                'feedback_incorrect' => $this->nullableString($extra['feedbackIncorrect'] ?? null),
                'explanation' => $this->nullableString($extra['explanation'] ?? null),
                'sort_order' => count($questions),
                'options' => $options,
            ];
        }

        if ($questions === []) {
            throw new \RuntimeException('That package contains no usable questions.');
        }

        $set = [
            'task_description' => $this->nullableString($extension['taskDescription'] ?? null),
            'auto_continue' => (bool) ($behaviour['autoContinue'] ?? true),
            'timeout_correct_ms' => max(0, (int) ($behaviour['timeoutCorrect'] ?? 2000)),
            'timeout_wrong_ms' => max(0, (int) ($behaviour['timeoutWrong'] ?? 3000)),
            'sound_effects' => (bool) ($behaviour['soundEffectsEnabled'] ?? false),
            'enable_retry' => (bool) ($behaviour['enableRetry'] ?? true),
            'enable_show_solution' => (bool) ($behaviour['enableSolutionsButton'] ?? true),
            'randomize_questions' => (bool) ($extension['randomizeQuestions'] ?? false),
            'randomize_answers' => (bool) ($extension['randomizeAnswers'] ?? true),
            'points_per_question' => max(1, (int) ($extension['pointsPerQuestion'] ?? 1)),
            // The extension first; then the library's own field; then, for a
            // package from neither, the band that reaches 100.
            'pass_percentage' => max(0, min(100, (int) (
                $extension['passPercentage']
                ?? $behaviour['passPercentage']
                ?? $this->passFromFeedback($bands, 60)
            ))),
            'show_progress' => (bool) ($extension['showProgress'] ?? true),
            'feedback_bands' => $this->feedbackBands($bands),
        ];

        return ['set' => $set, 'questions' => $questions, 'warnings' => $warnings];
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Wrap bare text in a paragraph, and leave existing markup alone.
     *
     * H5P's editor always writes HTML here, and its player renders the field
     * as HTML. An author who typed a plain sentence in this ERP's editor would
     * otherwise export a string that renders with no spacing at all.
     */
    private function html(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        return str_starts_with($text, '<') ? $text : '<p>' . $text . '</p>';
    }

    /** "" and null both mean "nothing authored"; the column holds null. */
    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    /** "a, b and c" -- for a sentence a teacher reads, not a log line. */
    private function humanList(array $items): string
    {
        if (count($items) === 1) {
            return (string) $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items) . ' and ' . $last;
    }

    /**
     * The library's UI strings.
     *
     * Written out in full rather than left to the host's defaults, because a
     * package that omits `l10n` renders with whatever the importing host has,
     * which for a school exporting to a parent's Lumi is usually Norwegian.
     *
     * @return array<string,string>
     */
    private function l10n(): array
    {
        return [
            'nextButtonLabel' => 'Next question',
            'showSolutionButtonLabel' => 'Show solution',
            'retryButtonLabel' => 'Retry',
            'solutionViewTitle' => 'Solution',
            'correctText' => 'Correct!',
            'incorrectText' => 'Incorrect!',
            'muteButtonLabel' => 'Mute feedback sound',
            'closeButtonLabel' => 'Close',
            'slideOfTotal' => 'Slide :num of :total',
            'scoreBarLabel' => 'You got :num out of :total points',
            'solutionListQuestionNumber' => 'Question :num',
            'resultSlideTitle' => 'You got @score of @total correct',
            'a11yShowSolution' => 'Show the solution. The task will be marked with its correct solution.',
            'a11yRetry' => 'Retry the task. Reset all responses and start the task over again.',
        ];
    }

    /** "H5P.SingleChoiceSet 1.11", for the library column and the manifest. */
    public function libraryString(): string
    {
        return $this->libraryVersionString(self::REGISTRY_CODE);
    }
}
