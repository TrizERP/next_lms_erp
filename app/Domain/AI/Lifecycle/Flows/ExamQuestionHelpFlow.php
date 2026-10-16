<?php

namespace App\Domain\AI\Lifecycle\Flows;

use App\Domain\AI\Lifecycle\StageContext;

/**
 * "I'm stuck on this question" - asked from the screen where the student is sitting the exam.
 *
 * WHY THIS EXISTS
 *
 * The exam screen (`/exam/online/{paperId}`) belongs to Exam & Assessment, whose tools all read
 * cohort figures for staff. A student asking for help with a question was therefore routed to
 * whatever the classifier made of the sentence - on one real run, "can you help me answer it?"
 * became "reject the recommendation" and printed other people's pending recommendations. This is
 * the route for the case where the person is a student, on an exam they are attempting, and the
 * sentence is about one of its questions.
 *
 * WHEN IT APPLIES
 *
 * Only on the attempt screen (or when a caller pins `paper_id`), and only when the sentence is
 * about a question: the button on a question pins its id; otherwise the sentence must refer to a
 * question by number or say it is stuck. "What is photosynthesis?" on the same screen is not
 * taken, and goes where it always went.
 *
 * The question itself is never decided here. This only works out WHICH paper and WHICH question
 * the person means; `exam_assessment.question_help` reads it from the database and decides whether
 * this person may see it.
 */
class ExamQuestionHelpFlow
{
    public const TOOL = 'exam_assessment.question_help';

    public const INTENT = 'exam_question_help';

    private const ORDINALS = [
        'first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4, 'fifth' => 5,
        'sixth' => 6, 'seventh' => 7, 'eighth' => 8, 'ninth' => 9, 'tenth' => 10,
    ];

    /**
     * The tool arguments for this turn, or null when it is not exam help.
     *
     * @return array{paper_id:int, question_id?:int, question_number?:int, question_text?:string}|null
     */
    public static function target(StageContext $context): ?array
    {
        if ($context->module->key !== 'exam_assessment' || ! in_array(self::TOOL, $context->module->mcpTools, true)) {
            return null;
        }

        // Where the person is beats what a client says they are on.
        $paperId = self::paperFromRoute((string) ($context->options['route'] ?? '')) ?? $context->payload('paper_id');

        if ($paperId === null || $paperId <= 0) {
            return null;
        }

        $question = $context->question;
        $number = self::numberIn($question);
        $pinned = $context->payload('question_id');

        // A question button pins the id; otherwise the sentence has to be about a question.
        if ($number === null && $pinned === null && ! self::soundsStuck($question)) {
            return null;
        }

        $arguments = ['paper_id' => $paperId, 'question_text' => mb_substr($question, 0, 1000)];

        // What the person typed beats what the page had selected: "question 5" means 5 even while 2 is open.
        if ($number !== null) {
            $arguments['question_number'] = $number;
        } elseif ($pinned !== null && $pinned > 0) {
            $arguments['question_id'] = $pinned;
        }

        return $arguments;
    }

    /**
     * Whether the caller pinned a paper and a question AND is on that paper's attempt screen.
     *
     * Both halves are needed: the payload alone is just numbers a client chose to send, and the route alone
     * is where the person happens to be. Together they say a question button on the exam screen was pressed.
     *
     * @param  array<string, mixed>  $options
     */
    public static function isPinned(array $options): bool
    {
        $payload = $options['payload'] ?? [];

        if (! is_array($payload) || ! is_numeric($payload['paper_id'] ?? null) || ! is_numeric($payload['question_id'] ?? null)) {
            return false;
        }

        return self::paperFromRoute((string) ($options['route'] ?? '')) === (int) $payload['paper_id'];
    }

    public static function paperFromRoute(string $route): ?int
    {
        // The attempt screen only: the result screen (`/result`) already shows every answer.
        if (preg_match('#/exam/online/(\d+)/?(?:[?\#]|$)#', $route, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /** "question 3", "q3", "Q.3", "question no. 3", "the third question". */
    public static function numberIn(string $text): ?int
    {
        if (preg_match('/\b(?:question|ques|que|q)\s*\.?\s*(?:no\.?|number|num|#)?\s*(\d{1,3})\b/iu', $text, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/\b(' . implode('|', array_keys(self::ORDINALS)) . ')\s+(?:question|one)\b/iu', $text, $m)) {
            return self::ORDINALS[strtolower($m[1])];
        }

        return null;
    }

    public static function soundsStuck(string $text): bool
    {
        return (bool) preg_match(
            '/\b(stuck|confus\w*|(?:do not|don\'?t|dont|cannot|can\'?t|cant|unable to|not able to)\s+(?:understand|get|know|solve|answer|figure)|'
            . 'no idea|help me|need help|explain|hint|clue|what(?:\'s| is) the (?:right |correct )?answer|which (?:option|one|is correct)|'
            . 'correct answer|right answer|this question|the question|how (?:do|to) (?:i )?(?:solve|answer)|why is)\b/iu',
            $text
        );
    }
}
