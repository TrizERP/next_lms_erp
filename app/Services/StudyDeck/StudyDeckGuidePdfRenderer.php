<?php

namespace App\Services\StudyDeck;

/**
 * The study guide PDF of a study deck, designed to read like the lesson it comes from.
 *
 * It says exactly what StudyDeckPdfRenderer says (every card, every interaction written out in full, every practice
 * question, nothing the deck does not hold) and changes how it looks and how it moves:
 *
 *   cover      the chapter's own picture edge to edge under a deep blue wash, "Chapter 2", the title, the standard and
 *              subject and four counts. Made with GD (the picture cropped to the page, the wash drawn onto it) so Dompdf
 *              only has to place one image; a deck with no picture gets a drawn cell pattern instead.
 *   colour     each topic has its own colour family, the same five the player uses (blue, teal, purple, cyan, amber; the
 *              opening slides are indigo), on the topic band, the slide heading, the activity panels and the key idea.
 *   pictures   a photograph is a figure card (the picture, its caption, its credit and a link to its source) placed
 *              right under the slide heading; a drawn diagram is a bordered card with its markers in the topic colour.
 *              Every picture is shrunk to what a page can use, so the file stays a size that can be mailed.
 *   moving     the contents page links every concept to its slide; each slide heading links to that slide in the Study
 *              Deck, and so does each activity panel; every page's footer links back to the contents; the viewer's
 *              bookmarks panel lists the topics and their slides (see PdfOutline). A PDF cannot run the player, and
 *              this does not pretend to: it opens it.
 *
 * LAYOUT. The same flowing layout and the same pagination rule as the base class: a slide does not start a new page, short
 * things are kept whole, long things run over. Dompdf has no flexbox or grid, so layout is tables.
 */
class StudyDeckGuidePdfRenderer extends StudyDeckPdfRenderer
{
    public const LAYOUT_VERSION = 5;

    /** The page, in CSS px (A4 at 96 dpi), and the width left for text between the margins. */
    protected const PAGE_W = 794;

    protected const PAGE_H = 1123;

    /** Colour families in topic order: the player's blue, teal, purple, cyan and amber (lib/study-deck/theme.ts). */
    protected const PALETTES = [
        ['accent' => '#1d4ed8', 'strong' => '#1e3a8a', 'soft' => '#dbeafe', 'softer' => '#eff6ff', 'line' => '#93c5fd', 'rgb' => [29, 78, 216]],
        ['accent' => '#0f766e', 'strong' => '#134e4a', 'soft' => '#ccfbf1', 'softer' => '#f0fdfa', 'line' => '#5eead4', 'rgb' => [15, 118, 110]],
        ['accent' => '#7c3aed', 'strong' => '#4c1d95', 'soft' => '#ede9fe', 'softer' => '#f5f3ff', 'line' => '#c4b5fd', 'rgb' => [124, 58, 237]],
        ['accent' => '#0e7490', 'strong' => '#164e63', 'soft' => '#cffafe', 'softer' => '#ecfeff', 'line' => '#67e8f9', 'rgb' => [14, 116, 144]],
        ['accent' => '#b45309', 'strong' => '#78350f', 'soft' => '#fef3c7', 'softer' => '#fffbeb', 'line' => '#fcd34d', 'rgb' => [180, 83, 9]],
    ];

    /** The opening and closing slides, which belong to no topic. */
    protected const BRAND = ['accent' => '#4f46e5', 'strong' => '#312e81', 'soft' => '#e0e7ff', 'softer' => '#eef2ff', 'line' => '#a5b4fc', 'rgb' => [79, 70, 229]];

    /** The longest side, in pixels, a picture is kept at in the PDF. A page prints a picture at most about 700px wide. */
    protected const MAX_PICTURE = 1100;

    /** @var array<string,mixed> the colour family of the slide being drawn */
    protected array $pal = self::BRAND;

    /** The slide whose photograph has already been placed under its heading, so the figure is not drawn a second time. */
    protected ?int $heroDoneFor = null;

    /** @var array<string,array{bytes:string,mime:string}|null> pictures already shrunk, by reference */
    private array $shrunk = [];

    /** @var array<int,array{first:int,last:int}> the slides each topic runs over, by topic position */
    private array $spans = [];

    // ---------------------------------------------------------------------------------------------------------
    // Colour

