<?php

namespace App\Services\CoursePresentation\Templates;

use App\Services\StudyDeck\CardTitle;

/**
 * Source-to-template adapter for PPT / interactive slides.
 *
 * Maps the chapter's STORED data into the existing study-deck schema (version 3, the one the existing viewer reads),
 * in the slide order of the chosen teaching method. It calls no model, reads and writes no table and fetches nothing:
 * the caller hands in the concept coverage map (ConceptCoverage), the learning outcomes and the verified picture pool.
 *
 * EVERY concept of the chapter gets its explanation slide. The text comes from the best source the coverage map found for
 * that concept: its own semantic intelligence where there is any, the textbook (the stored concept definition, checked
 * against the chapter text, and the chapter's own passages) where there is not, and both where the intelligence is only
 * partial. A concept with neither is reported as unresolved rather than dropped or invented.
 *
 * What goes on a slide is only what a source says. A section no source can fill is LEFT OUT and listed in `missing`: no
 * experiment, worked example, misconception or conclusion is invented. The only words not taken from a source are the
 * prompts that frame a phase ("What is X?"); they ask, they assert nothing.
 */
class SlideDeckAdapter
{
    /** Per topic, in order. context, question, evidence, explain, example, misconception, summary, reflect, activity. */
    public const PHASES = [
        'explicit_instruction' => ['explain', 'example', 'misconception', 'summary'],
        'inquiry_based' => ['context', 'question', 'evidence', 'explain', 'summary'],
        'problem_based' => ['context', 'question', 'evidence', 'explain', 'example', 'summary'],
        'project_based' => ['context', 'question', 'explain', 'example', 'summary'],
        'experiential' => ['context', 'activity', 'explain', 'reflect'],
        'collaborative' => ['question', 'explain', 'reflect', 'summary'],
        'socratic' => ['question', 'explain', 'misconception', 'reflect'],
        'flipped_classroom' => ['summary', 'question', 'explain', 'example'],
        'differentiated_mastery' => ['explain', 'misconception', 'example', 'summary'],
        'gamified' => ['question', 'explain', 'example', 'summary'],
    ];

    /** What a method wants that no stored source can give, so it is reported rather than invented. */
    private const NOT_IN_ANY_SOURCE = [
        'project_based' => 'The project plan, build and presentation stages are not part of any stored source for this chapter.',
        'gamified' => 'Levels, points and feedback rules are not part of any stored source for this chapter.',
    ];

    /** @var array<int,array<string,mixed>> */
    private array $slides = [];
    /** @var array<int,array{what:string,where:string,why:string}> */
    private array $missing = [];
    private int $derivedPrompts = 0;

    /**
     * @param array<string,mixed> $chapter chapter_master row plus standard_name and subject_name
     * @param array<string,mixed> $coverage ConceptCoverage::build()
     * @param array<int,array{type:string,code:string,description:string}> $outcomes learning outcomes and competencies of the chapter
     * @param array<int,array<string,mixed>> $pictures concept id => a verified picture (ImagePool)
     * @return array{deck:array<string,mixed>,missing:array<int,array{what:string,where:string,why:string}>,summary:array<string,mixed>,quality:array<string,mixed>}
     */
    public function build(array $chapter, array $coverage, array $outcomes, string $method, array $pictures = []): array
    {
        $phases = self::PHASES[$method] ?? null;
        if ($phases === null) {
            throw new \InvalidArgumentException("No slide template for the teaching method \"$method\".");
        }
        $this->slides = [];
        $this->missing = [];
        $this->derivedPrompts = 0;
        $label = (string) (config('course_presentation_pilot.pedagogies')[$method] ?? $method);

        $this->add('cover', (string) $chapter['chapter_name'], [], [], [
            'body' => trim(($chapter['standard_name'] ?? '') . ' ' . ($chapter['subject_name'] ?? '')) . ' · ' . $label,
        ], null, 'Introduction');
        $this->outcomeSlides($outcomes);
        if (isset(self::NOT_IN_ANY_SOURCE[$method])) {
            $this->missing[] = ['what' => 'Method stages', 'where' => 'whole chapter', 'why' => self::NOT_IN_ANY_SOURCE[$method]];
        }
        if (in_array('activity', $phases, true)) {
            $this->missing[] = ['what' => 'Hands-on activity', 'where' => 'whole chapter', 'why' => 'No stored, approved activity exists for this chapter (the Prayogshala labs are a separate, unpublished resource and are not used here), so none was invented. Observation, prediction and reflection are built from the stored data instead.'];
        }

        $concepts = (array) $coverage['concepts'];
        $topics = [];
        foreach ((array) $coverage['topics'] as $topic) {
            $ids = array_values(array_filter(array_map('intval', (array) $topic['concept_ids']), fn ($id) => isset($concepts[$id])));
            if ($ids !== []) {
                $topics[] = ['topic' => $topic, 'ids' => $ids];
            }
        }

        // ONE chapter overview, near the start. It is the only slide the viewer draws as the topic list.
        $this->add('hook', 'The chapter at a glance', [], [], [
            'body' => count($topics) . ' topics and ' . array_sum(array_map(fn ($t) => count($t['ids']), $topics)) . ' ideas. Choose a topic to jump to it, or continue in order.',
        ], null, 'Introduction');

        $explained = 0;
        foreach ($topics as $t) {
            $section = (string) $t['topic']['name'];
            $ids = $t['ids'];
            $example = $this->observe($method, $phases, $section, $t['topic'], $ids, $concepts);
            foreach ($ids as $i => $id) {
                $explained += $this->conceptSlide($concepts[$id], $method, $phases, $pictures[$id] ?? null, $section, $i === 0 ? $example : null);
            }
            if (in_array('misconception', $phases, true)) {
                $this->topicMisconceptions($section, $t['topic'], $ids, $concepts);
            }
            if (in_array('summary', $phases, true)) {
                $this->check($section, $ids, $concepts);
            }
        }

        $this->synthesis($topics, $concepts);

        return $this->finish($chapter, $coverage, $method, $label, $pictures, $explained);
    }

