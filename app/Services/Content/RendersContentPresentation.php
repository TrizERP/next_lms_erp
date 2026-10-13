<?php

namespace App\Services\Content;

use App\Services\StudyDeck\StudyDeckImages;
use DOMDocument;
use DOMElement;
use DOMNode;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\Drawing\Base64;
use PhpOffice\PhpPresentation\Shape\Placeholder;
use PhpOffice\PhpPresentation\Shape\RichText;
use PhpOffice\PhpPresentation\Shape\Table;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Slide\Animation;
use PhpOffice\PhpPresentation\Slide\Transition;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Border;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;

/**
 * Render Content Design System markup as an editable, animated PowerPoint file.
 *
 * WHY THIS EXISTS
 * A generated presentation used to reach teachers as a PDF - readable, but not
 * changeable. This produces a real .pptx they can open and edit.
 *
 * WHAT IT MAPS
 * The design system already guarantees the structure and the validator already
 * enforces it, so this is a translation rather than a guess:
 *
 *   .cover                  -> title slide
 *   table.tiles             -> a stat-tile slide (headline figures)
 *   .slide                  -> one content slide
 *     .slide-head h3        -> the title, in a real title PLACEHOLDER
 *     p, ul/ol              -> body text, in a body placeholder
 *     table                 -> a real PowerPoint table
 *     figure                -> an embedded picture
 *     .callout              -> its own tinted card, one per callout
 *
 * THE PEDAGOGY RULE THAT DRIVES THE ANIMATION
 * A check block carries the question AND the answer. Printed together on a
 * projected slide they are useless: the class reads the answer before being
 * asked anything. So the answer is split onto its own click build - click once
 * for the question, again to reveal the answer. That is the single most
 * valuable thing this renderer does, and it is why addAnimation() is used.
 *
 * TWO TRAPS, BOTH LEARNED THE HARD WAY
 * 1. Shapes must be walked in DOCUMENT ORDER from the root. An earlier version
 *    queried only .cover and .slide, so every top-level <table class="tiles">
 *    and <figure> - siblings, not children - was silently dropped from the
 *    deck while still appearing in the PDF.
 * 2. NEVER put a Shape\Group on a slide that carries animations. The writer
 *    builds its shape-id map by walking the top-level collection sequentially
 *    while group writing consumes an extra id, so the animation spids would
 *    point at the wrong shapes and fail silently. Flat shapes and z-order only.
 *
 * WHY THE COLOURS ARE LITERALS HERE
 * PowerPoint has no CSS custom properties, so a token cannot survive into the
 * file - the value has to be written in. These are the EduERP brand values and
 * this is the single place they appear.
 */
trait RendersContentPresentation
{
    /** EduERP brand + neutral + feedback ramps. */
    private const PPT_BRAND = 'FF4F46E5';
    private const PPT_BRAND_DARK = 'FF3730A3';
    private const PPT_BRAND_DEEP = 'FF1E1B4B';
    private const PPT_INK = 'FF1E293B';
    private const PPT_MUTED = 'FF475569';
    private const PPT_WHITE = 'FFFFFFFF';
    private const PPT_SURFACE = 'FFEEF2FF';
    private const PPT_LINE = 'FFC7D2FE';
    private const PPT_SLATE_300 = 'FFCBD5E1';
    private const PPT_WARN = 'FFFFFBEB';
    private const PPT_WARN_INK = 'FFB45309';
    private const PPT_EXAMPLE = 'FFECFDF5';
    private const PPT_EXAMPLE_INK = 'FF047857';
    private const PPT_TRY = 'FFF0F9FF';
    private const PPT_TRY_INK = 'FF0369A1';

    /**
     * The typeface, named on every run.
     *
     * PowerPoint substitutes silently when a font is absent, and a substituted
     * font reflows the text - which makes every height this file estimates
     * wrong. The design system's stack is Inter, then Segoe UI; Inter is not on
     * a school's Windows PC and Segoe UI always is, so the second choice is the
     * one that actually renders and the one we name.
     */
    private const PPT_FONT = 'Segoe UI';

    /** 16:9 at PHPPresentation's 960x540 pixel canvas. */
    private const PPT_W = 960;
    private const PPT_H = 540;
    private const PPT_MARGIN = 56;

    /** Height of the answer strip revealed on a callout's second click. */
    private const PPT_REVEAL_H = 52;

    /** Smallest callout card that still reads from the back of a classroom. */
    private const PPT_CALLOUT_MIN_H = 66;

    /** Temp files pulled down for Drawing shapes, removed after the save. */
    private array $pptTempFiles = [];

    /**
     * @return string Binary .pptx
     */
    protected function renderContentPresentationPptx(string $content, string $chapterName, string $contentType): string
    {
        $presentation = new PhpPresentation();
        $presentation->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_SCREEN_16X9);
        $presentation->getDocumentProperties()
            ->setTitle($chapterName . ' - ' . $contentType)
            ->setSubject($contentType)
            ->setDescription('Generated from the K-12 Content Design System.');