    /** The topic a slide belongs to, by its position in the outline; null for a slide that teaches no concept. */
    protected function topicIndexOf(array $deck, array $slide): ?int
    {
        foreach ($slide['taught_concept_ids'] ?? $slide['concept_ids'] ?? [] as $cid) {
            $topicId = $deck['concepts'][(string) $cid]['topic_id'] ?? null;
            if ($topicId === null) {
                continue;
            }
            foreach (array_values($deck['outline'] ?? []) as $i => $t) {
                if ((int) ($t['topic_id'] ?? 0) === (int) $topicId) {
                    return $i;
                }
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    protected function paletteOf(?int $topic): array
    {
        return $topic === null ? self::BRAND : self::PALETTES[$topic % count(self::PALETTES)];
    }

    protected function markerRgb(): array
    {
        return $this->pal['rgb'];
    }

    protected function slideClass(array $deck, array $slide): string
    {
        $topic = $this->topicIndexOf($deck, $slide);

        return 'sl tp' . ($topic === null ? 'b' : $topic % count(self::PALETTES));
    }

    // ---------------------------------------------------------------------------------------------------------
    // Cover

    /** @param array<string,mixed> $deck */
    protected function cover(array $deck): string
    {
        $ch = $deck['chapter'] ?? [];
        $slides = $deck['slides'] ?? [];
        $cover = null;
        foreach ($slides as $s) {
            if (($s['slide_type'] ?? '') === 'cover') {
                $cover = $s;
                break;
            }
        }
        $lede = trim((string) ($cover['content']['body'] ?? ''));
        $activities = count(array_filter($slides, fn ($s) => !empty($s['interaction'])));
        $number = (int) ($ch['number'] ?? 0);

        [$art, $credit] = $this->coverArt($deck, $cover);

        $tile = fn (string $n, string $label) => '<td class="gc-t"><span class="gc-n">' . $this->e($n) . '</span><span class="gc-l">' . $this->e($label) . '</span></td>';

        return '<div class="gc" id="cover">'
            . ($art !== null ? '<img class="gc-bg" src="' . $art . '" width="' . self::PAGE_W . '" height="1100" alt="">' : '')
            . '<div class="gc-top">' . $this->e(strtoupper('Class ' . ($ch['standard_name'] ?? '') . '  ·  ' . ($ch['subject_name'] ?? ''))) . '</div>'
            . '<div class="gc-main">'
            . ($number > 0 ? '<div class="gc-ch">Chapter ' . $number . '</div>' : '')
            . '<div class="gc-title">' . $this->e((string) ($ch['name'] ?? '')) . '</div>'
            . '<div class="gc-bar"></div>'
            . '<div class="gc-kind">' . ($this->variant === self::PRACTICE ? 'STUDY GUIDE  ·  PRACTICE COPY' : 'STUDY GUIDE') . '</div>'
            . ($lede !== '' ? '<div class="gc-lede">' . $this->e($lede) . '</div>' : '')
            . '</div>'
            . '<table class="gc-st"><tr>' . $tile((string) count($slides), 'slides') . $tile((string) count($deck['concepts'] ?? []), 'concepts')
            . $tile((string) $activities, 'interactive activities') . $tile((string) $this->practiceCount($deck), 'practice questions') . '</tr></table>'
            . ($credit !== '' ? '<div class="gc-cr">' . $this->e($credit) . '</div>' : '')
            . '</div>';
    }

    /**
     * The cover picture as a data: URI, and its credit line. The chapter's own picture (slide 1) is cropped to the page
     * and a blue wash is drawn onto it, strong behind the words at the top and at the foot, lighter between; with no picture
     * a drawn pattern of cells stands in. Null art (no GD) leaves the page to its CSS colour.
     *
     * @param array<string,mixed>|null $cover
     * @return array{0:?string,1:string}
     */
    protected function coverArt(array $deck, ?array $cover): array
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
            return [null, ''];
        }
        $W = 1191;
        $H = 1650;
        $canvas = imagecreatetruecolor($W, $H);
        $credit = '';

        $img = $cover['image'] ?? null;
        $picture = is_array($img) ? $this->picture((string) ($img['url'] ?? '')) : null;
        $photo = $picture !== null ? @imagecreatefromstring($picture['bytes']) : false;
        if ($photo !== false) {
            $pw = imagesx($photo);
            $ph = imagesy($photo);
            // Cover-fit: scale until the page is filled, then crop around a point a little right of centre (the picture's
            // interest is rarely at its left edge, where the words are).
            $scale = max($W / $pw, $H / $ph);
            $sw = (int) round($W / $scale);
            $sh = (int) round($H / $scale);
            $sx = (int) max(0, min($pw - $sw, round(($pw - $sw) * 0.62)));
            $sy = (int) max(0, min($ph - $sh, round(($ph - $sh) * 0.5)));
            imagecopyresampled($canvas, $photo, 0, 0, $sx, $sy, $W, $H, $sw, $sh);
            imagedestroy($photo);
            if (($img['type'] ?? '') === 'photo') {
                $credit = trim('Cover photo: ' . (($img['creator'] ?? '') ?: 'Openverse') . ', ' . ($img['licence'] ?? '') . (!empty($img['source_url']) ? '. ' . $img['source_url'] : ''));
            }
            $wash = [23, 37, 84];
            $profile = [[0.0, 0.94], [0.45, 0.86], [0.7, 0.55], [0.84, 0.86], [1.0, 0.96]];
        } else {
            // No picture: a deep gradient with a few cells, so the cover is still a designed page.
            $wash = [30, 41, 120];
            $profile = [[0.0, 0.0], [1.0, 0.0]];
            for ($y = 0; $y < $H; $y += 2) {
                $t = $y / $H;
                $col = imagecolorallocate($canvas, (int) (23 + (15 - 23) * $t), (int) (37 + (90 - 37) * $t), (int) (84 + (150 - 84) * $t));
                imagefilledrectangle($canvas, 0, $y, $W, $y + 1, $col);
            }
            imagealphablending($canvas, true);
            foreach ([[1000, 220, 330], [760, 120, 150], [1120, 560, 190], [160, 1380, 360], [470, 1560, 170]] as [$cx, $cy, $r]) {
                imagefilledellipse($canvas, $cx, $cy, $r * 2, $r * 2, imagecolorallocatealpha($canvas, 255, 255, 255, 116));
                imagefilledellipse($canvas, (int) ($cx + $r * 0.18), (int) ($cy - $r * 0.12), (int) ($r * 0.7), (int) ($r * 0.7), imagecolorallocatealpha($canvas, 255, 255, 255, 108));
            }
        }

        imagealphablending($canvas, true);
        for ($y = 0; $y < $H; $y += 2) {
            $o = $this->interpolate($profile, $y / $H);
            if ($o <= 0.0) {
                continue;
            }
            $col = imagecolorallocatealpha($canvas, $wash[0], $wash[1], $wash[2], (int) round(127 * (1 - $o)));
            imagefilledrectangle($canvas, 0, $y, $W, $y + 1, $col);
        }

        ob_start();
        imagejpeg($canvas, null, 82);
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);

        return [$jpeg !== '' ? 'data:image/jpeg;base64,' . base64_encode($jpeg) : null, $credit];
    }

