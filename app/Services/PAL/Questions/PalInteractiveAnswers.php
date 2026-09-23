<?php

namespace App\Services\PAL\Questions;

use Illuminate\Support\Facades\DB;

/**
 * Answers that are not a choice: a typed blank, a value, a matched pair, a
 * marked word.
 *
 * ---------------------------------------------------------------------------
 * WHY PAL NEEDED A SECOND CHANNEL AT ALL
 * ---------------------------------------------------------------------------
 * Every answer PAL has ever recorded is an `answer_master` row id.
 * `store()` writes it to `lms_online_exam_answer.answer_id`,
 * `get_calculate_marks()` marks the paper by splitting the `id##flag` pair the
 * browser sends back, and misconception detection reads WHICH distractor was
 * chosen. All of that works, and none of it changes.
 *
 * It only works, though, for a question whose answer IS one of its stored
 * options. Now that the PAL Test renders each question as the H5P activity its
 * form calls for, a learner may type "Paris" into a blank or drag a label onto
 * its pair -- and neither of those is an `answer_master` row. There is no id to
 * send, so there is nothing for the existing path to record.
 *
 * So those arrive on `answer_interactive[<question_id>]` as a small JSON
 * object, and this class is what reads it.
 *
 * ---------------------------------------------------------------------------
 * HOW MUCH OF IT IS TRUSTED
 * ---------------------------------------------------------------------------
 * The client sends its own verdict, because the client is what scored the
 * activity. That is the SAME trust model the option path already has -- the
 * correct flag is sent to the browser inside `answer_arr` and sent back in the
 * `id##flag` pair -- so this is not a new exposure, and saying otherwise would
 * be flattering the old path.
 *
 * Where the answer can be re-derived, though, it is: `verify()` marks a
 * single-slot typed answer -- one blank, one value -- against the question's
 * own stored `model_answer`, and the caller uses that verdict in place of the
 * client's. That covers the fabricated-claim case for the forms where it is
 * exactly checkable, which is strictly more than the option path does and
 * strictly less than a claim of full verification.
 *
 * IT DELIBERATELY DECLINES THE REST, and the reason is worth stating because
 * "verify everything" looks like the safer default and is not. A multi-blank
 * passage is marked blank by blank against a key the CLIENT derives from the
 * stem's own markup (`lib/h5p/text-activity-markup.ts`); this server has the
 * stem but not that parser, so it holds one model answer and no per-blank key.
 * Comparing a joined response against it would mark correct answers wrong --
 * a false negative on a learner's real work, which is worse than the
 * fabricated claim it would be guarding against. Same for a matching
 * activity, whose response is a summary of pairs rather than a value.
 *
 * So: exact where it can be exact, the client's verdict where it cannot, and
 * no pretence in between.
 */
class PalInteractiveAnswers
{
    /**
     * One learner's answer to one non-option question, or null when the value
     * is not something this server can read.
     *
     * Accepts the JSON string the browser sends and an already-decoded array
     * alike, because PHP's form parsing will hand back either depending on how
     * the field was named, and a reader that only handled one of them would
     * work until somebody changed a bracket.
     *
     * @param  string|array  $raw
     * @param  int|string  $questionId
     */
    public static function parse($raw, $questionId): ?array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        if (!is_array($decoded)) {
            return null;
        }

        return [
            'question_id' => (int) $questionId,
            'client_correct' => (bool) ($decoded['correct'] ?? false),
            'response' => self::readableResponse($decoded['response'] ?? null),
            'score' => isset($decoded['score']) && is_numeric($decoded['score'])
                ? (int) $decoded['score']
                : null,
            'max_score' => isset($decoded['max_score']) && is_numeric($decoded['max_score'])
                ? (int) $decoded['max_score']
                : null,
        ];
    }

    /**
     * Read the whole `answer_interactive` bag off a request, skipping anything
     * malformed.
     *
     * A malformed entry is DROPPED rather than defaulted, because defaulting
     * it either way is a mark a learner did not earn or a mark they did.
     * Dropped means the question falls through to `store()`'s unanswered
     * branch, which records it as unattempted -- the honest reading of "the
     * browser sent something this server cannot interpret".
     *
     * @return array<int, array<string, mixed>> keyed by question id
     */
    public static function readAll($bag): array
    {
        if (!is_array($bag)) {
            return [];
        }

        $answers = [];

        foreach ($bag as $questionId => $raw) {
            $parsed = self::parse($raw, $questionId);
            if ($parsed !== null && $parsed['question_id'] > 0) {
                $answers[$parsed['question_id']] = $parsed;
            }
        }

        return $answers;
    }

    /**
     * The server's own verdict on a typed answer, or null when it cannot have
     * one.
     *
     * MARKS ONLY WHAT IT CAN MARK HONESTLY. A stored answer that is a word, a
     * short phrase or a number is compared, normalised for case, spacing and
     * punctuation. A stored answer that is a written explanation is NOT:
     * comparing a learner's sentence against a model sentence as an exact
     * string fails everyone who wrote the same thing in their own words, and a
     * wrong mark is worse than no mark. That is the same line the client-side
     * projection draws (`lib/h5p/question-bank-h5p-map.ts` refuses to turn an
     * explanation into one paragraph-long blank), drawn in the same place.
     */
    public static function verify(int $questionId, string $response, ?int $maxScore = null): ?bool
    {
        // More than one thing to answer means more than one thing to mark, and
        // this server holds a single model answer. See the class note: a
        // joined response compared against it produces false negatives, and a
        // learner marked wrong for a right answer is the worse failure.
        if ($maxScore !== null && $maxScore > 1) {
            return null;
        }

        $stored = self::storedModelAnswer($questionId);
        if ($stored === null) {
            return null;
        }

        $expected = self::fold($stored);
        $given = self::fold($response);

        if ($expected === '' || $given === '') {
            return null;
        }

        // A model answer long enough to be prose is not markable as a string.
        // Six words is the boundary: "the sum of the interior angles" is a
        // phrase a learner reproduces; anything longer is an explanation.
        if (str_word_count($expected) > 6) {
            return null;
        }

        return $expected === $given;
    }

    /** The question's stored written answer, or null when it has none. */
    private static function storedModelAnswer(int $questionId): ?string
    {
        $raw = DB::table('lms_question_master')->where('id', $questionId)->value('answer');

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $trimmed = trim($raw);
        if (!str_starts_with($trimmed, '{')) {
            return $trimmed;
        }

        $envelope = json_decode($trimmed, true);
        $model = is_array($envelope) ? ($envelope['model_answer'] ?? null) : null;

        return is_string($model) && trim($model) !== '' ? $model : null;
    }

    /** Case, punctuation and repeated spacing removed, so only words compare. */
    private static function fold(string $value): string
    {
        $text = strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * The learner's response as a string short enough to store.
     *
     * `lms_online_exam_answer.narrative_answer` is where this lands, and a
     * player that reports a whole serialised attempt would otherwise truncate
     * mid-value at the column boundary.
     */
    private static function readableResponse($value): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_map(fn ($part) => is_scalar($part) ? (string) $part : '', $value));
        }

        $text = is_scalar($value) ? trim((string) $value) : '';

        return mb_substr($text, 0, 500);
    }
}
