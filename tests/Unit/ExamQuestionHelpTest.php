<?php

namespace Tests\Unit;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Lifecycle\Flows\ExamQuestionHelpFlow;
use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\ExamQuestionHelpAnswer;
use App\Domain\AI\Support\OpenRouterClient;
use App\Services\Mcp\McpRequestContext;
use PHPUnit\Framework\TestCase;

/**
 * A student stuck on a question of the exam they are sitting.
 *
 * Two things matter here more than the wording. The flow must only take a turn that is really about
 * an exam question on the attempt screen - "what is the capital of France?" on the same screen goes
 * where it always went. And the answer must follow the database: a model reply that does not name
 * the key's correct option is discarded, and where the teacher hides answers nothing may name one.
 *
 * The rows below are shaped like `exam_assessment.question_help` output; the tool and service
 * themselves are exercised against the real exam tables by hand (they need the database).
 */
class ExamQuestionHelpTest extends TestCase
{
    private function context(string $question, array $options = [], string $role = 'student'): StageContext
    {
        return new StageContext(
            question: $question,
            scope: new McpRequestContext(321647, $role, 1, [1], 8, null, 2022, null, false, $role === 'student'),
            module: new ModuleCapability(
                key: 'exam_assessment',
                label: 'Exam & Assessment',
                capabilities: ['conversational' => true],
                mcpTools: [ExamQuestionHelpFlow::TOOL],
            ),
            options: $options,
        );
    }

    public function test_a_question_number_on_the_attempt_screen_is_exam_help(): void
    {
        $target = ExamQuestionHelpFlow::target($this->context('I do not understand question 3', ['route' => '/exam/online/2503']));

        $this->assertSame(2503, $target['paper_id']);
        $this->assertSame(3, $target['question_number']);
    }

    public function test_the_number_typed_beats_the_question_the_page_had_selected(): void
    {
        $target = ExamQuestionHelpFlow::target($this->context('help me with q5', [
            'route' => '/exam/online/2503',
            'payload' => ['paper_id' => 2503, 'question_id' => 89245],
        ]));

        $this->assertSame(5, $target['question_number']);
        $this->assertArrayNotHasKey('question_id', $target);
    }

    public function test_a_question_button_pins_the_id_even_for_a_plain_follow_up(): void
    {
        $target = ExamQuestionHelpFlow::target($this->context('Give me a similar example.', [
            'route' => '/exam/online/2503',
            'payload' => ['paper_id' => 2503, 'question_id' => 89245],
        ]));

        $this->assertSame(89245, $target['question_id']);
    }

    public function test_an_unrelated_question_on_the_exam_screen_is_not_taken(): void
    {
        $this->assertNull(ExamQuestionHelpFlow::target($this->context('What is the capital of France?', ['route' => '/exam/online/2503'])));
    }

    public function test_the_result_screen_and_other_screens_are_not_taken(): void
    {
        $this->assertNull(ExamQuestionHelpFlow::target($this->context('help me with question 3', ['route' => '/exam/online/2503/result'])));
        $this->assertNull(ExamQuestionHelpFlow::target($this->context('help me with question 3', ['route' => '/fees/collect'])));
        $this->assertNull(ExamQuestionHelpFlow::target($this->context('help me with question 3')));
    }

    public function test_the_route_decides_the_paper_not_the_payload(): void
    {
        $target = ExamQuestionHelpFlow::target($this->context('help me with question 2', [
            'route' => '/exam/online/2503',
            'payload' => ['paper_id' => 4193],
        ]));

        $this->assertSame(2503, $target['paper_id']);
    }

    public function test_a_pin_only_counts_on_that_papers_own_attempt_screen(): void
    {
        $this->assertTrue(ExamQuestionHelpFlow::isPinned(['route' => '/exam/online/2503', 'payload' => ['paper_id' => 2503, 'question_id' => 1]]));
        $this->assertFalse(ExamQuestionHelpFlow::isPinned(['route' => '/exam/online/2503', 'payload' => ['paper_id' => 4193, 'question_id' => 1]]));
        $this->assertFalse(ExamQuestionHelpFlow::isPinned(['route' => '/fees', 'payload' => ['paper_id' => 2503, 'question_id' => 1]]));
        $this->assertFalse(ExamQuestionHelpFlow::isPinned(['route' => '/exam/online/2503', 'payload' => ['paper_id' => 2503]]));
    }