    /**
     * @param array<int,array{0:float,1:float}> $points (position 0..1, value) in order
     */
    private function interpolate(array $points, float $at): float
    {
        for ($i = 1; $i < count($points); $i++) {
            [$x0, $v0] = $points[$i - 1];
            [$x1, $v1] = $points[$i];
            if ($at <= $x1) {
                return $v0 + ($v1 - $v0) * (($at - $x0) / max(0.0001, $x1 - $x0));
            }
        }

        return $points[count($points) - 1][1];
    }

    // ---------------------------------------------------------------------------------------------------------
    // Contents

    /** @param array<string,mixed> $deck */
    protected function contents(array $deck): string
    {
        $this->spans = $this->topicSpans($deck);
        $taughtBy = $deck['taught_by'] ?? [];
        $concepts = $deck['concepts'] ?? [];

        // One block per topic: its band, then its concepts in two columns (the first half down the left, the rest down the right).
        $blocks = '';
        foreach (array_values($deck['outline'] ?? []) as $i => $topic) {
            $p = $this->paletteOf($i);
            $items = [];
            foreach ($topic['concept_ids'] ?? [] as $cid) {
                $c = $concepts[(string) $cid] ?? null;
                if (!$c) {
                    continue;
                }
                $slides = $taughtBy[(string) $cid] ?? [];
                $first = $slides[0] ?? null;
                $name = $this->e((string) $c['name']);
                $items[] = '<td class="gk-c">' . ($first !== null ? '<a href="#slide-' . (int) $first . '">' . $name . '</a>' : $name) . '</td>'
                    . '<td class="gk-s">' . ($first !== null ? '<a href="#slide-' . (int) $first . '" style="color:' . $p['accent'] . '">' . $this->e($this->unitName() . ' ' . $first) . '</a>' : '') . '</td>';
            }
            $half = (int) ceil(count($items) / 2);
            $rows = '';
            for ($r = 0; $r < $half; $r++) {
                $rows .= '<tr>' . $items[$r] . '<td class="gk-g"></td>' . ($items[$r + $half] ?? '<td class="gk-c"></td><td class="gk-s"></td>') . '</tr>';
            }
            $span = $this->spans[$i] ?? null;
            $blocks .= '<div class="keep gk"><table class="gk-h"><tr>'
                . '<td class="gk-no" style="background:' . $p['accent'] . '">' . ($i + 1) . '</td>'
                . '<td class="gk-name" style="background:' . $p['soft'] . ';color:' . $p['strong'] . '"><a href="#topic-' . ($i + 1) . '" style="color:' . $p['strong'] . '">' . $this->e((string) ($topic['name'] ?? '')) . '</a></td>'
                . '<td class="gk-span" style="background:' . $p['soft'] . ';color:' . $p['strong'] . '">' . ($span ? $this->e($this->unitName() . 's ' . $span['first'] . ' to ' . $span['last']) : '') . '</td></tr></table>'
                . '<table class="gk-r">' . $rows . '</table></div>';
        }

        // The colour key: one line, the name of each kind of card in its own colour. (What each is is said on the card.)
        $legend = [
            ['#4f46e5', 'Explanation'], ['#2563eb', 'Definition'], ['#059669', 'Worked example'], ['#d97706', 'Common mistake'],
            ['#7c3aed', 'Interactive activity'], ['#0284c7', 'Talk about it'], ['#334155', 'Practice question'],
        ];
        $lg = '';
        foreach ($legend as [$colour, $name]) {
            $lg .= '<td class="gl-t"><span class="gl-sw" style="background:' . $colour . '">&nbsp;</span> ' . $this->e($name) . '</td>';
        }

        return '<div class="gct" id="contents">'
            . '<div class="gct-h">Chapter at a glance</div>'
            . '<div class="gct-i">Select a concept to jump to its slide. Each slide has a button that opens it in the Study Deck.</div>'
            . $blocks
            . '<table class="gl2"><tr><td class="gl-h">Colour key</td>' . $lg . '</tr></table></div>';
    }

