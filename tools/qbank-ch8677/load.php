<?php
/**
 * Question-bank loader for chapter 8677 "Integers" (sub_institute 341, std 7, Mathematics).
 *
 * Reads the authored batch files in ./batches/*.json, validates every item against
 * semantic_intelligence #92 (the same slice QuestionGenerationService grounds on),
 * then writes lms_question_master + lms_question_mapping + answer_master using the
 * exact conventions of QuestionGenerationService::persist().
 *
 * Usage:  php load.php --check        validate only, write nothing
 *         php load.php --insert       validate then insert inside one transaction
 */

const CHAPTER_ID   = 8677;
const SEMANTIC_ID  = 92;
const SUB_INST     = 341;
const STANDARD_ID  = 4261;
const SUBJECT_ID   = 5492;
const GRADE_ID     = 1088;
const SYEAR        = 2026;

const DOK_PARENT   = 9;
const BLOOM_PARENT = 82;

const ENVELOPE_V   = 'ans-2.0';

/** sub_type -> [question_type (mcq|narrative), question_type_master.id] */
const TYPE_MAP = [
    'MCQ'             => ['mcq',       1], // multiple
    'Assertion-Reason'=> ['mcq',       8], // assertion & reason
    'Case-Based MCQ'  => ['mcq',       7], // CBE
    'Very Short Answer'=> ['narrative', 2], // narrative
    'Short Answer'    => ['narrative', 2], // narrative
    'Long Answer'     => ['narrative', 4], // hot questions
    'Case Study'      => ['narrative', 7], // CBE
];

/** Short authoring keys -> canonical sub_type. */
const KIND = [
    'MCQ' => 'MCQ', 'AR' => 'Assertion-Reason', 'CBM' => 'Case-Based MCQ',
    'VSA' => 'Very Short Answer', 'SA' => 'Short Answer',
    'LA'  => 'Long Answer', 'CS' => 'Case Study',
];

/** bloom -> [dok, difficulty]. Overridable per item with "dk" / "df". */
const BLOOM_LADDER = [
    'Remember'   => [1, 'Easy'],
    'Understand' => [2, 'Easy'],
    'Apply'      => [2, 'Medium'],
    'Analyze'    => [3, 'Hard'],
    'Evaluate'   => [3, 'Hard'],
    'Create'     => [4, 'Hard'],
];

const DOK_LABEL = [1 => 'Easy', 2 => 'Medium', 3 => 'Hard', 4 => 'Hard'];
/** lms_mapping_type children under 82 use their own spellings. */
const BLOOM_LABEL = [
    'Remember' => 'Remember', 'Understand' => 'Understand', 'Apply' => 'Apply',
    'Analyze' => 'Analyse', 'Evaluate' => 'Evaluate', 'Create' => 'Creating',
];

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--insert'], true)) {
    fwrite(STDERR, "usage: php load.php --check|--insert\n");
    exit(2);
}

$pdo = new PDO(
    'mysql:host=202.47.117.220;dbname=vivek_erp;charset=utf8mb4',
    'vivek_user',
    'vivek@sql',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

/* ---------------------------------------------------------------- context */

$norm = fn($s) => preg_replace('/\s+/', ' ', mb_strtolower(trim((string) $s)));

$concepts = $pdo->query(
    "select c.id, c.name, c.topic_id from lms_concept c where c.chapter_id = " . CHAPTER_ID
)->fetchAll(PDO::FETCH_ASSOC);
$conceptById = [];
foreach ($concepts as $c) { $conceptById[(int) $c['id']] = $c; }

$intel = $pdo->query("select * from semantic_intelligence where id = " . SEMANTIC_ID)->fetch(PDO::FETCH_ASSOC);

/** Build the per-concept allow-lists the envelope refs must come from. */
$allow = [];
$collect = function (string $column, string $field, string $bucket) use (&$allow, $intel, $norm) {
    foreach (json_decode($intel[$column], true) ?: [] as $row) {
        $key = $norm($row['concept_name'] ?? '');
        $val = trim((string) ($row[$field] ?? ''));
        if ($key === '' || $val === '') { continue; }
        $allow[$key][$bucket][$val] = true;
    }
};
$collect('knowledge',         'knowledge',   'knowledge');
$collect('ability',           'ability',     'ability');
$collect('misconceptions',    'misconception','misconception');
$collect('learning_outcomes', 'outcome',     'outcome');
$collect('competency',        'competency',  'competency');

/* ------------------------------------------------------------- load files */

$files = glob(__DIR__ . '/batches/*.json');
sort($files);
if (!$files) { fwrite(STDERR, "no batch files in ./batches\n"); exit(2); }

$items = [];
foreach ($files as $f) {
    $raw = file_get_contents($f);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "ERROR {$f}: " . json_last_error_msg() . "\n");
        exit(1);
    }
    foreach ($decoded as $i => $item) {
        $item['_src'] = basename($f) . "#{$i}";
        $items[] = $item;
    }
}

