<?php

namespace App\Services\StudyDeck\Documents;

use App\Services\StudyDeck\ActivityPlanner;
use App\Services\StudyDeck\DeckValidator;
use App\Services\StudyDeck\Documents\Writers\ActivityWriter;
use App\Services\StudyDeck\Documents\Writers\RemedialFrameWriter;
use App\Services\StudyDeck\Documents\Writers\RemedialWriter;
use App\Services\StudyDeck\Documents\Writers\RevisionNotesWriter;
use App\Services\StudyDeck\Documents\Writers\TopicRevisionWriter;
use App\Services\StudyDeck\InteractionPlanner;
use App\Services\StudyDeck\QuestionSelector;

/**
 * Checks a finished study document before it is allowed to be stored. It returns a report and never throws.
 *
 * The rules are the study deck's, applied to what a document is:
 *   - every concept in scope is covered, by a part of its own, and no concept is invented;
 *   - every bank question used is one the bank offered (self-contained, with a stored answer), is used once, and
 *     is played by a target the runtime has;
 *   - every interaction is well formed and every picture is a drawn diagram with its labels and alt text;
 *   - the wording passes the content design system's checks (labelled callouts, no emoji, short lists, a range of
 *     thinking levels) and the lexical grounding check: numbers that appear nowhere in the chapter text or the
 *     questions are an error, unfamiliar names a warning for a person to look at;
 *   - and each kind's own rules, which are the SAME rules its writer repaired against (the writer's `problems()`),
 *     so a document that left the writer clean is clean here.
 *
 * The grounding check is blunt on purpose: it cannot judge truth, only whether a claim was lifted from the book.
 */
class DocumentValidator
{
    private const HOTSPOT_KINDS = ['hotspots'];

    private const ITEM_KINDS = ['reveal', 'steps', 'timeline', 'compare'];

    public function __construct(
        private readonly InteractionPlanner $checker,
        private readonly QuestionSelector $questions = new QuestionSelector(),
    ) {
    }