    public function test_another_module_never_takes_the_route(): void
    {
        $context = new StageContext(
            question: 'help me with question 3',
            scope: new McpRequestContext(1, 'admin', 1, [1], null, null, 2022, null, true, false),
            module: new ModuleCapability(key: 'fees', label: 'Fees', mcpTools: [ExamQuestionHelpFlow::TOOL]),
            options: ['route' => '/exam/online/2503'],
        );

        $this->assertNull(ExamQuestionHelpFlow::target($context));
    }

    // ------------------------------------------------------------------ the answer

    /** @return array<string, mixed> */
    private function row(bool $reveal, string $teacher = 'Combination of chromosomes XX lead to the birth of a baby girl.'): array
    {
        $question = [
            'id' => 89245, 'number' => 3, 'title' => 'Which combination gives a baby girl?', 'type' => 'multiple_choice',
            'multiple_answers' => false, 'points' => 1.0,
            'options' => [['id' => 1, 'text' => 'XX'], ['id' => 2, 'text' => 'XY']],
            'topics' => [],
        ];

        if ($reveal) {
            $question += ['correct_options' => [['id' => 1, 'text' => 'XX']], 'teacher_explanation' => $teacher, 'model_answer' => '', 'option_feedback' => []];
        }

        return [
            'status' => 'ok',
            'paper' => ['id' => 2503, 'name' => 'Sample Exam', 'question_count' => 7],
            'answers_revealed' => $reveal,
            'question' => $question,
        ];
    }

    /** @return array<string, mixed> */
    private function lesson(string $explanation, array $ignored = [], string $unused = ''): array
    {
        return ['explanation' => $explanation !== '' ? $explanation : $unused];
    }

    private function tutor(?array $lesson): ExamQuestionHelpAnswer
    {
        $client = new class($lesson) extends OpenRouterClient
        {
            public function __construct(private readonly ?array $reply)
            {
            }

            public function isConfigured(): bool
            {
                return $this->reply !== null;
            }

            public function json(array $messages, ?string $model = null, int $maxTokens = 900, float $temperature = 0.0): ?array
            {
                return $this->reply;
            }
        };

        return new ExamQuestionHelpAnswer(new AnswerComposer(), $client);
    }

    private function compose(ExamQuestionHelpAnswer $answer, array $result): StageContext
    {
        $context = $this->context('help me with question 3', ['route' => '/exam/online/2503']);
        $context->set('mcp_step_results', ['read_question' => $result]);
        $answer->compose($context);

        return $context;
    }

    /** The one answer card the chat panel draws. @return array<string, mixed> */
    private function card(StageContext $context): array
    {
        return $context->sections()[0] ?? [];
    }

    private function body(StageContext $context): string
    {
        return json_encode($context->sections(), JSON_UNESCAPED_UNICODE);
    }

    public function test_the_headline_gives_the_answer_the_database_holds(): void
    {
        $context = $this->compose($this->tutor($this->lesson('The correct option is XX. A girl gets an X from each parent.', ['XX is right.', 'XY is wrong.'])), $this->row(true));

        $this->assertSame("Let's understand this question!", $context->headline());
        $this->assertSame('exam_answer', $this->card($context)['type']);
        $this->assertSame('Correct Answer', $this->card($context)['label']);
        $this->assertSame('XX.', $this->card($context)['value']);
        $this->assertSame('Why is this correct?', $this->card($context)['heading']);
        $this->assertStringContainsString('A girl gets an X from each parent.', $this->body($context));
        $this->assertStringNotContainsString('Check yourself', $this->body($context));
        $this->assertStringNotContainsString('similar example', $this->body($context));
        $this->assertSame([], $context->followUps());
    }

    public function test_a_model_reply_that_misses_the_correct_option_is_replaced_by_the_teachers_words(): void
    {
        $context = $this->compose($this->tutor($this->lesson('The answer is XY because of the Y chromosome.', ['XX is wrong.', 'XY is right.'])), $this->row(true));

        $this->assertStringNotContainsString('because of the Y chromosome', $this->body($context));
        $this->assertStringContainsString('Combination of chromosomes XX lead to the birth of a baby girl.', $this->body($context));
    }

    public function test_with_no_model_the_answer_and_teachers_explanation_still_arrive(): void
    {
        $context = $this->compose($this->tutor(null), $this->row(true));

        $this->assertSame('XX.', $this->card($context)['value']);
        $this->assertStringContainsString('Combination of chromosomes XX', $this->card($context)['text']);
    }

    public function test_a_teacher_note_that_contradicts_the_key_is_said_out_loud(): void
    {
        $context = $this->compose($this->tutor(null), $this->row(true, 'It is XY that gives a girl.'));

        $this->assertStringContainsString('ask your teacher to confirm', $this->card($context)['note']);
        $this->assertArrayNotHasKey('text', $this->card($context));
    }