/* -------------------------------------------------------------- validate */

$errors = [];
$seenHash = [];
$prepared = [];

foreach ($items as $item) {
    $src = $item['_src'];
    $err = function (string $m) use (&$errors, $src) { $errors[] = "{$src}: {$m}"; };

    $conceptId = (int) ($item['c'] ?? 0);
    if (!isset($conceptById[$conceptId])) { $err("unknown concept_id {$conceptId}"); continue; }
    $conceptName = $conceptById[$conceptId]['name'];
    $key = $norm($conceptName);
    $ok = $allow[$key] ?? [];

    $kind = (string) ($item['k'] ?? '');
    if (!isset(KIND[$kind])) { $err("unknown kind '{$kind}'"); continue; }
    $subType = KIND[$kind];
    [$qType, $questionTypeId] = TYPE_MAP[$subType];

    $bloom = (string) ($item['b'] ?? '');
    if (!isset(BLOOM_LADDER[$bloom])) { $err("unknown bloom '{$bloom}'"); continue; }
    [$dok, $difficulty] = BLOOM_LADDER[$bloom];
    $dok        = (int) ($item['dk'] ?? $dok);
    $difficulty = (string) ($item['df'] ?? $difficulty);
    if ($dok < 1 || $dok > 4) { $err("dok out of range"); }
    if (!in_array($difficulty, ['Easy', 'Medium', 'Hard'], true)) { $err("bad difficulty"); }

    $points = (int) ($item['p'] ?? 0);
    if ($points < 1 || $points > 10) { $err("points out of range ({$points})"); }

    $title = trim((string) ($item['q'] ?? ''));
    if ($title === '') { $err("empty question_title"); }
    $titleCap = $qType === 'mcq' ? 400 : 1200;
    if (mb_strlen($title) > $titleCap) { $err("question_title " . mb_strlen($title) . " > {$titleCap}"); }

    $desc = trim((string) ($item['d'] ?? ''));
    if ($desc === '' || mb_strlen($desc) > 240) { $err("description empty or > 240 (" . mb_strlen($desc) . ")"); }

    $sub = trim((string) ($item['s'] ?? ''));
    if ($sub === '' || mb_strlen($sub) > 240) { $err("subconcept empty or > 240"); }

    $hint = isset($item['h']) && $item['h'] !== null ? trim((string) $item['h']) : null;
    if ($hint !== null && mb_strlen($hint) > 200) { $err("hint > 200"); }

    // learning_outcome is json_encode'd into a varchar(191) column.
    $lo = trim((string) ($item['lo'] ?? ''));
    if ($lo === '') {
        $err("missing learning_outcome");
    } elseif (!isset($ok['outcome'][$lo])) {
        $err("learning_outcome not in semantic_intelligence for this concept: \"" . mb_substr($lo, 0, 60) . "...\"");
    }
    $loJson = json_encode([$lo], JSON_UNESCAPED_UNICODE);
    if (strlen($loJson) > 191) { $err("learning_outcome json " . strlen($loJson) . " > 191 bytes"); }

    // Referential integrity against the intelligence layer.
    foreach ((array) ($item['kr'] ?? []) as $k) {
        if (!isset($ok['knowledge'][$k])) { $err("knowledge_ref not in slice: \"{$k}\""); }
    }
    $ability = $item['ab'] ?? null;
    if ($ability !== null && !isset($ok['ability'][$ability])) { $err("ability_ref not in slice: \"{$ability}\""); }
    foreach ((array) ($item['mr'] ?? []) as $m) {
        if (!isset($ok['misconception'][$m])) { $err("misconception_ref not in slice: \"" . mb_substr($m, 0, 50) . "\""); }
    }
    $competency = $item['cp'] ?? null;
    if ($competency !== null && !isset($ok['competency'][$competency])) { $err("competency_ref not in slice: \"{$competency}\""); }

    $explanation = trim((string) ($item['e'] ?? ''));
    $remediation = trim((string) ($item['r'] ?? ''));
    if ($explanation === '') { $err("missing explanation"); }
    if ($remediation === '') { $err("missing remediation"); }

    $stimulus = isset($item['st']) && $item['st'] !== null ? trim((string) $item['st']) : null;
    if (in_array($subType, ['Case-Based MCQ', 'Case Study'], true) && ($stimulus === null || $stimulus === '')) {
        $err("{$subType} requires a stimulus");
    }

    $answer = [
        'v'                      => ENVELOPE_V,
        'question_type'          => $qType,
        'sub_type'               => $subType,
        'competency_ref'         => $competency,
        'bloom_level'            => $bloom,
        'dok_level'              => $dok,
        'difficulty'             => $difficulty,
        'stimulus'               => $stimulus,
        'estimated_time_seconds' => (int) ($item['ets'] ?? ($qType === 'mcq' ? 70 : 60 * $points)),
        'marks'                  => $points,
    ];

    if ($qType === 'mcq') {
        $options = $item['o'] ?? [];
        if (!is_array($options) || count($options) !== 4) {
            $err("mcq needs exactly 4 options, got " . (is_array($options) ? count($options) : 0));
            continue;
        }
        $built = [];
        $correctLabels = [];
        foreach ($options as $o) {
            // [label, text, is_correct, distractor_type, misconception_ref, knowledge_ref, rationale]
            [$label, $text, $isCorrect, $dType, $mRef, $kRef, $rationale] = array_pad((array) $o, 7, null);
            $label = (string) $label;
            $text = trim((string) $text);
            $rationale = trim((string) $rationale);
            if (!in_array($label, ['A', 'B', 'C', 'D'], true)) { $err("bad option label '{$label}'"); }
            if ($text === '') { $err("empty option text {$label}"); }
            if (mb_strlen($text) > 250) { $err("option {$label} text > 250 (answer_master limit)"); }
            if ($rationale === '') { $err("option {$label} missing rationale"); }
            if (mb_strlen($rationale) > 250) { $err("option {$label} rationale > 250 (answer_master.feedback limit)"); }
            if (!in_array($dType, ['correct', 'misconception', 'near_miss', 'overgeneral', 'plausible'], true)) {
                $err("option {$label} bad distractor_type '{$dType}'");
            }
            if ($mRef !== null && !isset($ok['misconception'][$mRef])) {
                $err("option {$label} misconception_ref not in slice: \"" . mb_substr((string) $mRef, 0, 50) . "\"");
            }
            if ($kRef !== null && !isset($ok['knowledge'][$kRef])) {
                $err("option {$label} knowledge_ref not in slice: \"{$kRef}\"");
            }
            if ($isCorrect) { $correctLabels[] = $label; }
            if ($isCorrect && $dType !== 'correct') { $err("option {$label} is_correct but distractor_type != correct"); }
            $built[] = [
                'label' => $label, 'text' => $text, 'is_correct' => (bool) $isCorrect,
                'distractor_type' => $dType, 'misconception_ref' => $mRef,
                'knowledge_ref' => $kRef, 'rationale' => $rationale,
            ];
        }
        if (count($correctLabels) !== 1) { $err("mcq must have exactly 1 correct option, got " . count($correctLabels)); }
        if (count(array_unique(array_column($built, 'label'))) !== 4) { $err("duplicate option labels"); }
        if (count(array_unique(array_map(fn($b) => mb_strtolower($b['text']), $built))) !== 4) { $err("duplicate option text"); }
        $correctOption = (string) ($item['ok'] ?? '');
        if ($correctLabels && $correctOption !== $correctLabels[0]) {
            $err("correct_option '{$correctOption}' does not match the option flagged correct '" . ($correctLabels[0] ?? '?') . "'");
        }
        $answer['options'] = $built;
        $answer['correct_option'] = $correctOption;
    } else {
        $modelAnswer = trim((string) ($item['ma'] ?? ''));
        if (mb_strlen($modelAnswer) < 20) { $err("model_answer shorter than 20 chars"); }
        $mp = $item['mp'] ?? [];
        if (!is_array($mp) || count($mp) !== $points) {
            $err("marking_points count " . (is_array($mp) ? count($mp) : 0) . " must equal points {$points}");
        }
        $marking = [];
        $markTotal = 0;
        foreach ((array) $mp as $row) {
            // [mark, criterion, accept[], reject[], knowledge_ref]
            [$mark, $criterion, $accept, $reject, $kRef] = array_pad((array) $row, 5, null);
            $criterion = trim((string) $criterion);
            if ($criterion === '') { $err("marking point missing criterion"); }
            if ((int) $mark < 1) { $err("marking point mark must be >= 1"); }
            if ($kRef !== null && !isset($ok['knowledge'][$kRef])) {
                $err("marking point knowledge_ref not in slice: \"{$kRef}\"");
            }
            $markTotal += (int) $mark;
            $marking[] = [
                'mark' => (int) $mark, 'criterion' => $criterion,
                'accept' => array_values((array) $accept), 'reject' => array_values((array) $reject),
                'knowledge_ref' => $kRef,
            ];
        }
        if ($markTotal !== $points) { $err("marking_points marks sum {$markTotal} != points {$points}"); }
        $keywords = [];
        foreach ((array) ($item['kw'] ?? []) as $row) {
            [$term, $weight, $syn] = array_pad((array) $row, 3, null);
            if (trim((string) $term) === '') { $err("keyword with empty term"); continue; }
            $keywords[] = ['term' => (string) $term, 'weight' => (float) $weight, 'synonyms' => array_values((array) $syn)];
        }
        if (!$keywords) { $err("narrative needs at least one keyword"); }
        $commonErrors = [];
        foreach ((array) ($item['ce'] ?? []) as $row) {
            [$mRef, $wrong, $ceil] = array_pad((array) $row, 3, null);
            if ($mRef !== null && !isset($ok['misconception'][$mRef])) {
                $err("common_error misconception_ref not in slice: \"" . mb_substr((string) $mRef, 0, 50) . "\"");
            }
            $commonErrors[] = [
                'misconception_ref' => $mRef,
                'erroneous_answer'  => (string) $wrong,
                'mark_ceiling'      => (int) $ceil,
            ];
        }
        if (!$commonErrors) { $err("narrative needs at least one common_error"); }
        $answer['sub_parts']            = $item['sp'] ?? null;
        $answer['model_answer']         = $modelAnswer;
        $answer['marking_points']       = $marking;
        $answer['keywords']             = $keywords;
        $answer['common_errors']        = $commonErrors;
        $answer['full_credit_threshold']= (int) ($item['fct'] ?? $points);
    }

    $answer['explanation']        = $explanation;
    $answer['remediation']        = $remediation;
    $answer['knowledge_refs']     = array_values((array) ($item['kr'] ?? []));
    $answer['ability_ref']        = $ability;
    $answer['misconception_refs'] = array_values((array) ($item['mr'] ?? []));

    // Same normalisation + hash as QuestionGenerationService::persist().
    $normTitle = preg_replace('/[^a-z0-9 ]/', '', strtolower(preg_replace('/\s+/', ' ', trim($title))));
    $hash = hash('sha256', $normTitle);
    $dupKey = $conceptId . '|' . $questionTypeId . '|' . $hash;
    if (isset($seenHash[$dupKey])) { $err("duplicate stem within batch (also {$seenHash[$dupKey]})"); }
    $seenHash[$dupKey] = $src;

    $answer['content_hash']         = $hash;
    $answer['semantic_concept_key'] = $conceptName;
    $answer['times_served']         = 0;
    $answer['times_correct']        = 0;
    $answer['p_value']              = null;
    $answer['discrimination']       = null;
    $answer['generation_meta']      = [
        'source'         => 'authored',
        'author'         => 'claude-opus-5',
        'grounding'      => ['document_extractions' => 147, 'semantic_intelligence' => SEMANTIC_ID],
        'prompt_version' => 'authored-integers-1.0',
        'authored_on'    => '2026-09-14',
    ];

    $prepared[] = [
        'src'              => $src,
        'concept_id'       => $conceptId,
        'concept_name'     => $conceptName,
        'topic_id'         => (int) $conceptById[$conceptId]['topic_id'],
        'question_type_id' => $questionTypeId,
        'sub_type'         => $subType,
        'bloom'            => $bloom,
        'dok'              => $dok,
        'points'           => $points,
        'title'            => $title,
        'description'      => $desc,
        'subconcept'       => $sub,
        'hint'             => $hint,
        'learning_outcome' => $loJson,
        'answer'           => $answer,
        'hash'             => $hash,
    ];
}

