<?php

namespace App\Services\StudyDeck;

/**
 * The classroom PDF of a study deck: the whole lesson as a readable document.
 *
 * It is drawn from the STORED DECK (the same JSON the interactive player reads), not from the presentation markup.
 * That markup is built for slides: it carries each slide's explanation, bullets, example, misconception and
 * discussion, but never what the learner opens by clicking (hotspot descriptions, card explanations, steps, events,
 * scenario outcomes, matches) and never the concept definitions. A PDF cannot click, so every one of those is written
 * out in full here, as the static form of the interaction:
 *
 *   hotspots   the diagram, then a numbered legend: each part and what it is
 *   reveal     each card's title and explanation
 *   steps      the steps in order, each explained
 *   timeline   each event with its date
 *   compare    the things side by side, then how they compare
 *   match      each term with its meaning
 *   order      the sequence in its correct order
 *   scenario   the situation, each decision with every choice, what happens and why, and the conclusion
 *   "Explain this concept"   the explanation and the concept's definition, as visible sections
 *   worked example, common mistake and instead, key idea, Think-Pair-Share with the possible answer
 *
 * Nothing is written here that the deck does not hold: a slide with no definition shows no definition. One slide
 * starts on its own page and runs on to the next page when it needs to; nothing is shrunk to fit.
 *
 * Only pictures on the shared store (`$imageBase`) are drawn, the same rule the other generated documents follow.
 * Dompdf has no flexbox or grid, so layout is tables.
 */
class StudyDeckPdfRenderer
{
    /** Bump when the layout changes, so a stored PDF made by an older layout can be told apart. */
    public const LAYOUT_VERSION = 3;

    private const STAGE = [
        'cover' => 'Chapter', 'hook' => 'Introduction', 'objectives' => 'What you will do', 'prior_knowledge' => 'What you already know',
        'concept_intro' => 'Explanation', 'concept_visual' => 'See it', 'worked_example' => 'Worked example', 'relationship' => 'How ideas connect',
        'misconception' => 'Common mistake', 'scenario' => 'Decide', 'recall' => 'Recall', 'practice' => 'Practice', 'application' => 'Use it',
        'summary' => 'Summary', 'concept_map' => 'Big picture', 'challenge' => 'Challenge', 'exit_ticket' => 'Exit ticket',
    ];

    private const HEADING = [
        'hotspots' => 'Explore the diagram', 'scenario' => 'What would you do?', 'reveal' => 'Discover', 'steps' => 'Step by step',
        'timeline' => 'Timeline', 'compare' => 'Compare', 'match' => 'Match', 'order' => 'Put in order',
    ];

    public function __construct(private readonly string $imageBase)
    {
    }

    /**
     * @param array<string,mixed> $deck the stored deck (version 3)
     * @param string $baseCss the print stylesheet the other generated documents use
     */
    public function html(array $deck, string $baseCss): string
    {
        $chapter = (string) ($deck['chapter']['name'] ?? 'Study deck');
        $body = '';
        $first = true;
        foreach ($deck['slides'] ?? [] as $slide) {
            $body .= ($slide['slide_type'] ?? '') === 'cover' ? $this->cover($deck, $slide) : $this->slide($deck, $slide, $first);
            $first = false;
        }
        $body .= $this->credits($deck);

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $baseCss . $this->css() . '</style></head><body>'
            . '<div class="doc-title-bar"><h1>' . $this->e($chapter . ' Study Deck') . '</h1></div>'
            . '<div class="content">' . $body . '</div></body></html>';
    }

    // ---------------------------------------------------------------------------------------------------------

    /** @param array<string,mixed> $slide */
    private function cover(array $deck, array $slide): string
    {
        $ch = $deck['chapter'] ?? [];
        $lede = (string) ($slide['content']['body'] ?? '');

        return '<div class="cover">'
            . '<p class="eyebrow">' . $this->e('CLASS ' . ($ch['standard_name'] ?? '') . ' · ' . strtoupper((string) ($ch['subject_name'] ?? ''))) . '</p>'
            . '<h2>' . $this->e((string) ($ch['name'] ?? $slide['title'] ?? '')) . '</h2>'
            . ($lede !== '' ? '<p class="lede">' . $this->e($lede) . '</p>' : '')
            . '</div>';
    }

