<?php
use Illuminate\Support\Facades\DB;
echo 'workflow_runs: ' . DB::table('workflow_runs')->count() . "\n";
foreach (DB::table('workflow_runs')->orderByDesc('id')->limit(5)->get() as $r) {
    $a = (array) $r;
    echo sprintf("  id=%-4s key=%-30s status=%-15s inst=%s subj=%s step=%s\n", 
        $a['id'] ?? '?', $a['workflow_key'] ?? '?', $a['status'] ?? '?', $a['sub_institute_id'] ?? 'NULL', $a['subject_id'] ?? '?', $a['current_step_key'] ?? '?');
}
echo "\nworkflow_steps: " . DB::table('workflow_steps')->count() . "\n";
foreach (DB::table('workflow_steps')->orderByDesc('id')->limit(5)->get() as $r) {
    $a = (array) $r;
    echo sprintf("  run=%-4s step=%-30s status=%s\n", $a['run_id'] ?? '?', $a['step_key'] ?? '?', $a['status'] ?? '?');
}