    /**
     * The first and last slide of each topic.
     *
     * @param array<string,mixed> $deck
     * @return array<int,array{first:int,last:int}>
     */
    protected function topicSpans(array $deck): array
    {
        $out = [];
        foreach ($deck['slides'] ?? [] as $s) {
            $i = $this->topicIndexOf($deck, $s);
            if ($i === null) {
                continue;
            }
            $n = (int) ($s['n'] ?? 0);
            $out[$i] = ['first' => min($out[$i]['first'] ?? $n, $n), 'last' => max($out[$i]['last'] ?? $n, $n)];
        }

        return $out;
    }

    // ---------------------------------------------------------------------------------------------------------
    // One slide

    /** @param array<string,mixed> $deck */
    protected function slideHead(array $deck, array $slide, ?int &$lastTopic): string
    {
        $this->pal = $this->paletteOf($this->topicIndexOf($deck, $slide));
        $p = $this->pal;
        $n = (int) ($slide['n'] ?? 0);
        $concepts = $deck['concepts'] ?? [];
        $name = fn ($id) => (string) ($concepts[(string) $id]['name'] ?? '');

        $url = $this->linkTo($n);
        $button = $url !== null
            ? '<a class="gbtn" href="' . $this->e($url) . '" style="background:' . $p['accent'] . '">' . (!empty($slide['interaction']) ? 'Try it' : 'Open') . ' in Study Deck &#9654;</a>'
            : '';

        $out = $this->topicBand($deck, $slide, $lastTopic);
        $out .= '<table class="gsh" id="slide-' . $n . '"><tr>'
            . '<td class="gsh-n" style="background:' . $p['accent'] . '">' . $n . '</td>'
            . '<td class="gsh-t" style="background:' . $p['softer'] . ';border-color:' . $p['line'] . '"><div class="gsh-e" style="color:' . $p['accent'] . '">' . $this->e(strtoupper(self::STAGE[$slide['slide_type'] ?? ''] ?? 'Lesson')) . '</div>'
            . '<div class="gsh-h" style="color:' . $p['strong'] . '">' . $this->e((string) ($slide['title'] ?? '')) . '</div></td>'
            . ($button !== '' ? '<td class="gsh-b" style="background:' . $p['softer'] . ';border-color:' . $p['line'] . '">' . $button . '</td>' : '')
            . '</tr></table>';

        $taught = array_values(array_filter(array_map($name, $slide['taught_concept_ids'] ?? [])));
        if ($taught) {
            $chips = implode(' ', array_map(fn ($t) => '<span class="gchip" style="background:' . $p['soft'] . ';color:' . $p['strong'] . '">' . $this->e($t) . '</span>', $taught));
            $out .= '<div class="gcon"><span class="gcon-l">Concept</span> ' . $chips . '</div>';
        }

        return $out;
    }

