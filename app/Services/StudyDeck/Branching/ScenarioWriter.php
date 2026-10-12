<?php

namespace App\Services\StudyDeck\Branching;

use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\ExtractsJson;

/**
 * Stage 2: the words.
 *
 * Given an accepted skeleton, writes the text of every node -- and ONLY the
 * text. The writer is handed the ids, kinds and links as fixed; what it returns
 * is keyed by node id, and an id it was not asked for is refused, so it cannot
 * add, remove or rewire anything. A scenario's structure was proved sound by
 * ScenarioBlueprint; nothing here can undo that.
 *
 * Nodes are written in chunks, in the order a learner meets them, each chunk
 * seeing the skeleton of the whole scenario and the titles and openings of what
 * has been written so far, so a consequence follows from the choice that caused
 * it and an ending reflects the path that reached it.
 *
 * WHAT THE WRITER DOES NOT WRITE: a bank question. For a knowledge check it
 * supplies a title and, if useful, a sentence connecting the question to the
 * story. The question, its options and its explanation are the bank's, word for
 * word, and are placed by ScenarioAssembler.
 */
final class ScenarioWriter
{
    use ExtractsJson;

    /** @var list<string> every raw reply, kept so a failed run can be inspected */
    public array $replies = [];

    public function __construct(
        private readonly Completer $completer,
        private readonly int $chunkSize = 6,
        private readonly int $maxRepairs = 2,
    ) {
    }

    /**
     * @param array<string,mixed> $blueprint an accepted skeleton
     * @return array<string,array<string,mixed>> node id => written content
     *
     * @throws \RuntimeException when a chunk cannot be written after the repair rounds
     */
    public function write(SourcePack $pack, array $blueprint, ?callable $progress = null): array
    {
        $say = $progress ?? fn () => null;
        $order = self::readingOrder($blueprint);
        $byId = [];
        foreach ($blueprint['nodes'] as $n) {
            $byId[$n['id']] = $n;
        }

        $written = [];
        $chunks = array_chunk($order, $this->chunkSize);

        foreach ($chunks as $c => $ids) {
            $say('write', 'Writing steps ' . (($c * $this->chunkSize) + 1) . ' to ' . min(($c + 1) * $this->chunkSize, count($order)) . ' of ' . count($order));

            $this->fill($pack, $blueprint, $byId, $ids, $written);
        }

        return $written;
    }

