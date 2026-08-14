<?php
// PHASE 2 — generate config/neo4j_graph.php from the approved Phase 1 classification.
$dir = __DIR__;
$cls = json_decode(file_get_contents("$dir/classification.json"), true);
$inv = json_decode(file_get_contents("$dir/inventory.json"), true);
$cols = [];
foreach ($inv as $t) $cols[$t['table']] = array_keys($t['columns']);

// ── tenancy: derivation paths for projected tables with no tenant column ──
// mode: column | derive | global | self
$TENANT = [
    'school_setup'  => ['mode' => 'self', 'column' => 'Id'],
    // assessment chain — question_paper is the anchor (carries sub_institute_id AND syear)
    'lms_online_exam'                 => ['mode' => 'derive', 'fk' => 'question_paper_id', 'table' => 'question_paper', 'key' => 'id'],
    'lms_online_exam_answer'          => ['mode' => 'derive', 'fk' => 'question_paper_id', 'table' => 'question_paper', 'key' => 'id'],
    'lms_online_exam_answer_student'  => ['mode' => 'derive', 'fk' => 'question_paper_id', 'table' => 'question_paper', 'key' => 'id'],
    'lms_online_exam_student'         => ['mode' => 'derive', 'fk' => 'question_paper_id', 'table' => 'question_paper', 'key' => 'id'],
    'lms_offline_exam_answer'         => ['mode' => 'derive', 'fk' => 'question_paper_id', 'table' => 'question_paper', 'key' => 'id'],
    'lms_question_mapping'            => ['mode' => 'derive', 'fk' => 'questionmaster_id', 'table' => 'lms_question_master', 'key' => 'id'],
    'counselling_online_exam_answer'  => ['mode' => 'derive', 'fk' => 'online_exam_id', 'table' => 'counselling_online_exam', 'key' => 'id'],
    'counselling_question_mapping'    => ['mode' => 'derive', 'fk' => 'questionmaster_id', 'table' => 'counselling_question_master', 'key' => 'id'],
    // curriculum
    'lms_units'                => ['mode' => 'derive', 'fk' => 'curriculum_id', 'table' => 'lms_curriculum', 'key' => 'id'],
    'content_mapping_type'     => ['mode' => 'derive', 'fk' => 'content_id', 'table' => 'content_master', 'key' => 'id'],
    // NB: the periods table hangs off lms_intelligence_lesson_plans (4 rows), NOT lms_lesson_plan (1,803)
    'lms_lesson_plan_periods'  => ['mode' => 'derive', 'fk' => 'lms_intelligence_lesson_plans_id', 'table' => 'lms_intelligence_lesson_plans', 'key' => 'id'],
    'lms_lesson_plan_concepts' => ['mode' => 'derive', 'fk' => 'lms_lesson_plan_periods_id', 'table' => 'lms_lesson_plan_periods', 'key' => 'id'],
    // LO-TENANCY (decided 2026-08-11): was derived via chapter_id -> chapter_master, but
    // MariaDB kept only 99 of those chapters, so the INNER JOIN dropped all 259 rows and
    // :LearningObjective loaded 0 nodes. subject_id resolves 252 of 259 (97%). The
    // chapter_id link survives as the HAS_LEARNING_OUTCOME hierarchy edge (soft, below).
    'lms_learning_outcomes'    => ['mode' => 'derive', 'fk' => 'subject_id', 'table' => 'subject', 'key' => 'id'],
    's_skill_matrix'           => ['mode' => 'derive', 'fk' => 'user_id', 'table' => 'tbluser', 'key' => 'id'],

    // CATEGORY-SCOPE (decided 2026-08-11): the table HAS a sub_institute_id column, so it
    // defaulted to tenant-scoped — but all 21 rows carry 0, and school_setup has no id 0.
    // ORPHAN-TENANTS then dropped every row and :ContentCategory loaded nothing. Tenant 0
    // is this codebase's marker for shared reference data, not a real institute.
    'lms_content_category'     => ['mode' => 'global'],

    // SCHOOL-SECTION-TENANCY (found 2026-08-11, Phase 4 correction applied during Phase 5).
    // The table has a sub_institute_id column but every row leaves it NULL; the tenant is in
    // `school_id` (all 10 rows = 1, resolving 10/10 against school_setup). Declared on the
    // NULL column, ORPHAN-TENANTS dropped all 10 and :SchoolSection — a Tier A label — sat
    // empty through a Foundation gate reported as 10/10 PASS. G11 now catches this class.
    'school_sections'          => ['mode' => 'column', 'column' => 'school_id'],
];
// everything else with no tenant column is global reference data
$GLOBAL_DEFAULT = ['mode' => 'global'];

