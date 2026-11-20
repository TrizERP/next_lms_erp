<?php

namespace App\Services\Fees\Eso;

use Carbon\Carbon;

/**
 * Quality gates for the Fees sequential pipeline.
 *
 * Every gate is a pure function over what the previous agent actually returned from a
 * governed MCP tool. Nothing here reads a number from configuration or invents one: a check
 * passes only when the evidence it inspects is present and internally consistent.
 *
 *   Gate 1 — after Agent 1 (Fee Position & Arrears Reader)
 *   Gate 2 — after Agent 2 (Collection Evidence & Reminder History Analyst)
 *   Gate 3 — after Agent 3 (Reminder Drafter); also asserts nothing was sent
 */
class FeesQualityGateEngine
{
    public function evaluateGate1(int $subInstituteId, ?int $academicYear, array $position, array $arrears, bool $filtered = false): array
    {
        $checks = [];

        $checks['tenant_isolation'] = $subInstituteId > 0
            ? $this->pass('Tenant resolved from the authenticated context, never from a default.')
            : $this->fail('No valid tenant institute context.');

        $checks['academic_year_resolved'] = $academicYear
            ? $this->pass("Fees were read for academic year {$academicYear}; fee data is per institute and year.")
            : $this->fail('No academic year could be resolved, so no fee ledger can be read.');

        $checks['ledger_grounded'] = ($position['success'] ?? false)
            ? $this->pass('School-wide position read from the fee ledger in one grouped query (not a sample).')
            : $this->fail('The fee ledger could not be read: ' . ($position['message'] ?? 'unknown reason'));

        $arrearsData = $arrears['data'] ?? [];
        $checks['arrears_scan_completed'] = ($arrears['success'] ?? false)
            ? $this->pass(sprintf(
                'Arrears scan checked %d of %d student(s) with %d lookup failure(s).',
                (int) ($arrearsData['students_checked'] ?? 0),
                (int) ($arrearsData['cohort_size'] ?? 0),
                count($arrearsData['failed'] ?? [])
            ))
            : $this->fail('The arrears scan failed: ' . ($arrears['message'] ?? 'unknown reason'));

        // A sample must never be reported as the whole school.
        $truncated = (bool) ($arrearsData['truncated'] ?? false);
        $checks['coverage_disclosed'] = isset($arrearsData['cohort_size'], $arrearsData['students_checked'])
            ? $this->pass($truncated
                ? 'Scan is a bounded sample and is labelled as such; school-wide totals come from the ledger.'
                : 'Scan covered the whole cohort.')
            : $this->fail('Arrears scan did not report how many students it checked against the cohort.');

        $owingLedger = (int) ($position['data']['students_owing'] ?? 0);
        $checkedCount = (int) ($arrearsData['students_checked'] ?? 0);
        $found = count($arrearsData['students_with_arrears'] ?? []);
        // The two sources must agree in kind. Zero defaulters from the per-student calculation
        // while the ledger says students owe is a contradiction, not a clean bill of health.
        // A scan narrowed to one class or a minimum amount can legitimately find nobody.
        $consistent = $owingLedger === 0 || ($filtered && $found <= $owingLedger) || ($found > 0 && $found <= $owingLedger);
        $checks['scan_consistent_with_ledger'] = $consistent
            ? $this->pass("Scan found {$found} defaulter(s); ledger reports {$owingLedger} student(s) owing.")
            : $this->fail($found === 0
                ? "The ledger reports {$owingLedger} student(s) owing, but the per-student arrears calculation found none among the {$checkedCount} checked. The two disagree; this is not a finding that nobody owes."
                : "Scan found {$found} defaulter(s) but the ledger reports only {$owingLedger} owing — the two disagree.");

        return $this->summarise('Gate 1', 'Position & Arrears Grounding', $checks);
    }

