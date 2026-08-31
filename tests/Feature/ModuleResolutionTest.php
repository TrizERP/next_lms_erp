<?php

namespace Tests\Feature;

use App\Domain\AI\Lifecycle\Modules\ModuleResolver;
use Tests\TestCase;

/**
 * Which module a question routes to.
 *
 * This is the first decision of every turn and the least visible: get it wrong and the
 * trace still renders twelve tidy stages, all of them honestly reporting that the module
 * they landed in had nothing to run. That is exactly what happened the first time the
 * pipeline was run end to end — "Which students are at academic risk?", the platform's
 * flagship question, resolved to the General module and the agent never fired.
 *
 * Two causes, both pinned below: the keyword "student" did not match the word
 * "students", and a tie between two modules of the same domain was treated as ambiguity
 * rather than as the non-choice it is.
 */
class ModuleResolutionTest extends TestCase
{
    private function resolve(string $question, array $options = []): array
    {
        return app(ModuleResolver::class)->resolve($question, $options, 1);
    }

    public function test_the_flagship_question_reaches_the_module_that_owns_the_agent(): void
    {
        $result = $this->resolve('Which students are at academic risk?');

        $this->assertSame('student', $result['module']->key);
        $this->assertTrue(
            $result['module']->hasAgent(),
            'The risk question must land on a module that can actually run the agent.'
        );
    }

    public function test_a_keyword_matches_its_plural(): void
    {
        // "student" scoring zero against "students" halved the flagship question's score
        // and produced the tie that sent it to General.
        $singular = $this->resolve('Which student is at academic risk?');
        $plural = $this->resolve('Which students are at academic risk?');

        $this->assertSame($singular['module']->key, $plural['module']->key);
        $this->assertSame(
            $singular['considered']['student'],
            $plural['considered']['student'],
            'Singular and plural forms of the same question should score identically.'
        );
    }

    public function test_a_tie_between_modules_of_one_domain_is_not_ambiguity(): void
    {
        // `student` and `students` bind the same agent, workflow and case type, so
        // either answer is the same answer. Refusing to choose meant refusing to run.
        $result = $this->resolve('academic risk students');

        $this->assertNotSame('general', $result['module']->key);
        $this->assertTrue($result['module']->hasAgent());
    }

    public function test_a_genuinely_ambiguous_question_still_declines(): void
    {
        // The fold above must not become a licence to guess. A question matching nothing
        // has no domain to fold into and should reach General, which is honest about
        // having no depth.
        $result = $this->resolve('what is the weather today in Mumbai');

        $this->assertSame('general', $result['module']->key);
        $this->assertContains($result['source'], ['no_module_matched', 'ambiguous_between_modules']);
    }

    public function test_a_declared_module_beats_the_words(): void
    {
        // The panel knows which screen it opened on; the words often do not.
        $result = $this->resolve('Which students are at academic risk?', ['module' => 'fees']);

        $this->assertSame('fees', $result['module']->key);
        $this->assertSame('declared_by_caller', $result['source']);
    }

    public function test_money_words_beat_student_words_when_both_appear(): void
    {
        $result = $this->resolve('Which students have pending fees?');

        $this->assertSame('fees', $result['module']->key);
    }

    /**
     * @dataProvider routableQuestions
     */
    public function test_a_question_about_a_real_module_never_falls_through_to_general(
        string $question,
        string $expected
    ): void {
        $result = $this->resolve($question);

        $this->assertSame(
            $expected,
            $result['module']->key,
            sprintf('"%s" routed to %s (%s)', $question, $result['module']->key, $result['source'])
        );
    }

    /**
     * @return array<string, array{0:string, 1:string}>
     */
    public static function routableQuestions(): array
    {
        return [
            'risk scan' => ['Which students are at academic risk?', 'student'],
            'struggling' => ['Who is struggling this term?', 'student'],
            'fee defaulters' => ['Show me the fee defaulters', 'fees'],
            'attendance' => ['Who has low attendance?', 'attendance'],
            'admissions' => ['Which admission enquiries are pending?', 'admissions'],
            'exams' => ['Show the exam results for this term', 'exam'],
            'departments' => ['How many staff are in each department?', 'hr'],
        ];
    }
}