// ── endpoint FK inference: label => candidate column names, most specific first ──
$FK = [
    'Institute' => ['sub_institute_id'], 'AcademicYear' => ['syear', 'academic_year_id'],
    'Standard' => ['standard_id'], 'Subject' => ['subject_id'], 'Division' => ['division_id', 'section_id'],
    'Batch' => ['batch_id'], 'Student' => ['student_id'], 'Staff' => ['user_id', 'teacher_id', 'created_by'],
    'Guardian' => ['student_id'], 'Department' => ['department_id'], 'Role' => ['user_profile_id', 'profile_id'],
    'Chapter' => ['chapter_id'], 'Topic' => ['topic_id'], 'Concept' => ['concept_id'],
    'Content' => ['content_id'], 'Question' => ['questionmaster_id', 'question_id'], 'Lesson' => ['lms_lesson_plan_id', 'lesson_id'],
    'Curriculum' => ['curriculum_id'], 'Unit' => ['unit_id'], 'MappingType' => ['mapping_type_id'],
    'Assessment' => ['question_paper_id', 'paper_id'], 'Result' => ['online_exam_id'], 'Exam' => ['exam_id'],
    'ExamType' => ['exam_id', 'exam_type_id'], 'Grade' => ['grade_id'], 'LeaveType' => ['leave_type_id'],
    'FeeHead' => ['fees_head_id', 'fee_type_id', 'head_id'], 'FeeTitle' => ['title_id', 'fees_title_id'],
    'Book' => ['book_id'], 'BookCopy' => ['item_code', 'item_id'], 'Route' => ['route_id'], 'Stop' => ['from_stop', 'stop_id'],
    'Vehicle' => ['bus_id', 'vehicle_id'], 'HostelRoom' => ['room_id'], 'InventoryItem' => ['item_id'],
    'Vendor' => ['vendor_id'], 'Skill' => ['skill_id', 'skill'], 'JobRole' => ['jobrole_id', 'jobrole'],
    'Task' => ['task_id', 'task'], 'Occupation' => ['onetsoc_code', 'occupation_id'],
    'LearningOutcome' => ['learning_outcome_id', 'indicator_id'], 'DisciplineCategory' => ['dicipline', 'dicipline_id'],
    'Misconception' => ['misconception_id'], 'LeaderboardRule' => ['lb_master_id'], 'PayrollType' => ['payroll_type_id'],
    'CoScholasticArea' => ['co_scholastic_id'], 'GradeScheme' => ['grade_master_id'], 'Holiday' => ['holiday_id'],
    'DocumentType' => ['document_type_id'], 'Quota' => ['student_quota', 'quota'], 'House' => ['house_id'],
];

function inferKey(array $tableCols, string $label, array $FK): ?string {
    $lower = array_change_key_case(array_flip($tableCols));
    foreach ($FK[$label] ?? [] as $cand) if (isset($lower[strtolower($cand)])) return $cand;
    return null;
}
// parse "(:A)-[:REL]->(:B)" -> [A, REL, B]
function parseEdge(string $t): ?array {
    if (preg_match('/\(:(\w+)[^)]*\)\s*-\[:(\w+)[^\]]*\]->\s*\(:(\w+)/', $t, $m)) return [$m[1], $m[2], $m[3]];
    return null;
}
function parseLabel(string $t): ?string {
    if (preg_match('/^:(\w+)/', trim($t), $m)) return $m[1];
    return null;
}

$MODULE_KEY = [
 'Foundation'=>'foundation','Curriculum'=>'curriculum','Assessment'=>'assessment','Student'=>'people',
 'Result'=>'result','Staff'=>'hr','Payroll'=>'hr','Leave'=>'hr','Timetable'=>'hr','Fees'=>'finance',
 'Library'=>'operations','Transport'=>'operations','Hostel'=>'operations','Inventory'=>'operations',
 'Visitor'=>'operations','Operations'=>'operations','FrontDesk'=>'operations','Skill'=>'skills',
 'ONET'=>'skills','SQAA'=>'skills','LearningOutcome'=>'curriculum','Admission'=>'people',
 'Counselling'=>'assessment','PAL'=>'platform','Platform'=>'platform','Communication'=>'platform',
 'Calendar'=>'platform','PTM'=>'platform','Leaderboard'=>'platform','Report'=>'platform',
 'Workflow'=>'platform','NotK12'=>'platform',
];
$NON_AUTH_MODULES = ['Fees' => true, 'Payroll' => true];

