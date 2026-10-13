<?php

namespace App\Services\StudyDeck\Documents\Writers;

/**
 * Writes the wording of a set of revision notes the way they are used: one SHEET for every topic, not one card for every
 * concept.
 *
 * Revision is for a learner who has already studied the chapter and is preparing for an examination. They need the
 * chapter in a form they can scan: the one fact that matters about each concept, the terms, the things that are easy to
 * confuse set side by side, a few facts to fix in memory, and a way to check themselves. They do not need the idea
 * explained again (that is what a remedial class is for), so nothing here teaches: a concept gets a single line, a topic
 * gets a table where the chapter compares things, and the explanation, the steps and the worked examples are left out
 * on purpose. The same chapter data feeds the remedial class, but it asks different questions of it.
 *
 * The model writes structured fields; the layout, the numbering, the glossary and the practice questions are assembled
 * by code from the same data. The questions are NOT written here: they are the bank's own (QuestionPlacement::forCheck).
 */
class TopicRevisionWriter extends RevisionNotesWriter
{
    public const BIG_IDEA_WORDS = [5, 30];

    public const ESSENTIAL_WORDS = [6, 32];

    public const TERMS_PER_ROW = 3;

    public const TERM_WORDS = 5;

    public const COMPARE_COLUMNS = [2, 4];

    public const COMPARE_ROWS = [2, 5];

    public const CELL_WORDS = 16;

    public const MIXUPS = 2;

    public const WRONG_WORDS = 20;

    public const CORRECT_WORDS = 26;

    public const RECALL = 3;

    public const RECALL_WORDS = 24;

    public const CHECKLIST = [1, 2];

    public const GLOSSARY = 4;

    public const MEANING_WORDS = [3, 28];

    /** Diagram layouts a revision sheet draws: the ones that set things side by side or around an idea (a remedial class draws processes). */
    public const LAYOUTS = ['compare', 'hub'];

    private const SHEET_SYSTEM = 'You are an experienced teacher preparing one-page exam revision sheets for the topics of a textbook chapter. '
        . 'You reply with a single JSON object and nothing else. You never add facts that are not in the chapter text, and you never teach: you condense.';

    public function __construct(\App\Services\StudyDeck\Contracts\Completer $completer, int $chunkSize = 2)
    {
        parent::__construct($completer, $chunkSize);
    }

    /**
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @param array<string,mixed> $map LearningPlanBuilder::build()
     * @param array<int,int> $scope the concepts to write, in teaching order
     * @param array<int,array<int,array<string,mixed>>> $placement unused (the practice questions are placed by code)
     * @param callable(int,int):void|null $progress
     * @return array{overview:array<string,mixed>, topics:array<int,array<string,mixed>>}
     */
    public function write(array $context, array $map, array $scope, array $placement = [], ?callable $progress = null): array
    {
        $overview = $this->overview($context, $map, $scope);

        $name = fn (int $id) => (string) ($map['concepts'][$id]['name'] ?? '');
        $work = [];
        foreach ($map['topics'] as $t) {
            $ids = array_values(array_intersect($t['concept_ids'], $scope));
            if ($ids === []) {
                continue;
            }
            $work[$t['id']] = [
                'topic_id' => $t['id'],
                'name' => $t['name'],
                'concepts' => array_map(function ($id) use ($map, $name) {
                    $c = $map['concepts'][$id];

                    return [
                        'concept_id' => $id,
                        'name' => $c['name'],
                        'definition' => $c['definition'],
                        'key_terms' => $c['terms'],
                        'knowledge' => array_slice($c['knowledge'], 0, 4),
                        'objectives' => array_slice($c['objectives'], 0, 2),
                        // Only the first listed misconception: a remedial class settles the LAST one, so the two documents never take the same.
                        'misconceptions' => array_map(fn ($m) => ['wrong_idea' => $m['wrong_idea'], 'correction' => $m['correction']], array_slice($c['misconceptions'], 0, 1)),
                        'builds_on' => array_values(array_filter(array_map($name, $c['requires']))),
                    ];
                }, $ids),
            ];
        }

        $chapterText = (string) $context['ground_truth'];
        $sheets = $this->inChunks(
            $work,
            self::SHEET_SYSTEM,
            12000,
            'revision sheets',
            fn (array $chunk) => $this->sheetPrompt($context, $chunk),
            fn (array $json, array $chunk) => $this->parseSheets($json, $chunk),
            fn (array $sheet, array $item) => self::sheetProblems($sheet, $item, $chapterText),
            $progress
        );

        return ['overview' => $overview, 'topics' => self::dedupe($this->finalise($sheets, $work))];
    }