/* ----------------------------------------------------- coverage reporting */

$byConcept = [];
$byType = [];
$byBloom = [];
$byDifficulty = [];
foreach ($prepared as $p) {
    $byConcept[$p['concept_id']] = ($byConcept[$p['concept_id']] ?? 0) + 1;
    $byType[$p['sub_type']] = ($byType[$p['sub_type']] ?? 0) + 1;
    $byBloom[$p['bloom']] = ($byBloom[$p['bloom']] ?? 0) + 1;
    $byDifficulty[$p['answer']['difficulty']] = ($byDifficulty[$p['answer']['difficulty']] ?? 0) + 1;
}
$missing = array_values(array_filter(array_keys($conceptById), fn($id) => !isset($byConcept[$id])));

echo "items parsed        : " . count($items) . "\n";
echo "items valid         : " . count($prepared) . "\n";
echo "concepts covered    : " . count($byConcept) . " / " . count($conceptById) . "\n";
if ($missing) { echo "UNCOVERED CONCEPTS  : " . implode(', ', $missing) . "\n"; }
ksort($byType);
echo "by sub_type         : " . json_encode($byType) . "\n";
echo "by bloom            : " . json_encode($byBloom) . "\n";
echo "by difficulty       : " . json_encode($byDifficulty) . "\n";
if ($byConcept) {
    echo "per-concept min/max : " . min($byConcept) . " / " . max($byConcept) . "\n";
}

