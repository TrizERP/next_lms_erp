<?php

/**
 * Stage-by-stage verification for the conversational AI pipeline.
 *
 *     php artisan tinker --execute="require base_path('ai_stage_check.php');"
 *
 * Run it before and after a browser test. The point of reading rows rather than the
 * chat reply is that a fluent answer proves nothing — the assistant can describe an
 * agent run that never happened, and only these tables say otherwise.
 */

use Illuminate\Support\Facades\DB;

function line(string $stage, string $verdict, string $detail = ''): void
{
    echo str_pad($stage, 26) . str_pad($verdict, 10) . $detail . "\n";
}

$since = now()->subHours(2)->toDateTimeString();

echo "\n=== AI pipeline check (activity since {$since}) ===\n\n";

// The decisive signal. AgentController::run stamps trigger_reference='api'; the
// workspace tabs stamp 'workspace:*' and the console stamps 'ask:*'. Only the chat
// produces 'api'.
$apiRuns = DB::table('ai_agent_runs')->where('trigger_reference', 'api')->count();
$latest = DB::table('ai_agent_runs')->orderByDesc('id')->first();

line('3 Agent (via chat)', $apiRuns > 0 ? 'PASS' : 'NOT YET',
    "ref=api runs: {$apiRuns}" .
    ($latest ? " | latest #{$latest->id} ref={$latest->trigger_reference} {$latest->status}" : ''));

line('7 Real data', 'INFO', 'signals: ' . DB::table('ai_signals')->count());
line('8 Evidence', 'INFO', 'rows: ' . DB::table('ai_evidence')->count());

$hypotheses = DB::table('ai_hypotheses')->count();
$corroborated = DB::table('ai_hypotheses')->where('confidence', '>=', 0.5)->count();
line('9 Reasoning', $hypotheses > 0 ? 'PASS' : 'FAIL',
    "hypotheses: {$hypotheses} (corroborated >=0.5: {$corroborated})");

line('10 Recommendation', 'INFO',
    'total: ' . DB::table('ai_recommendations')->count()
    . ' | awaiting approval: ' . DB::table('ai_recommendations')->where('status', 'pending_approval')->count());

line('11 Human Approval', 'INFO',
    'decisions: ' . DB::table('ai_decisions')->count()
    . ' | workflow steps waiting: ' . DB::table('workflow_approvals')->where('status', 'pending')->count());

line('12 Action', 'INFO', 'interventions: ' . DB::table('academic_interventions')->count());

echo "\n--- agent runs in this window ---\n";
$recent = DB::table('ai_agent_runs')->where('started_at', '>=', $since)->orderByDesc('id')->get();

if ($recent->isEmpty()) {
    echo "  none.\n";
}

foreach ($recent as $run) {
    echo "  #{$run->id} ref={$run->trigger_reference} {$run->status}"
        . " signals={$run->signals_detected} cases={$run->cases_opened}"
        . " recs={$run->recommendations_drafted} {$run->duration_ms}ms\n";

    if ($run->error_message) {
        echo "      error: {$run->error_message}\n";
    }
}

echo "\n--- audit events in this window ---\n";
$events = DB::table('ai_audit_logs')->where('created_at', '>=', $since)
    ->selectRaw('event_type, count(*) c')->groupBy('event_type')->orderByDesc('c')->get();

foreach ($events as $event) {
    echo "  {$event->event_type}: {$event->c}\n";
}

if ($events->isEmpty()) {
    echo "  none.\n";
}

echo "\n";
