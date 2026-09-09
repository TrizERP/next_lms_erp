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

    public function test_a_declared_module_beats_the_words_it_shares_them_with(): void
    {
        // The panel knows which screen it opened on, and for any question that screen
        // could plausibly be about, that is the best evidence there is.
        $result = $this->resolve('Which students have pending fees?', ['module' => 'fees']);

        $this->assertSame('fees', $result['module']->key);
        $this->assertSame('declared_by_caller', $result['source']);
        $this->assertNull($result['stood_down']);
    }

    public function test_a_declared_module_keeps_a_question_no_other_module_claims(): void
    {
        // Nothing scores for anything here, so there is no stronger signal to prefer and
        // the screen stands. This is the common case and it must not move.
        $result = $this->resolve('Show me the arrears report for this month', ['module' => 'fees']);

        $this->assertSame('fees', $result['module']->key);
        $this->assertSame('declared_by_caller', $result['source']);
    }

    /**
     * The bug this rule exists for.
     *
     * A user on the dashboard asked the platform's flagship question and got twelve tidy
     * stages reporting that the dashboard module has no agent. The risk agent never ran,
     * no case opened, no referents were recorded — so the follow-up that depended on them
     * had nothing to resolve either, and the whole conversation died on turn two. The
     * declared module was not adding context; it was removing the answer.
     *
     * @dataProvider screensWithNoClaimOnTheQuestion
     */
    public function test_a_screen_with_no_claim_on_the_question_is_stood_down(string $screen): void
    {
        $result = $this->resolve('Which Grade 8 students have slipped into academic risk this term?', [
            'module' => $screen,
        ]);

        $this->assertTrue(
            $result['module']->hasAgent(),
            sprintf('Asked on %s, the risk question reached %s, which binds no agent.', $screen, $result['module']->key)
        );
        $this->assertSame($screen, $result['stood_down']);
        $this->assertSame('question_overrode_declared_by_caller', $result['source']);
    }

    /** @return array<string, array{0:string}> */
    public static function screensWithNoClaimOnTheQuestion(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'fees' => ['fees'],
            'admissions' => ['admissions'],
            'exam' => ['exam'],
        ];
    }

    public function test_a_module_with_no_vocabulary_is_never_stood_down(): void
    {
        // Such a module scores zero for every question ever asked, so zero says nothing
        // about it. Reading that as "no claim" would re-route every question asked on
        // half the estate's screens to whichever module happened to score.
        $result = $this->resolve('Which students have not submitted homework?', ['module' => 'lms']);

        $this->assertSame('lms', $result['module']->key);
        $this->assertNull($result['stood_down']);
    }

    public function test_an_elliptical_follow_up_belongs_to_the_thread_not_the_screen(): void
    {
        // "Which one is worst?" is about the answer above it. A panel that stays mounted
        // across a whole conversation would otherwise re-assert its own page on every
        // follow-up and strand the thread on turn two.
        foreach (['Which one is worst?', 'Why?', 'Show the details of the first candidate'] as $question) {
            $result = $this->resolve($question, [
                'module' => 'dashboard',
                'conversation_module' => 'student',
            ]);

            $this->assertSame('student', $result['module']->key, $question);
            $this->assertSame('conversation_thread_over_declared', $result['source'], $question);
        }
    }

    public function test_a_screen_that_scores_still_wins_an_elliptical_follow_up(): void
    {
        // The exception is narrow on purpose: it applies only when the question scores
        // for nothing at all. A question that names the screen is not elliptical.
        $result = $this->resolve('Summarise the KPIs on this dashboard', [
            'module' => 'dashboard',
            'conversation_module' => 'student',
        ]);

        $this->assertSame('dashboard', $result['module']->key);
        $this->assertSame('declared_by_caller', $result['source']);
    }

    public function test_a_follow_up_inherits_the_conversation_module_before_keyword_guessing(): void
    {
        $result = $this->resolve('Approve the recommendation.', ['conversation_module' => 'student']);

        $this->assertSame('student', $result['module']->key);
        $this->assertSame('conversation_thread', $result['source']);
        $this->assertTrue(
            $result['module']->hasWorkflow(),
            'Approval follow-ups must stay on the module that can start the governed workflow.'
        );
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
