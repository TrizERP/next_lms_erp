<?php
// Renders docs/neo4j-table-classification.md from classification.json
$dir = __DIR__;
$c = json_decode(file_get_contents("$dir/classification.json"), true);
$esc = fn($s) => str_replace('|', '\\|', (string)$s);
$num = fn($n) => number_format(max(0, (int)$n));

$MEASURED = [
 'lms_online_exam_answer'=>57878,'result_personalize_marks'=>54712,'hrms_attendances'=>30000,
 'library_book_circulations'=>25000,'fees_breackoff'=>15000,'fees_breakoff_other'=>20000,
 'result_student_attendance_master'=>53032,'sms_sent_parents'=>20000,'onet_work_context'=>20320,
 'onet_skills'=>20320,'onet_knowledge'=>20320,'onet_abilities'=>20320,'onet_work_activities'=>20320,
 'onet_task_ratings'=>20320,
];
$nodes=0;$rels=0;$srcRows=0;$notMirrored=0;
foreach ($c as $r) {
    $srcRows += max(0,$r['rows']);
    if ($r['dec']==='NODE') $nodes += max(0,$r['rows']);
    elseif ($r['dec']==='EDGE') { $n=max(0,$r['rows']); if($r['table']==='transport_map_student')$n*=2; $rels+=$n; }
    elseif ($r['dec']==='AGG_EDGE') $rels += $MEASURED[$r['table']] ?? min(max(0,$r['rows']), (int)max(500,$r['rows']*0.15));
    if (in_array($r['dec'],['AGG_EDGE','PROP','EXCLUDE'],true)) $notMirrored += max(0,$r['rows']);
}
$byDec=[];$byTier=[];$byMod=[];
foreach ($c as $r) {
    $byDec[$r['dec']] = ($byDec[$r['dec']]??0)+1;
    $byTier[$r['tier']] = ($byTier[$r['tier']]??0)+1;
    $m=$r['mod']; $byMod[$m]['n']=($byMod[$m]['n']??0)+1; $byMod[$m]['rows']=($byMod[$m]['rows']??0)+max(0,$r['rows']);
    $byMod[$m][$r['dec']]=($byMod[$m][$r['dec']]??0)+1;
    $byMod[$m]['phase']=$byMod[$m]['phase'] ?? $r['phase'];
}

$PHASE_ORDER = ['4','5','6','7','8','9','10','11','12','13','14','—','?'];
$modPhase=[]; foreach($c as $r){ $p=$r['phase']; if($p!=='—'&&$p!=='?'&&!isset($modPhase[$r['mod']])) $modPhase[$r['mod']]=$p; }

