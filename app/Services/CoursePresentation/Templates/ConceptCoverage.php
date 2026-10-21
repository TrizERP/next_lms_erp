<?php

namespace App\Services\CoursePresentation\Templates;

/**
 * The chapter's CONCEPT COVERAGE MAP: for every concept of the chapter, what each source can say about it, and which
 * source the explanation will come from. No concept is ever dropped because the semantic intelligence has nothing for it.
 *
 * Sources, in priority order for each concept:
 *   1. Semantic intelligence (the chapter's `semantic_intelligence` row). Its entries are broad ideas (14 for a chapter of
 *      42 concepts) and carry ids that match nothing in `lms_concept`, so the link is made by NAME, in three tiers that are
 *      always recorded:
 *        exact      the normalised names are equal (the rule the deck generator uses);
 *        contained  every content word of the shorter name (at least two) is in the longer name ("Molecular Mass" in
 *                   "Molecular mass definition", "Polyatomic Ions" in "Brackets with multiple polyatomic ions");
 *        topic      the entry names the concept's TOPIC (same rule). That is context for the topic, not data about the
 *                   concept, so it is used at topic level only and never presented as the concept's own.
 *   2. The textbook chapter text (the extraction). Passages are found by the concept's name words and its stored
 *      definition; the concept record itself (`lms_concept.definition`/`description`) was extracted from that text and is
 *      checked against it. This fallback is mandatory: it is what covers a concept the intelligence does not.
 *   3. Neither: the concept is reported as UNRESOLVED, with the reason. It is not dropped silently and not invented.
 *
 * Pure: no database, no files. Learning outcomes are chapter-level (the concept-to-outcome links in the school data are
 * blanket links, not concept-specific), so none is attributed to a single concept here.
 */
class ConceptCoverage
{
    private const STOP = ['a', 'an', 'the', 'of', 'and', 'in', 'to', 'for', 'by', 'with', 'is', 'are', 'on', 'as', 'at', 'from', 'their', 'its', 'or', 'be'];

    /** The intelligence fields a concept can carry, and where they live in an entry. */
    private const FIELDS = [
        'teaching strategies' => 'pedagogy_recommendations', 'misconceptions' => 'misconceptions', 'real-world examples' => 'real_world_applications',
        'knowledge statements' => 'knowledge_items', 'learning objectives' => 'learning_objectives',
    ];

