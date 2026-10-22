<?php

namespace App\Services\StudyDeck\Documents\Writers;

use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\ExtractsJson;
use App\Services\StudyDeck\InteractionPlanner;

/**
 * Proposes ONE interaction for each part of a finished study document that has none: a match, an order, a side-by-side
 * comparison, a timeline, cards, or a short decision scenario.
 *
 * The same discipline as the deck's InteractionPlanner, whose own `check()` decides what is allowed (word budgets, shapes,
 * a forward-only scenario graph), so one set of rules governs the deck and the documents. The model is shown only the
 * part's own content and the chapter's own definitions of its concepts and may add nothing to them. What each kind of
 * document wants from an interaction differs (see PURPOSE), and so does which kinds suit which part.
 *
 * The result is a set keyed by `key()`, not a changed document, so it can be kept in the bundle, reused without a model
 * call, and applied by the assembler wherever the parts are built, which is what lets the page fit measure it.
 */
final class InteractionWriter
{
    use ExtractsJson;

    /** What an interaction is FOR in each kind of document. */
    private const PURPOSE = [
        'revision_notes' => 'QUICK REVISION. The learner already met the ideas and is retrieving them. Pick the interaction that makes them recall and connect '
            . 'the ideas of the sheet in about two minutes (pair terms with meanings, put a process in order, set two things side by side, decide what happens in a short situation). '
            . 'Feedback should say what is true, so a wrong try is corrected at once.',
        'remedial' => 'GUIDED REBUILDING for a learner who may be finding the idea hard. Do not say or imply the learner has a difficulty. '
            . 'Pick the interaction that rebuilds the idea in small steps and checks it straight away (put the steps of the reasoning in order, pair each piece with what it does, '
            . 'a tiny decision where the explanation of each choice teaches). The wording is simple and the explanations kind and concrete.',
        'activities' => 'PREPARATION AND CAPTURE for a hands-on classroom activity. The practical, the group work and the discussion stay in the classroom and are NOT replaced. '
            . 'Pick the interaction that gets students ready (predict, order the procedure, pair the equipment or term with its job) or that helps them organise what they observed '
            . 'and explain it (sort, compare, a decision based on the result).',
    ];

    /** Which interaction kinds a part type may carry. */
    private const KINDS = [
        'topic' => ['match', 'compare', 'order', 'reveal', 'timeline'],
        'glossary' => ['match'],
        'check' => ['scenario', 'order', 'compare'],
        'unit' => ['order', 'match', 'compare', 'scenario', 'reveal'],
        'activity' => ['order', 'match', 'compare', 'scenario', 'timeline', 'reveal'],
    ];

    private const SYSTEM = 'You are an experienced classroom teacher deciding how one part of a study document should become interactive. '
        . 'You reply with a single JSON object and nothing else. Every fact, number, name and example comes from the material supplied; you add none of your own. '
        . 'Plain sentence-case language addressed to the student as "you", no emoji, no exclamation marks.';

    /** Most scenarios a document may carry: a decision needs reading time. */
    private const MAX_SCENARIOS = 3;

    /** @var array<int,string> every raw reply, kept for inspection */
    public array $replies = [];

    public function __construct(private readonly Completer $completer, private readonly InteractionPlanner $planner, private readonly int $chunkSize = 2)
    {
    }

    /**
     * The key a part's interaction is stored and found by. Stable across rebuilds of the same document: the type and the
     * first concept the part teaches (parts with no concept of their own, such as the glossary, are keyed by type alone).
     *
     * @param array<string,mixed> $part
     */
    public static function key(array $part): string
    {
        $type = (string) ($part['type'] ?? '');
        if (in_array($type, ['topic', 'unit', 'activity', 'note'], true)) {
            $ids = (array) (($part['taught_concept_ids'] ?? []) ?: ($part['concept_ids'] ?? []));

            return $type . ':' . (int) ($ids[0] ?? 0);
        }

        return $type;
    }