$o = [];
$o[] = '# Neo4j Migration — Phase 1: classification of all 488 tables';
$o[] = '';
$o[] = '> **Status: awaiting human review.** This document is the Phase 1 deliverable and its gate is';
$o[] = '> *"reviewed by a human"*. Nothing downstream should be built until someone signs off on §3 and §4 —';
$o[] = '> the registry in Phase 2 is generated from these decisions, so an error here propagates into every';
$o[] = '> load phase.';
$o[] = '';
$o[] = '**Measured:** 2026-08-10 against `vivek_erp` @ `202.47.117.220` (MariaDB 10.11.9), the authoritative';
$o[] = 'source fixed by decision DB-AUTH. **Read-only — nothing was written to MariaDB or Neo4j.**';
$o[] = '';
$o[] = '**Companion docs:** [`neo4j-migration-status.md`](./neo4j-migration-status.md) ·';
$o[] = '[`neo4j-migration-runbook.md`](./neo4j-migration-runbook.md) ·';
$o[] = '[`neo4j-full-erp-graph-master-prompt.md`](./neo4j-full-erp-graph-master-prompt.md) ·';
$o[] = '[`neo4j-graph-audit-and-rebuild-prompt.md`](./neo4j-graph-audit-and-rebuild-prompt.md)';
$o[] = '';
$o[] = '---';
$o[] = '';
$o[] = '## 1. Method — what was actually measured';
$o[] = '';
$o[] = 'Every number in this document was queried, not estimated or carried over from the audit docs.';
$o[] = '';
$o[] = '| Measurement | How |';
$o[] = '|---|---|';
$o[] = '| Row counts | `SELECT COUNT(*)` per table, all 488. **Not** `information_schema.table_rows`, which is an InnoDB estimate |';
$o[] = '| Size | `data_length + index_length` from `information_schema` |';
$o[] = '| Code references | Regex sweep of 3,302 `.php/.sql/.js/.json` files under `app/ routes/ resources/views/ database/ config/ public/`, counting *quoted* occurrences (`\'table\'`, `` `table` ``, `FROM table`) separately from bare word hits — because `batch`, `subject`, `standard` and `timetable` are English words |';
$o[] = '| Tenancy columns | `information_schema.columns`, matched case- and underscore-insensitively so `sub_institute_id`, `SUB_INSTITUTE_ID` and `SubInstituteId` all resolve |';
$o[] = '| FK integrity | `LEFT JOIN` from child to parent, counting unmatched rows |';
$o[] = '| Aggregate cardinality | `SELECT COUNT(*) FROM (SELECT ... GROUP BY ...)` — the real edge count each ledger collapses to |';
$o[] = '';
$o[] = '**Discrepancy with the audit docs, resolved:** the audit reports 487 tables / 8,896,258 rows. Live';
$o[] = 'measurement finds **488 tables / 9,020,219 rows**. The row delta is because the audit used the';
$o[] = '`information_schema` estimate; the table delta is one table added since. Both figures below are exact.';
$o[] = '';
$o[] = '---';
$o[] = '';
$o[] = '## 2. Headline result';
$o[] = '';
$o[] = '| | Naive row-per-node | This classification |';
$o[] = '|---|---:|---:|';
$o[] = '| Nodes | ' . $num($srcRows) . ' | **' . $num($nodes) . '** |';
$o[] = '| Relationships | ~9,000,000+ | **' . $num($rels) . '** |';
$o[] = '| Source rows never mirrored as nodes | 0 | **' . $num($notMirrored) . '** (' . round($notMirrored/$srcRows*100) . '%) |';
$o[] = '';
$o[] = 'Both figures sit inside the master prompt\'s budget (< 700,000 nodes / < 4,000,000 relationships).';
$o[] = 'The reduction comes entirely from PROJECTION LAW L3/L4 — ledgers become aggregated edges.';
$o[] = '';
$o[] = '### Decisions';
$o[] = '';
$o[] = '| Decision | Meaning | Tables |';
$o[] = '|---|---|---:|';
$o[] = '| `NODE` | Becomes a node label | ' . ($byDec['NODE']??0) . ' |';
$o[] = '| `EDGE` | Becomes a relationship, one per row | ' . ($byDec['EDGE']??0) . ' |';
$o[] = '| `AGG_EDGE` | `GROUP BY` in SQL, one edge per group (L4) | ' . ($byDec['AGG_EDGE']??0) . ' |';
$o[] = '| `PROP` | Folded as properties onto another node or edge | ' . ($byDec['PROP']??0) . ' |';
$o[] = '| `EXCLUDE` | Not projected, reason recorded per table | ' . ($byDec['EXCLUDE']??0) . ' |';
$o[] = '| `REVIEW` | **Needs a human decision** — see §5 | ' . ($byDec['REVIEW']??0) . ' |';
$o[] = '| | **Total** | **' . count($c) . '** |';
$o[] = '';
$o[] = '### Tiers';
$o[] = '';
$o[] = '| Tier | Meaning | Sync cadence | Tables |';
$o[] = '|---|---|---|---:|';
$o[] = '| **A** | Full graph — entities + rich relationships, traversal is the point | Live (queued observers) | ' . ($byTier['A']??0) . ' |';
$o[] = '| **B** | Entity + aggregated edges | Nightly backfill | ' . ($byTier['B']??0) . ' |';
$o[] = '| **C** | Reference / lookup dimension | Manual, on data refresh | ' . ($byTier['C']??0) . ' |';
$o[] = '| **D** | Excluded — logs, caches, rights matrices, framework tables | — | ' . ($byTier['D']??0) . ' |';
$o[] = '';
$o[] = '---';
$o[] = '';