    /** The photograph goes right under the heading, so a slide opens on what it is about. */
    protected function composeLead(string $lead, ?string $firstCard, array $slide): string
    {
        $hero = $this->hero($slide);
        if ($hero === '') {
            return parent::composeLead($lead, $firstCard, $slide);
        }
        $this->heroDoneFor = (int) ($slide['n'] ?? 0);

        return '<div class="keep">' . $lead . $hero . '</div>' . ($firstCard ?? '');
    }

    /** A topic band at the first slide of each topic, in the topic's colour. */
    protected function topicBand(array $deck, array $slide, ?int &$lastTopic): string
    {
        $i = $this->topicIndexOf($deck, $slide);
        if ($i === null) {
            return '';
        }
        $topic = array_values($deck['outline'] ?? [])[$i] ?? null;
        $topicId = (int) ($topic['topic_id'] ?? 0);
        if ($topic === null || $topicId === $lastTopic) {
            return '';
        }
        $lastTopic = $topicId;
        $p = $this->paletteOf($i);
        $span = ($this->spans ?: ($this->spans = $this->topicSpans($deck)))[$i] ?? null;
        $ideas = count($topic['concept_ids'] ?? []);

        return '<table class="gtb" id="topic-' . ($i + 1) . '"><tr>'
            . '<td class="gtb-n" style="background:' . $p['strong'] . '">' . ($i + 1) . '</td>'
            . '<td class="gtb-t" style="background:' . $p['accent'] . '"><div class="gtb-e">TOPIC ' . ($i + 1) . '</div><div class="gtb-h">' . $this->e((string) ($topic['name'] ?? '')) . '</div></td>'
            . '<td class="gtb-m" style="background:' . $p['accent'] . '">' . $ideas . ' ideas' . ($span ? '<br>' . $this->e($this->unitName() . 's ' . $span['first'] . ' to ' . $span['last']) : '') . '</td></tr></table>';
    }

    /** The panel that opens this slide in the Study Deck. */
    protected function activityNote(array $slide, string $instruction): string
    {
        $p = $this->pal;
        $url = $this->linkTo((int) $slide['n']);

        return '<div class="keep gact" style="background:' . $p['softer'] . ';border-color:' . $p['line'] . '"><table><tr>'
            . '<td class="gact-i" style="background:' . $p['accent'] . '">&#9654;</td>'
            . '<td class="gact-t" style="color:' . $p['strong'] . '"><div class="gact-h">Try it in the Study Deck</div>' . $this->e($instruction) . '</td>'
            . ($url !== null ? '<td class="gact-b"><a class="gbtn" href="' . $this->e($url) . '" style="background:' . $p['accent'] . '">Open slide ' . (int) $slide['n'] . ' &#9654;</a></td>' : '')
            . '</tr></table></div>';
    }

    // ---------------------------------------------------------------------------------------------------------
    // Pictures

    /** A photograph as a figure card: the picture, then its caption, who made it, and where it is from. '' for a diagram. */
    protected function hero(array $slide): string
    {
        $img = $slide['image'] ?? null;
        if (!is_array($img) || ($img['type'] ?? 'photo') !== 'photo') {
            return '';
        }
        $picture = $this->picture((string) ($img['url'] ?? ''));
        if ($picture === null) {
            return '';
        }
        $p = $this->pal;
        $w = max(1, (int) ($img['width'] ?? 1280));
        $h = max(1, (int) ($img['height'] ?? 720));
        $width = (int) min(430, 300 * $w / $h);
        $credit = trim((($img['creator'] ?? '') ?: 'Openverse') . ', ' . ($img['licence'] ?? ''));
        $caption = trim(rtrim((string) ($img['caption'] ?? ''), '.'));
        $src = 'data:' . $picture['mime'] . ';base64,' . base64_encode($picture['bytes']);

        return '<table class="gph"><tr>'
            . '<td class="gph-i" style="width:' . $width . 'px"><img src="' . $this->e($src) . '" width="' . $width . '" alt="' . $this->e((string) ($img['alt'] ?? '')) . '"></td>'
            . '<td class="gph-c" style="background:' . $p['softer'] . ';border-color:' . $p['accent'] . '">'
            . '<div class="gph-l" style="color:' . $p['accent'] . '">FIGURE</div>'
            . ($caption !== '' ? '<div class="gph-t" style="color:' . $p['strong'] . '">' . $this->e($caption) . '</div>' : '')
            . '<div class="gph-cr">Photo: ' . $this->e($credit) . '</div>'
            . (!empty($img['source_url']) ? '<div class="gph-s"><a href="' . $this->e((string) $img['source_url']) . '" style="color:' . $p['accent'] . '">View the source &#9654;</a></div>' : '')
            . '</td></tr></table>'
            . '<div class="gph-a">' . $this->e($this->attribution($img)) . '</div>';
    }