    /**
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @param array<string,mixed> $map LearningPlanBuilder::build()
     * @param array<int,int> $scope the concepts the document covers
     * @param array<int,array<int,array<string,mixed>>> $eligible concept_id => questions
     * @param array<string,mixed> $document
     * @param array<int,array{id:int,flags:array<int,string>}> $flagged wording-flagged questions
     * @return array{ok:bool, errors:array<int,string>, warnings:array<int,string>, stats:array<string,mixed>}
     */
    public function validate(DocumentKind $kind, array $context, array $map, array $scope, array $eligible, array $document, string $html, array $flagged = []): array
    {
        $errors = [];
        $warnings = [];

        $this->envelope($kind, $context, $document, $errors);
        $this->coverage($map, $scope, $document, $errors);
        $this->questionRules($kind, $document, $eligible, $flagged, $errors, $warnings);
        $this->interactionRules($document, $errors);
        $this->imageRules($document, $errors);

        match (true) {
            $kind === DocumentKind::RevisionNotes && self::isPurpose($document) => $this->revisionPurpose($context, $map, $scope, $eligible, $document, $errors, $warnings),
            $kind === DocumentKind::Remedial && self::isPurpose($document) => $this->remedialPurpose($map, $scope, $eligible, $document, $errors, $warnings),
            $kind === DocumentKind::RevisionNotes => $this->revision($map, $scope, $eligible, $document, $errors, $warnings),
            $kind === DocumentKind::Remedial => $this->remedial($map, $scope, $eligible, $document, $errors, $warnings),
            default => $this->activities($map, $scope, $document, $errors),
        };

        $this->wording($kind, $context, $map, $scope, $eligible, $document, $html, $errors, $warnings);

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'stats' => $this->stats($document),
        ];
    }

    // ---------------------------------------------------------------------------------------------------------

    private function envelope(DocumentKind $kind, array $context, array $d, array &$errors): void
    {
        if (($d['version'] ?? null) !== DocumentKind::VERSION) {
            $errors[] = 'The document has the wrong version.';
        }
        if (($d['kind'] ?? null) !== $kind->value) {
            $errors[] = 'The document is not a ' . $kind->label() . ' document.';
        }
        if ((int) ($d['chapter']['id'] ?? 0) !== (int) $context['chapter']['id']) {
            $errors[] = 'The document does not say which chapter it is for.';
        }
        $sections = $d['sections'] ?? [];
        if (!is_array($sections) || count($sections) < 2) {
            $errors[] = 'The document has no parts besides its overview.';

            return;
        }
        foreach (array_values($sections) as $i => $s) {
            if (($s['n'] ?? null) !== $i) {
                $errors[] = 'Part ' . $i . ' is numbered ' . json_encode($s['n'] ?? null) . '; parts are numbered 0, 1, 2 ... in order.';
                break;
            }
        }
        if (($sections[0]['type'] ?? '') !== 'overview') {
            $errors[] = 'The first part must be the overview.';
        }
        if (self::isPurpose($d)) {
            $allowed = self::PURPOSE_PARTS[$kind->value] ?? [];
            foreach ($sections as $s) {
                if (!in_array($s['type'] ?? '', $allowed, true)) {
                    $errors[] = 'Part ' . ($s['n'] ?? '?') . ' is a "' . ($s['type'] ?? '') . '"; a purpose-based ' . $kind->label() . ' document has only: ' . implode(', ', $allowed) . '.';
                }
            }
            if (!isset($d['purpose']['min_pages'], $d['purpose']['max_pages'])) {
                $errors[] = 'A purpose-based document records the page range it must come to.';
            }
        }
    }

    /** Every concept in scope is covered; none is invented. */
    private function coverage(array $map, array $scope, array $d, array &$errors): void
    {
        $known = array_keys($map['concepts']);
        $uncovered = [];
        foreach ($scope as $id) {
            // A purpose-based remedial class teaches only some concepts; it must ADDRESS them all (remedialPurpose() checks that).
            if (empty($d['taught_by'][$id]) && !(self::isPurpose($d) && ($d['kind'] ?? '') === 'remedial')) {
                $uncovered[] = ($map['concepts'][$id]['name'] ?? '?') . " ($id)";
            }
        }
        if ($uncovered) {
            $errors[] = 'Concepts that no part of the document covers: ' . implode('; ', $uncovered);
        }
        foreach ($d['sections'] ?? [] as $s) {
            foreach (array_merge($s['concept_ids'] ?? [], $s['taught_concept_ids'] ?? []) as $id) {
                if (!in_array($id, $known, true)) {
                    $errors[] = 'Part ' . ($s['n'] ?? '?') . ' names concept ' . $id . ', which is not in this chapter.';
                }
            }
        }
        $outOfScope = array_diff(array_keys($d['taught_by'] ?? []), $scope);
        if ($outOfScope) {
            $errors[] = 'The document teaches concepts that are not in its scope: ' . implode(', ', $outOfScope);
        }
    }

    /**
     * @param array<int,array<int,array<string,mixed>>> $eligible
     * @param array<int,array{id:int,flags:array<int,string>}> $flagged
     */
    private function questionRules(DocumentKind $kind, array $d, array $eligible, array $flagged, array &$errors, array &$warnings): void
    {
        $byId = [];
        foreach ($eligible as $list) {
            foreach ($list as $q) {
                $byId[$q['id']] = $q;
            }
        }
        $flags = array_column($flagged, 'flags', 'id');
        $perPart = ['revision_notes' => 2, 'remedial' => 3, 'activities' => 5][$kind->value];
        $compact = self::isCompact($d);
        $purpose = self::isPurpose($d);
        $seen = [];

        foreach ($d['sections'] ?? [] as $s) {
            $n = $s['n'];
            $ids = $s['question_ids'] ?? [];
            if ($purpose) {
                $perPart = self::PURPOSE_QUESTIONS[$s['type'] ?? ''] ?? 0;
            }
            $placed = array_column(array_filter($s['activities'] ?? [], fn ($a) => ($a['source'] ?? '') === 'bank'), 'question_id');
            if ($ids !== $placed) {
                $errors[] = "Part $n: the activities do not match the questions placed on it.";
            }
            if (count($ids) > $perPart) {
                $errors[] = "Part $n has " . count($ids) . " bank questions; the limit is $perPart.";
            }
            foreach ($ids as $qid) {
                if (!isset($byId[$qid])) {
                    $errors[] = "Part $n: question $qid is not an eligible bank question.";
                    continue;
                }
                $q = $byId[$qid];
                if (isset($seen[$qid])) {
                    $errors[] = "Question $qid appears in more than one part.";
                }
                $seen[$qid] = true;
                if ($reason = $this->questions->dependsOnMissingContext($q['stem'])) {
                    $errors[] = "Part $n: question $qid is not self-contained ($reason).";
                }
                foreach ($q['options'] as $o) {
                    if ($reason = $this->questions->dependsOnMissingContext($o['text'])) {
                        $errors[] = "Part $n: an option of question $qid is not self-contained ($reason).";
                    }
                }
                if (trim($q['explanation']) === '' && trim($q['answer_text']) === '') {
                    $errors[] = "Part $n: question $qid has no stored explanation or model answer.";
                }
                if ($compact && !CompactRevisionPdfRenderer::fits($q)) {
                    $errors[] = "Part $n: question $qid cannot be printed whole in a compact pack (choices, and an answer and reason that fit the answer area).";
                }
                if (!empty($q['flags']) || isset($flags[$qid])) {
                    $warnings[] = "Part $n: question $qid needs a human wording review (" . implode('; ', $q['flags'] ?: $flags[$qid]) . ').';
                }
            }
            foreach ($s['activities'] ?? [] as $a) {
                if (!in_array($a['as'] ?? '', ActivityPlanner::TARGETS, true)) {
                    $errors[] = "Part $n: an activity asks for \"" . ($a['as'] ?? '') . '", which the player cannot render.';
                }
                if (!in_array($a['label'] ?? '', ActivityPlanner::LABELS, true)) {
                    $errors[] = "Part $n: an activity label \"" . ($a['label'] ?? '') . '" is not one of ' . implode(', ', ActivityPlanner::LABELS) . '.';
                }
                if (($a['source'] ?? '') !== 'bank') {
                    $errors[] = "Part $n: a question that is not from the question bank (documents use the bank's questions only).";
                }
            }
        }

        // A concept the bank can ask about but the document never asks about is worth a look, not a failure.
        if ($compact) {
            // A compact pack asks about a representative few, by design: one line says how many concepts have none.
            $without = 0;
            $total = 0;
            foreach ($d['scope']['concept_ids'] ?? [] as $conceptId) {
                $total++;
                $without += empty($d['concept_questions'][$conceptId]) ? 1 : 0;
            }
            if ($without > 0) {
                $warnings[] = "A compact pack asks about a representative few: $without of $total concepts have no practice question in it (the bank's questions for them are in the full version).";
            }
        } elseif ($kind !== DocumentKind::Activities && !$purpose) {
            foreach ($eligible as $conceptId => $list) {
                if ($list && empty($d['concept_questions'][$conceptId]) && in_array($conceptId, $d['scope']['concept_ids'] ?? [], true)) {
                    $warnings[] = "Concept $conceptId has bank questions but the document uses none of them.";
                }
            }
        }
    }

    /** Is this the compact profile of the revision notes? @param array<string,mixed> $d */
    private static function isCompact(array $d): bool
    {
        return ($d['profile'] ?? '') === 'compact';
    }

    /** The shapes the player and the PDF can draw. A document has no scenario: it needs a person to make decisions. */
    private function interactionRules(array $d, array &$errors): void
    {
        foreach ($d['sections'] ?? [] as $s) {
            $i = $s['interaction'] ?? null;
            if ($i === null) {
                continue;
            }
            $n = $s['n'];
            $kind = $i['kind'] ?? '';
            if (!in_array($kind, array_merge(self::HOTSPOT_KINDS, self::ITEM_KINDS, ['match', 'order']), true)) {
                $errors[] = "Part $n: interaction kind \"$kind\" is not one a document carries.";
                continue;
            }
            if (trim((string) ($i['reason'] ?? '')) === '') {
                $errors[] = "Part $n: the $kind interaction does not say why it is there.";
            }

            if ($kind === 'hotspots') {
                $labels = array_map('mb_strtolower', (array) ($s['image']['texts'] ?? []));
                if (($s['image']['type'] ?? '') !== 'diagram') {
                    $errors[] = "Part $n: hotspots need a drawn diagram to sit on.";
                }
                if (count($i['spots'] ?? []) < 2) {
                    $errors[] = "Part $n: hotspots need at least two parts.";
                }
                foreach ((array) ($i['spots'] ?? []) as $sp) {
                    if (!in_array(mb_strtolower((string) $sp['label']), $labels, true)) {
                        $errors[] = "Part $n: hotspot \"{$sp['label']}\" is not a label on the diagram.";
                    }
                    if ($sp['x'] < 0 || $sp['x'] > 100 || $sp['y'] < 0 || $sp['y'] > 100) {
                        $errors[] = "Part $n: hotspot \"{$sp['label']}\" is outside the picture.";
                    }
                }
            } elseif (in_array($kind, self::ITEM_KINDS, true)) {
                $min = in_array($kind, ['steps', 'timeline'], true) ? 3 : 2;
                if (count($i['items'] ?? []) < $min) {
                    $errors[] = "Part $n: a $kind interaction needs at least $min items.";
                }
                foreach ((array) ($i['items'] ?? []) as $item) {
                    if (trim((string) ($item['label'] ?? '')) === '' || trim((string) ($item['text'] ?? '')) === '') {
                        $errors[] = "Part $n: a $kind item has no label or no explanation.";
                    }
                }
                if ($kind === 'compare' && trim((string) ($i['wrapup'] ?? '')) === '') {
                    $errors[] = "Part $n: a compare has no line saying how the items compare.";
                }
            } elseif ($kind === 'match' && count($i['pairs'] ?? []) < 3) {
                $errors[] = "Part $n: a match needs at least three pairs.";
            } elseif ($kind === 'order' && count($i['items'] ?? []) < 3) {
                $errors[] = "Part $n: an order needs at least three items.";
            }
        }
    }

    private function imageRules(array $d, array &$errors): void
    {
        $count = 0;
        foreach ($d['sections'] ?? [] as $s) {
            $img = $s['image'] ?? null;
            if ($img === null) {
                continue;
            }
            $n = $s['n'];
            $count++;
            if (($img['type'] ?? '') !== 'diagram') {
                $errors[] = "Part $n: a document carries drawn diagrams only.";
            }
            if (trim((string) ($img['alt'] ?? '')) === '') {
                $errors[] = "Part $n: the picture has no alt text.";
            }
            if (empty($img['texts']) || empty($img['url']) || (int) ($img['width'] ?? 0) < 1 || (int) ($img['height'] ?? 0) < 1) {
                $errors[] = "Part $n: a drawn diagram has no recorded labels, address or size.";
            }
        }
        if ($count > DocumentAssembler::MAX_DIAGRAMS) {
            $errors[] = "The document has $count diagrams; the limit is " . DocumentAssembler::MAX_DIAGRAMS . '.';
        }
        if (self::isPurpose($d) && $count > (DocumentAssembler::PURPOSE_DIAGRAMS[$d['kind'] ?? ''] ?? 0)) {
            $errors[] = "A purpose-based document has $count diagrams; the limit is " . (DocumentAssembler::PURPOSE_DIAGRAMS[$d['kind'] ?? ''] ?? 0) . '.';
        }
        if (self::isCompact($d) && $count > DocumentAssembler::COMPACT_DIAGRAMS) {
            $errors[] = "A compact pack has $count diagrams; the limit is " . DocumentAssembler::COMPACT_DIAGRAMS . '.';
        }
    }

    // ---------------------------------------------------------------------------------------------------------
    // Each kind's own rules: the writer's rules, applied to what was stored

    /**
     * The chapter summary and a line for every topic: the same opening for every kind of revision notes.
     *
     * @param array<string,mixed> $d
     */
    private function revisionOverview(array $d, array &$errors): void
    {
        $overview = $d['sections'][0]['content'] ?? [];
        if (str_word_count((string) ($overview['summary'] ?? '')) < 12) {
            $errors[] = 'The chapter summary is missing or too short to say what the chapter is about.';
        }
        foreach ($d['outline'] as $t) {
            $gist = '';
            foreach ($overview['topics'] ?? [] as $x) {
                if ((int) $x['topic_id'] === (int) $t['topic_id']) {
                    $gist = (string) $x['gist'];
                }
            }
            if ($gist === '') {
                $errors[] = 'The summary says nothing about the topic "' . $t['name'] . '".';
            }
        }
    }

    // ---------------------------------------------------------------------------------------------------------
    // The purpose-based documents: what each is FOR, checked against what it holds

    /** Is this a purpose-based document (revision sheets, or a class that reteaches only some concepts)? @param array<string,mixed> $d */
    public static function isPurpose(array $d): bool
    {
        return ($d['profile'] ?? '') === 'purpose';
    }

    /** The part types a purpose-based document of each kind may hold. */
    private const PURPOSE_PARTS = [
        'revision_notes' => ['overview', 'topic', 'glossary', 'check'],
        'remedial' => ['overview', 'diagnostic', 'gaps', 'unit', 'clinic', 'independent', 'exit', 'teacher'],
    ];

    /** Most bank questions one part of a purpose-based document holds. */
    private const PURPOSE_QUESTIONS = ['check' => 14, 'diagnostic' => 10, 'unit' => 3, 'independent' => 10, 'exit' => 10];

    /**
     * Revision notes are sheets for exam preparation: a sheet for every topic with a line for every concept, the key terms,
     * and a small set of the bank's questions. Anything that teaches (an explanation, a worked example, steps) is not here.
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $map
     * @param array<int,int> $scope
     * @param array<int,array<int,array<string,mixed>>> $eligible
     * @param array<string,mixed> $d
     */
    private function revisionPurpose(array $context, array $map, array $scope, array $eligible, array $d, array &$errors, array &$warnings): void
    {
        $this->revisionOverview($d, $errors);

        $glossary = [];
        $check = null;
        foreach ($d['sections'] as $s) {
            if ($s['type'] === 'glossary') {
                $glossary = $s['content']['terms'] ?? [];
            }
            if ($s['type'] === 'check') {
                $check = $s;
            }
        }

        foreach ($d['outline'] as $t) {
            $sheet = null;
            foreach ($d['sections'] as $s) {
                if ($s['type'] === 'topic' && (int) $s['topic_id'] === (int) $t['topic_id']) {
                    $sheet = $s;
                }
            }
            if ($sheet === null) {
                $errors[] = 'Topic "' . $t['name'] . '" has no revision sheet.';
                continue;
            }
            $c = $sheet['content'];
            $ids = array_map('intval', $t['concept_ids']);
            if (array_map('intval', array_column($c['rows'], 'concept_id')) !== $ids) {
                $errors[] = 'Sheet "' . $sheet['title'] . '": it must have one row for every concept of the topic, in the chapter\'s order.';
                continue;
            }

            $rows = [];
            foreach ($c['rows'] as $r) {
                $rows[(int) $r['concept_id']] = ['essential' => $r['essential'], 'terms' => $r['terms']];
            }
            $work = ['concepts' => array_map(fn ($id) => [
                'concept_id' => $id,
                'name' => $map['concepts'][$id]['name'],
                'key_terms' => $map['concepts'][$id]['terms'],
                'misconceptions' => $map['concepts'][$id]['misconceptions'],
            ], $ids)];
            $sheetContent = [
                'big_idea' => $c['big_idea'], 'rows' => $rows, 'compare' => $c['compare'], 'mixups' => $c['mixups'], 'recall' => $c['recall'],
                'checklist' => $c['checklist'],
                'terms' => array_values(array_filter($glossary, fn ($g) => (int) ($g['topic_id'] ?? 0) === (int) $t['topic_id'])),
            ];
            foreach (TopicRevisionWriter::sheetProblems($sheetContent, $work, (string) $context['ground_truth']) as $problem) {
                $errors[] = 'Sheet "' . $sheet['title'] . '": ' . $problem;
            }
            // A sheet condenses; it must not have grown the parts of a lesson.
            foreach (['explanation', 'steps', 'worked_example', 'simple_explanation', 'guided'] as $teaching) {
                if (array_key_exists($teaching, $c)) {
                    $errors[] = 'Sheet "' . $sheet['title'] . '" carries "' . $teaching . '", which belongs in a lesson, not in revision notes.';
                }
            }
        }

        if (count($glossary) < min(8, count($scope))) {
            $warnings[] = 'The key terms list has only ' . count($glossary) . ' term(s); the chapter text may name more.';
        }
        $sorted = array_column($glossary, 'term');
        $alphabetical = $sorted;
        usort($alphabetical, 'strcasecmp');
        if ($sorted !== $alphabetical) {
            $errors[] = 'The key terms are not in alphabetical order.';
        }

        // A chapter whose bank offers few questions has a small set; one whose bank offers none has none (and the report says so).
        $available = 0;
        foreach ($scope as $id) {
            $available += count(array_filter($eligible[$id] ?? [], [QuestionPlacement::class, 'printable']));
        }
        if ($check === null) {
            if ($available > 0) {
                $errors[] = 'The revision notes have no "test yourself" set of questions.';
            } else {
                $warnings[] = 'The question bank offers no question that can be printed whole, so the revision notes have no "test yourself" set.';
            }

            return;
        }
        $n = count($check['question_ids']);
        if ($n < min(6, $available) || $n > self::PURPOSE_QUESTIONS['check']) {
            $errors[] = 'The "test yourself" set has ' . $n . ' questions; a small selection is ' . min(6, $available) . ' to ' . self::PURPOSE_QUESTIONS['check'] . '.';
        }
        $topics = [];
        foreach ($check['activities'] as $a) {
            $topics[(int) ($map['concepts'][$a['concept_id']]['topic_id'] ?? 0)] = true;
        }
        foreach ($d['outline'] as $t) {
            $hasQuestions = false;
            foreach ($t['concept_ids'] as $id) {
                $hasQuestions = $hasQuestions || !empty($eligible[$id]);
            }
            if ($hasQuestions && !isset($topics[(int) $t['topic_id']])) {
                $warnings[] = 'The "test yourself" set has no question on the topic "' . $t['name'] . '".';
            }
        }
    }

    /**
     * A remedial class reteaches. It begins by finding out where help is needed, reteaches only the concepts the chapter's data
     * suggests are hard (and says that is all it is), settles the common wrong ideas, gives practice without help, and says what
     * ready looks like. Every concept is still ADDRESSED, by the table of potential difficulties and the questions.
     *
     * @param array<string,mixed> $map
     * @param array<int,int> $scope
     * @param array<int,array<int,array<string,mixed>>> $eligible
     * @param array<string,mixed> $d
     */
    private function remedialPurpose(array $map, array $scope, array $eligible, array $d, array &$errors, array &$warnings): void
    {
        $byId = [];
        foreach ($eligible as $list) {
            foreach ($list as $q) {
                $byId[$q['id']] = $q;
            }
        }
        $parts = [];
        $units = [];
        foreach ($d['sections'] as $s) {
            $parts[$s['type']][] = $s;
            if ($s['type'] === 'unit') {
                $units[$s['n']] = $s;
            }
        }

        // The shape: each part once (the units as many as the class has), in the order of a class.
        $order = array_column($d['sections'], 'type');
        $expected = array_merge(['overview', 'diagnostic', 'gaps'], array_fill(0, count($units), 'unit'), ['clinic', 'independent', 'exit', 'teacher']);
        if ($order !== $expected) {
            $errors[] = 'A remedial class has these parts, in this order: the overview, the short check, the table of potential difficulties, the units, the mix-ups, practice on your own, the exit check and the teacher guide.';

            return;
        }

        // It reteaches SOME concepts: enough to be a class, few enough that it is not the whole chapter again.
        $k = count($units);
        if ($k < min(4, count($scope)) || $k > 8) {
            $errors[] = 'The class has ' . $k . ' units; a remedial class reteaches 4 to 8 concepts.';
        }
        if (count($scope) > 8 && $k >= count($scope)) {
            $errors[] = 'The class teaches every concept of the chapter. A remedial class reteaches the concepts that may be hard, not the whole chapter.';
        }

        // Every concept is addressed somewhere.
        $addressed = [];
        foreach ($d['sections'] as $s) {
            foreach (array_merge($s['concept_ids'] ?? [], $s['taught_concept_ids'] ?? []) as $id) {
                $addressed[(int) $id] = true;
            }
        }
        $missing = array_filter($scope, fn ($id) => !isset($addressed[$id]));
        if ($missing) {
            $errors[] = 'Concepts that no part of the class addresses: ' . implode('; ', array_map(fn ($id) => ($map['concepts'][$id]['name'] ?? '?') . " ($id)", $missing));
        }

        // The lessons.
        foreach ($units as $unit) {
            $c = $unit['content'];
            $id = $unit['taught_concept_ids'][0] ?? 0;
            $concept = $map['concepts'][$id] ?? null;
            if ($concept === null) {
                $errors[] = 'Unit "' . $unit['title'] . '" does not teach a concept of this chapter.';
                continue;
            }
            $work = [
                'misconceptions' => [],
                'practice' => array_map(fn ($g) => ['question_id' => $g['question_id'], 'model_answer' => $byId[$g['question_id']]['answer_text'] ?? null], $c['guided']),
            ];
            $as = [
                'simple_explanation' => $c['simple_explanation'], 'steps' => $c['steps'], 'mistakes' => $c['mistakes'], 'win' => $c['win'],
                'worked_example' => $c['worked_example'], 'real_life' => $c['real_life'],
                'practice' => array_map(fn ($g) => ['question_id' => $g['question_id'], 'hint' => $g['hint'], 'not_options' => $g['not_options']], $c['guided']),
            ];
            foreach (RemedialWriter::problems($as, $work, $this->checker, false, true) as $problem) {
                $errors[] = 'Unit "' . $unit['title'] . '": ' . $problem;
            }
            if ($c['mistakes'] !== []) {
                $errors[] = 'Unit "' . $unit['title'] . '": mistakes belong in the mix-ups, not in a unit.';
            }
            if ($c['guided'] === []) {
                $warnings[] = 'Unit "' . $unit['title'] . '" has no guided question to practise with.';
            }
            $levels = array_column($c['guided'], 'level');
            if ($levels !== [] && $levels !== range(1, count($levels))) {
                $errors[] = 'Unit "' . $unit['title'] . '": the guided levels must run 1, 2 in order.';
            }
            $needs = array_values(array_filter($concept['requires'], fn ($r) => isset($map['concepts'][$r])));
            if (array_column($c['prerequisites'], 'concept_id') !== $needs) {
                $errors[] = 'Unit "' . $unit['title'] . '": the prerequisites listed are not the concept\'s prerequisites.';
            }
            if ($c['real_life'] === null) {
                $warnings[] = 'Unit "' . $unit['title'] . '" has no real-life picture.';
            }
        }

        // The short check: a question for every topic that has one, each pointing at units that exist.
        $diag = $parts['diagnostic'][0];
        if (count($diag['content']['items']) !== count($diag['question_ids'])) {
            $errors[] = 'The short check does not say where to go for each of its questions.';
        }
        foreach ($diag['content']['items'] as $i => $item) {
            if ((int) $item['question_id'] !== (int) ($diag['question_ids'][$i] ?? 0)) {
                $errors[] = 'The short check\'s items are not in the order of its questions.';
                break;
            }
            foreach ($item['if_missed'] as $u) {
                if (!isset($units[(int) $u['n']])) {
                    $errors[] = 'The short check sends a learner to unit part ' . $u['n'] . ', which is not a unit of this class.';
                }
            }
        }
        $probed = [];
        foreach ($diag['content']['items'] as $item) {
            $probed[(int) $item['topic_id']] = true;
        }
        foreach ($d['outline'] as $t) {
            $has = false;
            foreach ($t['concept_ids'] as $id) {
                $has = $has || !empty($eligible[$id]);
            }
            if ($has && !isset($probed[(int) $t['topic_id']])) {
                $warnings[] = 'The short check has no question on the topic "' . $t['name'] . '".';
            }
        }

        // The table of potential difficulties says plainly that they are potential.
        $gaps = $parts['gaps'][0]['content'];
        if ($gaps['note'] !== GapAnalysis::NOTE) {
            $errors[] = 'The table of potential difficulties must carry the standard note that they are potential, not measured.';
        }
        foreach ($gaps['rows'] as $r) {
            if (!in_array($r['priority'], ['higher', 'medium'], true) || trim((string) $r['covered_in']) === '' || !in_array((int) $r['concept_id'], $scope, true)) {
                $errors[] = 'A row of the table of potential difficulties (' . ($r['name'] ?? '?') . ') is malformed.';
            }
        }
        foreach ($units as $unit) {
            $id = $unit['taught_concept_ids'][0] ?? 0;
            if (!in_array($id, array_column($gaps['rows'], 'concept_id'), true)) {
                $errors[] = 'The unit "' . $unit['title'] . '" is not in the table of potential difficulties, so the class does not say why it reteaches it.';
            }
        }

        // The objectives: one for each topic, in the learner's voice.
        $overview = $d['sections'][0]['content'];
        $statedTopics = array_map('intval', array_column($overview['objectives'] ?? [], 'topic_id'));
        foreach ($d['outline'] as $t) {
            if (!in_array((int) $t['topic_id'], $statedTopics, true)) {
                $errors[] = 'The class states no objective for the topic "' . $t['name'] . '".';
            }
        }
        foreach ($overview['objectives'] ?? [] as $o) {
            foreach (RemedialFrameWriter::objectiveProblems(['text' => $o['text']]) as $problem) {
                $errors[] = 'Objective for "' . $o['name'] . '": ' . $problem;
            }
        }
        foreach ($overview['pathway'] ?? [] as $p) {
            if (($d['sections'][$p['n']]['type'] ?? null) !== $p['type']) {
                $errors[] = 'The pathway names part ' . $p['n'] . ' as a ' . $p['type'] . '; it is not.';
            }
        }

        // The mix-ups: each a listed misconception, corrected.
        foreach ($parts['clinic'][0]['content']['items'] as $it) {
            $listed = array_column($map['concepts'][$it['concept_id']]['misconceptions'] ?? [], 'wrong_idea');
            if (!RemedialFrameWriter::traces($it['wrong_idea'], $listed)) {
                $errors[] = 'A mix-up ("' . mb_substr($it['wrong_idea'], 0, 40) . '...") is not one of the concept\'s listed misconceptions.';
            }
            foreach (RemedialFrameWriter::clinicProblems($it, ['wrong_idea' => $it['wrong_idea']]) as $problem) {
                $errors[] = 'Mix-up "' . mb_substr($it['wrong_idea'], 0, 40) . '...": ' . $problem;
            }
            if ($it['unit'] !== null && !isset($units[(int) $it['unit']])) {
                $errors[] = 'A mix-up points at unit part ' . $it['unit'] . ', which is not a unit of this class.';
            }
        }
        if ($parts['clinic'][0]['content']['items'] === [] && $this->anyMisconceptionListed($map, array_map(fn ($u) => (int) ($u['taught_concept_ids'][0] ?? 0), $units))) {
            $errors[] = 'The class has no mix-ups to settle, though the chapter data lists misconceptions.';
        }

        if (count($parts['independent'][0]['question_ids']) < 3) {
            $warnings[] = 'Practice on your own has fewer than 3 questions.';
        }

        // The exit check: a readiness rule that adds up, and a way back for every unit.
        $exit = $parts['exit'][0];
        $total = count($exit['question_ids']);
        $x = $exit['content'];
        if ((int) $x['total'] !== $total || (int) $x['ready_at'] !== (int) ceil(0.8 * $total)) {
            $errors[] = 'The exit check\'s readiness rule does not match its questions (ready at four fifths of them).';
        }
        if ($total < 3) {
            $warnings[] = 'The exit check has only ' . $total . ' question(s); the question bank offers too few for a reliable check of readiness.';
        }
        if ($x['criteria'] === []) {
            $errors[] = 'The exit check says nothing about what ready looks like.';
        }
        if (array_map('intval', array_column($x['revisit'], 'n')) !== array_map('intval', array_keys($units))) {
            $errors[] = 'The exit check must say which unit to go back to for each unit of the class.';
        }

        // The teacher guide: a note for every unit, written about what MAY be seen, and the times add up.
        $teacher = $parts['teacher'][0]['content'];
        $noted = array_map('intval', array_column($teacher['interventions'], 'n'));
        foreach (array_keys($units) as $n) {
            if (!in_array((int) $n, $noted, true)) {
                $errors[] = 'The teacher guide has nothing for the unit "' . $units[$n]['title'] . '".';
            }
        }
        foreach ($teacher['interventions'] as $iv) {
            foreach (RemedialFrameWriter::teacherProblems($iv) as $problem) {
                $errors[] = 'Teacher guide, "' . $iv['name'] . '": ' . $problem;
            }
        }
        if ((int) $teacher['total_minutes'] !== array_sum(array_map('intval', array_column($teacher['pacing'], 'minutes')))) {
            $errors[] = 'The teacher guide\'s total time does not add up.';
        }
    }

    /**
     * The concepts a purpose-based document names in its markup. A revision pack names every concept; a remedial class names the
     * ones it teaches, tables, asks about or writes guidance for (the rest are named in the table's last line, which the document
     * rules above hold it to).
     *
     * @param array<string,mixed> $d
     * @param array<int,array<string,mixed>> $concepts learning-map concepts of the scope, by id
     * @return array<int,array<string,mixed>>
     */
    private function namedConcepts(array $d, array $concepts): array
    {
        if (($d['kind'] ?? '') !== 'remedial') {
            return $concepts;
        }
        $named = [];
        foreach ($d['sections'] as $s) {
            foreach ($s['taught_concept_ids'] ?? [] as $id) {
                $named[(int) $id] = true;
            }
            if (in_array($s['type'], ['gaps', 'clinic', 'teacher'], true)) {
                foreach (array_merge(array_column($s['content']['rows'] ?? [], 'concept_id'), array_column($s['content']['items'] ?? [], 'concept_id'), array_column($s['content']['interventions'] ?? [], 'concept_id')) as $id) {
                    $named[(int) $id] = true;
                }
            }
            foreach ($s['activities'] ?? [] as $a) {
                if (!empty($a['concept_id'])) {
                    $named[(int) $a['concept_id']] = true;
                }
            }
        }

        return array_intersect_key($concepts, $named);
    }

    /**
     * Does the chapter's data list a misconception for any of these concepts?
     *
     * @param array<string,mixed> $map
     * @param array<int,int> $concepts
     */
    private function anyMisconceptionListed(array $map, array $concepts): bool
    {
        foreach ($concepts as $id) {
            if (!empty($map['concepts'][$id]['misconceptions'])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int,array<int,array<string,mixed>>> $eligible */
    private function revision(array $map, array $scope, array $eligible, array $d, array &$errors, array &$warnings): void
    {
        $this->revisionOverview($d, $errors);

        $notes = [];
        foreach ($d['sections'] as $s) {
            if ($s['type'] === 'note') {
                $notes[$s['taught_concept_ids'][0] ?? 0] = $s;
            }
        }
        $checklist = 0;
        $terms = 0;
        foreach ($scope as $id) {
            $note = $notes[$id] ?? null;
            if ($note === null) {
                $errors[] = 'Concept ' . ($map['concepts'][$id]['name'] ?? $id) . ' has no revision note.';
                continue;
            }
            foreach (RevisionNotesWriter::problems($note['content'], ['misconceptions' => $map['concepts'][$id]['misconceptions']], self::isCompact($d)) as $problem) {
                $errors[] = 'Note "' . $note['title'] . '": ' . $problem;
            }
            $checklist += count($note['content']['checklist']);
            $terms += $note['content']['definition'] !== null ? 1 : 0;
        }
        if ($scope && $terms / count($scope) < 0.5) {
            $warnings[] = 'Fewer than half of the concepts have a definition in the chapter text, so the key terms list is short.';
        }
        if ($checklist < count($scope)) {
            $errors[] = 'The revision checklist has fewer statements than there are concepts.';
        }
    }

    /** @param array<int,array<int,array<string,mixed>>> $eligible */
    private function remedial(array $map, array $scope, array $eligible, array $d, array &$errors, array &$warnings): void
    {
        $byId = [];
        foreach ($eligible as $list) {
            foreach ($list as $q) {
                $byId[$q['id']] = $q;
            }
        }
        $units = [];
        foreach ($d['sections'] as $s) {
            if ($s['type'] === 'unit') {
                $units[$s['taught_concept_ids'][0] ?? 0] = $s;
            }
        }

        foreach ($scope as $id) {
            $unit = $units[$id] ?? null;
            if ($unit === null) {
                $errors[] = 'Concept ' . ($map['concepts'][$id]['name'] ?? $id) . ' has no remedial unit.';
                continue;
            }
            $c = $unit['content'];
            $concept = $map['concepts'][$id];

            $practice = array_map(fn ($g) => ['question_id' => $g['question_id'], 'hint' => $g['hint'], 'not_options' => $g['not_options']], $c['guided']);
            $work = [
                'misconceptions' => array_map(fn ($m) => ['wrong_idea' => $m['wrong_idea'], 'what_learners_think' => $m['statement']], $concept['misconceptions']),
                'practice' => array_map(fn ($g) => ['question_id' => $g['question_id'], 'model_answer' => $byId[$g['question_id']]['answer_text'] ?? null], $c['guided']),
            ];
            $as = [
                'simple_explanation' => $c['simple_explanation'], 'steps' => $c['steps'], 'mistakes' => $c['mistakes'],
                'win' => $c['win'], 'worked_example' => $c['worked_example'], 'practice' => $practice,
            ];
            foreach (RemedialWriter::problems($as, $work, $this->checker, self::isCompact($d)) as $problem) {
                $errors[] = 'Unit "' . $unit['title'] . '": ' . $problem;
            }

            $levels = array_column($c['guided'], 'level');
            if ($levels !== array_values(array_unique($levels)) || $levels !== range(1, count($levels)) && $levels !== []) {
                $errors[] = 'Unit "' . $unit['title'] . '": the practice levels must run 1, 2, 3 in order.';
            }
            $needs = array_values(array_filter($concept['requires'], fn ($r) => isset($map['concepts'][$r])));
            if (array_column($c['prerequisites'], 'concept_id') !== $needs) {
                $errors[] = 'Unit "' . $unit['title'] . '": the prerequisites listed are not the concept\'s prerequisites.';
            }
            if (empty($c['follow_up'])) {
                $errors[] = 'Unit "' . $unit['title'] . '": it says nothing about what to revisit.';
            }
            // A compact class asks a representative few (and says so once, in the questions' own warning); a full one says which unit has none.
            if ($c['guided'] === [] && !self::isCompact($d)) {
                $warnings[] = 'Unit "' . $unit['title'] . '" has no bank question to practise with; it ends with its steps and its win only.';
            }
        }
    }

    private function activities(array $map, array $scope, array $d, array &$errors): void
    {
        $plan = [];
        $parts = [];
        foreach ($d['sections'] as $s) {
            if ($s['type'] === 'activity') {
                $c = $s['content'];
                $plan[] = ['id' => 'a' . $s['n'], 'title' => $s['title'], 'format' => $c['format'], 'grouping' => $c['grouping'], 'minutes' => (int) $c['minutes'], 'concept_ids' => $s['concept_ids'], 'focus' => (string) $c['focus']];
                $parts[] = $s;
            }
        }
        $compact = self::isCompact($d);
        [$min, $max] = ActivityWriter::range(count($scope), $compact);
        foreach (ActivityWriter::planProblems($plan, $scope, $map, $min, $max, $compact) as $problem) {
            $errors[] = $problem;
        }

        $total = 0;
        foreach ($parts as $s) {
            $c = $s['content'];
            $total += (int) $c['minutes'];
            $work = [
                'concept_ids' => $s['concept_ids'],
                'minutes' => (int) $c['minutes'],
                'format' => $c['format'],
                'concepts' => array_map(fn ($id) => ['misconceptions' => $map['concepts'][$id]['misconceptions'] ?? []], $s['concept_ids']),
            ];
            foreach (ActivityWriter::problems($c + ['interaction' => $s['interaction']], $work, $this->checker, $compact) as $problem) {
                $errors[] = 'Activity "' . $s['title'] . '": ' . $problem;
            }
        }
        if ((int) ($d['sections'][0]['content']['total_minutes'] ?? -1) !== $total) {
            $errors[] = 'The run sheet\'s total time does not add up.';
        }
    }

    // ---------------------------------------------------------------------------------------------------------

    /**
     * The content design system's markup rules, then grounding.
     *
     * Grounding reads the wording of the document as the design-system markup says it. Two kinds of number are not
     * claims about the subject and are left out of that pass: structural numbers (an activity's number, its
     * minutes) and procedure (group sizes, counts and times in the instructions to teacher and students). The
     * procedure is then checked separately for any number a classroom instruction would not need (a large number, a
     * decimal) that the chapter does not contain.
     *
     * @param array<int,array<int,array<string,mixed>>> $eligible
     */
    private function wording(DocumentKind $kind, array $context, array $map, array $scope, array $eligible, array $d, string $html, array &$errors, array &$warnings): void
    {
        $levels = count($scope) >= 6 ? 3 : (count($scope) >= 3 ? 2 : 1);
        $deck = new DeckValidator(minBloom: $levels);
        $scoped = ['concepts' => array_intersect_key($map['concepts'], array_flip($scope))];
        if (self::isPurpose($d)) {
            $scoped['concepts'] = $this->namedConcepts($d, $scoped['concepts']);
        }

        $firstNew = count($errors);
        $deck->markup($html, $scoped, ['slides' => []], $errors);
        $this->locateLongLists($html, $errors, $firstNew);

        $labels = [];
        foreach ($d['sections'] as $s) {
            if (is_array($s['image'] ?? null)) {
                $labels[] = implode(' ', (array) ($s['image']['texts'] ?? []));
            }
        }
        $deck->groundingCheck($html, ' ' . implode(' ', $labels), $context, $eligible, $errors, $warnings, [
            '//*[contains(@class,"doc-num")]',
            '//*[contains(@class,"doc-proc")]',
        ]);

        if ($kind === DocumentKind::Activities) {
            $procedure = [];
            foreach ($d['sections'] as $s) {
                if ($s['type'] !== 'activity') {
                    continue;
                }
                $c = $s['content'];
                $procedure = array_merge($procedure, $c['materials'], array_column($c['teacher_steps'], 'text'), $c['student_steps'], [(string) ($c['setup'] ?? '')]);
            }
            $text = (string) preg_replace('/(?<![\d.,])([1-9]|[1-5][0-9]|60)(?![\d]|[.,]\d)/', '', implode(' ', $procedure));
            $deck->groundingCheck('<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>', '', $context, $eligible, $errors, $warnings);
        }
    }

    /**
     * The deck's markup check says only that "a list" is too long. A document is long, so say which one: the heading
     * it sits under and how many items it has, so the person reading the report (or the writer's repair) can find it.
     *
     * @param array<int,string> $errors
     */
    private function locateLongLists(string $html, array &$errors, int $from): void
    {
        $generic = array_keys(array_filter(array_slice($errors, $from, null, true), fn ($e) => str_starts_with($e, 'A list exceeds ')));
        if ($generic === []) {
            return;
        }

        $limit = (int) preg_replace('/\D/', '', $errors[$generic[0]]);
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        $x = new \DOMXPath($dom);

        $located = [];
        foreach ($x->query('//ul|//ol') as $list) {
            $items = $x->query('./li', $list)->length;
            if ($items <= $limit) {
                continue;
            }
            $heading = $x->query('preceding::*[self::h2 or self::h3][1]', $list)->item(0);
            $where = $heading ? ' under "' . trim(preg_replace('/\s+/', ' ', (string) $heading->textContent)) . '"' : '';
            $located[] = "A list of $items items$where exceeds $limit bullets.";
        }

        foreach ($generic as $i) {
            unset($errors[$i]);
        }
        // The generic message stays when the list could not be found again, so the problem is never lost.
        $errors = array_values(array_merge($errors, $located ?: ['A list exceeds ' . $limit . ' bullets.']));
    }

    /** @return array<string,mixed> */
    private function stats(array $d): array
    {
        $sections = $d['sections'] ?? [];
        $count = fn (string $type) => count(array_filter($sections, fn ($s) => ($s['type'] ?? '') === $type));

        return [
            'sections' => count($sections),
            'concepts' => count($d['scope']['concept_ids'] ?? []),
            'notes' => $count('note'),
            'topics' => $count('topic'),
            'units' => $count('unit'),
            'activities' => $count('activity'),
            'questions' => array_sum(array_map(fn ($s) => count($s['question_ids'] ?? []), $sections)),
            'diagrams' => count(array_filter($sections, fn ($s) => ($s['image'] ?? null) !== null)),
            'interactions' => count(array_filter($sections, fn ($s) => ($s['interaction'] ?? null) !== null)),
            'minutes' => array_sum(array_map(fn ($s) => (int) ($s['content']['minutes'] ?? 0), $sections)),
        ];
    }
}