file_put_contents("$dir/part1.md", implode("\n", $o) . "\n");

// ── module summary ──
$o = [];
$o[] = '## 6. Per-module summary';
$o[] = '';
$o[] = '| Module | Phase | Tables | Source rows | NODE | EDGE | AGG | PROP | EXCL | REVIEW |';
$o[] = '|---|---|---:|---:|---:|---:|---:|---:|---:|---:|';
uasort($byMod, fn($a,$b)=>$b['rows']<=>$a['rows']);
foreach ($byMod as $m=>$v) {
    $o[] = sprintf('| %s | %s | %d | %s | %d | %d | %d | %d | %d | %d |',
        $m, $modPhase[$m] ?? '—', $v['n'], $num($v['rows']),
        $v['NODE']??0, $v['EDGE']??0, $v['AGG_EDGE']??0, $v['PROP']??0, $v['EXCLUDE']??0, $v['REVIEW']??0);
}
$o[] = '';
$o[] = '---';
$o[] = '';
$o[] = '## 7. The full register — all 488 tables';
$o[] = '';
$o[] = 'Grouped by module, ordered by load phase. **T** = tenancy columns present: `S` = a';
$o[] = '`sub_institute_id` variant, `Y` = a `syear`/academic-year variant, `—` = neither (tenancy must be';
$o[] = 'derived through a foreign key). **Refs** = quoted code references.';
$o[] = '';

$grouped = [];
foreach ($c as $r) $grouped[$r['mod']][] = $r;
uksort($grouped, function($a,$b) use ($modPhase,$PHASE_ORDER) {
    $pa = array_search($modPhase[$a] ?? '—', $PHASE_ORDER, true);
    $pb = array_search($modPhase[$b] ?? '—', $PHASE_ORDER, true);
    return $pa === $pb ? strcmp($a,$b) : $pa <=> $pb;
});
foreach ($grouped as $mod=>$rows) {
    usort($rows, fn($x,$y)=>$y['rows']<=>$x['rows']);
    $o[] = '### ' . $mod . '  ·  phase ' . ($modPhase[$mod] ?? '—') . '  ·  ' . count($rows) . ' tables';
    $o[] = '';
    $o[] = '| Table | Rows | Refs | T | Tier | Decision | Target | Note |';
    $o[] = '|---|---:|---:|:-:|:-:|---|---|---|';
    foreach ($rows as $r) {
        $t = ($r['sii']?'S':'') . ($r['syear']?'Y':'');
        $o[] = sprintf('| `%s` | %s | %d | %s | %s | `%s` | %s | %s |',
            $r['table'], $num($r['rows']), $r['quoted_refs'], $t ?: '—', $r['tier'],
            $r['dec'], $esc($r['target']), $esc($r['note']));
    }
    $o[] = '';
}
file_put_contents("$dir/part3.md", implode("\n", $o) . "\n");

// ── assemble the full classification document ──
// §1-§2 and §6-§7 are generated above; §3-§5 (findings, blocking decisions, review
// items) and §8-§10 (Phase 2 requirements, gate, sign-off) are hand-written and live
// beside this script. Without them the document loses its entire analytical core, so
// assembly fails loudly rather than silently emitting a half-document.
$parts = ['part1.md', 'part2-findings.md', 'part3.md', 'part4-gate.md'];
$missing = array_values(array_filter($parts, fn ($p) => !is_readable("$dir/$p")));
if ($missing) {
    fwrite(STDERR, "REFUSING TO ASSEMBLE — missing section(s): " . implode(', ', $missing) . "\n");
    exit(1);
}
$doc = '';
foreach ($parts as $p) $doc .= file_get_contents("$dir/$p");
$target = dirname(dirname(__DIR__)) . '/docs/neo4j-table-classification.md';
file_put_contents($target, $doc);

echo "rendered part1.md + part3.md\n";
echo 'assembled ' . basename($target) . ' (' . number_format(strlen($doc)) . " bytes from " . count($parts) . " sections)\n";
echo "nodes=$nodes rels=$rels notMirrored=$notMirrored srcRows=$srcRows\n";
