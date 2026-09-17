<?php
/**
 * Correct question 704081 (concept 2306).
 *
 * As authored the stem had a student finding the HCF of 20 and 26 to be 1, and
 * the keyed option endorsed that answer. But 20 and 26 are both even, so their
 * HCF is 2 — the student's answer was wrong and the key was wrong with it. The
 * item's teaching point is the over-generalisation from a coprime pair, which
 * needs a genuinely coprime pair: 20 and 21 share nothing but 1.
 *
 * Every surface carrying the old number is updated, along with the answer_master
 * option rows and the content hash derived from the stem.
 */

const QID = 704081;

$pdo = new PDO('mysql:host=202.47.117.220;dbname=vivek_erp;charset=utf8mb4', 'vivek_user', 'vivek@sql',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$row = $pdo->query('select question_title, answer from lms_question_master where id = ' . QID)->fetch(PDO::FETCH_ASSOC);
if (!$row) { fwrite(STDERR, "question not found\n"); exit(1); }

$title = str_replace('HCF of 20 and 26', 'HCF of 20 and 21', $row['question_title']);

$a = json_decode($row['answer'], true);

$optionFixes = [
    'A' => [
        'rationale' => 'The HCF of 20 and 21 really is 1, since 21 has factors 1, 3, 7 and 21, none of which divides 20 apart from 1, but many pairs share much more.',
    ],
    'C' => [
        'text'      => 'The answer is wrong: the HCF of 20 and 21 is 2',
        'rationale' => '2 divides 20 but it does not divide 21, so 2 is not a common factor of the pair at all.',
    ],
    'D' => [
        'text'      => 'The answer is wrong: the HCF of 20 and 21 is 420',
        'rationale' => '420 is the lowest common multiple of 20 and 21, not a common factor; a factor can never exceed the numbers it divides.',
    ],
];

foreach ($a['options'] as &$o) {
    $lab = $o['label'];
    if (isset($optionFixes[$lab])) {
        foreach ($optionFixes[$lab] as $k => $v) { $o[$k] = $v; }
    }
}
unset($o);

// the stem's number appears in the keyed option text too
foreach ($a['options'] as &$o) {
    $o['text']      = str_replace('20 and 26', '20 and 21', $o['text']);
    $o['rationale'] = str_replace('20 and 26', '20 and 21', $o['rationale']);
}
unset($o);

$a['explanation'] = 'The student\'s answer is right for this pair: 20 and 21 share no factor above 1. The conclusion drawn from it is not, because plenty of pairs share much more, and whether they sit in a common times table is not the test.';

// same normalisation and hash the loader used, so the idempotency key stays valid
$norm = preg_replace('/[^a-z0-9 ]/', '', strtolower(preg_replace('/\s+/', ' ', trim($title))));
$hash = hash('sha256', $norm);
$a['content_hash'] = $hash;

$pdo->beginTransaction();
try {
    $pdo->prepare('update lms_question_master set question_title = ?, answer = ?, g_content_hash = ? where id = ?')
        ->execute([$title, json_encode($a, JSON_UNESCAPED_UNICODE), $hash, QID]);

    // answer_master holds the option text the quiz runtime actually renders
    $pdo->prepare('delete from answer_master where question_id = ?')->execute([QID]);
    $insA = $pdo->prepare(
        'insert into answer_master (question_id, answer, feedback, correct_answer, sub_institute_id, created_by, created_on)
         values (?,?,?,?,?,?,now())'
    );
    foreach ($a['options'] as $o) {
        $insA->execute([
            QID,
            mb_substr($o['text'], 0, 250),
            mb_substr($o['rationale'], 0, 250),
            !empty($o['is_correct']) ? 1 : 0,
            341, null,
        ]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'fix failed, rolled back: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "updated question " . QID . "\n";
echo "stem: {$title}\n\n";
foreach ($a['options'] as $o) {
    echo ($o['is_correct'] ? '[KEY] ' : '      ') . $o['label'] . ') ' . $o['text'] . "\n";
}
