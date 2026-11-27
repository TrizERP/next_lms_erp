<?php

namespace App\Services\AI\Pipeline;

/**
 * Quality gate shared by the module agent pipelines (Users, Attendance).
 *
 * A gate is a list of named checks, each a boolean the calling agent computed from data it
 * actually read. Nothing here supplies a pass: a check is only as good as the evidence the
 * agent hands in. The output shape matches the Fees / Teach-Learn gate so the UI renders one
 * kind of gate everywhere.
 */
final class PipelineGate
{
    /**
     * @param  array<string, array{0: bool, 1: string}>  $checks  name => [passed, message]
     * @return array<string, mixed>
     */
    public static function evaluate(int $n, string $name, array $checks): array
    {
        $out = [];
        $passed = 0;

        foreach ($checks as $key => [$ok, $message]) {
            $out[$key] = ['status' => $ok ? 'passed' : 'failed', 'message' => $message];
            $passed += $ok ? 1 : 0;
        }

        $total = count($out);

        return [
            'gate' => 'gate_' . $n,
            'name' => $name,
            'status' => $passed === $total ? 'passed' : 'failed',
            'score' => $total === 0 ? 0 : (int) round($passed / $total * 100),
            'passed_checks' => $passed,
            'total_checks' => $total,
            'checks' => $out,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $gates
     * @return array<string, mixed>
     */
    public static function composite(array $gates): array
    {
        $scores = array_map(static fn (array $g) => (int) $g['score'], $gates);
        $avg = $scores === [] ? 0 : (int) round(array_sum($scores) / count($scores));
        $allPassed = $gates !== [] && count(array_filter($gates, static fn (array $g) => $g['status'] !== 'passed')) === 0;

        return [
            'composite_score' => $avg,
            'overall_status' => $allPassed ? 'passed' : 'flagged',
            'gates_evaluated' => count($gates),
            'quality_tier' => $avg >= 95 ? 'excellent' : ($avg >= 80 ? 'good' : 'needs_attention'),
            'gates' => $gates,
        ];
    }
}