    // ------------------------------------------------------------------------------------------ slides

    /**
     * What this concept says: its own intelligence first, the stored concept record where the intelligence has nothing.
     *
     * @param array<string,mixed> $c one coverage row
     * @return array{text:string,bullets:array<int,string>,source:string}
     */
    private function explanation(array $c): array
    {
        $sem = $c['semantic'];
        $book = $c['textbook'];
        $own = $sem['level'] === 'concept';
        $text = $own && trim((string) ($sem['definition'] ?? '')) !== '' ? trim((string) $sem['definition'])
            : ($book['definition'] !== '' ? $book['definition'] : (string) ($book['passages'][0]['text'] ?? '')); // hidden fallback: unlabelled, only when nothing else explains the concept

        return ['text' => $text, 'bullets' => $own ? array_slice((array) ($sem['knowledge'] ?? []), 0, 3) : [], 'source' => $own ? 'Source: semantic intelligence.' : 'Source: the chapter\'s stored concept record.'];
    }

    /**
     * The observation of a topic: situations the stored data links to it, opened one by one. One situation is carried onto the
     * first concept's slide instead (returned); none is reported. Nothing is invented.
     *
     * @param array<int,string> $phases
     * @param array<int,int> $ids
     */
    private function observe(string $method, array $phases, string $section, array $topic, array $ids, array $concepts): ?string
    {
        if (!in_array('context', $phases, true) && !in_array('example', $phases, true)) {
            return null;
        }
        $items = [];
        foreach ($ids as $id) {
            $own = $concepts[$id]['semantic']['level'] === 'concept' ? (array) ($concepts[$id]['semantic']['real_world'] ?? []) : [];
            if (isset($own[0])) {
                $items[] = ['label' => (string) $concepts[$id]['name'], 'text' => (string) $own[0]];
            }
        }
        $general = array_slice((array) ($topic['semantic']['real_world'] ?? []), 0, 2);
        foreach ($general as $i => $x) {
            $items[] = ['label' => count($general) > 1 ? 'In this topic (' . ($i + 1) . ')' : 'In this topic', 'text' => (string) $x];
        }
        $items = array_slice($items, 0, 5);
        if (count($items) === 1) {
            return $items[0]['text'];
        }
        if ($items === []) {
            $this->missing[] = ['what' => 'Real-world context', 'where' => $section, 'why' => 'No real-world example is stored in the intelligence for these concepts.'];

            return null;
        }
        $kind = ['inquiry_based' => 'Observe', 'problem_based' => 'The problem', 'project_based' => 'The context', 'experiential' => 'Observe'][$method] ?? 'In the real world';
        $this->derivedPrompts++;
        $this->add('application', $kind . ': ' . $section, $ids, [], ['body' => ''], null, $section, [
            'kind' => 'reveal', 'reason' => 'Situations the stored data links to this topic, opened one at a time.',
            'intro' => 'Open each situation. What do you notice?',
            'items' => array_map(fn ($x, $i) => ['id' => 'o' . ($i + 1), 'label' => $x['label'], 'text' => $x['text']], $items, array_keys($items)),
            'wrapup' => 'Keep these in mind as you meet the ideas behind them.',
        ], 'Interaction: the stored real-world examples are opened one by one.');

        return null;
    }