    public function evaluateGate2(array $candidates, array $eligible, array $held, array $collection, array $history): array
    {
        $checks = [];

        $checks['candidates_received'] = $candidates !== []
            ? $this->pass(count($candidates) . ' candidate(s) handed over by Agent 1.')
            : $this->fail('Agent 1 handed over no students with arrears.');

        $checks['collection_report_read'] = ($collection['success'] ?? false)
            ? $this->pass('Collections for the period read from receipts.')
            : $this->fail('Collection report unavailable: ' . ($collection['message'] ?? 'unknown reason'));

        $checks['reminder_history_read'] = ($history['success'] ?? false)
            ? $this->pass('Reminder history read, so nobody is reminded twice inside the cooldown window.')
            : $this->fail('Reminder history unavailable: ' . ($history['message'] ?? 'unknown reason'));

        $accounted = count($eligible) + count($held);
        $checks['every_candidate_accounted_for'] = $accounted === count($candidates)
            ? $this->pass('Every candidate is either eligible or held back with a reason.')
            : $this->fail("{$accounted} of " . count($candidates) . ' candidates were classified.');

        $noOverlap = array_intersect(array_column($eligible, 'student_id'), array_column($held, 'student_id')) === [];
        $checks['no_double_classification'] = $noOverlap
            ? $this->pass('No student is both eligible and held back.')
            : $this->fail('A student appears in both eligible and held-back lists.');

        $checks['amounts_positive'] = $this->allPositive($eligible)
            ? $this->pass('Every eligible student has a positive outstanding amount.')
            : $this->fail('An eligible student has a zero or negative outstanding amount.');

        return $this->summarise('Gate 2', 'Evidence & Duplicate-Reminder Audit', $checks);
    }

    public function evaluateGate3(array $eligible, array $preview, bool $previewAttempted, array $sentIndicators = []): array
    {
        $checks = [];
        $data = $preview['data'] ?? [];

        if (! $previewAttempted) {
            $checks['preview_attempted'] = $this->pass('No eligible student, so no preview was needed.');
            $checks['nothing_sent'] = $this->pass('Nothing was sent.');

            return $this->summarise('Gate 3', 'Reminder Draft Safety', $checks);
        }

        $checks['preview_generated'] = ($preview['success'] ?? false)
            ? $this->pass('Reminder preview generated from the real recipient list.')
            : $this->fail('Preview unavailable: ' . ($preview['message'] ?? 'unknown reason'));

        $eligibleIds = array_map('intval', array_column($eligible, 'student_id'));
        $recipientIds = array_map('intval', array_column($data['recipients'] ?? [], 'student_id'));
        $checks['recipients_within_eligible'] = array_diff($recipientIds, $eligibleIds) === []
            ? $this->pass('Every recipient was an eligible student from Gate 2.')
            : $this->fail('The preview contains a recipient Gate 2 did not clear.');

        $deadline = $data['deadline'] ?? null;
        $checks['deadline_valid'] = ($deadline && Carbon::parse($deadline)->startOfDay()->gte(Carbon::today()))
            ? $this->pass("Payment deadline {$deadline} is today or later.")
            : $this->fail('Deadline missing or in the past.');

        $checks['approval_fingerprint_present'] = ! empty($data['fingerprint'])
            ? $this->pass('Fingerprint issued — sending requires an administrator to approve exactly this list.')
            : $this->fail('No approval fingerprint was issued.');

        $checks['nothing_sent'] = $sentIndicators === []
            ? $this->pass('Preview only: no message was sent to any parent.')
            : $this->fail('A send indicator was present in a preview-only pipeline.');

        return $this->summarise('Gate 3', 'Reminder Draft Safety', $checks);
    }

    public function computeCompositeScore(array $gates): array
    {
        $scores = [];
        foreach ($gates as $gate) {
            if (is_array($gate) && isset($gate['score'])) {
                $scores[] = (int) $gate['score'];
            }
        }

        $composite = $scores === [] ? 0 : (int) round(array_sum($scores) / count($scores));
        $failed = array_filter($gates, static fn ($g) => ($g['status'] ?? '') === 'failed');

        return [
            'composite_score' => $composite,
            'overall_status' => $failed === [] ? 'passed' : 'flagged',
            'gates_evaluated' => count($scores),
            'quality_tier' => $composite >= 90 ? 'EXCELLENT' : ($composite >= 75 ? 'PROFICIENT' : ($composite >= 50 ? 'NEEDS_REVIEW' : 'CRITICAL_GAPS')),
            'gates' => $gates,
            'evaluated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    private function allPositive(array $rows): bool
    {
        foreach ($rows as $row) {
            if ((float) ($row['outstanding'] ?? 0) <= 0) {
                return false;
            }
        }

        return true;
    }

    private function summarise(string $gate, string $name, array $checks): array
    {
        $total = count($checks);
        $passed = count(array_filter($checks, static fn ($c) => $c['status'] === 'passed'));

        return [
            'gate' => $gate,
            'name' => $name,
            'status' => $passed === $total ? 'passed' : 'failed',
            'score' => $total === 0 ? 0 : (int) round($passed / $total * 100),
            'passed_checks' => $passed,
            'total_checks' => $total,
            'checks' => $checks,
            'evaluated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    private function pass(string $message): array
    {
        return ['status' => 'passed', 'message' => $message];
    }

    private function fail(string $message): array
    {
        return ['status' => 'failed', 'message' => $message];
    }
}
