<?php

namespace App\Services\StudyDeck\Documents\Writers;

use App\Services\StudyDeck\Documents\DocumentKind;

/**
 * Writes the wording of a set of revision notes: one concise, exam-oriented note for every concept in scope, and
 * the opening summary of the chapter.
 *
 * The model is asked for STRUCTURED fields (a gist, key points, a definition, rules to memorise, an example, a
 * "don't confuse" pair, a line to remember, "I can" checklist statements, and optionally a diagram) so the layout,
 * the numbering, the glossary, the answers and the checklist are all assembled by code from the same data, and the
 * printed notes and the online self-test can never disagree. The questions are NOT written here: they are the
 * bank's own, chosen by QuestionPlacement.
 */
class RevisionNotesWriter extends DocumentWriter
{
    public const SUMMARY_WORDS = [6, 45];

    public const POINT_WORDS = 28;

    public const POINTS = [2, 6];

    public const REMEMBER_WORDS = 25;

    private const SYSTEM = 'You are an experienced teacher writing concise exam revision notes for one textbook chapter. '
        . 'You reply with a single JSON object and nothing else. You never add facts that are not in the chapter text.';

    public function kind(): DocumentKind
    {
        return DocumentKind::RevisionNotes;
    }

    /**
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @param array<string,mixed> $map LearningPlanBuilder::build()
     * @param array<int,int> $scope the concepts to write, in teaching order
     * @param array<int,array<int,array<string,mixed>>> $placement concept_id => bank questions (not written here)
     * @param callable(int,int):void|null $progress
     * @return array{overview:array<string,mixed>, notes:array<int,array<string,mixed>>}
     */
    public function write(array $context, array $map, array $scope, array $placement = [], ?callable $progress = null): array
    {
        $overview = $this->overview($context, $map, $scope);

        $topicName = array_column($map['topics'], 'name', 'id');
        $work = [];
        foreach ($scope as $id) {
            $c = $map['concepts'][$id];
            $work[$id] = [
                'concept_id' => $id,
                'name' => $c['name'],
                'topic' => $topicName[$c['topic_id']] ?? '',
                'definition' => $c['definition'],
                'difficulty' => $c['difficulty'],
                'objectives' => $c['objectives'],
                'knowledge' => array_slice($c['knowledge'], 0, 5),
                'key_terms' => $c['terms'],
                'misconceptions' => array_map(fn ($m) => ['wrong_idea' => $m['wrong_idea'], 'correction' => $m['correction']], $c['misconceptions']),
                'real_world' => array_slice($c['real_world'], 0, 2),
                'bloom_levels' => $c['blooms'],
                'dok_levels' => $c['dok'],
                'builds_on' => array_values(array_filter(array_map(fn ($r) => $map['concepts'][$r]['name'] ?? null, $c['requires']))),
            ];
        }

        $notes = $this->inChunks(
            $work,
            self::SYSTEM,
            12000,
            'revision notes',
            fn (array $chunk) => $this->prompt($context, $chunk, count($scope)),
            fn (array $json, array $chunk) => $this->parse($json, $chunk),
            fn (array $note, array $item) => self::problems($note, $item),
            $progress
        );

        return ['overview' => $overview, 'notes' => $this->finalise($notes, $work)];
    }