    /**
     * One slide for one concept, with the strongest interaction its stored data supports:
     * hotspots on a verified drawn diagram, else a misconception/correction comparison (where the method asks for it), else
     * its knowledge statements as cards to open, else a plain explanation. The "predict first" prompt rides in the explain dock.
     *
     * @param array<string,mixed> $c
     * @param array<int,string> $phases
     * @param array<string,mixed>|null $picture
     */
    private function conceptSlide(array $c, string $method, array $phases, ?array $picture, string $section, ?string $example): int
    {
        $id = (int) $c['id'];
        $name = (string) $c['name'];
        $sem = $c['semantic'];
        $own = $sem['level'] === 'concept';
        $text = $this->explanation($c)['text'];
        // Each card is its stored statement plus the source's own short line and kind (older rows carry the statement only).
        $bank = $own ? (array) ($sem['knowledge_cards'] ?? []) : [];
        $cards = [];
        foreach ($own ? (array) ($sem['knowledge'] ?? []) : [] as $k) {
            $k = trim((string) $k);
            if ($k !== '' && $this->norm($k) !== $this->norm($text)) {
                $found = array_values(array_filter($bank, fn ($b) => ($b['statement'] ?? '') === $k))[0] ?? ['statement' => $k, 'knowledge' => '', 'type' => ''];
                $cards[] = $found;
            }
        }
        if ($text === '' && $cards) {
            $text = array_shift($cards)['statement'];
        }
        $knowledge = array_column($cards, 'statement');
        if ($text === '') {
            $this->missing[] = ['what' => 'Explanation (unresolved source mapping)', 'where' => $name, 'why' => (string) ($c['unresolved_reason'] ?? 'No stored explanation could be tied to this concept.')];

            return 0;
        }
        $mis = $own ? ($sem['misconceptions'][0] ?? null) : null;
        $image = $picture !== null ? array_diff_key($picture, ['interaction' => 1]) : null;

        $content = ['body' => $this->explanation($c)['source'], 'explanations' => [['concept_id' => $id, 'text' => $text]], 'bullets' => [], 'example' => $example];
        if (mb_strlen($text) > 200) {
            $content['key_idea'] = $this->shorten($text, 200);
        }
        $interaction = null;
        $reason = null;
        $type = 'concept_intro';

        if ($picture !== null && is_array($picture['interaction'] ?? null)) {
            $interaction = $picture['interaction'];
            $type = 'concept_visual';
            $reason = 'Interaction: select a part of the verified diagram to read about it (from the earlier validated draft; nothing is placed by guesswork).';
        } elseif ($mis && in_array('misconception', $phases, true)) {
            $type = 'misconception';
            $interaction = [
                'kind' => 'compare', 'reason' => 'A common mistake set beside its correction, from the stored intelligence.',
                'intro' => 'Open both to compare.',
                'items' => [
                    ['id' => 'wrong', 'label' => 'A common idea', 'text' => (string) $mis['wrong_idea']],
                    ['id' => 'right', 'label' => 'What is actually true', 'text' => (string) $mis['correction']],
                ],
                'wrapup' => 'The second card corrects the first.',
            ];
            $reason = 'Interaction: the stored misconception and its correction are opened side by side.';
            $mis = null;
        } elseif (count($knowledge) >= 2) {
            $shown = array_slice($cards, 0, 4);
            $heads = CardTitle::resolveAll($shown, $name);
            foreach ($heads as $h) {
                if ($h['level'] >= 3) {
                    $this->missing[] = ['what' => 'Card heading', 'where' => $name, 'why' => 'A card of this idea has no short line of its own and its text could not be reduced to a heading; "' . $h['title'] . '" was used instead.'];
                }
            }
            $interaction = [
                'kind' => 'reveal', 'reason' => 'What the stored intelligence records about this idea, opened one statement at a time.',
                'intro' => 'Open each card.',
                'items' => array_map(fn ($k, $i) => ['id' => 'k' . ($i + 1), 'label' => $heads[$i]['title'], 'text' => $k['statement']], $shown, array_keys($shown)),
                'wrapup' => 'Together these describe the idea.',
            ];
            $reason = 'Interaction: the idea\'s stored statements are opened one at a time.';
        }
        if ($interaction === null || ($interaction['kind'] ?? '') !== 'reveal') {
            // The stored statements are kept on the slide unless they are the cards themselves.
            $content['bullets'] = array_slice($knowledge, 0, 3);
        }

        if ($interaction === null && !$image) {
            $reason = 'Plain teaching: the stored data holds one explanation and no further statement for this idea.';
        }
        if ($mis) {
            $content['misconception'] = $mis;
        }
        if (array_intersect(['question', 'activity', 'reflect'], $phases)) {
            $prefix = [
                'experiential' => 'Predict first: what do you think "%s" means, and where might you notice it?',
                'gamified' => 'Clear this level: what is "%s"?',
                'collaborative' => 'As a group, say what "%s" is.',
                'socratic' => 'What do you already think about "%s"?',
                'flipped_classroom' => 'What do you remember about "%s"?',
            ][$method] ?? 'What is "%s"?';
            $this->derivedPrompts++;
            $content['discussion'] = ['prompt' => sprintf($prefix, $name), 'answer' => $text];
        }

        $this->add($type, $name, [$id], [$id], $content, $image, $section, $interaction, $reason);

        return 1;
    }