if ($errors) {
    echo "\nVALIDATION ERRORS (" . count($errors) . "):\n";
    foreach (array_slice($errors, 0, 60) as $e) { echo "  - {$e}\n"; }
    if (count($errors) > 60) { echo "  ... " . (count($errors) - 60) . " more\n"; }
    exit(1);
}
echo "\nvalidation: OK\n";

if ($mode === '--check') { exit(0); }

/* ---------------------------------------------------------------- insert */

$catalog = ['dok' => [], 'bloom' => []];
$rows = $pdo->query("select id, parent_id, name from lms_mapping_type where parent_id in (" . DOK_PARENT . "," . BLOOM_PARENT . ")")
            ->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $bucket = (int) $r['parent_id'] === DOK_PARENT ? 'dok' : 'bloom';
    $catalog[$bucket][mb_strtolower(trim($r['name']))] = ['id' => (int) $r['id'], 'name' => trim($r['name'])];
}

$existing = $pdo->prepare(
    "select 1 from lms_question_master
      where concept_id = ? and question_type_id = ?
        and JSON_EXTRACT(answer, '$.content_hash') = ? limit 1"
);

$insQ = $pdo->prepare(
    "insert into lms_question_master
     (question_type_id, grade_id, standard_id, subject_id, chapter_id, concept_id, topic_id,
      question_title, description, points, multiple_answer, concept, subconcept, category,
      sub_institute_id, status, created_by, created_on, answer, g_bloom, g_difficulty, g_dok,
      g_content_hash, hint_text, learning_outcome)
     values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,now(),?,?,?,?,?,?,?)"
);
$insM = $pdo->prepare(
    "insert into lms_question_mapping (questionmaster_id, mapping_type_id, mapping_value_id, reasons) values (?,?,?,?)"
);
$insA = $pdo->prepare(
    "insert into answer_master (question_id, answer, feedback, correct_answer, sub_institute_id, created_by, created_on)
     values (?,?,?,?,?,?,now())"
);

