<?php

namespace App\Services\StudyDeck;

/**
 * Turns planned slides + written content + found images + activity specs into
 *   1. the EXISTING content design-system markup (.cover / .slide / .callout with the
 *      five data-* attributes), which ContentGenerationService renders to PPTX/PDF, and
 *   2. the deck metadata the native student player reads (structured slide content,
 *      activity specs, concept -> slide and concept -> question maps).
 *
 * Both come from the same data, so the presentation file and the interactive player
 * can never disagree about what was taught.
 *
 * The markup follows the contract RendersContentPresentation parses: slide content as
 * DIRECT children of the section (p, ul, table, figure, callouts), and a check's answer
 * in a paragraph opening with a bold "Answer:" so the PPTX reveals it on its own click.
 * A stored explanation follows the answer, inside the same reveal.
 */
class SlideHtmlRenderer
{
    public const DECK_VERSION = 3;

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $map
     * @param array<string,mixed> $plan
     * @param array<int,array<string,mixed>> $content
     * @param array<int,array<string,mixed>> $images
     * @param array<int,array<int,array<string,mixed>>> $eligible concept_id => questions
     * @param array<int,array<int,array<string,mixed>>> $activities slide number => activity specs
     * @param array<int,array{interaction:?array<string,mixed>,reason:string}> $interactions slide number => hotspots / scenario / reveal, or why none
     * @return array{html:string, deck:array<string,mixed>}
     */
    public function render(array $context, array $map, array $plan, array $content, array $images, array $eligible, array $activities = [], array $interactions = []): array
    {
        $questions = [];
        foreach ($eligible as $list) {
            foreach ($list as $q) {
                $questions[$q['id']] = $q;
            }
        }

        $html = [];
        $deckSlides = [];

        foreach ($plan['slides'] as $slide) {
            $n = $slide['n'];
            $c = $content[$n];
            $img = isset($images[$n]) && !isset($images[$n]['missing']) ? $images[$n] : null;
            $acts = $activities[$n] ?? [];

            $html[] = $slide['slide_type'] === 'cover'
                ? $this->cover($context, $c)
                : $this->slide($map, $slide, $c, $img, $questions, $acts);
            $discussion = $slide['slide_type'] !== 'cover' && $slide['question_ids'] === [] && !empty($c['check'])
                ? ['prompt' => $c['check']['question'], 'answer' => $c['check']['answer']]
                : null;

            $deckSlides[] = [
                'n' => $n,
                'section' => $slide['section'] ?? null,
                'slide_type' => $slide['slide_type'],
                'title' => $c['title'],
                'concept_ids' => $slide['concept_ids'],
                'taught_concept_ids' => $slide['taught_concept_ids'],
                'concepts' => array_map(fn ($id) => $map['concepts'][$id]['name'], $slide['concept_ids']),
                'relationship' => $slide['relationship'] ?? null,
                'content' => [
                    'body' => $c['body'],
                    'explanations' => $c['explanations'],
                    'bullets' => $c['bullets'],
                    'example' => $c['example'],
                    'misconception' => $c['misconception'],
                    'relationship_note' => $c['relationship_note'],
                    'key_idea' => $c['key_idea'] ?? null,
                    'bloom' => $c['bloom'],
                    'dok' => $c['dok'],
                    'minutes' => $c['minutes'],
                    'discussion' => $discussion,
                ],
                'interaction' => $interactions[$n]['interaction'] ?? null,
                'interaction_reason' => $interactions[$n]['reason'] ?? '',
                'question_ids' => $slide['question_ids'],
                'activities' => $acts,
                'h5p_pattern' => $slide['h5p_pattern'],
                'pattern_dropped' => $slide['pattern_dropped'] ?? null,
                'visual' => $slide['visual'],
                'image' => $img,
                'image_missing' => isset($images[$n]['missing']) ? $images[$n]['missing'] : null,
                'sources' => [
                    'concept_intelligence' => $slide['concept_ids'] !== [],
                    'existing_ai_content' => ($slide['uses_baseline'] ?? 'new') !== 'new',
                    'question_bank' => $slide['question_ids'] !== [],
                    'h5p_pattern_reference' => $slide['h5p_pattern'] !== null,
                ],
            ];
        }

        $credits = [];
        foreach ($deckSlides as $d) {
            if ($d['image'] && ($d['image']['type'] ?? 'photo') === 'photo') {
                $i = $d['image'];
                $credits[] = 'Slide ' . $d['n'] . ': ' . ($i['attribution'] ?: ($i['title'] . ' by ' . $i['creator'] . ' (' . $i['licence'] . ')'))
                    . ' Source: ' . $i['source_url'];
            }
        }
        if ($credits) {
            $last = count($html) - 1;
            $html[$last] = preg_replace(
                '#</section>$#',
                '<section class="callout callout-key" data-block="summary"><span class="callout-label">Image credits</span>'
                . implode('', array_map(fn ($c) => '<p>' . $this->e($c) . '</p>', $credits)) . '</section></section>',
                $html[$last]
            );
        }

        $conceptSlides = [];
        $conceptQuestions = [];
        $taughtBy = [];
        foreach ($deckSlides as $d) {
            foreach ($d['concept_ids'] as $cid) {
                $conceptSlides[$cid][] = $d['n'];
            }
            foreach ($d['taught_concept_ids'] as $cid) {
                $taughtBy[$cid][] = $d['n'];
            }
            foreach ($d['question_ids'] as $qid) {
                $conceptQuestions[$questions[$qid]['concept_id']][] = $qid;
            }
        }

        $outline = [];
        foreach ($map['topics'] as $t) {
            $outline[] = ['topic_id' => $t['id'], 'name' => $t['name'], 'concept_ids' => $t['concept_ids']];
        }
        $concepts = [];
        foreach ($map['concepts'] as $id => $c) {
            $concepts[$id] = [
                'id' => $id, 'name' => $c['name'], 'topic_id' => $c['topic_id'],
                'requires' => $c['requires'], 'related' => $c['related'], 'definition' => $c['definition'],
            ];
        }

        return [
            'html' => implode("\n", $html),
            'deck' => [
                'version' => self::DECK_VERSION,
                'chapter' => [
                    'id' => (int) $context['chapter']['id'],
                    'name' => $context['chapter']['chapter_name'],
                    'standard_id' => (int) $context['chapter']['standard_id'],
                    'subject_id' => (int) $context['chapter']['subject_id'],
                    'standard_name' => $context['chapter']['standard_name'],
                    'subject_name' => $context['chapter']['subject_name'],
                ],
                'chapter_id' => (int) $context['chapter']['id'],
                'slide_count' => count($deckSlides),
                'teaching_strategy' => $plan['teaching_strategy'] ?? null,
                'baseline_review' => $plan['baseline_review'] ?? null,
                'outline' => $outline,
                'concepts' => $concepts,
                'slides' => $deckSlides,
                'concept_slides' => $conceptSlides,
                'taught_by' => $taughtBy,
                'concept_questions' => $conceptQuestions,
            ],
        ];
    }