    /** @param array<string,mixed> $slide */
    private function slide(array $deck, array $slide, bool $first): string
    {
        $c = $slide['content'] ?? [];
        $n = (int) ($slide['n'] ?? 0);
        $concepts = $deck['concepts'] ?? [];
        $name = fn ($id) => (string) ($concepts[(string) $id]['name'] ?? '');

        $out = '<div class="sd-slide' . ($first ? ' sd-first' : '') . '">';
        $out .= '<div class="slide-head"><span class="slide-num">' . $this->e('SLIDE ' . $n . ' · ' . strtoupper(self::STAGE[$slide['slide_type'] ?? ''] ?? 'Lesson')) . '</span>'
            . '<h3>' . $this->e((string) ($slide['title'] ?? '')) . '</h3></div>';

        $taught = array_values(array_filter(array_map($name, $slide['taught_concept_ids'] ?? [])));
        if ($taught) {
            $out .= '<p class="sd-sub">Concept: <strong>' . $this->e(implode(' and ', $taught)) . '</strong></p>';
        }

        // The explanation behind "Explain this concept", and each taught concept's definition.
        $explanations = $c['explanations'] ?? [];
        $multi = count($slide['taught_concept_ids'] ?? []) > 1;
        foreach ($explanations as $e) {
            $out .= '<div class="callout callout-key"><span class="callout-label">EXPLANATION' . ($multi && $name($e['concept_id'] ?? 0) !== '' ? ' · ' . $this->e(strtoupper($name($e['concept_id']))) : '') . '</span>'
                . '<p>' . $this->e((string) ($e['text'] ?? '')) . '</p></div>';
        }
        if (!$explanations && trim((string) ($c['body'] ?? '')) !== '') {
            $out .= '<p class="sd-lead">' . $this->e((string) $c['body']) . '</p>';
        }
        $said = array_map(fn ($e) => $this->norm((string) ($e['text'] ?? '')), $explanations);
        foreach ($slide['taught_concept_ids'] ?? [] as $id) {
            $def = trim((string) ($concepts[(string) $id]['definition'] ?? ''));
            if ($def !== '' && !in_array($this->norm($def), $said, true)) {
                $out .= '<div class="callout"><span class="callout-label">DEFINITION' . ($multi ? ' · ' . $this->e(strtoupper($name($id))) : '') . '</span><p>' . $this->e($def) . '</p></div>';
            }
        }

        if (!empty($c['bullets'])) {
            $out .= '<ul class="sd-points">' . implode('', array_map(fn ($b) => '<li>' . $this->e((string) $b) . '</li>', $c['bullets'])) . '</ul>';
        }

        $out .= $this->figure($slide['image'] ?? null);
        $out .= $this->interaction($slide['interaction'] ?? null);

        if (!empty($c['example'])) {
            $out .= '<div class="callout callout-example"><span class="callout-label">WORKED EXAMPLE</span><p>' . $this->e((string) $c['example']) . '</p></div>';
        }
        if (!empty($c['misconception'])) {
            $m = $c['misconception'];
            $out .= '<table class="sd-pair"><tr>'
                . '<td class="sd-wrong"><span class="callout-label">COMMON MISTAKE - NOT QUITE</span>' . $this->e((string) ($m['wrong_idea'] ?? '')) . '</td>'
                . '<td class="sd-right"><span class="callout-label">INSTEAD</span>' . $this->e((string) ($m['correction'] ?? '')) . '</td></tr></table>';
        }
        if (!empty($c['relationship_note'])) {
            $out .= '<div class="callout callout-key"><span class="callout-label">HOW THESE CONNECT</span><p>' . $this->e((string) $c['relationship_note']) . '</p></div>';
        }
        $key = trim((string) ($c['key_idea'] ?? ''));
        if ($key !== '') {
            $out .= '<div class="callout callout-key"><span class="callout-label">KEY IDEA</span><p>' . $this->e($key) . '</p></div>';
        }
        if (!empty($c['discussion'])) {
            $d = $c['discussion'];
            $out .= '<div class="callout callout-try"><span class="callout-label">TALK ABOUT IT · THINK, PAIR, SHARE</span>'
                . '<p><strong>' . $this->e((string) ($d['prompt'] ?? '')) . '</strong></p>'
                . '<p class="sd-tps"><strong>Think.</strong> On your own for a minute. <strong>Pair.</strong> Compare with the person next to you. <strong>Share.</strong> Tell the class what you decided.</p>'
                . (trim((string) ($d['answer'] ?? '')) !== '' ? '<p><strong>Possible answer:</strong> ' . $this->e((string) $d['answer']) . '</p>' : '')
                . '</div>';
        }

        return $out . '</div>';
    }

