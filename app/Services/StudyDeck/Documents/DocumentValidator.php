<?php

namespace App\Services\StudyDeck\Documents;

use App\Services\StudyDeck\ActivityPlanner;
use App\Services\StudyDeck\DeckValidator;
use App\Services\StudyDeck\Documents\Writers\ActivityWriter;
use App\Services\StudyDeck\Documents\Writers\RemedialWriter;
use App\Services\StudyDeck\Documents\Writers\RevisionNotesWriter;
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

        match ($kind) {
            DocumentKind::RevisionNotes => $this->revision($map, $scope, $eligible, $document, $errors, $warnings),
            DocumentKind::Remedial => $this->remedial($map, $scope, $eligible, $document, $errors, $warnings),
            DocumentKind::Activities => $this->activities($map, $scope, $document, $errors),
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
    }

    /** Every concept in scope is covered; none is invented. */
    private function coverage(array $map, array $scope, array $d, array &$errors): void
    {
        $known = array_keys($map['concepts']);
        $uncovered = [];
        foreach ($scope as $id) {
            if (empty($d['taught_by'][$id])) {
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
        $seen = [];

        foreach ($d['sections'] ?? [] as $s) {
            $n = $s['n'];
            $ids = $s['question_ids'] ?? [];
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
        if ($kind !== DocumentKind::Activities) {
            foreach ($eligible as $conceptId => $list) {
                if ($list && empty($d['concept_questions'][$conceptId]) && in_array($conceptId, $d['scope']['concept_ids'] ?? [], true)) {
                    $warnings[] = "Concept $conceptId has bank questions but the document uses none of them.";
                }
            }
        }
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
    }

    // ---------------------------------------------------------------------------------------------------------
    // Each kind's own rules: the writer's rules, applied to what was stored

    /** @param array<int,array<int,array<string,mixed>>> $eligible */
    private function revision(array $map, array $scope, array $eligible, array $d, array &$errors, array &$warnings): void
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
            foreach (RevisionNotesWriter::problems($note['content'], ['misconceptions' => $map['concepts'][$id]['misconceptions']]) as $problem) {
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
            foreach (RemedialWriter::problems($as, $work, $this->checker) as $problem) {
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
            if ($c['guided'] === []) {
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
        [$min, $max] = ActivityWriter::range(count($scope));
        foreach (ActivityWriter::planProblems($plan, $scope, $map, $min, $max) as $problem) {
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
            foreach (ActivityWriter::problems($c + ['interaction' => $s['interaction']], $work, $this->checker) as $problem) {
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
            'units' => $count('unit'),
            'activities' => $count('activity'),
            'questions' => array_sum(array_map(fn ($s) => count($s['question_ids'] ?? []), $sections)),
            'diagrams' => count(array_filter($sections, fn ($s) => ($s['image'] ?? null) !== null)),
            'interactions' => count(array_filter($sections, fn ($s) => ($s['interaction'] ?? null) !== null)),
            'minutes' => array_sum(array_map(fn ($s) => (int) ($s['content']['minutes'] ?? 0), $sections)),
        ];
    }
}