    private function cover(array $context, array $c): string
    {
        $ch = $context['chapter'];

        return '<section class="cover" data-block="intro">'
            . '<p class="eyebrow">' . $this->e('Class ' . $ch['standard_name'] . ' · ' . $ch['subject_name']) . '</p>'
            . '<h2>' . $this->e($ch['chapter_name']) . '</h2>'
            . '<p class="lede">' . $this->e($c['body'] !== '' ? $c['body'] : $c['title']) . '</p>'
            . '</section>';
    }

    /**
     * @param array<string,mixed> $map
     * @param array<string,mixed> $slide
     * @param array<string,mixed> $c
     * @param array<string,mixed>|null $img
     * @param array<int,array<string,mixed>> $questions
     * @param array<int,array<string,mixed>> $acts
     */
    private function slide(array $map, array $slide, array $c, ?array $img, array $questions, array $acts): string
    {
        $name = fn (int $id) => $map['concepts'][$id]['name'];
        $first = $slide['taught_concept_ids'][0] ?? $slide['concept_ids'][0] ?? null;
        $firstName = $first !== null ? $name($first) : null;

        $attrs = function (string $block, ?string $concept, ?string $bloom) use ($c): string {
            return ' data-block="' . $block . '"'
                . ($concept !== null ? ' data-concept="' . $this->e($concept) . '"' : '')
                . ($bloom !== null && $bloom !== '' ? ' data-bloom="' . $bloom . '"' : '')
                . ' data-dok="' . $c['dok'] . '" data-minutes="' . $c['minutes'] . '"';
        };

        $out = '<section class="slide">'
            . '<div class="slide-head"><span class="slide-num">Slide ' . $slide['n'] . '</span><h3>' . $this->e($c['title']) . '</h3></div>';

        if ($c['explanations']) {
            foreach ($c['explanations'] as $e) {
                $out .= '<p' . $attrs('explain', $name($e['concept_id']), $c['bloom']) . '>' . $this->e($e['text']) . '</p>';
            }
        } elseif ($c['body'] !== '') {
            $out .= '<p' . $attrs('explain', $firstName, $c['bloom']) . '>' . $this->e($c['body']) . '</p>';
        }
        if ($c['bullets']) {
            $out .= '<ul>' . implode('', array_map(fn ($b) => '<li>' . $this->e($b) . '</li>', $c['bullets'])) . '</ul>';
        }

        if ($img) {
            $credit = ($img['type'] ?? 'photo') === 'photo' ? '. ' . trim(($img['creator'] ?: 'Openverse') . ', ' . $img['licence']) : '';
            $out .= '<figure class="fig-md"' . $attrs('visual', $firstName, $c['bloom']) . '>'
                . '<img src="' . $this->e($img['url']) . '" alt="' . $this->e($img['alt']) . '">'
                . '<figcaption>' . $this->e(rtrim((string) $img['caption'], '.') . $credit) . '</figcaption></figure>';
        }

        if ($c['example'] !== null) {
            $out .= '<section class="callout callout-example"' . $attrs('example', $firstName, $c['bloom']) . '><span class="callout-label">Worked example</span><p>'
                . $this->e($c['example']) . '</p></section>';
        }
        if ($c['misconception'] !== null) {
            $out .= '<section class="callout callout-warn"' . $attrs('misconception', $firstName, $c['bloom']) . '><span class="callout-label">Common misconception</span>'
                . '<p>' . $this->e($c['misconception']['wrong_idea']) . '</p><p>' . $this->e($c['misconception']['correction']) . '</p></section>';
        }
        if ($c['relationship_note'] !== null) {
            $to = $slide['relationship']['to'] ?? null;
            $out .= '<section class="callout callout-key"' . $attrs('summary', $to !== null && isset($map['concepts'][$to]) ? $name($to) : $firstName, $c['bloom']) . '><span class="callout-label">How these connect</span><p>'
                . $this->e($c['relationship_note']) . '</p></section>';
        }

        $byQuestion = [];
        foreach ($acts as $a) {
            if (($a['source'] ?? '') === 'bank') {
                $byQuestion[$a['question_id']] = $a;
            }
        }
        foreach ($slide['question_ids'] as $qid) {
            $q = $questions[$qid];
            $a = $byQuestion[$qid] ?? ['label' => 'Check'];
            $out .= $this->bankCheck($q, $a['label'], $attrs('check', $name($q['concept_id']), $q['bloom']));
        }
        // A discussion prompt for the class, with a possible answer the PPTX reveals on its own click.
        // A slide that carries a bank question has that instead.
        if ($slide['question_ids'] === [] && !empty($c['check'])) {
            $out .= '<section class="callout callout-try"' . $attrs('check', $firstName, $c['bloom']) . '><span class="callout-label">Discuss</span>'
                . '<p>' . $this->e($c['check']['question']) . '</p>'
                . '<p><strong>Answer:</strong> ' . $this->e($c['check']['answer']) . '</p></section>';
        }

        return $out . '</section>';
    }