    /** The full attribution the licence asks for, small, under the figure. */
    protected function attribution(array $img): string
    {
        $text = trim((string) (($img['attribution'] ?? '') ?: trim(($img['title'] ?? '') . ' by ' . ($img['creator'] ?? '') . ' (' . ($img['licence'] ?? '') . ')')));

        return $text . (!empty($img['source_url']) ? ' Source: ' . $img['source_url'] : '');
    }

    /** A drawn diagram, as a card; a photograph that was placed under the heading is not drawn again. */
    protected function figure(array $slide): string
    {
        if ($this->heroDoneFor === (int) ($slide['n'] ?? 0)) {
            return '';
        }
        $img = $slide['image'] ?? null;
        $url = (string) ($img['url'] ?? '');
        $picture = is_array($img) ? $this->picture($url) : null;
        if ($picture === null) {
            return '';
        }
        if (($img['type'] ?? 'photo') === 'photo') {
            // A photograph that could not go under the heading (it did not fit that block) is still a figure card.
            return $this->hero($slide);
        }
        $p = $this->pal;
        $w = max(1, (int) ($img['width'] ?? 1280));
        $h = max(1, (int) ($img['height'] ?? 720));
        $width = (int) min($this->diagramWidth + 80, ($this->diagramHeight + 40) * $w / $h);
        $src = $this->markedPicture($slide, $url) ?? 'data:' . $picture['mime'] . ';base64,' . base64_encode($picture['bytes']);
        $caption = trim(rtrim((string) ($img['caption'] ?? ''), '.'));

        return '<div class="keep gfig"><table class="gfig-t" style="border-color:' . $p['line'] . '"><tr><td class="gfig-i"><img src="' . $this->e($src) . '" width="' . $width . '" alt="' . $this->e((string) ($img['alt'] ?? '')) . '"></td></tr>'
            . ($caption !== '' ? '<tr><td class="gfig-c" style="background:' . $p['softer'] . ';color:' . $p['strong'] . '"><strong>' . $this->e($caption) . '.</strong> Drawn for this lesson.</td></tr>' : '')
            . '</table></div>';
    }