    /**
     * @param array<int,array<string,mixed>> $topics topic_master rows (id, name, description)
     * @param array<int,array<string,mixed>> $concepts lms_concept rows (id, name, description, definition, topic_id)
     * @param array<string,mixed>|null $semanticRow the chapter's accepted semantic_intelligence row
     * @return array<string,mixed>
     */
    public function build(array $topics, array $concepts, ?array $semanticRow, string $textbook): array
    {
        $entries = $this->entries($semanticRow);
        $passages = $this->passages($textbook);
        $textTokens = array_flip($this->tokens($textbook, 1));
        $topicById = [];
        foreach ($topics as $t) {
            $topicById[(int) $t['id']] = $t;
        }

        // ---- which intelligence entry belongs to which concept / topic
        $byConcept = [];
        $byTopic = [];
        $mappedEntries = [];
        foreach ($entries as $ei => $entry) {
            $en = (string) ($entry['concept']['concept_name'] ?? '');
            foreach ($concepts as $c) {
                if (($tier = $this->tier($en, (string) $c['name'])) !== null) {
                    $byConcept[(int) $c['id']][] = ['entry' => $ei, 'match' => $tier];
                    $mappedEntries[$ei] = true;
                }
            }
            foreach ($topics as $t) {
                if ($this->tier($en, (string) $t['name']) !== null) {
                    $byTopic[(int) $t['id']][] = $ei;
                    $mappedEntries[$ei] = true;
                }
            }
        }

        $rows = [];
        $topicSummary = [];
        foreach ($concepts as $c) {
            $id = (int) $c['id'];
            $tid = $c['topic_id'] !== null ? (int) $c['topic_id'] : null;
            $own = $byConcept[$id] ?? [];
            $topicEntries = $tid !== null ? ($byTopic[$tid] ?? []) : [];

            $semantic = $own !== []
                ? ['level' => 'concept', 'match' => $this->strongest(array_column($own, 'match')), 'entries' => array_map(fn ($m) => $entries[$m['entry']]['concept']['concept_name'], $own)] + $this->fields(array_column($own, 'entry'), $entries)
                : ($topicEntries !== []
                    ? ['level' => 'topic', 'match' => 'topic', 'entries' => array_map(fn ($ei) => $entries[$ei]['concept']['concept_name'], $topicEntries)]
                    : ['level' => 'none', 'match' => null, 'entries' => []]);

            $definition = trim((string) ($c['definition'] ?? '')) !== '' ? trim((string) $c['definition']) : trim((string) ($c['description'] ?? ''));
            $found = $this->supporting($this->tokens((string) $c['name'], 1), $definition, $passages);
            $book = [
                'definition' => $definition,
                'definition_grounded' => $definition === '' ? null : $this->grounded($definition, $textTokens),
                'passages' => $found,
            ];

            $semanticDefinition = $semantic['level'] === 'concept' ? trim((string) ($semantic['definition'] ?? '')) : '';
            $bookSupports = $found !== [] || ($definition !== '' && ($book['definition_grounded'] ?? 0) >= 0.6);
            $source = match (true) {
                $semanticDefinition !== '' || ($semantic['level'] === 'concept' && $bookSupports) => $bookSupports ? 'semantic_and_textbook' : 'semantic',
                $bookSupports => 'textbook',
                default => 'unresolved',
            };
            // A concept whose own intelligence carries only supporting fields (no definition) is explained from the textbook and
            // enriched by those fields; that is "both".
            if ($semantic['level'] === 'concept' && $semanticDefinition === '' && $bookSupports) {
                $source = 'semantic_and_textbook';
            }

            $rows[$id] = [
                'id' => $id, 'name' => (string) $c['name'], 'topic_id' => $tid, 'topic' => $tid !== null ? (string) ($topicById[$tid]['name'] ?? '') : null,
                'semantic' => $semantic, 'textbook' => $book,
                'explanation_source' => $source,
                'sufficient' => $source !== 'unresolved',
                'unresolved_reason' => $source === 'unresolved' ? 'The concept has no usable stored definition and no passage of the chapter text could be tied to it by its name.' : null,
            ];
        }

        foreach ($topics as $t) {
            $tid = (int) $t['id'];
            $ids = array_keys(array_filter($rows, fn ($r) => $r['topic_id'] === $tid));
            $topicSummary[$tid] = ['id' => $tid, 'name' => (string) $t['name'], 'description' => $t['description'] ?? null, 'concept_ids' => $ids]
                + ['semantic' => $this->fields($byTopic[$tid] ?? [], $entries, true)];
        }

        return [
            'concepts' => $rows,
            'topics' => $topicSummary,
            'summary' => $this->summary($rows, $entries, $mappedEntries),
            'unmapped_entries' => array_values(array_map(fn ($ei) => (string) $entries[$ei]['concept']['concept_name'], array_diff(array_keys($entries), array_keys($mappedEntries)))),
        ];
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function summary(array $rows, array $entries, array $mappedEntries): array
    {
        $count = fn (callable $f) => count(array_filter($rows, $f));
        $conceptLevel = $count(fn ($r) => $r['semantic']['level'] === 'concept');
        $bookSupported = $count(fn ($r) => in_array($r['explanation_source'], ['textbook', 'semantic_and_textbook'], true));

        return [
            'total_concepts' => count($rows),
            'intelligence_entries' => count($entries),
            'intelligence_entries_mapped' => count($mappedEntries),
            'semantic_concept_level' => $conceptLevel,
            'semantic_exact' => $count(fn ($r) => ($r['semantic']['match'] ?? null) === 'exact'),
            'semantic_contained' => $count(fn ($r) => ($r['semantic']['match'] ?? null) === 'contained'),
            'semantic_topic_level_only' => $count(fn ($r) => $r['semantic']['level'] === 'topic'),
            'no_semantic' => $count(fn ($r) => $r['semantic']['level'] === 'none'),
            'textbook_supported' => $bookSupported,
            'both_sources' => $count(fn ($r) => $r['explanation_source'] === 'semantic_and_textbook'),
            'semantic_only' => $count(fn ($r) => $r['explanation_source'] === 'semantic'),
            'textbook_fallback_only' => $count(fn ($r) => $r['explanation_source'] === 'textbook'),
            'unresolved' => $count(fn ($r) => $r['explanation_source'] === 'unresolved'),
            'renderable' => $count(fn ($r) => $r['sufficient']),
        ];
    }

    // ---------------------------------------------------------------------------------------------- matching

    /** exact | contained | null */
    private function tier(string $entryName, string $otherName): ?string
    {
        $a = $this->tokens($entryName, 2);
        $b = $this->tokens($otherName, 2);
        if ($a === [] || $b === []) {
            return null;
        }
        if ($this->norm($entryName) === $this->norm($otherName) || ($a === $b)) {
            return 'exact';
        }
        $aInB = array_diff($a, $b) === [];
        $bInA = array_diff($b, $a) === [];
        if (($aInB && count($a) >= 2) || ($bInA && count($b) >= 2)) {
            return 'contained';
        }

        return null;
    }

    /** @param array<int,string> $tiers */
    private function strongest(array $tiers): string
    {
        return in_array('exact', $tiers, true) ? 'exact' : 'contained';
    }

    /** @return array<int,string> sorted distinct content tokens, stemmed */
    private function tokens(string $s, int $minLen): array
    {
        $words = preg_split('/[^a-z0-9]+/', mb_strtolower($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($words as $w) {
            if (in_array($w, self::STOP, true) || strlen($w) < $minLen) {
                continue;
            }
            $out[$this->stem($w)] = true;
        }
        $t = array_keys($out);
        sort($t);

        return $t;
    }

    private function stem(string $w): string
    {
        if (str_ends_with($w, 'ae') && strlen($w) > 4) {
            return substr($w, 0, -1);   // formulae -> formula
        }
        if (strlen($w) > 3 && str_ends_with($w, 's') && !str_ends_with($w, 'ss')) {
            return substr($w, 0, -1);   // ions -> ion
        }

        return $w;
    }

    private function norm(string $s): string
    {
        return implode(' ', preg_split('/[^a-z0-9]+/', mb_strtolower($s), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    // ---------------------------------------------------------------------------------------------- intelligence fields

    /** @return array<int,array<string,mixed>> */
    private function entries(?array $row): array
    {
        $decoded = json_decode((string) ($row['full_intelegance_json'] ?? ''), true);

        return is_array($decoded) ? array_values(array_filter((array) ($decoded['concepts'] ?? $decoded['intelligence']['concepts'] ?? []), fn ($e) => !empty($e['concept']['concept_name']))) : [];
    }

    /**
     * What the matched entries actually hold, field by field. Only fields with data appear.
     *
     * @param array<int,int> $entryIds
     * @return array<string,mixed>
     */
    private function fields(array $entryIds, array $entries, bool $topicLevel = false): array
    {
        $take = function (string $key, string $field, int $max) use ($entryIds, $entries): array {
            $out = [];
            foreach ($entryIds as $ei) {
                foreach ((array) ($entries[$ei][$key] ?? []) as $x) {
                    $v = is_array($x) ? trim((string) ($x[$field] ?? '')) : '';
                    if ($v !== '') {
                        $out[$v] = true;
                    }
                }
            }

            return array_slice(array_keys($out), 0, $max);
        };
        // The cards behind "knowledge": each stored statement with the source's own short line and kind (for card headings).
        $cards = [];
        foreach ($entryIds as $ei) {
            foreach ((array) ($entries[$ei]['knowledge_items'] ?? []) as $x) {
                $st = is_array($x) ? trim((string) ($x['statement'] ?? '')) : '';
                if ($st !== '' && !isset($cards[$st])) {
                    $cards[$st] = ['statement' => $st, 'knowledge' => trim((string) ($x['knowledge'] ?? '')), 'type' => trim((string) ($x['knowledge_type'] ?? ''))];
                }
            }
        }
        $mis = [];
        foreach ($entryIds as $ei) {
            foreach ((array) ($entries[$ei]['misconceptions'] ?? []) as $m) {
                if (is_array($m) && !empty($m['misconception']) && !empty($m['correction'])) {
                    $mis[] = ['wrong_idea' => (string) $m['misconception'], 'correction' => (string) $m['correction']];
                }
            }
        }
        $definition = '';
        foreach ($entryIds as $ei) {
            $d = trim((string) ($entries[$ei]['concept']['definition'] ?? ''));
            if ($d !== '') {
                $definition = $d;
                break;
            }
        }

        $data = [
            'definition' => $topicLevel ? '' : $definition,
            'knowledge' => $take('knowledge_items', 'statement', 5),
            'knowledge_cards' => array_slice(array_values($cards), 0, 5),
            'misconceptions' => array_slice($mis, 0, 3),
            'real_world' => $take('real_world_applications', 'example', 4),
            'strategies' => $take('pedagogy_recommendations', 'strategy', 3),
            'objectives' => $take('learning_objectives', 'objective', 4),
        ];
        $data['fields_with_data'] = array_values(array_filter([
            $data['definition'] !== '' ? 'definition' : null, $data['knowledge'] ? 'knowledge statements' : null, $data['misconceptions'] ? 'misconceptions' : null,
            $data['real_world'] ? 'real-world examples' : null, $data['strategies'] ? 'teaching strategies' : null, $data['objectives'] ? 'learning objectives' : null,
        ]));

        return $data;
    }

    // ---------------------------------------------------------------------------------------------- the textbook

    /**
     * The chapter text as passages: page, text. Pictures, headings with no sentence and fragments are left out.
     *
     * @return array<int,array{page:?int,text:string,tokens:array<string,bool>}>
     */
    public function passages(string $md): array
    {
        $page = null;
        $out = [];
        foreach (preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $md)) ?: [] as $block) {
            $block = trim($block);
            if (preg_match('/^##\s*Page\s+(\d+)/i', $block, $m)) {
                $page = (int) $m[1];
                $block = trim((string) preg_replace('/^##\s*Page\s+\d+\s*/i', '', $block));
            }
            $block = trim((string) preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $block));
            $block = trim((string) preg_replace('/^#+\s*/m', '', $block));
            $block = trim((string) preg_replace('/\s+/', ' ', $block));
            if (mb_strlen($block) < 50) {
                continue;
            }
            $out[] = ['page' => $page, 'text' => $block, 'tokens' => array_flip($this->tokens($block, 1))];
        }

        return $out;
    }

    /**
     * Up to two passages that talk about the concept: most of its name words are in the passage, and the stored
     * definition's words add to the score. A passage is verbatim chapter text, cut at a sentence.
     *
     * @param array<int,string> $nameTokens
     * @param array<int,array{page:?int,text:string,tokens:array<string,bool>}> $passages
     * @return array<int,array{page:?int,text:string}>
     */
    private function supporting(array $nameTokens, string $definition, array $passages): array
    {
        if ($nameTokens === []) {
            return [];
        }
        $defTokens = array_slice(array_filter($this->tokens($definition, 5), fn ($t) => !in_array($t, $nameTokens, true)), 0, 12);
        $scored = [];
        foreach ($passages as $i => $p) {
            $hit = count(array_filter($nameTokens, fn ($t) => isset($p['tokens'][$t])));
            $need = count($nameTokens) === 1 ? 1 : (int) ceil(0.6 * count($nameTokens));
            if ($hit < $need) {
                continue;
            }
            $defHit = count(array_filter($defTokens, fn ($t) => isset($p['tokens'][$t])));
            $scored[] = [$hit / count($nameTokens) * 10 + $defHit * 2 - strlen($p['text']) / 4000, $i];
        }
        usort($scored, fn ($a, $b) => $b[0] <=> $a[0]);
        $out = [];
        foreach (array_slice($scored, 0, 2) as [$score, $i]) {
            $out[] = ['page' => $passages[$i]['page'], 'text' => $this->excerpt($passages[$i]['text'])];
        }

        return $out;
    }

    private function excerpt(string $text): string
    {
        if (mb_strlen($text) <= 320) {
            return $text;
        }
        $cut = mb_substr($text, 0, 320);
        $dot = max(mb_strrpos($cut, '. ') ?: 0, 0);

        return $dot > 120 ? mb_substr($cut, 0, $dot + 1) : rtrim($cut) . '…';
    }

    /** Share of the definition's content words that occur anywhere in the chapter text. @param array<string,int> $textTokens */
    private function grounded(string $definition, array $textTokens): float
    {
        $t = $this->tokens($definition, 4);

        return $t === [] ? 0.0 : count(array_filter($t, fn ($w) => isset($textTokens[$w]))) / count($t);
    }
}