    /**
     * What is wrong with one note, as instructions to the model. The same rules the validator holds the finished
     * document to, so a note that passes here passes there.
     *
     * @param array<string,mixed> $note a cleaned note
     * @param array<string,mixed> $concept the work item it was written for
     * @return array<int,string>
     */
    public static function problems(array $note, array $concept): array
    {
        $out = [];

        $w = self::words($note['summary']);
        if ($w < self::SUMMARY_WORDS[0] || $w > self::SUMMARY_WORDS[1]) {
            $out[] = 'The summary has ' . $w . ' words; it must be ' . self::SUMMARY_WORDS[0] . ' to ' . self::SUMMARY_WORDS[1] . ' and say what the concept is.';
        }

        $n = count($note['key_points']);
        if ($n < self::POINTS[0] || $n > self::POINTS[1]) {
            $out[] = 'There are ' . $n . ' key points; there must be ' . self::POINTS[0] . ' to ' . self::POINTS[1] . '.';
        }
        foreach ($note['key_points'] as $p) {
            if (self::words($p) > self::POINT_WORDS) {
                $out[] = 'A key point is over ' . self::POINT_WORDS . ' words ("' . mb_substr($p, 0, 40) . '..."); keep each to one short idea.';
            }
        }
        if (count(array_unique(array_map('mb_strtolower', $note['key_points']))) !== $n) {
            $out[] = 'Two key points are the same.';
        }

        if ($note['definition'] !== null && self::words($note['definition']['text']) < 4) {
            $out[] = 'The definition is too short to define anything; give the chapter text\'s own wording or set definition to null.';
        }

        if ($note['checklist'] === []) {
            $out[] = 'Write 1 to 3 checklist statements starting "I can".';
        }
        foreach ($note['checklist'] as $c) {
            if (!preg_match('/^I can\b/i', $c)) {
                $out[] = 'The checklist statement "' . mb_substr($c, 0, 40) . '..." must start "I can".';
            }
        }

        if ($note['remember'] !== null && self::words($note['remember']) > self::REMEMBER_WORDS) {
            $out[] = '"remember" is over ' . self::REMEMBER_WORDS . ' words; make it one line.';
        }

        if ($note['misconception'] !== null && !self::traces($note['misconception']['wrong_idea'], array_column($concept['misconceptions'], 'wrong_idea'))) {
            $out[] = 'The misconception is not one of this concept\'s listed misconceptions; use one of them (same idea, tidier words) or set it to null.';
        }

        return $out;
    }

    // ---------------------------------------------------------------------------------------------------------