    /** Mistakes the intelligence records for the TOPIC as a whole (not for one concept), each set beside its correction. */
    private function topicMisconceptions(string $section, array $topic, array $ids, array $concepts): void
    {
        $own = 0;
        foreach ($ids as $id) {
            $own += $concepts[$id]['semantic']['level'] === 'concept' ? count((array) ($concepts[$id]['semantic']['misconceptions'] ?? [])) : 0;
        }
        $n = 0;
        foreach (array_slice((array) ($topic['semantic']['misconceptions'] ?? []), 0, 2) as $m) {
            $this->add('misconception', 'A common mistake in this topic: ' . $section, $ids, [], ['body' => 'Recorded for the topic as a whole.', 'misconception' => $m], null, $section, [
                'kind' => 'compare', 'reason' => 'A mistake recorded for the whole topic, set beside its correction.', 'intro' => 'Open both to compare.',
                'items' => [['id' => 'wrong', 'label' => 'A common idea', 'text' => (string) $m['wrong_idea']], ['id' => 'right', 'label' => 'What is actually true', 'text' => (string) $m['correction']]],
                'wrapup' => 'The second card corrects the first.',
            ], 'Interaction: the stored misconception and its correction are opened side by side.');
            $n++;
        }
        if ($n === 0 && $own === 0) {
            $this->missing[] = ['what' => 'Common mistakes', 'where' => $section, 'why' => 'No misconception with a correction is stored for these concepts or this topic.'];
        }
    }

    /** A short knowledge check for a topic: pair each idea with its meaning. Only where there are enough ideas. */
    private function check(string $section, array $ids, array $concepts): void
    {
        $pairs = [];
        foreach ($ids as $id) {
            $t = $this->explanation($concepts[$id])['text'];
            if ($t !== '') {
                $pairs[] = ['id' => 'p' . $id, 'term' => (string) $concepts[$id]['name'], 'meaning' => $this->shorten($t, 120)];
            }
        }
        if (count($pairs) < 3) {
            return;
        }
        $this->derivedPrompts++;
        $this->add('exit_ticket', 'Check yourself: ' . $section, $ids, [], ['body' => ''], null, $section, [
            'kind' => 'match', 'reason' => 'Match each idea of the topic with its meaning.', 'intro' => 'Pair each idea with what it means.',
            'pairs' => array_slice($pairs, 0, 5), 'wrapup' => 'These are the meanings stored for the topic.',
        ], 'Interaction: a short match check built from the stored definitions.');
    }

