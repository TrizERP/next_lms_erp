<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pTrueFalse;

/**
 * Translates between h5p_true_false rows and H5P.TrueFalse params.
 *
 * THE MISMATCH THIS FILE EXISTS TO HANDLE.
 *
 * H5P.TrueFalse holds ONE statement. This schema holds a POOL, because a
 * single true/false question is not an activity anybody sets -- see the
 * migration. So an export does both things at once:
 *
 *   - `question`, `correct`, `media` and `behaviour` describe the FIRST
 *     question in the pool, which is what a stock H5P host will run; and
 *   - `eduerpPool` carries every question, the draw rules and the scoring,
 *     which parse() reads back exactly.
 *
 * A ten-question pool therefore opens in Moodle as a one-question activity
 * rather than as a broken one, and re-imports here complete. The package
 * service raises a warning naming the nine questions that a foreign host will
 * not show, because an author handing that file to a colleague needs to know
 * before the colleague does.
 *
 * THE ANSWER CROSSES A TYPE BOUNDARY. H5P's `correct` is the STRING "true" or
 * "false"; the column is a real boolean. Everything in between -- a JSON
 * `true`, the string "1", the integer 1 -- is something a hand-written or
 * third-party package plausibly contains. `readCorrect()` is the one place
 * that decides, and it treats only an explicit falsehood as false, because a
 * statement whose answer cannot be read is far more likely to be a true one
 * written oddly than a silently inverted one.
 */
class H5PTrueFalseBuilder
{
    use ConvertsToH5PParams;

    private const REGISTRY_CODE = 'true_false';

    // -----------------------------------------------------------------------
    // Build
    // -----------------------------------------------------------------------

    /**
     * Build the H5P.TrueFalse `params` object for one item.
     *
     * @return array<string,mixed>
     */
    public function build(H5pTrueFalse $item): array
    {
        $questions = $item->relationLoaded('questions') ? $item->questions : $item->questions()->get();
        $first = $questions->first();

        $pool = [];
        foreach ($questions as $question) {
            $pool[] = [
                'subContentId' => $this->subContentId('true_false_question', (int) $question->id),
                'question' => $this->html($question->question_text),
                // The string, because that is what the format uses everywhere
                // else and a pool that disagreed with its own first question
                // would be the worst possible bug here.
                'correct' => $question->h5pCorrect(),
                'feedbackOnCorrect' => (string) ($question->feedback_correct ?? ''),
                'feedbackOnWrong' => (string) ($question->feedback_incorrect ?? ''),
                'explanation' => (string) ($question->explanation ?? ''),
                'media' => $this->mediaNode($question->media_image, $question->media_alt),
            ];
        }

        $params = [
            // The first question, as a stock host will run it. An empty pool
            // is a draft, and a draft cannot be published or exported -- but
            // build() is also called to refresh the params cache on every
            // save, including the save that creates an empty draft, so it must
            // not fault here.
            'question' => $first !== null ? $this->html($first->question_text) : '',
            'correct' => $first !== null ? $first->h5pCorrect() : 'true',
            'media' => $first !== null
                ? ['type' => $this->mediaNode($first->media_image, $first->media_alt), 'disableImageZooming' => false]
                : ['type' => null, 'disableImageZooming' => false],

            'behaviour' => [
                'enableRetry' => (bool) $item->enable_retry,
                'enableSolutionsButton' => (bool) $item->enable_show_solution,
                'enableCheckButton' => (bool) $item->enable_check_button,
                'confirmCheckDialog' => (bool) $item->confirm_check_dialog,
                'confirmRetryDialog' => (bool) $item->confirm_retry_dialog,
                'autoCheck' => (bool) $item->auto_check,
                'feedbackOnCorrect' => $first !== null ? (string) ($first->feedback_correct ?? '') : '',
                'feedbackOnWrong' => $first !== null ? (string) ($first->feedback_incorrect ?? '') : '',
            ],

            'l10n' => $this->l10n(),
            'confirmCheck' => $this->confirmDialog('Finish?', 'Are you sure you want to finish?'),
            'confirmRetry' => $this->confirmDialog('Retry?', 'Are you sure you want to retry?'),
            'overallFeedback' => $this->feedbackBands($item->feedback_bands),

            // The whole pool. See the class header.
            'eduerpPool' => [
                'taskDescription' => (string) ($item->task_description ?? ''),
                'randomizeQuestions' => (bool) $item->randomize_questions,
                'questionsToAsk' => max(0, (int) $item->questions_to_ask),
                'pointsPerQuestion' => max(1, (int) $item->points_per_question),
                'passPercentage' => max(0, min(100, (int) $item->pass_percentage)),
                'showProgress' => (bool) $item->show_progress,
                'questions' => $pool,
            ],
        ];

        return $this->mergePreservedKeys($params, $item->content_json);
    }