    /**
     * What is wrong with one topic sheet, as instructions to the model. The validator holds the finished document to the
     * same rules, so a sheet that leaves the writer clean is clean there.
     *
     * @param array<string,mixed> $sheet a cleaned sheet
     * @param array<string,mixed> $work the work item it was written for
     * @return array<int,string>
     */
    public static function sheetProblems(array $sheet, array $work, string $chapterText = ''): array
    {
        $out = [];

        $w = self::words($sheet['big_idea']);
        if ($w < self::BIG_IDEA_WORDS[0] || $w > self::BIG_IDEA_WORDS[1]) {
            $out[] = 'The big idea has ' . $w . ' words; it must be ' . self::BIG_IDEA_WORDS[0] . ' to ' . self::BIG_IDEA_WORDS[1] . '.';
        }

        $wanted = array_column($work['concepts'], 'concept_id');
        $got = array_keys($sheet['rows']);
        foreach (array_diff($wanted, $got) as $missing) {
            $out[] = 'There is no row for concept ' . $missing . '; write one row for every concept of the topic.';
        }
        foreach ($sheet['rows'] as $id => $row) {
            $n = self::words($row['essential']);
            if ($n < self::ESSENTIAL_WORDS[0] || $n > self::ESSENTIAL_WORDS[1]) {
                $out[] = 'The row for concept ' . $id . ' has ' . $n . ' words; it must be ' . self::ESSENTIAL_WORDS[0] . ' to ' . self::ESSENTIAL_WORDS[1] . ' and state the one fact to remember, not explain it.';
            }
            foreach ($row['terms'] as $term) {
                if (self::words($term) > self::TERM_WORDS) {
                    $out[] = 'A key term in the row for concept ' . $id . ' is over ' . self::TERM_WORDS . ' words ("' . mb_substr($term, 0, 40) . '...").';
                }
            }
        }

        if ($sheet['compare'] !== null) {
            $c = $sheet['compare'];
            if (count($c['columns']) < self::COMPARE_COLUMNS[0] || count($c['columns']) > self::COMPARE_COLUMNS[1]) {
                $out[] = 'The comparison table needs ' . self::COMPARE_COLUMNS[0] . ' to ' . self::COMPARE_COLUMNS[1] . ' columns.';
            }
            if (count($c['rows']) < self::COMPARE_ROWS[0] || count($c['rows']) > self::COMPARE_ROWS[1]) {
                $out[] = 'The comparison table needs ' . self::COMPARE_ROWS[0] . ' to ' . self::COMPARE_ROWS[1] . ' rows.';
            }
            foreach ($c['rows'] as $row) {
                if (count($row) !== count($c['columns'])) {
                    $out[] = 'Every row of the comparison table needs exactly one cell for each column.';
                    break;
                }
                foreach ($row as $cell) {
                    if (self::words($cell) > self::CELL_WORDS) {
                        $out[] = 'A cell of the comparison table is over ' . self::CELL_WORDS . ' words ("' . mb_substr($cell, 0, 40) . '...").';
                        break 2;
                    }
                }
            }
        }

        $listed = [];
        foreach ($work['concepts'] as $c) {
            $listed = array_merge($listed, array_column($c['misconceptions'] ?? [], 'wrong_idea'));
        }
        if (count($sheet['mixups']) > self::MIXUPS) {
            $out[] = 'At most ' . self::MIXUPS . ' mix-ups.';
        }
        foreach ($sheet['mixups'] as $m) {
            if (!self::traces($m['wrong_idea'], $listed)) {
                $out[] = 'The mix-up "' . mb_substr($m['wrong_idea'], 0, 40) . '..." is not one of the listed misconceptions; use a listed one (same idea, tidier words) or leave it out.';
            }
            if (self::words($m['wrong_idea']) > self::WRONG_WORDS || self::words($m['correct']) > self::CORRECT_WORDS || self::words($m['correct']) < 4) {
                $out[] = 'A mix-up must be at most ' . self::WRONG_WORDS . ' words for the wrong idea and 4 to ' . self::CORRECT_WORDS . ' for what is true.';
            }
        }

        if (count($sheet['recall']) > self::RECALL) {
            $out[] = 'At most ' . self::RECALL . ' recall points.';
        }
        foreach ($sheet['recall'] as $r) {
            if (self::words($r) > self::RECALL_WORDS) {
                $out[] = 'A recall point is over ' . self::RECALL_WORDS . ' words ("' . mb_substr($r, 0, 40) . '...").';
            }
            foreach ($sheet['rows'] as $row) {
                if (self::overlap($r, $row['essential']) >= 0.8) {
                    $out[] = 'The recall point "' . mb_substr($r, 0, 40) . '..." only repeats a row; give a different fact or leave it out.';
                    break;
                }
            }
        }

        if (count($sheet['checklist']) < self::CHECKLIST[0] || count($sheet['checklist']) > self::CHECKLIST[1]) {
            $out[] = 'Write ' . self::CHECKLIST[0] . ' or ' . self::CHECKLIST[1] . ' checklist statements starting "I can".';
        }
        foreach ($sheet['checklist'] as $c) {
            if (!preg_match('/^I can\b/i', $c)) {
                $out[] = 'The checklist statement "' . mb_substr($c, 0, 40) . '..." must start "I can".';
            }
        }

        if (count($sheet['terms']) > self::GLOSSARY) {
            $out[] = 'At most ' . self::GLOSSARY . ' glossary terms.';
        }
        foreach ($sheet['terms'] as $t) {
            if (!in_array($t['concept_id'], $wanted, true)) {
                $out[] = 'The term "' . $t['term'] . '" names a concept that is not in this topic.';
            }
            $m = self::words($t['meaning']);
            if ($m < self::MEANING_WORDS[0] || $m > self::MEANING_WORDS[1]) {
                $out[] = 'The meaning of "' . $t['term'] . '" has ' . $m . ' words; it must be ' . self::MEANING_WORDS[0] . ' to ' . self::MEANING_WORDS[1] . '.';
            }
            // The glossary is vocabulary the learner has to know, not the titles of the concepts (each of those already has its row).
            $title = mb_strtolower(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $t['term'])));
            foreach ($work['concepts'] as $c) {
                if ($title === mb_strtolower(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string) $c['name'])))) {
                    $out[] = 'The term "' . $t['term'] . '" is the title of a concept; a glossary term is a word or phrase the learner must know, with its meaning.';
                    break;
                }
            }
            if ($chapterText !== '' && mb_stripos($chapterText, $t['term']) === false && !self::traces($t['term'], array_merge(array_column($work['concepts'], 'name'), ...array_column($work['concepts'], 'key_terms')))) {
                $out[] = 'The term "' . $t['term'] . '" is not a word or phrase of the chapter text; use only terms the chapter names.';
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------------------------------------------------
    // The document as a whole: what it must not say twice

    /** The least content words a sentence needs before it can be called a repeat of another (a short phrase repeats nothing). */
    private const REPEAT_WORDS = 4;

    /** How much of a sentence another must hold for the two to be the same statement (the share of its content words found in the other). */
    private const REPEAT_SHARE = 0.7;

    /**
     * Drops what one topic's sheet says that the document already says. The model writes a sheet at a time, so it cannot see
     * that a recall point of topic 4 is a row of topic 5, or that the glossary's meaning for a term is the sentence already in
     * that concept's row. Said twice, it costs the learner a line to read and the pack a few lines of page, and says nothing new.
     *
     *   recall    dropped when it repeats any row of the document or an earlier recall point
     *   terms     dropped when the meaning repeats any row, any cell of a comparison table or an earlier meaning
     *   mixups    dropped when they repeat an earlier mix-up
     *
     * The rows and the comparison tables are what stay: they are the sheet. Pure and idempotent, so a draft from an earlier run can
     * be put through it again.
     *
     * @param array<int,array<string,mixed>> $sheets the sheets by topic id, in the order of the document
     * @return array<int,array<string,mixed>>
     */
    public static function dedupe(array $sheets): array
    {
        $said = [];
        foreach ($sheets as $sheet) {
            foreach ($sheet['rows'] as $row) {
                $said[] = $row['essential'];
            }
            foreach ($sheet['compare']['rows'] ?? [] as $cells) {
                foreach ($cells as $cell) {
                    $said[] = (string) $cell;
                }
            }
        }

        $recalled = [];
        $defined = [];
        $mixed = [];
        foreach ($sheets as $id => $sheet) {
            $sheets[$id]['recall'] = array_values(array_filter($sheet['recall'], function ($r) use ($said, &$recalled) {
                if (self::repeats($r, array_merge($said, $recalled))) {
                    return false;
                }
                $recalled[] = $r;

                return true;
            }));
            $sheets[$id]['terms'] = array_values(array_filter($sheet['terms'], function ($t) use ($said, &$defined) {
                if (self::repeats($t['meaning'], array_merge($said, $defined))) {
                    return false;
                }
                $defined[] = $t['meaning'];

                return true;
            }));
            $sheets[$id]['mixups'] = array_values(array_filter($sheet['mixups'], function ($m) use (&$mixed) {
                if (self::repeats($m['wrong_idea'] . ' ' . $m['correct'], $mixed)) {
                    return false;
                }
                $mixed[] = $m['wrong_idea'] . ' ' . $m['correct'];

                return true;
            }));
        }

        return $sheets;
    }

    /**
     * Does the text say what one of the others already says: most of the content words of either are in the other?
     *
     * @param array<int,string> $others
     */
    private static function repeats(string $text, array $others): bool
    {
        if (self::contentWords($text) < self::REPEAT_WORDS) {
            return false;
        }
        foreach ($others as $other) {
            if (self::contentWords($other) >= self::REPEAT_WORDS && max(self::overlap($text, $other), self::overlap($other, $text)) >= self::REPEAT_SHARE) {
                return true;
            }
        }

        return false;
    }

    /** How many words of more than three letters a text has (the ones that carry what it says). */
    private static function contentWords(string $text): int
    {
        return count(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [], fn ($w) => mb_strlen($w) > 3));
    }

    // ---------------------------------------------------------------------------------------------------------

    /**
     * @param array<int,array<string,mixed>> $chunk work items by topic id
     */
    private function sheetPrompt(array $context, array $chunk): string
    {
        $input = json_encode(array_values($chunk), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        [$bigMin, $bigMax] = self::BIG_IDEA_WORDS;
        [$essMin, $essMax] = self::ESSENTIAL_WORDS;
        [$colMin, $colMax] = self::COMPARE_COLUMNS;
        [$rowMin, $rowMax] = self::COMPARE_ROWS;
        [$meanMin, $meanMax] = self::MEANING_WORDS;
        $termWords = self::TERM_WORDS;
        $cell = self::CELL_WORDS;
        $wrong = self::WRONG_WORDS;
        $correct = self::CORRECT_WORDS;
        $recall = self::RECALL_WORDS;
        $glossary = self::GLOSSARY;
        $ground = self::GROUND_RULES;

        return <<<PROMPT
Write the REVISION SHEET for EVERY topic listed under TOPICS TO WRITE. A learner uses it in the days before an examination, after they have already studied the chapter. It is NOT a lesson and NOT a re-teaching: no explanations of why, no step-by-step teaching, no worked examples, no practice. It condenses the chapter into what can be scanned and recalled: the one fact that matters about each concept, the terms, the things that are easy to confuse set side by side, and a few facts to fix in memory.

Rules
{$ground}
- "big_idea": the one thing to remember about the topic, one sentence of {$bigMin} to {$bigMax} words.
- "rows": one entry for EVERY concept of the topic, in the order given: {"concept_id": id, "essential": "...", "terms": ["..."]}. "essential" is the single fact, rule, definition or distinction an examiner asks about that concept: one sentence of {$essMin} to {$essMax} words, in the chapter text's own wording where the chapter defines the concept. Do not explain it, do not give an example, and do not just repeat the concept's name. "terms": 0 to 3 key words or short phrases from the chapter that a learner must be able to use, each at most {$termWords} words.
- "compare": ONLY where the chapter text sets two or more things side by side (kinds of something, a classification, a difference, stages in order) so that a table makes them easier to recall. The table must show a CONTRAST or a classification the rows do not already give: it is not the rows again in cells. {"title": "...", "columns": [{$colMin} to {$colMax} headings], "rows": [{$rowMin} to {$rowMax} rows, each an array with EXACTLY one short cell per column, at most {$cell} words]}. null when the chapter text gives nothing to compare. Every cell is a fact from the chapter text.
- "mixups": 0 to 2 pairs of ideas learners confuse, ONLY from the concepts' listed misconceptions (same idea, tidier words): {"wrong_idea": "at most {$wrong} words", "correct": "what is true, at most {$correct} words"}. [] when none are listed.
- "recall": 0 to 3 short facts to fix in memory that are about THIS topic's own concepts and are NOT already a row's "essential" (nor said in another topic): a list the chapter gives, a formula, a rule, or a named example. Each at most {$recall} words. [] when the chapter gives none beyond the rows.
- "checklist": 1 or 2 statements starting "I can", built from the listed objectives of the topic's concepts: what the learner should be able to do before the examination.
- "terms": the topic's vocabulary for a glossary, 0 to {$glossary} entries {"concept_id": id, "term": "...", "meaning": "{$meanMin} to {$meanMax} words"}. A term is a word or short phrase the learner must be able to USE (for example a name the chapter introduces), never the title of a concept (each concept already has its row) and never a sentence. Its meaning is a DEFINITION in the chapter text's own words: what the term means, as a whole sentence or a noun phrase ("A ... that ..."), never a fragment and never the sentence of a row said again. 0 entries is right when the topic has no vocabulary beyond its rows.
- "diagram" and "diagram_notes": ONLY where the topic has several named parts, kinds or relations that the chapter lists and a picture would help recall; at most ONE in this whole reply, and about one topic in three has one (null and [] for the rest); do not draw what the comparison table of the same topic already shows. A diagram is {"layout": "compare" | "hub", "title": "...", ...}: hub has "center" and "nodes" (3 to 6 labels); compare has "left" and "right", each {"heading": "...", "items": [2 to 5 labels]}. Every label uses words from the chapter text and is at most 40 characters. "diagram_notes" has one entry for EVERY label drawn: [{"label": "the label exactly", "text": "5 to 25 words saying what it is"}].

Reply with:
{"topics": [{"topic_id": 0, "big_idea": "", "rows": [{"concept_id": 0, "essential": "", "terms": [""]}], "compare": null, "mixups": [{"wrong_idea": "", "correct": ""}], "recall": [""], "checklist": ["I can ..."], "terms": [{"concept_id": 0, "term": "", "meaning": ""}], "diagram": null, "diagram_notes": []}]}

TOPICS TO WRITE
{$input}

{$this->chapterBlock($context)}
PROMPT;
    }

    /**
     * @param array<string,mixed> $json
     * @param array<int,array<string,mixed>> $chunk
     * @return array<int,array<string,mixed>>
     */
    private function parseSheets(array $json, array $chunk): array
    {
        $out = [];
        foreach ((array) ($json['topics'] ?? []) as $t) {
            $id = (int) ($t['topic_id'] ?? 0);
            if (!is_array($t) || !isset($chunk[$id]) || $this->str($t['big_idea'] ?? '') === '') {
                continue;
            }
            $out[$id] = $this->cleanSheet($t, $chunk[$id]);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $t
     * @param array<string,mixed> $work
     * @return array<string,mixed>
     */
    private function cleanSheet(array $t, array $work): array
    {
        $wanted = array_column($work['concepts'], 'concept_id');

        // Rows in the order of the topic's concepts, whatever order the model used; a concept it named twice keeps its first row.
        $rows = [];
        foreach ((array) ($t['rows'] ?? []) as $r) {
            $id = (int) ($r['concept_id'] ?? 0);
            if (is_array($r) && in_array($id, $wanted, true) && !isset($rows[$id]) && $this->str($r['essential'] ?? '') !== '') {
                $rows[$id] = ['essential' => $this->str($r['essential']), 'terms' => $this->strings($r['terms'] ?? [], self::TERMS_PER_ROW)];
            }
        }
        $rows = array_replace(array_intersect_key(array_flip($wanted), $rows), $rows);

        $compare = null;
        $c = $t['compare'] ?? null;
        if (is_array($c)) {
            $columns = $this->strings($c['columns'] ?? [], self::COMPARE_COLUMNS[1]);
            $table = [];
            foreach ((array) ($c['rows'] ?? []) as $row) {
                $cells = array_map(fn ($x) => $this->str($x), array_values((array) $row));
                if (count($cells) === count($columns) && !in_array('', $cells, true)) {
                    $table[] = $cells;
                }
            }
            $compare = $this->str($c['title'] ?? '') !== '' && $columns !== [] ? ['title' => $this->str($c['title']), 'columns' => $columns, 'rows' => array_slice($table, 0, self::COMPARE_ROWS[1])] : null;
        }

        $mixups = [];
        foreach ((array) ($t['mixups'] ?? []) as $m) {
            if (is_array($m) && $this->str($m['wrong_idea'] ?? '') !== '' && $this->str($m['correct'] ?? '') !== '') {
                $mixups[] = ['wrong_idea' => $this->str($m['wrong_idea']), 'correct' => $this->str($m['correct'])];
            }
        }

        $terms = [];
        foreach ((array) ($t['terms'] ?? []) as $x) {
            if (is_array($x) && $this->str($x['term'] ?? '') !== '' && $this->str($x['meaning'] ?? '') !== '') {
                $terms[] = ['concept_id' => (int) ($x['concept_id'] ?? 0), 'term' => $this->str($x['term']), 'meaning' => $this->str($x['meaning'])];
            }
        }

        [$diagram, $notes] = $this->diagram($t['diagram'] ?? null, $t['diagram_notes'] ?? []);
        if ($diagram !== null && !in_array($diagram['layout'], self::LAYOUTS, true)) {
            [$diagram, $notes] = [null, []];
        }

        return [
            'big_idea' => $this->str($t['big_idea']),
            'rows' => $rows,
            'compare' => $compare,
            'mixups' => array_slice($mixups, 0, self::MIXUPS + 2),
            'recall' => $this->strings($t['recall'] ?? [], self::RECALL + 2),
            'checklist' => $this->strings($t['checklist'] ?? [], self::CHECKLIST[1] + 2),
            'terms' => array_slice($terms, 0, self::GLOSSARY + 2),
            'diagram' => $diagram,
            'diagram_notes' => $notes,
        ];
    }

    /**
     * What the sheets end up as once the soft rules have had their say. A mix-up that is not a listed misconception, a
     * recall point that only repeats a row, and a term the chapter does not name are dropped rather than printed: none is
     * worth failing a pack for and none is worth printing. Lists are cut to their limits.
     *
     * @param array<int,array<string,mixed>> $sheets
     * @param array<int,array<string,mixed>> $work
     * @return array<int,array<string,mixed>>
     */
    private function finalise(array $sheets, array $work): array
    {
        foreach ($sheets as $id => $sheet) {
            $listed = [];
            foreach ($work[$id]['concepts'] as $c) {
                $listed = array_merge($listed, array_column($c['misconceptions'], 'wrong_idea'));
            }
            $sheets[$id]['mixups'] = array_slice(array_values(array_filter($sheet['mixups'], fn ($m) => self::traces($m['wrong_idea'], $listed))), 0, self::MIXUPS);
            $sheets[$id]['recall'] = array_slice(array_values(array_filter($sheet['recall'], function ($r) use ($sheet) {
                foreach ($sheet['rows'] as $row) {
                    if (self::overlap($r, $row['essential']) >= 0.8) {
                        return false;
                    }
                }

                return true;
            })), 0, self::RECALL);
            $sheets[$id]['checklist'] = array_slice($sheet['checklist'], 0, self::CHECKLIST[1]);
            $sheets[$id]['terms'] = array_slice(array_values(array_filter($sheet['terms'], fn ($t) => in_array($t['concept_id'], array_column($work[$id]['concepts'], 'concept_id'), true))), 0, self::GLOSSARY);
        }

        return $sheets;
    }
}