    /** One closing slide: a card per topic to reflect on and connect. Replaces a recap after every topic. */
    private function synthesis(array $topics, array $concepts): void
    {
        $items = [];
        foreach ($topics as $i => $t) {
            $names = array_map(fn ($id) => (string) $concepts[$id]['name'], $t['ids']);
            $keys = array_values(array_filter(array_map(function ($id) use ($concepts) {
                $x = $this->explanation($concepts[$id])['text'];

                return $x === '' ? null : $concepts[$id]['name'] . ': ' . $this->shorten($x, 90);
            }, array_slice($t['ids'], 0, 3))));
            $items[] = [
                'id' => 't' . ($i + 1), 'label' => (string) $t['topic']['name'],
                'text' => 'How do ' . $this->joinNames(array_slice($names, 0, 3)) . ' fit together? Say it in your own words, then check: ' . implode(' ', $keys),
            ];
        }
        if (count($items) >= 2) {
            $this->derivedPrompts += count($items);
            $this->add('summary', 'Reflect and connect', [], [], ['body' => ''], null, 'Review', [
                'kind' => 'reveal', 'reason' => 'One card per topic: reflect first, then check against the stored meanings.', 'intro' => 'Open a topic, reflect, then check yourself.',
                'items' => $items, 'wrapup' => 'That is the chapter.',
            ], 'Interaction: a reflection card per topic replaces a recap after every topic.');

            return;
        }
        $this->add('summary', 'Review: what this chapter covered', [], [], ['body' => 'The topics of this chapter.', 'bullets' => array_map(fn ($t) => (string) $t['topic']['name'], $topics)], null, 'Review');
    }

    private function joinNames(array $names): string
    {
        return count($names) <= 1 ? implode('', $names) : implode(', ', array_slice($names, 0, -1)) . ' and ' . end($names);
    }

    /** The first words of a statement, as the label of a closed card. */
    private function teaser(string $text, int $words = 6): string
    {
        $all = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($all) <= $words ? implode(' ', $all) : rtrim(implode(' ', array_slice($all, 0, $words)), ',;:.-') . '…';
    }