    /**
     * What a stock H5P host will not show, or null when it will show it all.
     *
     * A one-question pool with no extras exports losslessly, which is worth
     * saying by saying nothing: a warning on every single export teaches
     * authors to ignore warnings.
     */
    public function exportCaveat(H5pTrueFalse $item): ?string
    {
        $questions = $item->relationLoaded('questions') ? $item->questions : $item->questions()->get();
        $count = $questions->count();

        if ($count <= 1) {
            return null;
        }

        return sprintf(
            'This activity holds %d statements. H5P.TrueFalse holds one, so a host outside this ERP will show only the first ("%s"). Re-imported here it keeps all %d.',
            $count,
            $questions->first()->plainQuestion(40),
            $count
        );
    }

    // -----------------------------------------------------------------------
    // Parse
    // -----------------------------------------------------------------------

    /**
     * Read an H5P.TrueFalse params object into row payloads.
     *
     * Two package shapes arrive here and both are valid:
     *
     *   - one written by this product, carrying `eduerpPool` -- read whole;
     *   - a stock one-question package from Lumi, Moodle or h5p.org -- read
     *     as a pool of one, which is exactly what it is.
     *
     * @param  array<string,mixed>  $params
     * @return array{item: array<string,mixed>, questions: list<array<string,mixed>>, warnings: list<string>}
     */
    public function parse(array $params): array
    {
        $extension = (array) ($params['eduerpPool'] ?? []);
        $behaviour = (array) ($params['behaviour'] ?? []);
        $bands = (array) ($params['overallFeedback'] ?? []);

        $warnings = [];
        $questions = [];

        $poolSource = array_values((array) ($extension['questions'] ?? []));

        if ($poolSource === []) {
            // A stock package. Its single question is the whole pool, and its
            // feedback lives on `behaviour` rather than on the question.
            $media = (array) ($params['media'] ?? []);

            $poolSource = [[
                'question' => $params['question'] ?? '',
                'correct' => $params['correct'] ?? 'true',
                'feedbackOnCorrect' => $behaviour['feedbackOnCorrect'] ?? '',
                'feedbackOnWrong' => $behaviour['feedbackOnWrong'] ?? '',
                // The library wraps the node one level deeper than the pool
                // does (`media.type`), so unwrap before readMedia sees it.
                'media' => $media['type'] ?? null,
            ]];
        }

        foreach ($poolSource as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $text = trim((string) ($entry['question'] ?? ''));
            if ($text === '') {
                // An empty statement is not a question, and a blank row in a
                // pool is a row a learner would be asked to answer.
                $warnings[] = sprintf('Statement %d was skipped: it has no text.', $index + 1);

                continue;
            }

            [$image, $alt] = $this->readMedia($entry['media'] ?? null);

            $questions[] = [
                'question_text' => $text,
                'correct_answer' => $this->readCorrect($entry['correct'] ?? null),
                'feedback_correct' => $this->nullableString($entry['feedbackOnCorrect'] ?? null),
                'feedback_incorrect' => $this->nullableString($entry['feedbackOnWrong'] ?? null),
                'explanation' => $this->nullableString($entry['explanation'] ?? null),
                'media_image' => $image,
                'media_alt' => $alt,
                'sort_order' => count($questions),
            ];
        }

        if ($questions === []) {
            throw new \RuntimeException('That package contains no usable statements.');
        }

        $item = [
            'task_description' => $this->nullableString($extension['taskDescription'] ?? null),
            'enable_retry' => (bool) ($behaviour['enableRetry'] ?? true),
            'enable_show_solution' => (bool) ($behaviour['enableSolutionsButton'] ?? true),
            'enable_check_button' => (bool) ($behaviour['enableCheckButton'] ?? true),
            'auto_check' => (bool) ($behaviour['autoCheck'] ?? false),
            'confirm_check_dialog' => (bool) ($behaviour['confirmCheckDialog'] ?? false),
            'confirm_retry_dialog' => (bool) ($behaviour['confirmRetryDialog'] ?? false),
            'randomize_questions' => (bool) ($extension['randomizeQuestions'] ?? false),
            // Clamped to the pool that actually arrived: a package claiming
            // "ask 10" whose pool lost questions to the skips above would
            // otherwise be saved asking for more than it has.
            'questions_to_ask' => min(
                max(0, (int) ($extension['questionsToAsk'] ?? 0)),
                count($questions)
            ),
            'points_per_question' => max(1, (int) ($extension['pointsPerQuestion'] ?? 1)),
            'pass_percentage' => max(0, min(100, (int) (
                $extension['passPercentage'] ?? $this->passFromFeedback($bands, 60)
            ))),
            'show_progress' => (bool) ($extension['showProgress'] ?? true),
            'feedback_bands' => $this->feedbackBands($bands),
        ];

        return ['item' => $item, 'questions' => $questions, 'warnings' => $warnings];
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Read H5P's `correct` into a boolean.
     *
     * See the class header for why only an explicit falsehood is false.
     */
    private function readCorrect(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        return ! in_array(strtolower(trim((string) $value)), ['false', 'no', 'f', '0', ''], true);
    }

    /**
     * An H5P.Image node for a media URL, or null when there is none.
     *
     * @return array<string,mixed>|null
     */
    private function mediaNode(?string $path, ?string $alt): ?array
    {
        $url = trim((string) $path);
        if ($url === '') {
            return null;
        }

        return [
            'library' => 'H5P.Image 1.1',
            'params' => [
                'file' => ['path' => $url, 'mime' => $this->mimeFor($url), 'copyright' => ['license' => 'U']],
                'alt' => (string) ($alt ?? ''),
            ],
            'subContentId' => $this->subContentId('true_false_media', crc32($url)),
            'metadata' => ['contentType' => 'Image', 'license' => 'U', 'title' => (string) ($alt ?? 'Image')],
        ];
    }

    /**
     * The URL and alt text out of an H5P.Image node.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function readMedia(mixed $node): array
    {
        if (! is_array($node)) {
            return [null, null];
        }

        $params = (array) ($node['params'] ?? $node);
        $file = (array) ($params['file'] ?? []);
        $path = trim((string) ($file['path'] ?? ''));

        return [$path === '' ? null : $path, $this->nullableString($params['alt'] ?? null)];
    }

    /**
     * Wrap bare text in a paragraph, and leave existing markup alone.
     *
     * H5P's editor always writes HTML here and its player renders it as HTML.
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

    /** @return array<string,string> */
    private function confirmDialog(string $header, string $body): array
    {
        return [
            'header' => $header,
            'body' => $body,
            'cancelLabel' => 'Cancel',
            'confirmLabel' => 'Confirm',
        ];
    }

    /**
     * The library's UI strings.
     *
     * Written out in full rather than left to the host's defaults, for the
     * reason H5PSingleChoiceSetBuilder records: a package that omits `l10n`
     * renders in whatever language the importing host happens to default to.
     *
     * @return array<string,string>
     */
    private function l10n(): array
    {
        return [
            'trueText' => 'True',
            'falseText' => 'False',
            'score' => 'You got @score of @total points',
            'checkAnswer' => 'Check',
            'showSolutionButton' => 'Show solution',
            'tryAgain' => 'Retry',
            'wrongAnswerMessage' => 'Wrong answer',
            'correctAnswerMessage' => 'Correct answer',
            'scoreBarLabel' => 'You got :num out of :total points',
            'a11yCheck' => 'Check the answers. The responses will be marked as correct, incorrect, or unanswered.',
            'a11yShowSolution' => 'Show the solution. The task will be marked with its correct solution.',
            'a11yRetry' => 'Retry the task. Reset all responses and start the task over again.',
        ];
    }

    /** "H5P.TrueFalse 1.8", for the library column and the manifest. */
    public function libraryString(): string
    {
        return $this->libraryVersionString(self::REGISTRY_CODE);
    }
}