    // ---------------------------------------------------------------------------------------------------------
    // Interactions, as static content

    /** @param array<string,mixed>|null $i */
    private function interaction(?array $i): string
    {
        if (!$i || empty($i['kind'])) {
            return '';
        }
        $kind = (string) $i['kind'];
        $out = '<h4 class="sd-h">' . $this->e(self::HEADING[$kind] ?? 'Explore') . '</h4>';
        $out .= $this->intro($i);

        switch ($kind) {
            case 'hotspots':
                $out .= $this->legend(array_map(fn ($s) => [(string) ($s['label'] ?? ''), (string) ($s['text'] ?? '')], $i['spots'] ?? []));
                break;
            case 'reveal':
                $out .= $this->legend(array_map(fn ($s) => [(string) ($s['label'] ?? ''), (string) ($s['text'] ?? '')], $i['items'] ?? []));
                break;
            case 'steps':
                $out .= $this->legend(array_map(fn ($s) => [(string) ($s['label'] ?? ''), (string) ($s['text'] ?? '')], $i['items'] ?? []), 'Step ');
                break;
            case 'timeline':
                $out .= $this->legend(array_map(fn ($s) => [trim(($s['when'] ?? '') . ' ' . ($s['label'] ?? '')), (string) ($s['text'] ?? '')], $i['items'] ?? []));
                break;
            case 'compare':
                $out .= $this->compare($i['items'] ?? []);
                break;
            case 'match':
                $out .= '<table class="sd-legend"><tr><th>Term</th><th>Meaning</th></tr>'
                    . implode('', array_map(fn ($p) => '<tr><td class="sd-term">' . $this->e((string) ($p['term'] ?? '')) . '</td><td>' . $this->e((string) ($p['meaning'] ?? '')) . '</td></tr>', $i['pairs'] ?? []))
                    . '</table>';
                break;
            case 'order':
                $out .= '<p class="sd-note">The correct order:</p><ol class="sd-points">'
                    . implode('', array_map(fn ($s) => '<li>' . $this->e((string) ($s['text'] ?? '')) . '</li>', $i['items'] ?? [])) . '</ol>';
                break;
            case 'scenario':
                $out .= $this->scenario($i);
                break;
        }

        $wrap = trim((string) ($i['wrapup'] ?? $i['conclusion'] ?? ''));
        if ($wrap !== '') {
            $out .= '<div class="callout callout-key"><span class="callout-label">' . ($kind === 'compare' ? 'HOW THEY COMPARE' : ($kind === 'scenario' ? 'IN CONCLUSION' : 'PUTTING IT TOGETHER')) . '</span><p>' . $this->e($wrap) . '</p></div>';
        }

        return $out;
    }