    /**
     * Ask again for just the nodes a validator found fault with.
     *
     * The nodes' previous text and the exact problems go back to the model, which
     * returns corrected text for those nodes only. A node that still cannot be
     * written keeps what it had, so the validator reports it instead of this
     * method throwing away a scenario that was almost right.
     *
     * @param array<string,mixed> $blueprint
     * @param array<string,array<string,mixed>> $written
     * @param array<string,list<string>> $problemsByNode node id => what is wrong with it
     * @return array<string,array<string,mixed>> the corrected written text
     */
    public function fix(SourcePack $pack, array $blueprint, array $written, array $problemsByNode): array
    {
        $byId = [];
        foreach ($blueprint['nodes'] as $n) {
            $byId[$n['id']] = $n;
        }

        $ids = array_values(array_filter(array_keys($problemsByNode), fn ($id) => isset($byId[$id])));
        if ($ids === []) {
            return $written;
        }

        $errors = [];
        foreach ($ids as $id) {
            foreach ($problemsByNode[$id] as $problem) {
                $errors[] = 'Node "' . $id . '": ' . $problem;
            }
        }

        $previous = json_encode(array_intersect_key($written, array_flip($ids)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $attempt = array_diff_key($written, array_flip($ids));

        try {
            $this->fill($pack, $blueprint, $byId, $ids, $attempt, [(string) $previous, $errors]);
        } catch (\RuntimeException) {
            // Whatever was fixed is kept; the rest stays as it was.
        }

        return $attempt + array_intersect_key($written, array_flip($ids));
    }

    /**
     * Write $pending nodes into $written, asking again for those whose reply is
     * incomplete.
     *
     * @param array<string,mixed> $blueprint
     * @param array<string,array<string,mixed>> $byId
     * @param list<string> $pending
     * @param array<string,array<string,mixed>> $written filled in place
     * @param array{0:string,1:list<string>}|null $repair the previous reply and its problems, for a re-write
     *
     * @throws \RuntimeException when a node still cannot be written after the repair rounds
     */
    private function fill(SourcePack $pack, array $blueprint, array $byId, array $pending, array &$written, ?array $repair = null): void
    {
        $errors = $repair[1] ?? [];
        $reply = $repair[0] ?? '';

        for ($attempt = 0; $attempt <= $this->maxRepairs && $pending !== []; $attempt++) {
            $seed = ($attempt > 0 || $repair !== null) ? [$reply, $errors] : null;
            $prompt = $this->prompt($pack, $blueprint, $byId, $pending, $written, $seed);
            $reply = $this->completer->complete($this->system(), $prompt, 12000);
            $this->replies[] = $reply;

            try {
                $data = $this->extractJson($reply);
            } catch (\RuntimeException $e) {
                $errors = [$e->getMessage()];
                continue;
            }

            $errors = [];
            $returned = (array) ($data['nodes'] ?? $data);

            foreach ($returned as $id => $content) {
                if (! isset($byId[(string) $id])) {
                    $errors[] = 'You returned "' . $id . '", which is not a node. Return only the nodes asked for.';
                }
            }

            foreach ($pending as $id) {
                $problems = [];
                $clean = self::normalise($byId[$id], $returned[$id] ?? null, $problems);
                if ($problems === []) {
                    $written[$id] = $clean;
                } else {
                    foreach ($problems as $p) {
                        $errors[] = 'Node "' . $id . '": ' . $p;
                    }
                }
            }

            $pending = array_values(array_filter($pending, fn ($id) => ! isset($written[$id])));
        }

        if ($pending !== []) {
            throw new \RuntimeException('The text for steps ' . implode(', ', $pending) . " could not be written. Problems found:\n- " . implode("\n- ", array_slice($errors, 0, 10)));
        }
    }

    /**
     * Nodes in the order a learner first meets them (breadth-first from the
     * start), so every chunk comes after the nodes that lead to it.
     *
     * @param array<string,mixed> $blueprint
     * @return list<string>
     */
    public static function readingOrder(array $blueprint): array
    {
        $byId = [];
        foreach ($blueprint['nodes'] as $n) {
            $byId[$n['id']] = $n;
        }

        // A node is placed once everything that leads to it has been, so a
        // rejoining node is written after BOTH of its parents.
        $parents = [];
        foreach ($byId as $id => $n) {
            foreach (self::targets($n) as $t) {
                $parents[$t][] = $id;
            }
        }

        $start = $blueprint['nodes'][0]['id'];
        $placed = [];
        $order = [];
        $queue = [$start];
        while ($queue !== []) {
            $next = [];
            foreach ($queue as $id) {
                if (isset($placed[$id])) {
                    continue;
                }
                if (array_diff($parents[$id] ?? [], array_keys($placed)) !== []) {
                    $next[] = $id;
                    continue;
                }
                $placed[$id] = true;
                $order[] = $id;
                foreach (self::targets($byId[$id]) as $t) {
                    $next[] = $t;
                }
            }
            if ($next === [] || $next === $queue) {
                break;
            }
            $queue = array_values(array_unique($next));
        }

        // Anything the walk could not place (it should be nothing in an acyclic
        // tree) still gets written.
        foreach (array_keys($byId) as $id) {
            if (! isset($placed[$id])) {
                $order[] = $id;
            }
        }

        return $order;
    }

    /** @return list<string> */
    public static function targets(array $node): array
    {
        $out = match ($node['kind']) {
            'scene' => [$node['next'] ?? null],
            'decision' => isset($node['question_id'])
                ? [$node['on_correct'] ?? null, $node['on_wrong'] ?? null]
                : array_map(fn ($c) => $c['next'] ?? null, $node['choices'] ?? []),
            default => [],
        };

        return array_values(array_filter($out, fn ($t) => $t !== null && $t !== ''));
    }

    private function system(): string
    {
        return 'You write the screens of a decision-based lesson for school students, from a fixed outline and the chapter material you are given. '
            . 'You write text only: you cannot add, remove or relink a screen. You reply with one JSON object and nothing else. '
            . 'You never invent facts, numbers, names or experiments that the material does not support.';
    }

    /**
     * @param array<string,mixed> $blueprint
     * @param array<string,array<string,mixed>> $byId
     * @param list<string> $ids nodes to write now
     * @param array<string,array<string,mixed>> $written what exists already
     * @param array{0:string,1:list<string>}|null $repair previous reply and its problems
     */
    private function prompt(SourcePack $pack, array $blueprint, array $byId, array $ids, array $written, ?array $repair): string
    {
        $conceptIds = array_values(array_unique(array_filter(array_map(fn ($id) => $byId[$id]['concept_id'] ?? null, $ids))));
        // A chunk may touch no concept (an ending): fall back to the whole set.
        $conceptIds = $conceptIds !== [] ? $conceptIds : $pack->conceptIds();

        $out = [];
        $out[] = 'CLASS AND CHAPTER: ' . $pack->heading();
        $out[] = 'WHO IT IS FOR: ' . ScenarioPrompts::audience($pack->level());
        $out[] = 'SCENARIO: "' . $blueprint['title'] . '". ' . $blueprint['premise'];
        $out[] = "WHAT LEARNERS WILL BE ABLE TO DO:\n- " . implode("\n- ", $blueprint['objectives']);

        $out[] = "THE WHOLE OUTLINE (fixed; for continuity only):\n" . $this->outline($blueprint, $byId);

        $subset = $this->subsetText($pack, $conceptIds);
        $out[] = "FACTS YOU MAY USE (concepts):\n" . $subset;
        $excerpts = $pack->excerpts($conceptIds, 5000);
        if ($excerpts !== '') {
            $out[] = "CHAPTER TEXT (the authority for every fact):\n" . $excerpts;
        }

        if ($written !== []) {
            $recent = [];
            foreach (array_slice(array_keys($written), -8) as $id) {
                $w = $written[$id];
                $recent[] = '- ' . $id . ': "' . ($w['title'] ?? '') . '" ' . mb_substr(SourcePack::flat((string) ($w['body'] ?? $w['question'] ?? '')), 0, 150);
            }
            $out[] = "ALREADY WRITTEN (do not repeat it; continue from it):\n" . implode("\n", $recent);
        }

        $out[] = "WRITING RULES\n" . ScenarioPrompts::style();
        $out[] = "WRITE THESE NODES NOW:\n" . implode("\n\n", array_map(fn ($id) => $this->spec($byId[$id], $byId, $pack), $ids));

        $out[] = <<<'TXT'
REPLY with this JSON and nothing else, keyed by node id, using exactly the fields shown for each kind:
{"nodes": {
  "s1": {"title": "...", "body_html": "<p>...</p>", "visual": "a picture or diagram that would help, or null"},
  "d1": {"title": "...", "question_html": "<p>...</p>", "choices": [{"text": "...", "feedback_title": "...", "feedback_text": "<p>...</p>"}]},
  "d2": {"title": "...", "lead_in": "<p>...</p> or null"},
  "e1": {"title": "...", "body_html": "<p>...</p><ul><li>...</li></ul>"}
}}
TXT;

        if ($repair !== null) {
            $out[] = "YOUR PREVIOUS REPLY WAS:\n" . $repair[0] . "\n\nIT HAS THESE PROBLEMS:\n- " . implode("\n- ", array_slice($repair[1], 0, 12))
                . "\n\nReturn the corrected JSON for the nodes above; keep what was already right.";
        }

        return implode("\n\n", $out);
    }

    /** One line per node, so the writer can see the story it is part of. */
    private function outline(array $blueprint, array $byId): string
    {
        $lines = [];
        foreach ($blueprint['nodes'] as $n) {
            $links = self::targets($n);
            $what = match (true) {
                $n['kind'] === 'scene' => $n['covers'],
                $n['kind'] === 'ending' => 'ENDING (' . $n['outcome'] . '): ' . $n['covers'],
                isset($n['question_id']) => 'KNOWLEDGE CHECK on question ' . $n['question_id'],
                default => 'DECISION: ' . $n['asks'],
            };
            $lines[] = sprintf('%s [%s%s] %s%s', $n['id'], $n['kind'], $n['purpose'] ? ' / ' . $n['purpose'] : '', $what, $links ? ' -> ' . implode(', ', $links) : '');
        }

        return implode("\n", $lines);
    }

    /** Only the chosen concepts the chunk touches, in the pack's rendering. */
    private function subsetText(SourcePack $pack, array $conceptIds): string
    {
        $sub = clone $pack;
        $sub->concepts = array_intersect_key($pack->concepts, array_flip($conceptIds));

        return $sub->conceptsText();
    }

    /** What to write for one node. */
    private function spec(array $node, array $byId, SourcePack $pack): string
    {
        $head = '### ' . $node['id'] . ' (' . $node['kind'] . ($node['purpose'] ? ', ' . $node['purpose'] : '') . ')';
        $concept = ! empty($node['concept_id']) && isset($pack->concepts[$node['concept_id']]) ? "\nteaches concept: " . $pack->concepts[$node['concept_id']]['name'] : '';
        $leads = fn (?string $id) => $id !== null && isset($byId[$id]) ? ($byId[$id]['covers'] ?? $byId[$id]['asks'] ?? ('question ' . ($byId[$id]['question_id'] ?? ''))) : '';

        if ($node['kind'] === 'scene') {
            return $head . $concept . "\npurpose of the screen: " . $node['covers'] . "\nthe next screen is about: " . $leads($node['next'] ?? null)
                . "\nwrite: title; body_html (60 to 120 words, in 2 to 3 short paragraphs or a paragraph and a short list); visual: a picture or diagram that would genuinely teach something on this screen, or null (most screens need none; suggest at most one in this batch)."
                . match ($node['purpose']) {
                    'situation' => "\nThis opens the scenario: put the student in the case in the second person (\"You are...\") and end on what has to be decided or found out.",
                    'consequence', 'remedial' => "\nBegin with what happened as a result of the choice that led here, then explain WHY using the chapter's ideas, then what to reconsider. Do not blame.",
                    'reflection' => "\nEnd with one question for the student to think about.",
                    default => '',
                };
        }

        if ($node['kind'] === 'ending') {
            return $head . $concept . "\nwhat this path shows: " . $node['covers'] . "\noutcome: " . $node['outcome']
                . "\nwrite: title; body_html (70 to 140 words): a paragraph on what this path showed, then a list of 2 to 4 key takeaways (<ul><li>). "
                . ($node['outcome'] === 'best' ? 'Affirm the sound reasoning.' : 'Say kindly what to revisit and how.');
        }

        if (isset($node['question_id'])) {
            $q = $pack->questionsById()[$node['question_id']] ?? null;

            return $head . $concept . "\nThis is a knowledge check on a bank question: \"" . ($q ? SourcePack::flat($q['stem']) : '') . "\""
                . "\nwrite: title; lead_in (one or two sentences that bring the question into the story, or null). Do NOT write or restate the question.";
        }

        $choices = [];
        foreach ($node['choices'] as $k => $c) {
            $explainsNext = isset($byId[$c['next']]) && $byId[$c['next']]['kind'] === 'scene' && in_array($byId[$c['next']]['purpose'], ['consequence', 'remedial'], true);
            $choices[] = sprintf("  %d. [%s] %s -> leads to: %s%s", $k + 1, $c['quality'], $c['intent'], $leads($c['next']),
                $explainsNext ? ' (a separate screen then explains WHY, so here say only what happens, in one or two sentences)' : '');
        }

        return $head . $concept . "\nthe decision: " . $node['asks'] . "\nchoices, in this order (write exactly this many):\n" . implode("\n", $choices)
            . "\nwrite: title; question_html (15 to 45 words, ends with the question); and for each choice in order: text (at most 20 words, written as the student would say it), feedback_title (2 to 5 words), feedback_text (what happens and why, 30 to 70 words; shorter where the choice leads to a screen that explains why). "
            . "A best choice affirms the reasoning; a partial choice says what is right and what is missing; a misstep says what it led to without blame. The feedback should set up the screen it leads to, not repeat it.";
    }

    /**
     * One node's reply, checked and reduced to the fields we keep.
     *
     * @param array<string,mixed> $node the skeleton node
     * @param mixed $reply what the model returned for it
     * @param list<string> $problems filled with what is wrong
     * @return array<string,mixed>
     */
    private static function normalise(array $node, mixed $reply, array &$problems): array
    {
        if (! is_array($reply)) {
            $problems[] = 'no text was returned.';

            return [];
        }

        $title = SourcePack::flat((string) ($reply['title'] ?? ''));
        if ($title === '') {
            $problems[] = 'it needs a "title".';
        }
        $words = fn (string $html) => str_word_count(SourcePack::flat($html));

        if ($node['kind'] === 'scene' || $node['kind'] === 'ending') {
            $body = trim((string) ($reply['body_html'] ?? $reply['body'] ?? ''));
            if ($words($body) < 20) {
                $problems[] = 'its "body_html" is missing or too short (at least 20 words).';
            }
            $visual = isset($reply['visual']) && is_string($reply['visual']) && trim($reply['visual']) !== '' && strtolower(trim($reply['visual'])) !== 'null'
                ? SourcePack::flat($reply['visual']) : null;

            return ['title' => $title, 'body' => $body, 'visual' => $visual];
        }

        if (isset($node['question_id'])) {
            $lead = isset($reply['lead_in']) && is_string($reply['lead_in']) && strtolower(trim($reply['lead_in'])) !== 'null' ? trim($reply['lead_in']) : '';

            return ['title' => $title, 'lead_in' => $lead];
        }

        $question = trim((string) ($reply['question_html'] ?? $reply['question'] ?? ''));
        if ($words($question) < 6) {
            $problems[] = 'its "question_html" is missing or too short.';
        }

        $choices = array_values(array_filter((array) ($reply['choices'] ?? []), 'is_array'));
        if (count($choices) !== count($node['choices'])) {
            $problems[] = 'it needs exactly ' . count($node['choices']) . ' choices, in the order given (it has ' . count($choices) . ').';
        }

        $out = [];
        foreach ($choices as $k => $c) {
            $text = SourcePack::flat((string) ($c['text'] ?? ''));
            $ft = trim((string) ($c['feedback_text'] ?? ''));
            if ($text === '') {
                $problems[] = 'choice ' . ($k + 1) . ' needs "text".';
            }
            if ($words($ft) < 10) {
                $problems[] = 'choice ' . ($k + 1) . ' needs "feedback_text" saying what happens and why (at least 10 words).';
            }
            $out[] = [
                'text' => $text,
                'feedback_title' => SourcePack::flat((string) ($c['feedback_title'] ?? '')),
                'feedback_text' => $ft,
            ];
        }

        return ['title' => $title, 'question' => $question, 'choices' => $out];
    }
}
