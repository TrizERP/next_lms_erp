<?php

namespace Tests\Feature\AI\Attendance;

use App\Domain\AI\Conversation\IntentClassifier;
use App\Domain\AI\Lifecycle\Plan\DeterministicPlanner;
use App\Domain\AI\Lifecycle\Plan\PlanStep;
use Tests\TestCase;

/**
 * The attendance questions that should reach the agent, and — just as important — the
 * ones that should not.
 *
 * `attendance_risk_scan` has to win a sentence against two neighbours that already score
 * highly on it. `student_risk_scan` fires on the bare word "students" and would run the
 * academic-risk agent, whose recommendation is an academic intervention rather than a
 * conversation about absence. `fees_risk_scan` shares the verbs. The second half of this
 * file is therefore the part that matters most: it pins the neighbours' behaviour so a
 * later tweak to the attendance signals cannot quietly steal their sentences.
 */
class AttendanceIntentTest extends TestCase
{
    private IntentClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new IntentClassifier();
    }

    /**
     * @dataProvider attendanceAgentPhrasings
     */
    public function test_attendance_analysis_questions_reach_the_attendance_agent(string $question): void
    {
        $intent = $this->classifier->classify($question);

        $this->assertSame('attendance_risk_scan', $intent->key, "Question: {$question}");
        $this->assertGreaterThanOrEqual(0.34, $intent->confidence, "Confidence too low for: {$question}");
    }

    /**
     * @return array<int, array{0:string}>
     */
    public static function attendanceAgentPhrasings(): array
    {
        return [
            // The single most common phrasing, and the one the whole feature is for.
            ['Show me students with attendance below 75%.'],
            ['Which students have poor attendance?'],
            ['Analyse attendance risk for class 7'],
            ['flag chronically absent students'],
            ['Assess absenteeism this term'],
            ['Who is at risk on attendance?'],
            ['Review low attendance and follow up'],
            ['Which children are persistently absent?'],
            ['students under 80% attendance'],
        ];
    }

    /**
     * @dataProvider neighbouringIntents
     */
    public function test_neighbouring_modules_keep_their_own_questions(string $question, string $expected): void
    {
        $intent = $this->classifier->classify($question);

        $this->assertSame($expected, $intent->key, "Question: {$question}");
    }

    /**
     * @return array<int, array{0:string, 1:string}>
     */
    public static function neighbouringIntents(): array
    {
        return [
            ['Analyse fee payment risk', 'fees_risk_scan'],
            ['Which students are at risk of non-payment?', 'fees_risk_scan'],
            ['Which students are at risk academically?', 'student_risk_scan'],
            ['Scan for students at academic risk', 'student_risk_scan'],
            ['Show me students with unpaid fees', 'fees_query'],
        ];
    }

    /**
     * A threshold with nothing to say what it is a threshold ON must not be claimed.
     *
     * "Students below 75%" is ambiguous between marks and attendance. Answering it
     * confidently from the register would be answering a different question than the one
     * that was asked, so it is left to the module router — which resolves it from the rest
     * of the page's context or says it could not place it.
     */
    public function test_a_bare_threshold_is_not_claimed_as_an_attendance_question(): void
    {
        $intent = $this->classifier->classify('Show me students below 75%.');

        $this->assertNotSame('attendance_risk_scan', $intent->key);
    }

    /**
     * The route and the ladder the attendance scan is planned as.
     *
     * Reached by reflection because `plan()` takes a whole `StageContext` — a thread, a
     * scope and a classified intent — and building one here would be testing the harness
     * rather than the binding. These two private tables ARE the binding: without the
     * route the intent never reaches an agent, and without the steps the trace has
     * nothing to show a person.
     */
    public function test_the_attendance_scan_is_planned_as_an_agent_run_with_four_stages(): void
    {
        $planner = app(DeterministicPlanner::class);

        $route = new \ReflectionMethod($planner, 'routeFor');
        $route->setAccessible(true);
        $this->assertSame('agent_runner', $route->invoke($planner, 'attendance_risk_scan'));

        $steps = new \ReflectionMethod($planner, 'stepsFor');
        $steps->setAccessible(true);
        $plan = $steps->invoke($planner, 'attendance_risk_scan');

        $this->assertIsArray($plan, 'The attendance scan has no deterministic steps.');

        // detect → analyse → recommend → report. The same ladder the fees and academic
        // scans climb, because it is the same journey over a different record.
        $this->assertSame(
            ['detect', 'analyse', 'recommend', 'report'],
            array_map(static fn (PlanStep $step) => $step->id, $plan)
        );
    }

    /**
     * The fees scan keeps its own route and ladder.
     *
     * Here rather than in the fees tests because this file is what changed the table
     * they both live in — a new entry that landed in the wrong arm of the match would
     * break Fees, and this is the assertion that would catch it.
     */
    public function test_the_fees_scan_is_unchanged(): void
    {
        $planner = app(DeterministicPlanner::class);

        $route = new \ReflectionMethod($planner, 'routeFor');
        $route->setAccessible(true);
        $this->assertSame('agent_runner', $route->invoke($planner, 'fees_risk_scan'));

        $steps = new \ReflectionMethod($planner, 'stepsFor');
        $steps->setAccessible(true);

        $this->assertSame(
            ['detect', 'analyse', 'recommend', 'report'],
            array_map(static fn (PlanStep $step) => $step->id, $steps->invoke($planner, 'fees_risk_scan'))
        );
    }
}