    private function norm(string $s): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s)));
    }

    /** Shortens text at the end of a sentence, or else at a word, never in the middle of one. */
    private function shorten(string $text, int $limit): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        $cut = mb_substr($text, 0, $limit);
        $stop = max((int) mb_strrpos($cut, '. '), (int) mb_strrpos($cut, '? '), (int) mb_strrpos($cut, '! '));
        if ($stop >= (int) ($limit * 0.5)) {
            return mb_substr($cut, 0, $stop + 1);
        }
        $space = (int) mb_strrpos($cut, ' ');

        return rtrim(mb_substr($cut, 0, $space > 0 ? $space : $limit), " ,;:") . '…';
    }

    /** @param array<int,array{type:string,code:string,description:string}> $outcomes */
    private function outcomeSlides(array $outcomes): void
    {
        $by = ['learning_outcome' => [], 'competency' => []];
        foreach ($outcomes as $o) {
            if (isset($by[$o['type']]) && trim((string) $o['description']) !== '') {
                $by[$o['type']][] = trim((string) $o['code']) . ' ' . trim((string) $o['description']);
            }
        }
        foreach (['learning_outcome' => 'Learning outcomes', 'competency' => 'Competencies'] as $type => $title) {
            // At most five points and about 650 characters a slide, so a slide stays readable on a projector.
            $chunks = [];
            $cur = [];
            $size = 0;
            foreach ($by[$type] as $line) {
                if ($cur && (count($cur) >= 5 || $size + mb_strlen($line) > 650)) {
                    $chunks[] = $cur;
                    $cur = [];
                    $size = 0;
                }
                $cur[] = $line;
                $size += mb_strlen($line);
            }
            if ($cur) {
                $chunks[] = $cur;
            }
            if (!$chunks) {
                $this->missing[] = ['what' => $title, 'where' => 'whole chapter', 'why' => 'None are stored for this chapter.'];
            }
            foreach ($chunks as $i => $chunk) {
                $this->add('objectives', $title . (count($chunks) > 1 ? ' (' . ($i + 1) . ' of ' . count($chunks) . ')' : ''), [], [], ['body' => 'From the curriculum for this chapter.', 'bullets' => $chunk], null, 'Introduction');
            }
        }
    }

    // ------------------------------------------------------------------------------------------ assembly

    /**
     * @param array<int,int> $conceptIds
     * @param array<int,int> $taught
     * @param array<string,mixed> $content
     * @param array<string,mixed>|null $image
     */
    private function add(string $type, string $title, array $conceptIds, array $taught, array $content, ?array $image = null, ?string $section = null, ?array $interaction = null, ?string $reason = null): void
    {
        $this->slides[] = [
            'n' => 0, 'section' => $section, 'slide_type' => $type, 'title' => $title,
            'concept_ids' => array_values($conceptIds), 'taught_concept_ids' => array_values($taught), 'concepts' => [],
            'relationship' => null,
            'content' => $content + ['body' => '', 'explanations' => [], 'bullets' => [], 'example' => null, 'misconception' => null, 'relationship_note' => null, 'key_idea' => null, 'discussion' => null, 'bloom' => 'understand', 'dok' => 2, 'minutes' => 2],
            'question_ids' => [], 'activities' => [], 'interaction' => $interaction,
            'interaction_reason' => $reason ?? 'Plain teaching: built directly from the stored chapter data.',
            'h5p_pattern' => null, 'image' => $image,
            'image_missing' => $image === null && in_array($type, ['concept_intro', 'misconception'], true) && $taught ? 'No stored picture is verified for this concept.' : null,
        ];
    }

    /** @return array{deck:array<string,mixed>,missing:array<int,array{what:string,where:string,why:string}>,summary:array<string,mixed>} */
    private function finish(array $chapter, array $coverage, string $method, string $label, array $pictures, int $explained): array
    {
        $concepts = (array) $coverage['concepts'];
        $names = fn (array $ids) => array_values(array_filter(array_map(fn ($id) => $concepts[$id]['name'] ?? null, $ids)));
        $conceptSlides = [];
        $taughtBy = [];
        foreach ($this->slides as $i => &$s) {
            $s['n'] = $i + 1;
            $s['concepts'] = $names($s['concept_ids']);
            foreach ($s['concept_ids'] as $id) {
                $conceptSlides[(string) $id][] = $s['n'];
            }
            foreach ($s['taught_concept_ids'] as $id) {
                $taughtBy[(string) $id][] = $s['n'];
            }
        }
        unset($s);

        $deckConcepts = [];
        foreach ($concepts as $id => $c) {
            $text = $this->explanation($c)['text'];
            $deckConcepts[(string) $id] = ['id' => (int) $id, 'name' => $c['name'], 'topic_id' => $c['topic_id'], 'requires' => [], 'related' => [], 'definition' => $text !== '' ? $text : null];
        }
        $withImage = count(array_filter($this->slides, fn ($s) => $s['image'] !== null));

        $deck = [
            'version' => 3,
            'chapter' => ['id' => (int) $chapter['id'], 'name' => (string) $chapter['chapter_name'], 'standard_id' => (int) $chapter['standard_id'], 'subject_id' => (int) $chapter['subject_id'],
                'standard_name' => (string) ($chapter['standard_name'] ?? ''), 'subject_name' => (string) ($chapter['subject_name'] ?? '')],
            'slide_count' => count($this->slides),
            'teaching_strategy' => "{$label}. One chapter overview, then each topic in turn: its observation (where the data has one), one slide per idea with the strongest interaction its data supports, and a short check where the method asks for one; a reflection card per topic closes the chapter. Built directly from the chapter\'s stored data; nothing is generated.",
            'outline' => array_values(array_map(fn ($t) => ['topic_id' => (int) $t['id'], 'name' => (string) $t['name'], 'concept_ids' => array_map('intval', $t['concept_ids'])], (array) $coverage['topics'])),
            'concepts' => $deckConcepts,
            'slides' => $this->slides,
            'concept_slides' => (object) $conceptSlides,
            'taught_by' => (object) $taughtBy,
            'concept_questions' => (object) [],
        ];

        $problems = (new \App\Services\CoursePresentation\PresentationQuality())->check($deck, array_map('intval', array_keys($concepts)));
        $byKind = [];
        foreach ($this->slides as $s) {
            if (is_array($s['interaction'])) {
                $byKind[$s['interaction']['kind']] = ($byKind[$s['interaction']['kind']] ?? 0) + 1;
            }
        }

        return [
            'deck' => $deck,
            'missing' => $this->missing,
            'quality' => ['ok' => $problems === [], 'problems' => $problems],
            'summary' => ['method' => $method, 'slides' => count($this->slides), 'pictures_placed' => $withImage, 'pictures_available' => count($pictures),
                'interactive_slides' => array_sum($byKind), 'interactions' => $byKind,
                'derived_prompts' => $this->derivedPrompts, 'concepts_explained' => $explained] + (array) $coverage['summary'],
        ];
    }
}