    public function test_where_answers_are_hidden_nothing_names_one(): void
    {
        $context = $this->compose($this->tutor($this->lesson('', ['This is a pair.', 'This is a boy pair.'], 'The correct answer is XX.')), $this->row(false));

        $this->assertStringContainsString('chosen not to show answers', $this->card($context)['note']);
        $this->assertArrayNotHasKey('value', $this->card($context));
        $this->assertSame('A hint', $this->card($context)['heading']);
        $this->assertStringNotContainsString('The correct answer is XX', $this->body($context));
        $this->assertStringNotContainsString('✓', $this->body($context));
    }

    public function test_an_unavailable_exam_says_why_and_reads_nothing(): void
    {
        $context = $this->context('help me with question 3', ['route' => '/exam/online/4193']);
        $context->set('mcp_step_results', ['read_question' => ['status' => 'not_available', 'message' => 'That exam is not assigned to your class.']]);

        $this->tutor(null)->compose($context);

        $this->assertSame('That exam is not assigned to your class.', $context->headline());
        $this->assertSame([], $context->sections());
    }

    public function test_no_question_named_lists_the_real_ones(): void
    {
        $context = $this->context('I am stuck', ['route' => '/exam/online/2503']);
        $context->set('mcp_step_results', ['read_question' => [
            'status' => 'need_question',
            'paper' => ['id' => 2503, 'name' => 'Sample Exam', 'question_count' => 2],
            'questions' => [['number' => 1, 'id' => 10, 'title' => 'First?'], ['number' => 2, 'id' => 11, 'title' => 'Second?']],
        ]]);

        $this->tutor(null)->compose($context);

        $this->assertStringContainsString('1. First?', $this->body($context));
        $this->assertContains('Help me with question 1.', $context->followUps());
    }

    /**
     * A model that answers a different reply each time it is asked.
     *
     * @param  array<int, array<string, mixed>>  $replies
     */
    private function tutorSequence(array $replies, int &$asked): ExamQuestionHelpAnswer
    {
        $client = new class($replies, $asked) extends OpenRouterClient
        {
            public function __construct(private array $replies, private int &$asked)
            {
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function json(array $messages, ?string $model = null, int $maxTokens = 900, float $temperature = 0.0): ?array
            {
                $this->asked++;

                return array_shift($this->replies);
            }
        };

        return new ExamQuestionHelpAnswer(new AnswerComposer(), $client);
    }

    public function test_an_explanation_that_only_repeats_the_teachers_line_is_asked_for_again(): void
    {
        $thin = $this->lesson('Combination of chromosomes XX lead to the birth of a baby girl.');
        $good = $this->lesson('Sex chromosomes decide whether a baby is a girl or a boy. The combination XX gives a girl, because she receives an X from each parent. XY gives a boy, so it is the tempting but wrong choice.');
        $asked = 0;

        $context = $this->compose($this->tutorSequence([$thin, $good], $asked), $this->row(true));

        $this->assertSame(2, $asked);
        $this->assertStringContainsString('receives an X from each parent', $this->card($context)['text']);
        $this->assertSame('XX.', $this->card($context)['value'], 'the answer is the database key whatever the model says');
    }

    public function test_a_good_first_explanation_is_not_asked_for_twice(): void
    {
        $good = $this->lesson('Sex chromosomes decide whether a baby is a girl or a boy. The combination XX gives a girl, because she receives an X from each parent. XY gives a boy, so it is the tempting but wrong choice.');
        $asked = 0;

        $this->compose($this->tutorSequence([$good, $good], $asked), $this->row(true));

        $this->assertSame(1, $asked);
    }

    public function test_when_the_retry_is_no_better_the_first_reply_still_stands(): void
    {
        $thin = $this->lesson('The correct option is XX. A girl gets an X from each parent.');
        $asked = 0;

        $context = $this->compose($this->tutorSequence([$thin, $thin], $asked), $this->row(true));

        $this->assertSame(2, $asked);
        $this->assertStringContainsString('A girl gets an X from each parent.', $this->card($context)['text']);
    }

    public function test_a_model_that_returns_nothing_usable_the_first_time_is_asked_once_more(): void
    {
        $good = $this->lesson('Sex chromosomes decide whether a baby is a girl or a boy. The combination XX gives a girl, because she receives an X from each parent. XY gives a boy, so it is the tempting but wrong choice.');
        $asked = 0;

        $context = $this->compose($this->tutorSequence([null, $good], $asked), $this->row(true));

        $this->assertSame(2, $asked);
        $this->assertStringContainsString('receives an X from each parent', $this->card($context)['text']);
    }
}