        $blocks = $this->presentationBlocks($content);
        $first = true;

        foreach ($blocks as $block) {
            $slide = $first ? $presentation->getSlide(0) : $presentation->createSlide();
            $first = false;

            match ($block['kind']) {
                'cover' => $this->writeCoverSlide($slide, $block, $chapterName, $contentType),
                'tiles' => $this->writeTilesSlide($slide, $block),
                default => $this->writeContentSlide($slide, $block),
            };

            $this->armAutoFit($slide);
        }

        if ($first) {
            // Neither a cover nor a .slide - the caller handed us a document,
            // not a deck. One honest slide beats a corrupt file.
            $this->writeCoverSlide($presentation->getSlide(0), ['eyebrow' => $contentType, 'title' => $chapterName, 'lede' => ''], $chapterName, $contentType);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pptx');
        IOFactory::createWriter($presentation, 'PowerPoint2007')->save($tmp);
        $binary = (string) file_get_contents($tmp);
        @unlink($tmp);

        foreach ($this->pptTempFiles as $file) {
            @unlink($file);
        }
        $this->pptTempFiles = [];

        return $binary;
    }

    // -----------------------------------------------------------------------
    // Parsing - document order, so nothing is silently dropped
    // -----------------------------------------------------------------------

    /**
     * Walk the document's top-level children in order and normalise each into
     * a block the emitters understand.
     *
     * @return array<int, array<string,mixed>>
     */
    private function presentationBlocks(string $content): array
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // The encoding hint is what keeps UTF-8 intact - without it DOMDocument
        // assumes ISO-8859-1 and mangles every non-ASCII character.
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="lms-deck-root">' . $content . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('lms-deck-root');
        if (!$root) {
            return [];
        }

        $blocks = [];

        foreach ($root->childNodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            if ($this->hasClass($node, 'cover')) {
                $blocks[] = [
                    'kind' => 'cover',
                    'eyebrow' => $this->firstText($node, 'eyebrow'),
                    'title' => $this->firstTag($node, 'h2'),
                    'lede' => $this->firstText($node, 'lede'),
                ];
                continue;
            }

            if ($this->hasClass($node, 'tiles')) {
                $tiles = [];
                foreach ($node->getElementsByTagName('td') as $cell) {
                    $num = $this->firstText($cell, 'tile-num');
                    $label = $this->firstText($cell, 'tile-label');
                    if ($num !== '' || $label !== '') {
                        $tiles[] = ['num' => $num, 'label' => $label];
                    }
                }
                if ($tiles) {
                    $blocks[] = ['kind' => 'tiles', 'tiles' => $tiles];
                }
                continue;
            }

            if ($this->hasClass($node, 'slide')) {
                $blocks[] = $this->parseSlide($node);
                continue;
            }

            // A top-level callout or figure (the summary card that closes a
            // deck, for instance) still deserves its own slide rather than
            // being thrown away.
            if ($this->hasClass($node, 'callout') || strtolower($node->nodeName) === 'figure') {
                $blocks[] = [
                    'kind' => 'slide',
                    'num' => '',
                    'title' => $this->firstText($node, 'callout-label'),
                    'parts' => strtolower($node->nodeName) === 'figure' ? $this->figureParts($node) : [],
                    'callouts' => $this->hasClass($node, 'callout') ? [$this->parseCallout($node)] : [],
                ];
            }
        }