    /** @param array<string,mixed> $q */
    private function bankCheck(array $q, string $label, string $attrs): string
    {
        $out = '<section class="callout callout-try"' . $attrs . '><span class="callout-label">' . $this->e($label) . '</span>';
        $out .= $this->stem($q['stem']);

        if ($q['options']) {
            $out .= '<ul>' . implode('', array_map(
                fn ($o) => '<li>' . $this->e($o['label'] . '. ' . $o['text']) . '</li>',
                $q['options']
            )) . '</ul>';
            $correct = array_values(array_filter($q['options'], fn ($o) => $o['label'] === $q['correct_label']))[0] ?? null;
            $answer = $correct ? $correct['label'] . '. ' . $correct['text'] : (string) $q['correct_label'];
        } else {
            $answer = $q['answer_text'];
        }

        $out .= '<p><strong>Answer:</strong> ' . $this->e($answer) . '</p>';
        // The stored rationale rides in the same reveal as the answer, so a learner is told why.
        if ($q['explanation'] !== '' && $q['explanation'] !== $answer) {
            $out .= '<p><strong>Why:</strong> ' . $this->e($q['explanation']) . '</p>';
        }

        return $out . '</section>';
    }

    /**
     * The stem verbatim, with any embedded table flattened to one line per row:
     * the PPTX callout reader keeps paragraphs and list items only, and a table
     * the learner needs to read must survive it.
     */
    public function stem(string $stem): string
    {
        $paragraphs = [];
        $text = preg_replace_callback('#<table.*?</table>#is', function ($m) use (&$paragraphs) {
            $rows = [];
            if (preg_match_all('#<tr.*?</tr>#is', $m[0], $trs)) {
                foreach ($trs[0] as $tr) {
                    preg_match_all('#<t[dh][^>]*>(.*?)</t[dh]>#is', $tr, $cells);
                    $rows[] = implode(' | ', array_map(fn ($x) => trim(html_entity_decode(strip_tags($x))), $cells[1]));
                }
            }
            $paragraphs[] = $rows;

            return "\n@@TABLE" . (count($paragraphs) - 1) . "@@\n";
        }, $stem);

        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", (string) $text)));
        $out = '';
        foreach (preg_split('/\n+/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^@@TABLE(\d+)@@$/', $line, $m)) {
                foreach ($paragraphs[(int) $m[1]] as $row) {
                    $out .= '<p>' . $this->e($row) . '</p>';
                }
            } else {
                $out .= '<p>' . $this->e($line) . '</p>';
            }
        }

        return $out;
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