$out = [];
$stats = ['node'=>0,'edge'=>0,'agg'=>0,'prop'=>0,'exclude'=>0,'review'=>0,'needs_endpoint'=>0,'global'=>0,'derive'=>0];

foreach ($cls as $r) {
    $t = $r['table'];
    $e = [
        'module'   => $MODULE_KEY[$r['mod']] ?? 'platform',
        'domain'   => $r['mod'],
        'phase'    => $r['phase'] === '—' || $r['phase'] === '?' ? null : (int)$r['phase'],
        'tier'     => $r['tier'],
        'decision' => $r['dec'],
        'rows_at_classification' => $r['rows'],
    ];
    if (in_array($r['dec'], ['EXCLUDE','REVIEW'], true)) {
        $e['reason'] = $r['note'] ?: 'see docs/neo4j-table-classification.md';
        $stats[$r['dec']==='EXCLUDE'?'exclude':'review']++;
        $out[$t] = $e; continue;
    }

    // pk — several O*NET tables have no declared PRIMARY KEY; use the natural key
    $NATURAL_PK = [
        'onet_career_cluster' => 'career_id', 'onet_occupation_data' => 'onetsoc_code',
        'onet_task_statements' => 'task_id', 'onet_job_zones' => 'onetsoc_code',
        'onet_skills' => 'onetsoc_code', 'onet_knowledge' => 'onetsoc_code',
        'onet_abilities' => 'onetsoc_code', 'onet_work_activities' => 'onetsoc_code',
        'onet_work_context' => 'onetsoc_code', 'onet_work_styles' => 'onetsoc_code',
        'onet_work_values' => 'onetsoc_code', 'onet_interests' => 'onetsoc_code',
        'onet_technology_skills' => 'onetsoc_code', 'onet_tools_used' => 'onetsoc_code',
        'onet_task_ratings' => 'task_id',
    ];
    $pkRow = null; foreach ($inv as $i) if ($i['table'] === $t) { $pkRow = $i; break; }
    $e['pk'] = $pkRow['pk'][0] ?? ($NATURAL_PK[$t] ?? null);
    if (!($pkRow['pk'] ?? []) && isset($NATURAL_PK[$t])) $e['pk_is_natural'] = true;

    // tenancy
    if (isset($TENANT[$t])) { $e['tenant'] = $TENANT[$t]; }
    elseif ($r['sii'] !== '') { $e['tenant'] = ['mode' => 'column', 'column' => $r['sii']]; }
    else { $e['tenant'] = $GLOBAL_DEFAULT; }
    if ($e['tenant']['mode'] === 'global') $stats['global']++;
    if ($e['tenant']['mode'] === 'derive') $stats['derive']++;

    // ── year-scoping (L1's <syear> segment) ─────────────────────────────────────
    // The uid format never changes; what changes is whether <syear> carries a value.
    // It must be 0 for anything whose identity is its primary key rather than a
    // per-year fact, because a year-scoped uid breaks two things:
    //   1. re-loading the same entity in a later year mints a SECOND node;
    //   2. a child in syear X can never resolve a parent stored under syear Y.
    // Both were live: chapter_master carries syear 2026 while the rescue export carries
    // an empty syear, so the 13 overlapping chapter ids would have produced two nodes
    // each, and topics from 2019 could not have reached them at all.
    $NOT_YEAR_SCOPED = [
        'Institute', 'Curriculum', 'Unit', 'Chapter', 'Topic', 'Concept', 'Content',
        'MappingType', 'Extraction', 'LearningObjective', 'LOCategory', 'LearningOutcome',
        'Misconception', 'ContentCategory', 'TextBook', 'Syllabus',
    ];
    $lblForYear = ($r['dec'] === 'NODE') ? (parseLabel($r['target']) ?? '') : '';
    if (($e['tenant']['mode'] ?? '') === 'self') $e['syear'] = ['mode' => 'constant', 'value' => 0];
    elseif ($lblForYear && in_array($lblForYear, $NOT_YEAR_SCOPED, true)) $e['syear'] = ['mode' => 'constant', 'value' => 0];
    elseif ($r['syear'] !== '') $e['syear'] = ['mode' => 'column', 'column' => $r['syear']];
    elseif (($e['tenant']['mode'] ?? '') === 'derive') $e['syear'] = ['mode' => 'derive'] + $e['tenant'];
    else $e['syear'] = ['mode' => 'constant', 'value' => 0];

    if (!empty($NON_AUTH_MODULES[$r['mod']])) $e['authoritative'] = false;

    if ($r['dec'] === 'NODE') {
        $lbl = parseLabel($r['target']) ?? 'UNRESOLVED';
        $e['label'] = $lbl;
        $e['uid'] = $lbl . ':{tenant}:{syear}:{pk}';
        $stats['node']++;
    } elseif (in_array($r['dec'], ['EDGE','AGG_EDGE'], true)) {
        $p = parseEdge($r['target']);
        if ($p) {
            [$from, $rel, $to] = $p;
            $fk = inferKey($cols[$t] ?? [], $from, $FK);
            $tk = inferKey($cols[$t] ?? [], $to, $FK);
            $e['rel'] = $rel;
            $e['from'] = ['label' => $from, 'key' => $fk];
            $e['to']   = ['label' => $to,   'key' => $tk];
            if (!$fk || !$tk) { $e['needs_endpoint_keys'] = true; $stats['needs_endpoint']++; }
        } else {
            $e['rel'] = 'UNRESOLVED'; $e['needs_endpoint_keys'] = true; $stats['needs_endpoint']++;
        }
        if ($r['dec'] === 'AGG_EDGE') { $e['aggregate'] = ['group_by' => null, 'note' => 'GROUP BY must be written in the export SQL (L4)']; $stats['agg']++; }
        else $stats['edge']++;
    } elseif ($r['dec'] === 'PROP') {
        $e['target'] = $r['target'];
        $stats['prop']++;
    }
    $e['note'] = $r['note'];
    $out[$t] = $e;
}