    /**
     * The chapter in a paragraph and a line for each topic. If the model cannot be made to answer, the opening is
     * built from the chapter's own data instead: a document is never held up by its cover line.
     *
     * @param array<int,int> $scope
     * @return array{lede:string, summary:string, topics:array<int,string>}
     */
    private function overview(array $context, array $map, array $scope): array
    {
        $topics = [];
        foreach ($map['topics'] as $t) {
            $ids = array_values(array_intersect($t['concept_ids'], $scope));
            if ($ids) {
                $topics[] = ['topic_id' => $t['id'], 'name' => $t['name'], 'concepts' => array_map(fn ($id) => $map['concepts'][$id]['name'], $ids)];
            }
        }

        $prompt = "Write the opening of an exam revision pack for the chapter below. Nothing else is written yet.\n\n"
            . "Rules\n" . self::GROUND_RULES . "\n"
            . "- \"lede\": one line, at most 25 words, telling the learner what this pack helps them do before the examination.\n"
            . "- \"summary\": the whole chapter in 60 to 100 words: what it is about and how its ideas connect. No list.\n"
            . "- \"topics\": one entry for EVERY topic listed, \"gist\" at most 30 words saying what the topic covers.\n\n"
            . "Reply with {\"lede\": \"\", \"summary\": \"\", \"topics\": [{\"topic_id\": 0, \"gist\": \"\"}]}\n\n"
            . "CHAPTER\n" . json_encode(['name' => $context['chapter']['chapter_name'], 'class' => $context['chapter']['standard_name'] . ' ' . $context['chapter']['subject_name'], 'learning_objective' => $context['learning_objective'], 'topics' => $topics], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\n\n" . $this->chapterBlock($context);

        $fallbackTopics = array_column($topics, 'name', 'topic_id');
        $out = ['lede' => '', 'summary' => '', 'topics' => []];

        for ($try = 0; $try < 2; $try++) {
            $json = $this->readJson($this->ask(self::SYSTEM, $prompt, 6000));
            if ($json === null) {
                continue;
            }
            $lede = $this->str($json['lede'] ?? '');
            $summary = $this->str($json['summary'] ?? '');
            $gists = [];
            foreach ((array) ($json['topics'] ?? []) as $g) {
                if (is_array($g) && isset($fallbackTopics[(int) ($g['topic_id'] ?? 0)]) && $this->str($g['gist'] ?? '') !== '') {
                    $gists[(int) $g['topic_id']] = $this->str($g['gist']);
                }
            }
            if ($lede !== '' && self::words($summary) >= 20 && count($gists) === count($fallbackTopics)) {
                return ['lede' => $lede, 'summary' => $summary, 'topics' => $gists];
            }
            // Keep the best partial answer for the fallback below.
            $out = ['lede' => $lede ?: $out['lede'], 'summary' => $summary ?: $out['summary'], 'topics' => $gists + $out['topics']];
        }

        $chapter = $context['chapter']['chapter_name'];
        foreach ($topics as $t) {
            $out['topics'][$t['topic_id']] ??= 'Covers ' . implode(', ', array_slice($t['concepts'], 0, 4)) . (count($t['concepts']) > 4 ? ' and more.' : '.');
        }

        return [
            'lede' => $out['lede'] ?: 'Revise ' . $chapter . ' before your examination.',
            'summary' => $out['summary'] ?: trim('This pack revises the ideas of ' . $chapter . ', topic by topic: ' . implode('; ', array_column($topics, 'name')) . '. ' . trim((string) ($context['learning_objective'] ?? ''))),
            'topics' => $out['topics'],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $chunk work items by concept id
     */
    private function prompt(array $context, array $chunk, int $total): string
    {
        $input = json_encode(array_values($chunk), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pointMin = self::POINTS[0];
        $pointMax = self::POINTS[1];
        $sumMin = self::SUMMARY_WORDS[0];
        $sumMax = self::SUMMARY_WORDS[1];
        $pointWords = self::POINT_WORDS;
        $remember = self::REMEMBER_WORDS;
        $diagrams = max(1, (int) ceil($this->chunkSize / 4));
        $ground = self::GROUND_RULES;

        return <<<PROMPT
Write exam revision notes for the concepts listed under CONCEPTS TO WRITE. This is the pack a learner uses in the days before an examination: concise, exact and easy to scan. It is not a re-teaching of the chapter and it is not a summary of the textbook page by page. Write ONE note for EVERY listed concept.

Rules
{$ground}
- "summary": what the concept is, in one or two sentences a learner could write in an exam ({$sumMin} to {$sumMax} words).
- "key_points": {$pointMin} to {$pointMax} short points the learner must remember: the facts, the conditions, the steps, the differences. One idea each, at most {$pointWords} words, no filler, and not a repeat of the summary.
- "definition": {"term": "...", "text": "..."} in the chapter text's own words when the chapter defines this concept; null when it does not.
- "rules": formulas, laws, rules or facts the learner should memorise for this concept, exactly as the chapter states them: [{"label": "...", "statement": "..."}], at most 3. [] when the chapter states none.
- "example": a worked example or a real case from the chapter text, at most 45 words, that adds something the key points do not already say. null if there is none.
- "misconception": a "don't confuse" pair, ONLY from the concept's listed misconceptions (same idea, tidier words): {"wrong_idea": "...", "correction": "..."}. null otherwise.
- "remember": one line, at most {$remember} words, in the chapter's own words, that fixes the idea in memory. null if nothing earns the line. Not a repeat of the title.
- "checklist": 1 to 3 statements, each starting "I can", of what a learner should be able to do once they have revised this concept. Build them from the listed objectives.
- "diagram" and "diagram_notes": ONLY where the concept has several named parts, steps or kinds that the chapter text lists and a picture would help recall; at most {$diagrams} notes in this reply, and most notes have none (null and []). A diagram is {"layout": "flow" | "compare" | "hub", "title": "...", ...}: flow has "nodes": 2 to 6 short labels in order; hub has "center" and "nodes": 2 to 6 labels; compare has "left" and "right", each {"heading": "...", "items": [1 to 5 labels]}. Every label uses words from the chapter text and is at most 60 characters. "diagram_notes" has one entry for EVERY label drawn: [{"label": "the label exactly", "text": "5 to 35 words saying what it is"}].
- "bloom" is one of remember, understand, apply, analyze, evaluate, create. "dok" is 1 to 4. "minutes" is whole minutes to revise it.

Reply with:
{"notes": [{"concept_id": 0, "summary": "", "key_points": [""], "definition": null, "rules": [], "example": null, "misconception": null, "remember": null, "checklist": ["I can ..."], "diagram": null, "diagram_notes": [], "bloom": "understand", "dok": 2, "minutes": 3}]}

THE PACK HAS {$total} CONCEPTS IN ALL. CONCEPTS TO WRITE
{$input}

{$this->chapterBlock($context)}
PROMPT;
    }

    /**
     * @param array<string,mixed> $json
     * @param array<int,array<string,mixed>> $chunk
     * @return array<int,array<string,mixed>>
     */
    private function parse(array $json, array $chunk): array
    {
        $out = [];
        foreach ((array) ($json['notes'] ?? []) as $n) {
            $id = (int) ($n['concept_id'] ?? 0);
            if (!is_array($n) || !isset($chunk[$id]) || $this->str($n['summary'] ?? '') === '') {
                continue;
            }
            $out[$id] = $this->clean($n);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $n
     * @return array<string,mixed>
     */
    private function clean(array $n): array
    {
        $def = $n['definition'] ?? null;
        $definition = is_array($def) && $this->str($def['term'] ?? '') !== '' && $this->str($def['text'] ?? '') !== ''
            ? ['term' => $this->str($def['term']), 'text' => $this->str($def['text'])]
            : null;

        $rules = [];
        foreach ((array) ($n['rules'] ?? []) as $r) {
            if (is_array($r) && $this->str($r['label'] ?? '') !== '' && $this->str($r['statement'] ?? '') !== '') {
                $rules[] = ['label' => $this->str($r['label']), 'statement' => $this->str($r['statement'])];
            }
        }

        $m = $n['misconception'] ?? null;
        $misconception = is_array($m) && $this->str($m['wrong_idea'] ?? '') !== '' && $this->str($m['correction'] ?? '') !== ''
            ? ['wrong_idea' => $this->str($m['wrong_idea']), 'correction' => $this->str($m['correction'])]
            : null;

        [$diagram, $notes] = $this->diagram($n['diagram'] ?? null, $n['diagram_notes'] ?? []);

        return [
            'summary' => $this->str($n['summary'] ?? ''),
            'key_points' => $this->strings($n['key_points'] ?? [], 8),
            'definition' => $definition,
            'rules' => array_slice($rules, 0, 3),
            'example' => $this->nullable($n['example'] ?? null),
            'misconception' => $misconception,
            'remember' => $this->nullable($n['remember'] ?? null),
            'checklist' => $this->strings($n['checklist'] ?? [], 3),
            'diagram' => $diagram,
            'diagram_notes' => $notes,
            'bloom' => $this->bloom($n['bloom'] ?? ''),
            'dok' => $this->dok($n['dok'] ?? 2),
            'minutes' => $this->minutes($n['minutes'] ?? 3, 3, 30),
        ];
    }

    /**
     * What the notes end up as once the soft rules have had their say. A misconception that is not one of the
     * concept's listed ones, or an example that only repeats the note, is dropped rather than printed: neither is
     * worth failing a whole pack for, and neither is worth printing.
     *
     * @param array<int,array<string,mixed>> $notes
     * @param array<int,array<string,mixed>> $work
     * @return array<int,array<string,mixed>>
     */
    private function finalise(array $notes, array $work): array
    {
        foreach ($notes as $id => $note) {
            if ($note['misconception'] !== null && !self::traces($note['misconception']['wrong_idea'], array_column($work[$id]['misconceptions'], 'wrong_idea'))) {
                $notes[$id]['misconception'] = null;
            }
            if ($note['example'] !== null && self::overlap($note['example'], $note['summary'] . ' ' . implode(' ', $note['key_points'])) >= 0.8) {
                $notes[$id]['example'] = null;
            }
            $notes[$id]['key_points'] = array_slice($note['key_points'], 0, self::POINTS[1]);
        }

        return $notes;
    }
}