    /** @param array<string,mixed> $i */
    private function intro(array $i): string
    {
        $text = trim((string) ($i['intro'] ?? $i['situation'] ?? ''));
        // "Select each quantity..." tells a learner to click; on paper it is noise. A scenario's situation is content and stays.
        if (empty($i['situation']) && preg_match('/^(select|open|click|tap|choose|pick|explore|match|put|drag)\b/i', $text)) {
            return '';
        }

        return $text !== '' ? '<p class="sd-note">' . $this->e($text) . '</p>' : '';
    }

    /**
     * A numbered list of (title, explanation): the static form of "select one to see it".
     *
     * @param array<int,array{0:string,1:string}> $rows
     */
    private function legend(array $rows, string $numberPrefix = ''): string
    {
        $out = '<table class="sd-legend">';
        foreach (array_values($rows) as $index => [$label, $text]) {
            $out .= '<tr><td class="sd-num">' . $this->e($numberPrefix . ($index + 1)) . '</td>'
                . '<td class="sd-term">' . $this->e($label) . '</td><td>' . $this->e($text) . '</td></tr>';
        }

        return $out . '</table>';
    }

    /** @param array<int,array<string,mixed>> $items */
    private function compare(array $items): string
    {
        if (!$items) {
            return '';
        }
        $cells = fn (callable $f, string $tag) => implode('', array_map(fn ($it) => "<$tag>" . $f($it) . "</$tag>", $items));

        return '<table class="sd-compare"><tr>' . $cells(fn ($it) => $this->e((string) ($it['label'] ?? '')), 'th') . '</tr>'
            . '<tr>' . $cells(fn ($it) => $this->e((string) ($it['text'] ?? '')), 'td') . '</tr></table>';
    }

    /** @param array<string,mixed> $i */
    private function scenario(array $i): string
    {
        $out = '';
        foreach (array_values($i['nodes'] ?? []) as $index => $node) {
            $out .= '<p class="sd-decision"><strong>Decision ' . ($index + 1) . '.</strong> ' . $this->e((string) ($node['prompt'] ?? '')) . '</p>';
            $out .= '<table class="sd-legend"><tr><th>Choice</th><th>What happens</th><th>Why</th></tr>';
            foreach ($node['choices'] ?? [] as $ch) {
                $out .= '<tr><td class="sd-term">' . $this->e((string) ($ch['text'] ?? '')) . (!empty($ch['sound']) ? '<br><span class="sd-sound">Sound choice</span>' : '') . '</td>'
                    . '<td>' . $this->e((string) ($ch['outcome'] ?? '')) . '</td><td>' . $this->e((string) ($ch['why'] ?? '')) . '</td></tr>';
            }
            $out .= '</table>';
        }

        return $out;
    }

    // ---------------------------------------------------------------------------------------------------------

    /** @param array<string,mixed>|null $img */
    private function figure(?array $img): string
    {
        $url = (string) ($img['url'] ?? '');
        if (!$img || $url === '' || !str_starts_with($url, $this->imageBase)) {
            return '';
        }
        // Wide pictures run the page's width; tall ones are limited in height so the legend below still fits.
        $w = max(1, (int) ($img['width'] ?? 1280));
        $h = max(1, (int) ($img['height'] ?? 720));
        $width = (int) min(450, 215 * $w / $h);
        $credit = ($img['type'] ?? 'photo') === 'photo' ? trim(($img['creator'] ?: 'Openverse') . ', ' . ($img['licence'] ?? '')) : '';
        $caption = trim(rtrim((string) ($img['caption'] ?? ''), '.') . ($credit !== '' ? '. ' . $credit : ''));

        return '<div class="sd-fig"><img src="' . $this->e($url) . '" width="' . $width . '" alt="' . $this->e((string) ($img['alt'] ?? '')) . '">'
            . ($caption !== '' ? '<div class="sd-cap">' . $this->e($caption) . '</div>' : '') . '</div>';
    }