/** Mirror of QuestionGenerationService::learningFlowCategory(). */
$category = function (array $answer): string {
    $bloom = (string) ($answer['bloom_level'] ?? 'Understand');
    $subType = (string) ($answer['sub_type'] ?? '');
    $options = (array) ($answer['options'] ?? []);
    $misconceptionDistractors = 0;
    foreach ($options as $o) {
        if (($o['distractor_type'] ?? null) === 'misconception') { $misconceptionDistractors++; }
    }
    if (str_contains($subType, 'Case') || $bloom === 'Create') { return 'mastery_reverification'; }
    if ($bloom === 'Evaluate') { return 'mastery_check'; }
    if ($bloom === 'Analyze')  { return 'adaptive_test'; }
    if ($bloom === 'Remember') { return 'adaptive_diagnostic'; }
    if (!empty($options))      { return $misconceptionDistractors >= 2 ? 'misconception_detection' : 'concept_diagnostic'; }
    if ($bloom === 'Apply')    { return 'adaptive_test'; }
    return 'concept_understanding';
};

$inserted = 0; $skipped = 0; $mapRows = 0; $ansRows = 0;
$pdo->beginTransaction();
try {
    foreach ($prepared as $p) {
        $existing->execute([$p['concept_id'], $p['question_type_id'], $p['hash']]);
        if ($existing->fetchColumn()) { $skipped++; continue; }

        $a = $p['answer'];
        $insQ->execute([
            $p['question_type_id'], GRADE_ID, STANDARD_ID, SUBJECT_ID, CHAPTER_ID,
            $p['concept_id'], $p['topic_id'],
            $p['title'], $p['description'], $p['points'], 0,
            $p['concept_name'], $p['subconcept'], $category($a),
            SUB_INST, 1, null,
            json_encode($a, JSON_UNESCAPED_UNICODE),
            $p['bloom'], $a['difficulty'], $p['dok'], $p['hash'],
            $p['hint'], $p['learning_outcome'],
        ]);
        $id = (int) $pdo->lastInsertId();
        $inserted++;

        $dokLabel = mb_strtolower(DOK_LABEL[$p['dok']] ?? 'medium');
        if (isset($catalog['dok'][$dokLabel])) {
            $insM->execute([$id, DOK_PARENT, $catalog['dok'][$dokLabel]['id'], $catalog['dok'][$dokLabel]['name']]);
            $mapRows++;
        }
        $bloomLabel = mb_strtolower(BLOOM_LABEL[$p['bloom']] ?? '');
        if (isset($catalog['bloom'][$bloomLabel])) {
            $insM->execute([$id, BLOOM_PARENT, $catalog['bloom'][$bloomLabel]['id'], $catalog['bloom'][$bloomLabel]['name']]);
            $mapRows++;
        }

        foreach ((array) ($a['options'] ?? []) as $o) {
            $insA->execute([
                $id, $o['text'], $o['rationale'] !== '' ? $o['rationale'] : null,
                $o['is_correct'] ? 1 : 0, SUB_INST, null,
            ]);
            $ansRows++;
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "INSERT FAILED, rolled back: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\ninserted questions  : {$inserted}\n";
echo "skipped duplicates  : {$skipped}\n";
echo "mapping rows        : {$mapRows}\n";
echo "answer_master rows  : {$ansRows}\n";