    /**
     * @param array<string,mixed> $document a finished document (document.json)
     * @param array<string,array<string,mixed>> $existing a set from an earlier run: parts it already answers are left alone
     * @return array<string,array<string,mixed>> key => interaction (with its `reason`)
     */
    public function propose(array $document, array $existing = [], ?callable $say = null): array
    {
        $say ??= fn () => null;
        $kind = (string) $document['kind'];
        $set = $existing;

        $targets = [];
        foreach ($document['sections'] as $part) {
            $type = (string) $part['type'];
            if (!isset(self::KINDS[$type]) || ($part['interaction'] ?? null) !== null || isset($set[self::key($part)])) {
                continue;
            }
            $targets[$part['n']] = $part;
        }
        if ($targets === []) {
            return $set;
        }

        $done = 0;
        foreach (array_chunk($targets, $this->chunkSize, true) as $chunk) {
            $meta = [];
            foreach ($chunk as $n => $part) {
                $meta[$n] = ['_anchors' => [], 'scenario_allowed' => in_array('scenario', self::KINDS[$part['type']], true), 'slide_type' => 'application'];
            }

            $prompt = $this->prompt($kind, $document, $chunk, $set);
            [$ok, $bad] = $this->planner->check($this->parse($this->ask($prompt), $chunk), $meta);
            $this->take($set, $chunk, $ok);

            if ($bad) {
                $notes = [];
                foreach ($bad as $n => $issues) {
                    $notes[] = "Part $n: " . implode(' ', $issues);
                }
                $retry = $this->prompt($kind, $document, array_intersect_key($chunk, $bad), $set)
                    . "\n\nThese were rejected. For each part below return a corrected interaction that follows the rules exactly:\n" . implode("\n", $notes);
                [$fixed] = $this->planner->check($this->parse($this->ask($retry), array_intersect_key($chunk, $bad)), array_intersect_key($meta, $bad));
                $this->take($set, $chunk, $fixed);
            }

            $done += count($chunk);
            $say('interactions', "$done/" . count($targets) . ' parts considered, ' . count($set) . ' interactions so far');
        }

        return $set;
    }

    /** Everyday words that need no support in the chapter text. */
    private const COMMON = 'about above after again also always another around because before below between both cannot cause could does doing during each either every first found from gives given having here into just keep known later least less like made make many might more most much must never next once only other over part place point rather really right same several should since some still such take than that their them then there these they thing think this those three through together under until upon used uses using very want well were what when where whether which while whole will with within without would your yours';

    /**
     * Words of 5 letters or more in an interaction that the chapter text and the document itself never use, per key. Not a
     * verdict (a paraphrase has new words), but where an invented example or fact shows first, so a person reads those
     * interactions before the document is stored.
     *
     * @param array<string,array<string,mixed>> $set
     * @param array<string,mixed> $document
     * @return array<string,list<string>>
     */
    public function unsupportedWords(array $set, string $groundTruth, array $document): array
    {
        $haystack = mb_strtolower($groundTruth . ' ' . json_encode($document['sections'] ?? [], JSON_UNESCAPED_UNICODE) . ' ' . json_encode($document['concepts'] ?? [], JSON_UNESCAPED_UNICODE));
        $common = array_flip(explode(' ', self::COMMON));
        $out = [];
        foreach ($set as $key => $interaction) {
            $text = mb_strtolower(strip_tags(json_encode(array_diff_key($interaction, ['reason' => 1, 'kind' => 1]), JSON_UNESCAPED_UNICODE)));
            preg_match_all('/\p{L}{5,}/u', $text, $m);
            $unknown = [];
            foreach (array_unique($m[0]) as $w) {
                if (isset($common[$w]) || str_contains($haystack, mb_substr($w, 0, max(5, mb_strlen($w) - 3)))) {
                    continue;
                }
                $unknown[] = $w;
            }
            if ($unknown !== []) {
                $out[$key] = $unknown;
            }
        }

        return $out;
    }

    /** @param array<string,array<string,mixed>> $set */
    private function take(array &$set, array $chunk, array $accepted): void
    {
        foreach ($accepted as $n => $d) {
            if (($d['interaction'] ?? null) !== null && isset($chunk[$n])) {
                $set[self::key($chunk[$n])] = $d['interaction'];
            }
        }
    }