// ── emit PHP ──
$php = "<?php\n\n";
$php .= "/*\n";
$php .= "|--------------------------------------------------------------------------\n";
$php .= "| Neo4j graph registry — generated Phase 2, 2026-08-10\n";
$php .= "|--------------------------------------------------------------------------\n";
$php .= "|\n";
$php .= "| One entry per table in `vivek_erp` (488). Generated from the approved Phase 1\n";
$php .= "| classification in docs/neo4j-table-classification.md — edit that document and\n";
$php .= "| regenerate rather than hand-editing decisions here.\n";
$php .= "|\n";
$php .= "| PROJECTION LAW (docs/neo4j-full-erp-graph-master-prompt.md):\n";
$php .= "|   L1 uid = \"<Label>:<sub_institute_id>:<syear>:<pk>\" — the ONLY MERGE key\n";
$php .= "|   L2 every node carries a tenant; no edge crosses tenants\n";
$php .= "|   L3 ledgers are edges, never nodes\n";
$php .= "|   L4 aggregate in SQL before projecting\n";
$php .= "|   L7 MariaDB is the source of truth\n";
$php .= "|\n";
$php .= "| tenant.mode:  column  read sub_institute_id from this column\n";
$php .= "|               derive  join to a parent table that has it\n";
$php .= "|               self    the table's PK *is* the institute (school_setup)\n";
$php .= "|               global  reference data shared by all tenants -> sub_institute_id 0\n";
$php .= "|                       + scope='global'. This is the ONE documented exception to L2;\n";
$php .= "|                       neo4j:verify exempts declared-global labels and nothing else.\n";
$php .= "|\n";
$php .= "| Approved decisions carried in here (2026-08-10):\n";
$php .= "|   CHAPTER-SOURCE seed :Chapter from docs/neo4j-backup-2026-08-10/nodes_Chapter.csv\n";
$php .= "|   CONCEPT-LINK   MASTERS aggregates to :Chapter, not :Concept\n";
$php .= "|   FEES-MODEL     LIABLE_FOR from fees_breakoff_other only\n";
$php .= "|   JOBROLE-KEY    resolve name strings to ids in SQL at export; drop + count misses\n";
$php .= "|\n";
$php .= "*/\n\n";
$php .= "return [\n\n";
$php .= "    'meta' => [\n";
$php .= "        'generated'      => '2026-08-10',\n";
$php .= "        'source_db'      => 'vivek_erp',\n";
$php .= "        'classification' => 'docs/neo4j-table-classification.md',\n";
$php .= "        'table_count'    => " . count($out) . ",\n";
$php .= "        'neo4j_version'  => '4.4.40 Community',\n";
$php .= "    ],\n\n";
$php .= "    /*\n";
$php .= "     | The rescue export is the ONLY surviving record of 5,521 :Chapter nodes and the\n";
$php .= "     | 86,265 question->chapter edges that Phase 3 deleted; MariaDB has 99 chapters.\n";
$php .= "     | It lives OUTSIDE the repo because the in-repo copy was silently deleted four\n";
$php .= "     | times on 2026-08-10 while every other untracked file survived. Point\n";
$php .= "     | NEO4J_RESCUE_DIR at wherever you keep it.\n";
$php .= "     */\n";
$php .= "    'rescue' => [\n";
$php .= "        'dir'   => env('NEO4J_RESCUE_DIR', base_path('docs/neo4j-backup-2026-08-10')),\n";
$php .= "        'files' => [\n";
$php .= "            'Chapter' => [\n";
$php .= "                'csv'    => 'nodes_Chapter.csv',\n";
$php .= "                'id_col' => 'chId',\n";
$php .= "                'source' => 'graph-rescue-2026-08-10',\n";
$php .= "                'lines'  => 5537,\n";
$php .= "                'md5'    => '3f81662cf695b1e8',\n";
$php .= "                'note'   => '5,521 chapters absent from MariaDB; repairs 70.9% of the F1 break',\n";
$php .= "            ],\n";
$php .= "            'BELONGS_TO' => [\n";
$php .= "                'csv'   => 'rels_BELONGS_TO.csv',\n";
$php .= "                'lines' => 86266,\n";
$php .= "                'md5'   => '7866123c886cd9d9',\n";
$php .= "                'note'  => 'question->chapter mapping; MariaDB lost 95.3% of these refs',\n";
$php .= "            ],\n";
$php .= "        ],\n";
$php .= "    ],\n\n";
$php .= "    /*\n";
$php .= "     | Hierarchy edges derived from FK COLUMNS ON ENTITY TABLES, not from junction\n";
$php .= "     | tables. The Phase 1 classification only modelled junction tables as EDGE\n";
$php .= "     | sources, which left every Foundation dimension orphaned — Phase 4's verify\n";
$php .= "     | gate (G8) caught it. `fk` is a column on `table`; the parent uid is built from\n";
$php .= "     | that value. A row whose parent does not exist is skipped and counted, never\n";
$php .= "     | MERGEd into existence (that is how defect D9 happened).\n";
$php .= "     */\n";
$php .= "    'hierarchy' => [\n";
foreach ([
    ['academic_section',      'sub_institute_id', 'Institute', 'HAS_SECTION',        'AcademicSection'],
    ['academic_year',         'sub_institute_id', 'Institute', 'HAS_ACADEMIC_YEAR',  'AcademicYear'],
    ['standard',              'sub_institute_id', 'Institute', 'HAS_STANDARD',       'Standard'],
    ['division',              'sub_institute_id', 'Institute', 'HAS_DIVISION_POOL',  'Division'],
    ['subject',               'sub_institute_id', 'Institute', 'OFFERS_SUBJECT',     'Subject'],
    // SCHOOL-SECTION-TENANCY: keyed on school_id for the same reason the tenancy is —
    // sub_institute_id is NULL on all 10 rows, so this edge was skipped as "null fk" and
    // :SchoolSection floated even once the nodes loaded.
    ['school_sections',       'school_id',        'Institute', 'HAS_SCHOOL_SECTION', 'SchoolSection'],
    ['hrms_departments',      'sub_institute_id', 'Institute', 'HAS_DEPARTMENT',     'Department'],
    ['tbluserprofilemaster',  'sub_institute_id', 'Institute', 'HAS_ROLE',           'Role'],
    ['batch',                 'division_id',      'Division',  'HAS_BATCH',          'Batch'],
    // ── curriculum (phase 5) ──
    // Chapter-parented edges are the payoff for CHAPTER-SOURCE: 95-99% of these FKs do
    // not resolve against chapter_master's 99 rows, but the graph also holds the 5,532
    // rescued chapters, so most should now find a parent. They are validated in the
    // LOADER against the graph, never in SQL against MariaDB — see ExportCommand.
    ['lms_units',             'curriculum_id',    'Curriculum',  'HAS_UNIT',            'Unit'],
    ['chapter_master',        'unit_id',          'Unit',        'HAS_CHAPTER',         'Chapter'],
    ['chapter_master',        'subject_id',       'Subject',     'COVERS_CHAPTER',      'Chapter'],
    ['topic_master',          'chapter_id',       'Chapter',     'HAS_TOPIC',           'Topic'],
    ['lms_concept',           'chapter_id',       'Chapter',     'HAS_CONCEPT',         'Concept'],
    ['content_master',        'chapter_id',       'Chapter',     'HAS_CONTENT',         'Content'],
    ['lms_lesson_plan',       'chapter_id',       'Chapter',     'HAS_LESSON',          'Lesson'],
    ['lms_learning_outcomes', 'chapter_id',       'Chapter',     'HAS_LEARNING_OUTCOME','LearningObjective'],
    // self-referencing taxonomy tree (Blooms, Depth of Knowledge); global scope
    ['lms_mapping_type',      'parent_id',        'MappingType', 'PARENT_OF',           'MappingType'],
    // ORPHAN-LABELS (approved 2026-08-11). These five labels were projected as Tier A
    // NODEs with no parent path declared anywhere, so every node floated: 108 + 23 + 12
    // + 2 + 1 = 146 nodes that G8 counted as orphans. Each table does carry a resolvable
    // FK — the registry simply never said so.
    ['document_extractions',  'subject_id',       'Subject',     'HAS_EXTRACTION',      'Extraction'],
    ['lo_master',             'subject_id',       'Subject',     'HAS_LO_CATEGORY',     'LOCategory'],
    ['lo_category',           'subject_id',       'Subject',     'HAS_LO_CATEGORY_ALT', 'LOCategoryAlt'],
    ['lo_indicator',          'lomaster_id',      'LOCategory',  'HAS_INDICATOR',       'LearningOutcome'],
    ['pal_misconceptions',    'concept_id',       'Concept',     'HAS_MISCONCEPTION',   'Misconception'],
    // MAPPINGTYPE-SCOPE (approved 2026-08-12). The master prompt requires
    // (:MappingType)-[:SCOPED_TO]->(:Chapter|:Topic); it was never declared, which is why
    // 30 root :MappingType had no edge at all. These two are BY_ID, not by uid:
    // lms_mapping_type is tenant-GLOBAL while Chapter/Topic are tenant-scoped, so the
    // parent's tenant cannot be taken from the child row the way every other entry does.
    // Safe because the uid tail is unique per label (verified: 0 collisions on 5,625
    // :Chapter and 13,561 :Topic) — resolving on it cannot fan out the way D10 did.
    ['lms_mapping_type',      'chapter_id',       'Chapter',     'SCOPED_TO',           'MappingType', true],
    ['lms_mapping_type',      'topic_id',         'Topic',       'SCOPED_TO',           'MappingType', true],
] as $h) {
    [$tbl, $fk, $parent, $rel, $child] = $h;
    $byId = $h[5] ?? false;
    // ORPHAN-CHAPTERS (approved 2026-08-10): a chapter-parented FK may legitimately fail
    // to resolve — ~29% of the F1 damage is unrecoverable, those chapter ids existing in
    // neither MariaDB nor the rescue export. Mark such paths soft: neo4j:verify reports
    // their orphans and dangling refs but does not hard-fail the gate on them.
    $soft = ($parent === 'Chapter');
    $php .= "        ['table' => " . str_pad(var_export($tbl, true), 24)
          . ", 'fk' => " . str_pad(var_export($fk, true), 20)
          . ", 'parent' => " . str_pad(var_export($parent, true), 12)
          . ", 'rel' => " . str_pad(var_export($rel, true), 22)
          . ", 'child' => " . str_pad(var_export($child, true), 20)
          . ", 'soft' => " . var_export($soft, true)
          . ($byId ? ", 'by_id' => true" : '') . "],\n";
}
$php .= "    ],\n\n";
$php .= "    'tables' => [\n";

$fmt = function ($v, $ind) use (&$fmt) {
    if (is_array($v)) {
        $parts = [];
        foreach ($v as $k => $vv) $parts[] = str_repeat(' ', $ind + 4) . var_export($k, true) . ' => ' . $fmt($vv, $ind + 4) . ',';
        return "[\n" . implode("\n", $parts) . "\n" . str_repeat(' ', $ind) . ']';
    }
    return var_export($v, true);
};
foreach ($out as $t => $e) {
    $php .= "\n        " . var_export($t, true) . " => " . $fmt($e, 8) . ",\n";
}
$php .= "    ],\n];\n";

file_put_contents(dirname(dirname(__DIR__)) . '/config/neo4j_graph.php', $php);
echo "wrote config/neo4j_graph.php (" . number_format(strlen($php)) . " bytes, " . count($out) . " tables)\n\n";
foreach ($stats as $k => $v) echo "  $k = $v\n";
