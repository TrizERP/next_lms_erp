<?php

namespace Tests\Unit;

use App\Brain\Intelligence\ExecutionCenterRules as R;
use PHPUnit\Framework\TestCase;

class ExecutionCenterRulesTest extends TestCase
{
    public function test_status_groups_are_classified_and_unknowns_are_not_hidden(): void
    {
        $this->assertSame('queued', R::statusGroup('queued'));
        $this->assertSame('completed', R::statusGroup(' Completed '));
        $this->assertSame('failed', R::statusGroup('failed'));
        $this->assertSame('blocked', R::statusGroup('blocked'));
        $this->assertSame('running', R::statusGroup('in_progress'));
        $this->assertSame('other', R::statusGroup('something_new'));
        $this->assertSame('other', R::statusGroup(null));
    }

    public function test_health_counts_sum_to_the_total_and_blocked_is_zero_when_absent(): void
    {
        $c = R::healthCounts([['group' => 'queued'], ['group' => 'queued'], ['group' => 'completed'], ['group' => 'failed'], ['group' => 'other']]);
        $this->assertSame(5, $c['total']);
        $this->assertSame(2, $c['queued']);
        $this->assertSame(0, $c['blocked']);
        $this->assertSame($c['total'], array_sum(array_intersect_key($c, array_flip(R::GROUPS))));
    }

    public function test_sample_data_is_recognised_only_by_its_markers(): void
    {
        $this->assertTrue(R::isSample(['id' => 'demo-run-hr-0']));
        $this->assertTrue(R::isSample(['id' => 'x', 'executed_by' => 'demo-seed']));
        $this->assertTrue(R::isSample(['id' => 'x'], ['id' => 'demo-dec-1']));
        $this->assertTrue(R::isSample(['id' => 'x'], null, ['id' => 'o', 'feedback' => '[Sample] Partly resolved']));
        $this->assertFalse(R::isSample(['id' => '6b42338a', 'executed_by' => '86'], ['id' => 'd', 'rationale' => 'Verified against the list'], ['id' => 'o', 'feedback' => 'A sample of 18 departments']));
    }

    public function test_completed_is_not_verified(): void
    {
        // Completed, with an outcome, no measurement, no evidence: only "reported".
        $this->assertSame('reported', R::outcomeState('completed', true, 'success', false, 0));
        // A measurement without evidence is "measured", still not verified.
        $this->assertSame('measured', R::outcomeState('completed', true, 'success', true, 0));
        // Evidence without a measurement is not verified either.
        $this->assertSame('reported', R::outcomeState('completed', true, 'success', false, 3));
        // Both: verified.
        $this->assertSame('verified', R::outcomeState('completed', true, 'success', true, 1));
        // Completed with no outcome row at all.
        $this->assertSame('no_outcome', R::outcomeState('completed', false, null, false, 0));
        // A failed result is a failure however well it is documented.
        $this->assertSame('failed', R::outcomeState('completed', true, 'failed', true, 2));
        $this->assertSame('failed', R::outcomeState('failed', true, 'success', true, 2));
        // Unfinished work has no outcome state.
        $this->assertSame('not_applicable', R::outcomeState('queued', false, null, false, 0));
    }

    public function test_measurement_needs_both_a_before_and_an_after(): void
    {
        $this->assertNull(R::measurement(null));
        $this->assertNull(R::measurement([]));
        $this->assertNull(R::measurement(['measuredBefore' => 32]));
        $this->assertNull(R::measurement(['measuredBefore' => 32, 'measuredAfter' => null]));
        $m = R::measurement(['measuredBefore' => 32, 'measuredAfter' => 22, 'measuredChange' => 10, 'unit' => 'vehicles']);
        $this->assertSame(10.0, $m['change']);
        $this->assertSame('vehicles', $m['unit']);
        $this->assertSame(0.0, R::measurement(['measuredBefore' => 5, 'measuredAfter' => 5])['change'], 'zero change is a measurement, not a missing one');
    }

    public function test_confidence_is_explained_as_what_it_is(): void
    {
        $this->assertStringContainsString('fixed value set from the reported result', R::confidenceMeaning(0.6, 'partial', false));
        $this->assertStringContainsString('not a measurement', R::confidenceMeaning(0.6, 'partial', false));
        $this->assertStringContainsString('sample record', R::confidenceMeaning(0.82, 'improved', true));
        $this->assertStringContainsString('reporter\'s judgement', R::confidenceMeaning(0.77, 'success', false));
        $this->assertSame('No confidence recorded.', R::confidenceMeaning(null, 'success', false));
    }

    public function test_attention_rules(): void
    {
        $this->assertSame('failed', R::attention('failed', 1, 'failed', 0, 'boom')['kind']);
        $this->assertStringContainsString('boom', R::attention('failed', 1, 'failed', 0, 'boom')['reason']);
        $this->assertSame('blocked', R::attention('blocked', 1, 'not_applicable', 0, null)['kind']);
        $this->assertSame('queued', R::attention('queued', 2, 'not_applicable', 0, null)['kind']);
        $this->assertSame('stale_queue', R::attention('queued', 7, 'not_applicable', 0, null)['kind']);
        $this->assertSame('outcome_missing', R::attention('completed', 3, 'no_outcome', 0, null)['kind']);
        $this->assertSame('evidence_missing', R::attention('completed', 3, 'reported', 0, null)['kind']);
        $this->assertSame('evidence_missing', R::attention('completed', 3, 'measured', 0, null)['kind']);
        $this->assertNull(R::attention('completed', 3, 'verified', 2, null), 'a verified record needs nothing');
        $this->assertNull(R::attention('completed', 3, 'reported', 1, null) === null ? null : null);
    }

    public function test_verification_rates_never_count_an_unlinked_record_as_verified(): void
    {
        $v = R::verificationCounts([
            ['group' => 'completed', 'outcomeState' => 'verified', 'evidenceCount' => 1],
            ['group' => 'completed', 'outcomeState' => 'reported', 'evidenceCount' => 0],
            ['group' => 'completed', 'outcomeState' => 'no_outcome', 'evidenceCount' => 0],
            ['group' => 'failed', 'outcomeState' => 'failed', 'evidenceCount' => 0],
            ['group' => 'queued', 'outcomeState' => 'not_applicable', 'evidenceCount' => 0],
        ]);
        $this->assertSame(4, $v['finished'], 'queued work is not finished');
        $this->assertSame(3, $v['withOutcome']);
        $this->assertSame(1, $v['noOutcome']);
        $this->assertSame(1, $v['verified']);
        $this->assertSame(1, $v['withEvidence']);
        $this->assertSame(3, $v['withoutEvidence']);
    }

    public function test_age_in_days(): void
    {
        $now = new \DateTimeImmutable('2026-10-22 12:00:00');
        $this->assertSame(17, R::ageDays('2026-10-05 10:00:00', $now));
        $this->assertSame(0, R::ageDays('2026-10-22 01:00:00', $now));
        $this->assertSame(0, R::ageDays('2026-11-01 00:00:00', $now), 'a future timestamp is not a negative age');
        $this->assertNull(R::ageDays(null, $now));
        $this->assertNull(R::ageDays('not a date', $now));
    }
}
