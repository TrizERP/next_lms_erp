<?php
// Phase 1 classifier: explicit decisions + prefix rules -> classification.json
$dir = __DIR__;
$inv  = json_decode(file_get_contents("$dir/inventory.json"), true);
$refs = json_decode(file_get_contents("$dir/coderefs.json"), true);
$ten  = json_decode(file_get_contents("$dir/tenancy.json"), true);
$D    = require "$dir/decisions.php";

// ── O*NET dimension tables get projected; rating ledgers get top-N or excluded ──
$ONET_DIM = [
    'onet_occupation_data'   => ':Occupation',
    'onet_employer'          => ':Employer',
    'onet_explore_sector'    => ':Sector',
    'onet_career_cluster'    => ':CareerCluster',
    'onet_job_zone_reference' => ':JobZone',
    'onet_scales_reference'  => ':ONetScale',
    'onet_content_model_reference' => ':ONetElement',
    'onet_work_context_categories' => ':WorkContextCategory',
    'onet_unspsc_reference'  => ':UNSPSCCategory',
    'onet_institute_courses' => ':Course',
    'onet_expert_advice'     => ':ExpertAdvice',
    'o_net_data_categories'  => ':ONetDataCategory',
    'o_net_data_sub_categories' => ':ONetDataSubCategory',
    // Split from :Occupation 2026-08-10 — all 1,016 onet_occupation_data ids collide
    // with this table on (tenant, pk), so sharing the label silently lost every one.
    'o_net_data_occupations' => ':ONetDataOccupation',
    'o_net_data_table_lists' => ':ONetDataTable',
];
// rating ledgers -> weighted edge, top-N per occupation
$ONET_RATING = [
    'onet_skills' => ':Skill', 'onet_knowledge' => ':Knowledge', 'onet_abilities' => ':Ability',
    'onet_work_activities' => ':WorkActivity', 'onet_work_context' => ':WorkContext',
    'onet_work_styles' => ':WorkStyle', 'onet_work_values' => ':WorkValue',
    'onet_interests' => ':Interest', 'onet_technology_skills' => ':TechnologySkill',
    'onet_tools_used' => ':Tool', 'onet_task_ratings' => ':Task', 'onet_task_statements' => ':Task',
    'onet_job_zones' => ':JobZone',
];

$out = [];
$uncovered = [];
foreach ($inv as $t) {
    $name = $t['table'];
    $rec = [
        'table' => $name,
        'rows' => $t['exact_rows'],
        'size_mb' => $t['size_mb'],
        'quoted_refs' => $refs[$name]['quoted'] ?? 0,
        'pk' => implode('+', $t['pk']) ?: '(none)',
        'sii' => $ten[$name]['sii'] ?? '',
        'syear' => $ten[$name]['syear'] ?? '',
    ];

    if (isset($D[$name])) {
        [$mod, $tier, $dec, $target, $phase, $note] = array_pad(explode('|', $D[$name], 6), 6, '');
        $rec += compact('mod', 'tier', 'dec', 'target', 'phase', 'note');
    } elseif (isset($ONET_DIM[$name])) {
        $rec += ['mod' => 'ONET', 'tier' => 'C', 'dec' => 'NODE', 'target' => $ONET_DIM[$name],
                 'phase' => '13', 'note' => 'O*NET dimension — load once, no live sync'];
    } elseif (isset($ONET_RATING[$name])) {
        $big = $t['exact_rows'] > 50000;
        $rec += ['mod' => 'ONET', 'tier' => 'C',
                 'dec' => $big ? 'AGG_EDGE' : 'EDGE',
                 'target' => '(:Occupation)-[:REQUIRES]->(' . $ONET_RATING[$name] . ')',
                 'phase' => '13',
                 'note' => $big ? 'Rating ledger — project top-20 per occupation as weighted edges, log dropped rows'
                                : 'Rating table — project as weighted edges'];
    } elseif (str_starts_with($name, 'o_net_occupation_detail') || $name === 'o_net_occupation_details' || $name === 'o_net_data_tables') {
        $rec += ['mod' => 'ONET', 'tier' => 'D', 'dec' => 'EXCLUDE', 'target' => '—', 'phase' => '—',
                 'note' => 'Denormalised summary table, ' . ($refs[$name]['quoted'] ?? 0) . ' refs — duplicates the dimension+rating tables. Report and ask'];
    } elseif (str_starts_with($name, 'pal_')) {
        $rec += ['mod' => 'PAL', 'tier' => $t['exact_rows'] > 0 ? 'B' : 'D',
                 'dec' => $t['exact_rows'] > 0 ? 'REVIEW' : 'EXCLUDE', 'target' => '—', 'phase' => '14',
                 'note' => $t['exact_rows'] > 0
                     ? 'PAL stub, ' . $t['exact_rows'] . ' row(s) — schema exists, feature not in production'
                     : 'PAL stub, empty — schema exists, feature not in production'];
    } elseif (str_starts_with($name, 'wk_')) {
        $rec += ['mod' => 'Workflow', 'tier' => 'D', 'dec' => 'EXCLUDE', 'target' => '—', 'phase' => '—',
                 'note' => 'Workflow-engine table, empty (wk_main has 76 code refs but 0 rows)'];
    } else {
        $rec += ['mod' => 'UNASSIGNED', 'tier' => '?', 'dec' => 'REVIEW', 'target' => '—', 'phase' => '?',
                 'note' => 'Not covered by an explicit decision or rule — needs a human call'];
        $uncovered[] = $name;
    }
    $out[] = $rec;
}

file_put_contents("$dir/classification.json", json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

$byDec = $byTier = [];
foreach ($out as $r) { $byDec[$r['dec']] = ($byDec[$r['dec']] ?? 0) + 1; $byTier[$r['tier']] = ($byTier[$r['tier']] ?? 0) + 1; }
echo "TOTAL: " . count($out) . "\n\nBY DECISION:\n";
arsort($byDec); foreach ($byDec as $k => $v) echo "  $k = $v\n";
echo "\nBY TIER:\n"; ksort($byTier); foreach ($byTier as $k => $v) echo "  $k = $v\n";
echo "\nUNCOVERED (" . count($uncovered) . "): " . implode(', ', $uncovered) . "\n";