    /** @param array<string,mixed> $deck */
    private function credits(array $deck): string
    {
        $rows = [];
        foreach ($deck['slides'] ?? [] as $slide) {
            $img = $slide['image'] ?? null;
            if ($img && ($img['type'] ?? 'photo') === 'photo') {
                $rows[] = 'Slide ' . $slide['n'] . ': ' . ($img['attribution'] ?: trim(($img['title'] ?? '') . ' by ' . ($img['creator'] ?? '') . ' (' . ($img['licence'] ?? '') . ')')) . (!empty($img['source_url']) ? ' Source: ' . $img['source_url'] : '');
            }
        }
        if (!$rows) {
            return '';
        }

        return '<div class="sd-slide"><div class="slide-head"><span class="slide-num">CREDITS</span><h3>Image credits</h3></div>'
            . implode('', array_map(fn ($r) => '<p class="sd-note">' . $this->e($r) . '</p>', $rows)) . '</div>';
    }

    private function css(): string
    {
        return <<<'CSS'

.sd-slide { page-break-before: always; margin: 0; line-height: 1.4; font-size: 12px; }
.sd-slide p { margin: 0 0 6px; }
.sd-slide .callout { margin: 7px 0; padding: 7px 12px; }
.sd-slide .slide-head { margin-bottom: 6px; }
.sd-slide ul, .sd-slide ol { margin-bottom: 6px; }
.sd-slide li { margin-bottom: 2px; }
.sd-first { page-break-before: avoid; }
.sd-sub { color: #64748b; font-size: 10.5px; margin: 0 0 8px; }
.sd-lead { font-size: 13px; margin: 0 0 7px; }
.sd-note { color: #475569; font-size: 11px; margin: 0 0 6px; }
.sd-points { margin: 0 0 10px 18px; }
.sd-h { color: #4338ca; font-size: 10.5px; letter-spacing: 1px; text-transform: uppercase; margin: 8px 0 3px; page-break-after: avoid; }
.sd-fig { text-align: center; margin: 5px 0 6px; page-break-inside: avoid; }
.sd-fig img { border: 1px solid #e2e8f0; border-radius: 6px; }
.sd-cap { color: #64748b; font-size: 10px; font-style: italic; margin-top: 4px; }
.sd-legend { width: 100%; border-collapse: collapse; margin: 3px 0 7px; }
.sd-legend th { background: #eef2ff; color: #3730a3; font-size: 10px; text-align: left; border: 1px solid #c7d2fe; padding: 5px 7px; }
.sd-legend td { border: 1px solid #e2e8f0; padding: 4px 8px; vertical-align: top; font-size: 12px; page-break-inside: avoid; }
.sd-legend tr { page-break-inside: avoid; }
.sd-num { width: 46px; white-space: nowrap; color: #4f46e5; font-weight: bold; text-align: center; background: #f8fafc; }
.sd-term { width: 24%; font-weight: bold; color: #1e293b; background: #f8fafc; }
.sd-sound { color: #047857; font-size: 10px; font-weight: bold; }
.sd-decision { margin: 8px 0 3px; font-size: 12.5px; page-break-after: avoid; }
.sd-compare { width: 100%; border-collapse: collapse; margin: 4px 0 10px; table-layout: fixed; }
.sd-compare th { background: #eef2ff; color: #3730a3; border: 1px solid #c7d2fe; padding: 6px 8px; text-align: left; }
.sd-compare td { border: 1px solid #e2e8f0; padding: 7px 8px; vertical-align: top; font-size: 12px; }
.sd-pair { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 7px 0; }
.sd-pair td { width: 50%; vertical-align: top; padding: 9px 12px; font-size: 12px; page-break-inside: avoid; }
.sd-wrong { background: #fffbeb; border: 1px solid #fcd34d; }
.sd-right { background: #ecfdf5; border: 1px solid #6ee7b7; }
.sd-pair .callout-label { margin-bottom: 4px; }
.sd-wrong .callout-label { color: #b45309; }
.sd-right .callout-label { color: #047857; }
.sd-tps { color: #475569; font-size: 11px; }
.slide-head { page-break-after: avoid; }
CSS;
    }

    private function norm(string $s): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