    /**
     * The stored picture, shrunk to what a page can use: a photograph becomes a JPEG at most MAX_PICTURE pixels on its
     * long side, a drawing stays a PNG at that size. A picture that is already small is left as it is.
     *
     * @return array{bytes:string,mime:string}|null
     */
    protected function picture(string $url): ?array
    {
        if (array_key_exists($url, $this->shrunk)) {
            return $this->shrunk[$url];
        }
        $found = parent::picture($url);
        if ($found === null || !function_exists('imagecreatefromstring')) {
            return $this->shrunk[$url] = $found;
        }
        $im = @imagecreatefromstring($found['bytes']);
        if ($im === false) {
            return $this->shrunk[$url] = $found;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $scale = min(1.0, self::MAX_PICTURE / max($w, $h));
        $isPng = str_contains($found['mime'], 'png');
        if ($scale >= 1.0 && (!$isPng || strlen($found['bytes']) < 400000)) {
            imagedestroy($im);

            return $this->shrunk[$url] = $found;
        }
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        if ($isPng) {
            imagealphablending($out, false);
            imagesavealpha($out, true);
        }
        imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($im);
        ob_start();
        $isPng ? imagepng($out, null, 7) : imagejpeg($out, null, 82);
        $bytes = (string) ob_get_clean();
        imagedestroy($out);

        return $this->shrunk[$url] = ($bytes !== '' ? ['bytes' => $bytes, 'mime' => $isPng ? 'image/png' : 'image/jpeg'] : $found);
    }

    // ---------------------------------------------------------------------------------------------------------
    // Bookmarks

    /**
     * The tree the viewer's bookmarks panel shows: the cover, the contents, then each topic with its slides. Slides before
     * the first topic sit under "Getting started"; slides that follow a topic and belong to none (the concept map) stay
     * under it. Each item names the anchor it jumps to (see PdfOutline).
     *
     * @param array<string,mixed> $deck
     * @return array<int,array{title:string,anchor:string,children:array<int,array{title:string,anchor:string}>}>
     */
    public function outline(array $deck): array
    {
        $tree = [
            ['title' => 'Cover', 'anchor' => 'cover', 'children' => []],
            ['title' => 'Chapter at a glance', 'anchor' => 'contents', 'children' => []],
        ];
        $current = null;
        $topics = array_values($deck['outline'] ?? []);
        foreach ($deck['slides'] ?? [] as $s) {
            if (($s['slide_type'] ?? '') === 'cover') {
                continue;
            }
            $n = (int) ($s['n'] ?? 0);
            $i = $this->topicIndexOf($deck, $s);
            if ($i !== null && $i !== $current) {
                $tree[] = ['title' => ($i + 1) . '  ' . (string) ($topics[$i]['name'] ?? 'Topic'), 'anchor' => 'topic-' . ($i + 1), 'children' => []];
                $current = $i;
            } elseif ($current === null && count($tree) === 2) {
                $tree[] = ['title' => 'Getting started', 'anchor' => 'slide-' . $n, 'children' => []];
            }
            $tree[count($tree) - 1]['children'][] = ['title' => $n . '  ' . (string) ($s['title'] ?? ''), 'anchor' => 'slide-' . $n];
        }

        return $tree;
    }

    // ---------------------------------------------------------------------------------------------------------

    protected function css(): string
    {
        $css = parent::css() . <<<'CSS'

@page :first { margin: 0; }
body { margin: 0; }
a { color: inherit; text-decoration: none; }

/* Cover */
.gc { position: relative; width: 794px; height: 1100px; background: #172554; overflow: hidden; page-break-after: always; }
.gc-bg { position: absolute; left: 0; top: 0; }
.gc-top { position: absolute; left: 64px; top: 70px; color: #bfdbfe; font-size: 12px; font-weight: bold; letter-spacing: 3px; }
.gc-main { position: absolute; left: 64px; top: 230px; width: 560px; }
.gc-ch { color: #67e8f9; font-size: 30px; font-weight: bold; margin-bottom: 6px; }
.gc-title { color: #ffffff; font-size: 46px; line-height: 1.1; font-weight: bold; margin-bottom: 22px; }
.gc-bar { width: 70px; height: 6px; background: #7dd3fc; margin-bottom: 20px; }
.gc-kind { color: #bae6fd; font-size: 12px; font-weight: bold; letter-spacing: 4px; margin-bottom: 26px; }
.gc-lede { color: #e0f2fe; font-size: 17px; line-height: 1.55; }
.gc-st { position: absolute; left: 64px; top: 905px; width: 666px; border-collapse: separate; border-spacing: 8px 0; margin-left: -8px; }
.gc-t { width: 25%; background: #1e3a8a; border: 1px solid #3b5bb5; text-align: center; padding: 12px 4px 10px; }
.gc-n { display: block; color: #ffffff; font-size: 30px; font-weight: bold; }
.gc-l { display: block; color: #bfdbfe; font-size: 10px; letter-spacing: 0.5px; margin-top: 2px; }
.gc-cr { position: absolute; left: 64px; top: 1058px; width: 660px; color: #94a3b8; font-size: 7.5px; }

/* Contents */
.gct { page-break-after: always; }
.gct-h { color: #1e3a8a; font-size: 26px; font-weight: bold; padding-bottom: 7px; border-bottom: 4px solid #4f46e5; margin-bottom: 6px; }
.gct-i { color: #64748b; font-size: 11px; margin-bottom: 10px; }
.gk { margin-bottom: 10px; }
.gk-h { width: 100%; border-collapse: separate; border-spacing: 0; }
.gk-no { width: 30px; color: #ffffff; font-size: 15px; font-weight: bold; text-align: center; padding: 5px 0; border-radius: 7px 0 0 0; }
.gk-name { font-size: 12.5px; font-weight: bold; padding: 5px 10px; }
.gk-span { width: 92px; font-size: 8.5px; text-align: right; padding: 5px 10px; border-radius: 0 7px 0 0; }
.gk-r { width: 100%; border-collapse: collapse; }
.gk-r td { padding: 2px 8px; border-bottom: 1px solid #eef2f7; font-size: 10px; line-height: 1.25; vertical-align: top; }
.gk-c { width: 40%; }
.gk-s { width: 9%; text-align: right; white-space: nowrap; font-size: 8.5px !important; }
.gk-g { width: 2%; border-bottom: 0 !important; }
.gl2 { width: 100%; border-collapse: collapse; margin-top: 8px; border-top: 2px solid #c7d2fe; }
.gl2 td { border: 0; padding: 7px 4px 0; vertical-align: middle; font-size: 8.5px; white-space: nowrap; }
.gl-h { color: #1e3a8a; font-size: 10px; font-weight: bold; }
.gl-sw { display: inline-block; width: 9px; height: 9px; border-radius: 2px; }

/* Slide heading */
.sl { margin-top: 22px; }
.gtb { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 12px; }
.gtb-n { width: 52px; color: #ffffff; font-size: 30px; font-weight: bold; text-align: center; padding: 8px 0; border-radius: 10px 0 0 10px; }
.gtb-t { color: #ffffff; padding: 9px 14px; }
.gtb-e { font-size: 9px; letter-spacing: 2px; font-weight: bold; opacity: 0.85; }
.gtb-h { font-size: 17px; font-weight: bold; line-height: 1.2; }
.gtb-m { width: 92px; color: #ffffff; font-size: 9.5px; text-align: right; padding: 9px 14px; border-radius: 0 10px 10px 0; }
.gsh { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 5px; }
.gsh-n { width: 46px; color: #ffffff; font-size: 24px; font-weight: bold; text-align: center; padding: 8px 0; border-radius: 10px 0 0 10px; }
.gsh-t { padding: 6px 14px; border-top: 1px solid #c7d2fe; border-bottom: 1px solid #c7d2fe; }
.gsh-b { width: 150px; text-align: right; padding: 6px 12px; border: 1px solid #c7d2fe; border-left: 0; border-radius: 0 10px 10px 0; }
.gsh-e { font-size: 8.5px; letter-spacing: 1.6px; font-weight: bold; }
.gsh-h { font-size: 17px; font-weight: bold; line-height: 1.2; }
.gbtn { display: inline-block; color: #ffffff; font-size: 9.5px; font-weight: bold; padding: 5px 11px; border-radius: 12px; }
.gcon { margin: 4px 0 8px; font-size: 10px; color: #64748b; }
.gcon-l { display: inline-block; vertical-align: middle; font-size: 8px; letter-spacing: 1.2px; font-weight: bold; color: #94a3b8; text-transform: uppercase; padding: 2px 6px 2px 0; }
.gchip { display: inline-block; vertical-align: middle; font-size: 10px; font-weight: bold; padding: 2px 9px; border-radius: 10px; margin-right: 3px; }

/* Pictures */
.gph { width: 100%; border-collapse: separate; border-spacing: 0; margin: 4px 0 2px; }
.gph-i { padding: 0; vertical-align: top; }
.gph-i img { border-radius: 10px 0 0 10px; }
.gph-c { vertical-align: middle; padding: 12px 16px; border-left: 5px solid #4f46e5; border-radius: 0 10px 10px 0; }
.gph-l { font-size: 8.5px; letter-spacing: 1.6px; font-weight: bold; margin-bottom: 3px; }
.gph-t { font-size: 13px; font-weight: bold; line-height: 1.3; margin-bottom: 6px; }
.gph-cr { font-size: 9.5px; color: #475569; }
.gph-s { font-size: 9.5px; font-weight: bold; margin-top: 4px; }
.gph-a { color: #94a3b8; font-size: 7px; margin: 2px 0 8px; }
.gfig { margin: 6px 0 8px; text-align: center; }
.gfig-t { margin: 0 auto; border-collapse: separate; border-spacing: 0; border: 1px solid #c7d2fe; border-radius: 10px; }
.gfig-i { padding: 8px; text-align: center; background: #ffffff; }
.gfig-c { font-size: 9.5px; padding: 5px 12px; text-align: left; border-radius: 0 0 9px 9px; }

/* Activity panel */
.gact { margin: 4px 0 10px; border: 1px solid #c7d2fe; border-radius: 10px; }
.gact table { width: 100%; border-collapse: collapse; }
.gact td { border: 0; }
.gact-i { width: 34px; color: #ffffff; text-align: center; font-size: 13px; border-radius: 9px 0 0 9px; }
.gact-t { padding: 7px 12px; font-size: 10.5px; }
.gact-h { font-size: 11.5px; font-weight: bold; }
.gact-b { width: 130px; text-align: right; padding: 7px 10px; }
CSS;
        // The colour families, where the base class's classes (activity tags, tables, key idea) take the topic's colour.
        $families = ['b' => self::BRAND];
        foreach (self::PALETTES as $i => $p) {
            $families[(string) $i] = $p;
        }
        foreach ($families as $k => $p) {
            $css .= "\n.tp$k .ix-tag { background: {$p['accent']}; }"
                . " .tp$k .ix-h { color: {$p['strong']}; }"
                . " .tp$k .lgd th, .tp$k .cmp th { background: {$p['softer']}; color: {$p['strong']}; border-color: {$p['line']}; }"
                . " .tp$k .no, .tp$k .tm { background: {$p['softer']}; color: {$p['strong']}; }"
                . " .tp$k .key-l { background: {$p['accent']}; }"
                . " .tp$k .key-t { background: {$p['soft']}; color: {$p['strong']}; }"
                . " .tp$k .c-exp { background: {$p['softer']}; border-left-color: {$p['accent']}; }"
                . " .tp$k .lb-exp { color: {$p['accent']}; }"
                . " .tp$k .qc { border-top-color: {$p['strong']}; }"
                . " .tp$k .op-l { color: {$p['accent']}; }";
        }

        return $css;
    }
}