        return $blocks;
    }

    /** @return array<string,mixed> */
    private function parseSlide(DOMElement $section): array
    {
        $parts = [];
        $callouts = [];

        foreach ($section->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            if ($this->hasClass($child, 'callout')) {
                $callouts[] = $this->parseCallout($child);
                continue;
            }

            if ($this->hasClass($child, 'slide-head')) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if ($tag === 'p') {
                $text = $this->text($child);
                if ($text !== '') {
                    $parts[] = ['type' => 'prose', 'text' => $text];
                }
                continue;
            }

            if ($tag === 'ul' || $tag === 'ol') {
                $items = [];
                foreach ($child->getElementsByTagName('li') as $li) {
                    $t = $this->text($li);
                    if ($t !== '') {
                        $items[] = $t;
                    }
                }
                if ($items) {
                    $parts[] = ['type' => 'list', 'items' => $items];
                }
                continue;
            }

            if ($tag === 'table') {
                $rows = [];
                foreach ($child->getElementsByTagName('tr') as $tr) {
                    $cells = [];
                    $header = false;
                    foreach ($tr->childNodes as $cell) {
                        if (!$cell instanceof DOMElement) {
                            continue;
                        }
                        $name = strtolower($cell->nodeName);
                        if ($name !== 'td' && $name !== 'th') {
                            continue;
                        }
                        $header = $header || $name === 'th';
                        $cells[] = $this->text($cell);
                    }
                    if ($cells) {
                        $rows[] = ['cells' => $cells, 'header' => $header];
                    }
                }
                if ($rows) {
                    $parts[] = ['type' => 'table', 'rows' => $rows];
                }
                continue;
            }

            if ($tag === 'figure') {
                $parts = array_merge($parts, $this->figureParts($child));
            }
        }

        return [
            'kind' => 'slide',
            'num' => $this->firstText($section, 'slide-num'),
            'title' => $this->firstTag($section, 'h3'),
            'parts' => $parts,
            'callouts' => $callouts,
        ];
    }

    /** @return array<int, array<string,mixed>> */
    private function figureParts(DOMElement $figure): array
    {
        $img = $figure->getElementsByTagName('img')->item(0);
        if (!$img instanceof DOMElement) {
            return [];
        }

        $src = trim($img->getAttribute('src'));

        // Same host allowlist the PDF path enforces, so the deck can never
        // beacon out to a third party. Guarded because the trait that owns it
        // is composed separately.
        if (method_exists($this, 'isAllowedImageSrc') && !$this->isAllowedImageSrc($src)) {
            return [];
        }

        return [[
            'type' => 'figure',
            'src' => $src,
            'alt' => trim($img->getAttribute('alt')),
            'caption' => $this->firstTagText($figure, 'figcaption'),
        ]];
    }

    /**
     * Split a callout into label, prose and - for a check - the question and
     * answer separately, so the answer can be held back to its own click.
     *
     * @return array<string,mixed>
     */
    private function parseCallout(DOMElement $node): array
    {
        [$fill, $ink] = match (true) {
            $this->hasClass($node, 'callout-warn') => [self::PPT_WARN, self::PPT_WARN_INK],
            $this->hasClass($node, 'callout-example') => [self::PPT_EXAMPLE, self::PPT_EXAMPLE_INK],
            $this->hasClass($node, 'callout-try') => [self::PPT_TRY, self::PPT_TRY_INK],
            default => [self::PPT_SURFACE, self::PPT_BRAND_DARK],
        };

        $question = [];
        $answer = [];
        $seenAnswer = false;

        foreach ($node->getElementsByTagName('p') as $p) {
            $text = $this->text($p);
            if ($text === '') {
                continue;
            }
            // The contract: an answer paragraph opens with a bold "Answer:".
            if (!$seenAnswer && preg_match('/^Answer\s*:/i', $text)) {
                $seenAnswer = true;
            }
            if ($seenAnswer) {
                $answer[] = $text;
            } else {
                $question[] = $text;
            }
        }

        foreach ($node->getElementsByTagName('li') as $li) {
            $text = $this->text($li);
            if ($text === '') {
                continue;
            }
            if ($seenAnswer) {
                $answer[] = '- ' . $text;
            } else {
                $question[] = '- ' . $text;
            }
        }

        return [
            'label' => $this->firstText($node, 'callout-label'),
            'body' => implode("\n", $question),
            'answer' => implode("\n", $answer),
            'fill' => $fill,
            'ink' => $ink,
        ];
    }

    // -----------------------------------------------------------------------
    // Emitting
    // -----------------------------------------------------------------------

    /** @param array<string,mixed> $block */
    private function writeCoverSlide(Slide $slide, array $block, string $chapterName, string $contentType): void
    {
        $this->fillSlide($slide, self::PPT_BRAND_DEEP);
        $this->transition($slide, Transition::TRANSITION_FADE);

        // Right-hand colour block gives the cover a composition rather than a
        // centred wall of text.
        $panel = $slide->createRichTextShape();
        $panel->setOffsetX(750)->setOffsetY(0)->setWidth(210)->setHeight(self::PPT_H);
        $panel->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color(self::PPT_BRAND_DARK));

        $rule = $slide->createRichTextShape();
        $rule->setOffsetX(self::PPT_MARGIN)->setOffsetY(150)->setWidth(76)->setHeight(6);
        $rule->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color(self::PPT_BRAND));

        $eyebrow = (string) ($block['eyebrow'] ?? '') ?: $contentType;
        $title = (string) ($block['title'] ?? '') ?: $chapterName;
        $lede = (string) ($block['lede'] ?? '');

        $head = $this->textShape($slide, self::PPT_MARGIN, 180, 660, 190);
        $head->setPlaceHolder(new Placeholder(Placeholder::PH_TYPE_TITLE));
        if ($eyebrow !== '') {
            $this->para($head, $this->upper($eyebrow), 13, self::PPT_SLATE_300, true, 12);
        }
        $this->para($head, $title, 40, self::PPT_WHITE, true, 0);

        if ($lede !== '') {
            // Room for a four-line lede; the panel starts at x=750, so a wider
            // box is not available, but the slide has vertical room to spare.
            $ledeHeight = min(140, max(70, $this->estimateTextHeight([[$lede, 17, 0]], 640)));
            $body = $this->textShape($slide, self::PPT_MARGIN, 390, 640, $ledeHeight);
            $body->setPlaceHolder(new Placeholder(Placeholder::PH_TYPE_BODY));
            $this->para($body, $lede, 17, self::PPT_SLATE_300, false, 0);
        }
    }

    /**
     * Headline figures as a row of tiles.
     *
     * This slide existed in the HTML all along and never reached the file,
     * because the old renderer only visited .cover and .slide and the tiles
     * table is a sibling of both.
     *
     * @param array<string,mixed> $block
     */
    private function writeTilesSlide(Slide $slide, array $block): void
    {
        $this->fillSlide($slide, self::PPT_WHITE);
        $this->transition($slide, Transition::TRANSITION_FADE);

        $tiles = $block['tiles'];
        $count = max(1, count($tiles));
        $gutter = 24;
        $available = self::PPT_W - (2 * self::PPT_MARGIN);
        $width = (int) (($available - ($gutter * ($count - 1))) / $count);

        $head = $this->textShape($slide, self::PPT_MARGIN, 90, $available, 60);
        $head->setPlaceHolder(new Placeholder(Placeholder::PH_TYPE_TITLE));
        $this->para($head, 'At a glance', 30, self::PPT_BRAND_DARK, true, 0);

        // Tiles are the one place a fixed box reliably overflowed: a 44pt
        // numeral plus a label that wraps to three lines in a narrow tile needs
        // ~255px, and the box was 130px. PHP cannot measure the wrap, so instead
        // of guessing better the geometry is made generous and the numeral is
        // scaled down as tiles get narrower. The text box also fills the whole
        // card, so PowerPoint's own shrink-on-overflow has room to act.
        $cardTop = 190;
        $cardHeight = 230;
        $numeralSize = match (true) {
            $count >= 5 => 30,
            $count === 4 => 36,
            default => 44,
        };

        foreach (array_values($tiles) as $i => $tile) {
            $x = self::PPT_MARGIN + ($i * ($width + $gutter));

            $card = $slide->createRichTextShape();
            $card->setOffsetX($x)->setOffsetY($cardTop)->setWidth($width)->setHeight($cardHeight);
            $card->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color(self::PPT_SURFACE));
            $card->getBorder()->setLineStyle(Border::LINE_SINGLE)->setColor(new Color(self::PPT_LINE));

            $text = $this->textShape($slide, $x + 12, $cardTop + 16, $width - 24, $cardHeight - 32);
            $this->para($text, (string) $tile['num'], $numeralSize, self::PPT_BRAND_DARK, true, 8, false, Alignment::HORIZONTAL_CENTER);
            $this->para($text, (string) $tile['label'], 13, self::PPT_MUTED, false, 0, false, Alignment::HORIZONTAL_CENTER);
        }
    }

    /** @param array<string,mixed> $block */
    private function writeContentSlide(Slide $slide, array $block): void
    {
        $this->fillSlide($slide, self::PPT_WHITE);
        $this->transition($slide, Transition::TRANSITION_FADE);

        $full = self::PPT_W - (2 * self::PPT_MARGIN);

        // Header, in a real title placeholder so Outline view, Reset Slide,
        // the accessibility "slide has a title" rule and PowerPoint Designer
        // all work. Without it we disable the one built-in button labelled
        // "make this look better".
        //
        // Height comes from the title, not a constant: a title long enough to
        // wrap to two lines needed 113px and used to be given 86, so the
        // second line sat on top of the first paragraph of body text.
        $headParas = [];
        if (($block['num'] ?? '') !== '') {
            $headParas[] = [$this->upper((string) $block['num']), 12, 6];
        }
        $headParas[] = [(string) ($block['title'] ?? ''), 28, 0];
        $headHeight = min(120, max(70, $this->estimateTextHeight($headParas, $full)));

        $header = $this->textShape($slide, self::PPT_MARGIN, 40, $full, $headHeight);
        $header->setPlaceHolder(new Placeholder(Placeholder::PH_TYPE_TITLE));
        foreach ($headParas as $i => [$text, $pt, $after]) {
            $this->para($header, $text, $pt, $i === 0 && count($headParas) > 1 ? self::PPT_BRAND : self::PPT_BRAND_DARK, true, $after);
        }

        $figures = array_values(array_filter($block['parts'], static fn ($p) => $p['type'] === 'figure'));
        $flow = array_values(array_filter($block['parts'], static fn ($p) => $p['type'] !== 'figure'));

        // Everything below the header starts under it, wherever it ended.
        $y = max(150, 40 + $headHeight + 12);

        // A figure earns half the slide; without one the text runs full width.
        $figureTop = $y;
        $hasFigure = $figures !== [] && $this->drawFigure($slide, $figures[0], 520, $figureTop, 384, 250);
        $figureBottom = $figureTop + 250 + (($figures[0]['caption'] ?? '') !== '' ? 46 : 0);
        $textWidth = $hasFigure ? 430 : $full;

        // Reserve the callouts' space BEFORE the prose is laid out. Without
        // this the prose took whatever it wanted and the question cards got
        // whatever was left, which on a wordy slide meant a squeezed box with
        // the text auto-shrunk to fit. The question is the part that has to
        // stay readable from the back of the room, so it wins the argument.
        // The callouts live in the same column as the prose. Handing them the
        // full 848px while the prose was narrowed to 430 would run the cards
        // straight under the picture - latent today only because no chapter has
        // figures yet, and live the moment lms:extract-chapter-figures runs.
        $calloutWidth = $textWidth;
        $reserve = $this->calloutStackHeight($block['callouts'] ?? [], $calloutWidth);
        $ceiling = self::PPT_H - 22 - $reserve;

        $y = $this->drawFlow($slide, $flow, self::PPT_MARGIN, $y, $textWidth, $ceiling);

        // A figure and its caption also claim vertical space in the right-hand
        // column; the stack must clear them, not slide up beside them.
        if ($hasFigure) {
            $y = max($y, $figureBottom);
        }

        $this->drawCallouts($slide, $block['callouts'] ?? [], $y, $calloutWidth);
    }

    /**
     * Prose, lists and real tables, stacked.
     *
     * @param array<int, array<string,mixed>> $parts
     */
    private function drawFlow(Slide $slide, array $parts, int $x, int $y, int $width, int $ceiling = self::PPT_H): int
    {
        $prose = [];
        foreach ($parts as $part) {
            if ($part['type'] === 'table') {
                if ($prose) {
                    $y = $this->drawProse($slide, $prose, $x, $y, $width, $ceiling);
                    $prose = [];
                }
                $y = $this->drawTable($slide, $part['rows'], $x, $y, $width);
                continue;
            }
            $prose[] = $part;
        }

        if ($prose) {
            $y = $this->drawProse($slide, $prose, $x, $y, $width, $ceiling);
        }

        return $y;
    }

    /** @param array<int, array<string,mixed>> $parts */
    private function drawProse(Slide $slide, array $parts, int $x, int $y, int $width, int $ceiling = self::PPT_H): int
    {
        // Sized from the strings, not from a flat "assume two lines per
        // paragraph". The old constant under-budgeted every paragraph longer
        // than about 90 characters, which is most of them.
        $measured = [];
        foreach ($parts as $part) {
            if ($part['type'] === 'list') {
                foreach ($part['items'] as $item) {
                    $measured[] = [$item, 15, 8];
                }
                continue;
            }
            $measured[] = [$part['text'], 16, 10];
        }
        $height = min(250, max(60, $this->estimateTextHeight($measured, $width)));
        // Never past the space the callouts already claimed.
        $height = max(50, min($height, $ceiling - $y - 12));

        $shape = $this->textShape($slide, $x, $y, $width, $height);
        $shape->setPlaceHolder(new Placeholder(Placeholder::PH_TYPE_BODY));

        foreach ($parts as $part) {
            if ($part['type'] === 'list') {
                foreach ($part['items'] as $item) {
                    $this->para($shape, $item, 15, self::PPT_INK, false, 8, true);
                }
                continue;
            }
            $this->para($shape, $part['text'], 16, self::PPT_INK, false, 10);
        }

        return $y + $height + 12;
    }

    /**
     * A real PowerPoint table.
     *
     * The old renderer flattened every table into pipe-separated bullet lines,
     * which a teacher cannot restyle, sort or extend.
     *
     * @param array<int, array{cells: array<int,string>, header: bool}> $rows
     */
    private function drawTable(Slide $slide, array $rows, int $x, int $y, int $width): int
    {
        $columns = 0;
        foreach ($rows as $row) {
            $columns = max($columns, count($row['cells']));
        }
        if ($columns === 0) {
            return $y;
        }

        $table = $slide->createTableShape($columns);
        $table->setOffsetX($x)->setOffsetY($y)->setWidth($width);

        foreach ($rows as $row) {
            $tableRow = $table->createRow();
            $isHeader = $row['header'];
            $tableRow->setHeight($isHeader ? 30 : 26);

            foreach (range(0, $columns - 1) as $i) {
                $cell = $tableRow->getCell($i);
                $value = $row['cells'][$i] ?? '';
                $run = $cell->createTextRun($value);
                $run->getFont()->setSize(12)->setBold($isHeader)
                    ->setColor(new Color($isHeader ? self::PPT_BRAND_DARK : self::PPT_INK))
                    ->setName(self::PPT_FONT);
                $cell->getFill()->setFillType(Fill::FILL_SOLID)
                    ->setStartColor(new Color($isHeader ? self::PPT_SURFACE : self::PPT_WHITE));
                $cell->getBorders()->getBottom()->setLineStyle(Border::LINE_SINGLE)
                    ->setColor(new Color(self::PPT_LINE));
            }
        }

        return $y + (count($rows) * 28) + 16;
    }

    /**
     * Embed a figure, downloading it if it is remote.
     *
     * Drawing\File needs a local path, and our figures live on object storage.
     * Failure is non-fatal: a deck without one picture beats no deck.
     *
     * @param array<string,mixed> $figure
     */
    private function drawFigure(Slide $slide, array $figure, int $x, int $y, int $width, int $height): bool
    {
        $src = (string) $figure['src'];

        try {
            if (StudyDeckImages::idFromRef($src) !== null) {
                // A stored study-deck picture comes out of the database and goes into the file from memory: no download
                // and no temporary file.
                $picture = $this->storedPicture($src);
                if ($picture === null) {
                    return false;
                }
                $shape = new Base64();
                $shape->setData('data:' . $picture['mime'] . ';base64,' . base64_encode($picture['bytes']));
                $slide->addShape($shape);
            } else {
                $path = $this->localiseImage($src);
                if ($path === null) {
                    return false;
                }
                $shape = $slide->createDrawingShape();
                $shape->setPath($path, false);
            }
            $shape->setName(($figure['alt'] ?? '') !== '' ? (string) $figure['alt'] : 'Figure');
            $shape->setDescription((string) ($figure['alt'] ?? ''));
            $shape->setOffsetX($x)->setOffsetY($y)->setWidth($width)->setHeight($height);
        } catch (\Throwable $e) {
            return false;
        }

        if (($figure['caption'] ?? '') !== '') {
            $caption = $this->textShape($slide, $x, $y + $height + 6, $width, 40);
            $this->para($caption, (string) $figure['caption'], 11, self::PPT_MUTED, false, 0);
        }

        return true;
    }

    /**
     * How tall each callout's card wants to be, measured from its own text.
     *
     * Equal shares looked tidy in the code and wrong on the slide: a one-line
     * misconception got the same box as a three-line check question, so one
     * card sat half empty while the other spilled.
     *
     * @param array<int, array<string,mixed>> $callouts
     * @return array<int, int>
     */
    private function calloutCardHeights(array $callouts, int $width): array
    {
        $inner = $width - 28;
        $heights = [];

        foreach ($callouts as $callout) {
            $paragraphs = [];
            if ($callout['label'] !== '') {
                // Measured as drawn - drawCallouts uppercases the label, and
                // capitals are the wider glyphs.
                $paragraphs[] = [$this->upper($callout['label']), 11, 5];
            }
            if ($callout['body'] !== '') {
                $paragraphs[] = [$callout['body'], 14, 0];
            }
            $heights[] = max(self::PPT_CALLOUT_MIN_H, $this->estimateTextHeight($paragraphs, $inner) + 20);
        }

        return $heights;
    }

    /**
     * Total vertical space the callout stack needs, reveal strips and gaps in.
     *
     * writeContentSlide subtracts this from the canvas before it lays out any
     * prose, so the two cannot both claim the same pixels.
     *
     * @param array<int, array<string,mixed>> $callouts
     */
    private function calloutStackHeight(array $callouts, int $width): int
    {
        if (!$callouts) {
            return 0;
        }

        $reveals = 0;
        foreach ($callouts as $callout) {
            if ($callout['answer'] !== '') {
                $reveals += self::PPT_REVEAL_H;
            }
        }

        return array_sum($this->calloutCardHeights($callouts, $width))
            + $reveals
            + (12 * (count($callouts) - 1));
    }

    /**
     * Each callout gets its OWN card in its OWN colour.
     *
     * The old renderer stacked every callout into one 150px panel painted with
     * the first one's fill, so a warm amber misconception and a cool blue check
     * merged into a single amber rectangle with the labels running together.
     *
     * The check callout is split: the question is always visible once clicked,
     * and the answer is held back to a second click, so the teacher can ask the
     * class before revealing it.
     *
     * @param array<int, array<string,mixed>> $callouts
     */
    private function drawCallouts(Slide $slide, array $callouts, int $y, int $width): void
    {
        if (!$callouts) {
            return;
        }

        $count = count($callouts);
        $gaps = 12 * ($count - 1);
        $bottomPad = 22;

        // Every callout that carries an answer needs a reveal strip UNDER its
        // card. The old budget divided the free space by the callout count and
        // then added the strip on top of each share, so a slide with two
        // answered checks overshot the canvas by ~112px - that, plus a hard
        // 70px floor on the card, is what pushed shapes off the bottom.
        $reveals = array_map(
            static fn (array $callout): int => $callout['answer'] !== '' ? self::PPT_REVEAL_H : 0,
            $callouts
        );
        $revealTotal = array_sum($reveals);

        // Ask each callout how tall it actually needs to be, then hand out the
        // free space in proportion. Equal shares looked tidy in the code and
        // wrong on the slide: a one-line misconception got the same box as a
        // three-line check question, so one card sat half empty while the other
        // spilled.
        $wants = $this->calloutCardHeights($callouts, $width);

        // Preferred start keeps the question zone in a constant place down the
        // deck; a long body pushes it lower, and a tall stack pulls it back up.
        $wantTotal = array_sum($wants);
        $top = max($y, 300);
        $top = min($top, self::PPT_H - $bottomPad - $wantTotal - $revealTotal - $gaps);
        // ...but never above the body text that just ended at $y. Pulling the
        // stack up to make it fit is what put a "COMMON MISCONCEPTION" label on
        // top of the last line of prose. When there is genuinely not enough
        // room left, $scale below shrinks the cards instead.
        $top = max($top, $y, 150);

        $room = (self::PPT_H - $top - $bottomPad) - $revealTotal - $gaps;
        $scale = $wantTotal > 0 ? min(1.0, $room / $wantTotal) : 1.0;

        $y = $top;

        foreach ($callouts as $index => $callout) {
            $each = max(40, (int) floor($wants[$index] * $scale));

            $card = $slide->createRichTextShape();
            $card->setOffsetX(self::PPT_MARGIN)->setOffsetY($y)->setWidth($width)->setHeight($each);
            $card->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($callout['fill']));
            $card->getBorder()->setLineStyle(Border::LINE_SINGLE)->setColor(new Color($callout['ink']));

            $body = $this->textShape($slide, self::PPT_MARGIN + 14, $y + 10, $width - 28, $each - 20);
            if ($callout['label'] !== '') {
                $this->para($body, $this->upper($callout['label']), 11, $callout['ink'], true, 5);
            }
            if ($callout['body'] !== '') {
                $this->para($body, $callout['body'], 14, self::PPT_MUTED, false, 0);
            }

            // The answer, on its own click. Both the card and its question ride
            // the first click so the block appears as one piece.
            if ($reveals[$index] > 0) {
                $reveal = $this->textShape(
                    $slide,
                    self::PPT_MARGIN + 14,
                    $y + $each + 2,
                    $width - 28,
                    self::PPT_REVEAL_H - 6
                );
                $this->para($reveal, $callout['answer'], 14, $callout['ink'], true, 0);

                $step1 = new Animation();
                $step1->addShape($card);
                $step1->addShape($body);
                $slide->addAnimation($step1);

                $step2 = new Animation();
                $step2->addShape($reveal);
                $slide->addAnimation($step2);
            }

            $y += $each + $reveals[$index] + 12;
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * A stored study-deck picture, from the resolver the caller installed for this render; null when there is no
     * resolver (the reference means nothing then) or the picture is not there or not this school's.
     *
     * @return array{bytes:string,mime:string}|null
     */
    private function storedPicture(string $src): ?array
    {
        if (!property_exists($this, 'deckImageResolver') || $this->deckImageResolver === null) {
            return null;
        }
        $picture = ($this->deckImageResolver)($src);

        return is_array($picture) && ($picture['bytes'] ?? '') !== '' && ($picture['mime'] ?? '') !== '' ? $picture : null;
    }

    private function localiseImage(string $src): ?string
    {
        $data = @file_get_contents($src, false, stream_context_create([
            'http' => ['timeout' => 15, 'user_agent' => 'Mozilla/5.0 (compatible; EduERP/1.0)'],
            'ssl' => ['verify_peer' => true],
        ]));

        if ($data === false || strlen($data) < 128) {
            return null;
        }

        $extension = match (true) {
            str_contains($src, '.png') => '.png',
            str_contains($src, '.gif') => '.gif',
            default => '.jpg',
        };

        $path = tempnam(sys_get_temp_dir(), 'fig') . $extension;
        if (@file_put_contents($path, $data) === false) {
            return null;
        }

        $this->pptTempFiles[] = $path;

        return $path;
    }

    private function fillSlide(Slide $slide, string $color): void
    {
        $background = new Slide\Background\Color();
        $background->setColor(new Color($color));
        $slide->setBackground($background);
    }

    private function transition(Slide $slide, string $type): void
    {
        $transition = new Transition();
        $transition->setTransitionType($type)
            ->setSpeed(Transition::SPEED_MEDIUM)
            ->setManualTrigger(true);
        $slide->setTransition($transition);
    }

    private function textShape(Slide $slide, int $x, int $y, int $width, int $height): RichText
    {
        $shape = $slide->createRichTextShape();
        $shape->setOffsetX($x)->setOffsetY($y)->setWidth($width)->setHeight($height);
        $shape->setWrap(RichText::WRAP_SQUARE);
        // AUTOFIT_NORMAL is PowerPoint's "shrink text on overflow": a deck whose
        // text runs off the slide is worse than one a point smaller. (There is
        // no AUTOFIT_SHRINK - the constants are DEFAULT/SHAPE, NOAUTOFIT, NORMAL.)
        $shape->setAutoFit(RichText::AUTOFIT_NORMAL);

        return $shape;
    }

    private function para(
        RichText $shape,
        string $text,
        int $size,
        string $color,
        bool $bold,
        int $spaceAfter,
        bool $bullet = false,
        string $align = Alignment::HORIZONTAL_LEFT
    ): void {
        $paragraph = $shape->createParagraph();
        $paragraph->getAlignment()
            ->setHorizontal($align)
            ->setMarginLeft($bullet ? 24 : 0)
            ->setIndent($bullet ? -12 : 0);
        $paragraph->setSpacingAfter($spaceAfter);

        $run = $paragraph->createTextRun(($bullet ? '• ' : '') . $text);
        $run->getFont()->setSize($size)->setBold($bold)->setColor(new Color($color))->setName(self::PPT_FONT);
    }

    /**
     * How tall a stack of paragraphs will be once PowerPoint wraps it.
     *
     * PHP cannot measure a font - PHPPresentation exposes no metrics, and the
     * fonts live on the teacher's machine, not ours. So this is an estimate,
     * not a measurement, and it is deliberately a slight over-estimate: a box
     * with spare room at the bottom costs nothing, a box one line too short
     * pushes text over the shape below it.
     *
     * Constants: 1pt = 96/72 px, Calibri's average advance across mixed-case
     * prose is about half an em, and PowerPoint's single line spacing is about
     * 1.25x the point size. Boxes sized this way, plus AUTOFIT_NORMAL as the
     * safety net, is what replaced the flat "assume two lines" guesses.
     *
     * @param array<int, array{0: string, 1: int, 2: int}> $paragraphs [text, pt, spaceAfter]
     */
    private function estimateTextHeight(array $paragraphs, int $boxWidth): int
    {
        $height = 0;

        foreach ($paragraphs as [$text, $pt, $spaceAfter]) {
            $px = $pt * (96 / 72);
            $charWidth = max(1.0, $px * 0.5);
            $perLine = max(8, (int) floor(max(40, $boxWidth - 12) / $charWidth));
            $lines = max(1, (int) ceil(mb_strlen($text) / $perLine));
            $height += (int) ceil($lines * $px * 1.25) + $spaceAfter;
        }

        // Top and bottom inset PowerPoint applies inside every text box.
        return $height + 10;
    }

    /**
     * Arm PowerPoint's shrink-on-overflow on every text shape of a slide.
     *
     * setAutoFit(AUTOFIT_NORMAL) alone writes a bare <a:normAutofit/>, whose
     * fontScale defaults to 100%. PowerPoint only recomputes that scale when a
     * human clicks into the box, so on first open the text does not shrink at
     * all - the net was switched on and empty. Passing a scale fills it.
     *
     * Done as one pass over the finished slide rather than at each draw site,
     * because it then cannot be forgotten when a new kind of shape is added,
     * and because it measures what the shape ENDED UP holding rather than what
     * the caller expected to put in it.
     *
     * The writer multiplies by 1000 (fontScale is thousandths of a percent), so
     * the value here is a plain percentage: 85 means 85%. Floored at 60 - below
     * that nobody reads it from the back of a classroom, and the content is
     * then the thing to fix, not the font size.
     */
    private function armAutoFit(Slide $slide): void
    {
        foreach ($slide->getShapeCollection() as $shape) {
            if (!$shape instanceof RichText) {
                continue;
            }

            $height = (int) $shape->getHeight();
            $width = (int) $shape->getWidth();
            if ($height <= 0 || $width <= 0 || $shape->getAutoFit() !== RichText::AUTOFIT_NORMAL) {
                continue;
            }

            $paragraphs = [];
            foreach ($shape->getParagraphs() as $paragraph) {
                $text = '';
                $size = 18;
                foreach ($paragraph->getRichTextElements() as $element) {
                    $text .= $element->getText();
                    if (method_exists($element, 'getFont') && $element->getFont()) {
                        $size = (int) $element->getFont()->getSize();
                    }
                }
                if (trim($text) !== '') {
                    $paragraphs[] = [$text, $size, (int) $paragraph->getSpacingAfter()];
                }
            }

            if (!$paragraphs) {
                continue;
            }

            $needed = $this->estimateTextHeight($paragraphs, $width);
            if ($needed <= $height) {
                continue;
            }

            $shape->setAutoFit(RichText::AUTOFIT_NORMAL, (float) max(60, (int) floor(100 * $height / $needed)));
        }
    }

    /** Uppercase that does not mangle non-ASCII. */
    private function upper(string $value): string
    {
        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    private function hasClass(DOMElement $node, string $class): bool
    {
        return in_array($class, preg_split('/\s+/', trim($node->getAttribute('class'))) ?: [], true);
    }

    private function firstText(DOMNode $scope, string $class): string
    {
        if (!$scope instanceof DOMElement) {
            return '';
        }

        foreach ($scope->getElementsByTagName('*') as $node) {
            if ($node instanceof DOMElement && $this->hasClass($node, $class)) {
                return $this->text($node);
            }
        }

        return '';
    }

    private function firstTag(DOMElement $scope, string $tag): string
    {
        $found = $scope->getElementsByTagName($tag)->item(0);

        return $found ? $this->text($found) : '';
    }

    private function firstTagText(DOMElement $scope, string $tag): string
    {
        return $this->firstTag($scope, $tag);
    }

    private function text(DOMNode $node): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $node->textContent) ?? '');
    }
}