    /**
     * @param array<int,array<string,mixed>> $chunk
     * @param array<string,array<string,mixed>> $set what other parts already carry, so the document varies
     */
    private function prompt(string $kind, array $document, array $chunk, array $set): string
    {
        $used = [];
        foreach ($set as $i) {
            $used[$i['kind']] = ($used[$i['kind']] ?? 0) + 1;
        }
        foreach ($document['sections'] as $p) {
            if (($p['interaction']['kind'] ?? null) !== null) {
                $used[$p['interaction']['kind']] = ($used[$p['interaction']['kind']] ?? 0) + 1;
            }
        }

        $parts = [];
        foreach ($chunk as $n => $part) {
            $concepts = [];
            foreach (array_unique(array_merge((array) ($part['taught_concept_ids'] ?? []), (array) ($part['concept_ids'] ?? []))) as $id) {
                $c = $document['concepts'][(string) $id] ?? null;
                if ($c) {
                    $concepts[] = ['name' => $c['name'], 'definition' => $c['definition'] ?? ''];
                }
                if (count($concepts) >= 6) {
                    break;
                }
            }
            $parts[] = [
                'n' => $n,
                'part_type' => $part['type'],
                'title' => $part['title'],
                'allowed_kinds' => self::KINDS[$part['type']],
                'material' => $this->trim($part['content']),
                'chapter_definitions_of_its_concepts' => $concepts,
            ];
        }

        $scenarios = (int) ($used['scenario'] ?? 0);
        $varietyNote = $used === [] ? 'None chosen yet.' : json_encode($used);

        return "KIND OF DOCUMENT: {$kind}.\nWHAT AN INTERACTION IS FOR HERE: " . self::PURPOSE[$kind] . "\n\n"
            . "For EACH part below, choose the ONE allowed kind that best fits what the part teaches, and write it using only the part's material and the chapter definitions given.\n"
            . "Kinds already chosen elsewhere in this document: {$varietyNote}. Prefer a kind not used much yet, but never force a poor fit. "
            . ($scenarios >= self::MAX_SCENARIOS ? 'Do NOT use a scenario: the document already has its limit. ' : 'Use at most one scenario in this reply, and only where there is a real decision or experiment to reason about. ')
            . "Return null for a part only if nothing in it can be turned into a sound interaction, and give the reason.\n\n"
            . "SHAPES (word budgets are enforced):\n"
            . "match: {\"kind\":\"match\",\"intro\":\"<=25 words\",\"pairs\":[{\"term\":\"1-6 words\",\"meaning\":\"4-20 words\"}],\"wrapup\":\"<=30 words\"} with 3 to 5 pairs.\n"
            . "order: {\"kind\":\"order\",\"intro\":\"...\",\"items\":[\"2-14 words\"],\"wrapup\":\"...\"} with 3 to 5 items listed in the CORRECT order.\n"
            . "compare: {\"kind\":\"compare\",\"intro\":\"...\",\"items\":[{\"label\":\"1-8 words\",\"text\":\"5-35 words\"}],\"wrapup\":\"how they compare, <=30 words\"} with 2 or 3 items.\n"
            . "reveal: same as compare but 2 to 5 items and wrapup optional. timeline: items also have \"when\" (a year or date from the material), 3 to 6 items; the learner OPENS each event in date order, so the intro says to open them and never says to place or match them.\n"
            . "scenario: {\"kind\":\"scenario\",\"situation\":\"8-60 words\",\"nodes\":[{\"id\":\"n1\",\"prompt\":\"the decision, <=40 words\",\"choices\":[{\"text\":\"2-25 words\",\"outcome\":\"what happens, 5-40 words\",\"why\":\"the explanation, 5-40 words\",\"sound\":true,\"next\":null}]}],\"conclusion\":\"5-40 words\"} "
            . "with 1 to 3 nodes, 2 or 3 choices per node, at least one sound choice per node, and \"next\" naming a LATER node id or null.\n"
            . "Every interaction also has \"reason\": one sentence saying why this teaches the idea better than reading.\n\n"
            . "REPLY: {\"parts\":[{\"n\":<part number>,\"interaction\":{...} or null,\"reason\":\"...\"}]}\n\n"
            . 'PARTS: ' . json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** The part's content, kept short enough to send: long lists are cut, nothing is reworded. */
    private function trim(array $content): array
    {
        $out = [];
        foreach ($content as $k => $v) {
            if (is_array($v)) {
                $v = array_slice($v, 0, 12, true);
            } elseif (is_string($v) && mb_strlen($v) > 700) {
                $v = mb_substr($v, 0, 700);
            }
            if ($v !== [] && $v !== null && $v !== '') {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    private function ask(string $prompt): string
    {
        return $this->replies[] = $this->completer->complete(self::SYSTEM, $prompt, 9000);
    }

    /** @return array<int,array<string,mixed>> */
    private function parse(string $reply, array $wanted): array
    {
        try {
            $json = $this->extractJson($reply);
        } catch (\RuntimeException) {
            return [];
        }
        $out = [];
        foreach ((array) ($json['parts'] ?? []) as $p) {
            $n = (int) ($p['n'] ?? 0);
            if (isset($wanted[$n]) && is_array($p)) {
                $out[$n] = $p;
            }
        }

        return $out;
    }
}
