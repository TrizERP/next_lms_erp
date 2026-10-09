<?php

namespace App\Console\Commands\LMS;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Console\Command;

/**
 * Check generated content against the Content Design System.
 *
 * WHY THIS EXISTS
 * The SLATE benchmark (arxiv 2609.06212) measured AI-generated teaching slides
 * against real learner knowledge gain and found a dissociation between artifact
 * quality and instructional effectiveness: content validity correlated only
 * weakly with learning, while pedagogical design correlated robustly. Frontier
 * models produced polished decks that taught nothing - some with NEGATIVE
 * learning gains.
 *
 * A generator cannot be trusted to grade its own pedagogy, and a human cannot
 * read 830 documents. So the rules that actually predict learning are made
 * mechanical here and enforced before anything reaches a teacher.
 *
 * WHAT IT CANNOT DO
 * It checks structure, coverage and load - not truth. It cannot tell you the
 * content is factually right, only that every concept was addressed, every
 * slide asks the student something, the Bloom range is not flat, and no slide
 * is a wall of text. Factual grounding is still a human review step.
 *
 * See docs/content-design-system/README.md section 5.
 */
class ValidateContentCommand extends Command
{
    protected $signature = 'lms:validate-content
        {bundle : path to a chapter bundle directory}
        {--file=* : validate only these files inside out/ (default: all)}
        {--max-slide-words=60 : body-text word cap per slide}
        {--max-bullets=7 : bullets allowed in one list}
        {--min-bloom=3 : distinct Bloom levels required across an item}';

    protected $description = 'Validate generated chapter content against the Content Design System';

    private const BLOOM = ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'];

    /**
     * Which content types must cover every concept in the chapter.
     *
     * A presentation and a set of revision notes are the comprehensive
     * artefacts - a concept missing from either is a hole in the chapter. An
     * activity, a remedial session or a training deck are deliberately
     * selective: forcing all twenty concepts into a single classroom activity
     * would produce a worse lesson, not a more complete one. Those are still
     * checked for INVENTED concept names, which are a defect at any scope.
     */
    private const FULL_COVERAGE = ['presentation', 'revision-notes'];

    /**
     * Content types whose audience is adults, not students.
     *
     * The slide word cap comes from Mayer's coherence and segmenting
     * principles, which are calibrated on NOVICE LEARNERS building a schema
     * they do not yet have. A teacher reading a training deck already holds the
     * subject and is skimming for decisions, so the same ceiling is the wrong
     * instrument. The cap is raised, not removed - a wall of text is still a
     * wall of text.
     */
    private const TEACHER_FACING = ['teacher-training'];
    private const TEACHER_SLIDE_WORDS = 90;
    private const BLOCKS = [
        'intro', 'explain', 'visual', 'example', 'real-world',
        'misconception', 'check', 'activity', 'summary', 'assess',
    ];

    private int $failures = 0;

    public function handle(): int
    {
        $bundle = rtrim((string) $this->argument('bundle'), '/\\');

        if (!is_file($bundle . '/concepts.json')) {
            $this->error('Not a bundle (no concepts.json): ' . $bundle);

            return self::FAILURE;
        }

        $concepts = json_decode((string) file_get_contents($bundle . '/concepts.json'), true) ?: [];
        $wanted = (array) $this->option('file');

        $files = $wanted
            ? array_map(static fn ($f) => $bundle . '/out/' . $f, $wanted)
            : (glob($bundle . '/out/*.html') ?: []);

        if (!$files) {
            $this->error('No generated files found in ' . $bundle . '/out/');

            return self::FAILURE;
        }

        foreach ($files as $file) {
            $this->validateFile($file, $concepts);
        }

        $this->newLine();

        if ($this->failures > 0) {
            $this->error(sprintf('%d problem(s) found. Regenerate before storing.', $this->failures));

            return self::FAILURE;
        }

        $this->info('All checks passed.');

        return self::SUCCESS;
    }

    private function validateFile(string $file, array $concepts): void
    {
        $this->newLine();
        $this->line('<options=bold>' . basename($file) . '</>');

        $html = (string) file_get_contents($file);
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // The XML encoding hint is what keeps UTF-8 intact; without it
        // DOMDocument assumes ISO-8859-1 and mangles every non-ASCII character.
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);

        $blocks = [];
        foreach ($xpath->query('//*[@data-block]') as $node) {
            if ($node instanceof DOMElement) {
                $blocks[] = $node;
            }
        }

        if (!$blocks) {
            $this->problem('No data-block elements at all - this is not Content Design System markup.');

            return;
        }

        $this->checkVocabulary($blocks);
        $this->checkCoverage($blocks, $concepts, in_array(
            pathinfo($file, PATHINFO_FILENAME),
            self::FULL_COVERAGE,
            true
        ));
        $this->checkBloomRange($blocks);
        $this->checkSlides($xpath, in_array(pathinfo($file, PATHINFO_FILENAME), self::TEACHER_FACING, true)
            ? self::TEACHER_SLIDE_WORDS
            : (int) $this->option('max-slide-words'));
        $this->checkCallouts($xpath);
        $this->checkLists($xpath);
        $this->checkImages($xpath);
        $this->checkEmoji($html);
    }

    /** @param DOMElement[] $blocks */
    private function checkVocabulary(array $blocks): void
    {
        foreach ($blocks as $node) {
            $block = $node->getAttribute('data-block');
            if (!in_array($block, self::BLOCKS, true)) {
                $this->problem('Unknown block type: "' . $block . '"');
            }

            $bloom = $node->getAttribute('data-bloom');
            if ($bloom !== '' && !in_array($bloom, self::BLOOM, true)) {
                $this->problem('Bloom level must be lowercase and one of the six: got "' . $bloom . '"');
            }

            $dok = $node->getAttribute('data-dok');
            if ($dok !== '' && !in_array($dok, ['1', '2', '3', '4'], true)) {
                $this->problem('DOK must be 1-4 (Webb scale, not difficulty): got "' . $dok . '"');
            }
        }
    }

    /** @param DOMElement[] $blocks */
    private function checkCoverage(array $blocks, array $concepts, bool $requireFull): void
    {
        $used = [];
        foreach ($blocks as $node) {
            $name = trim($node->getAttribute('data-concept'));
            if ($name !== '') {
                $used[$name] = true;
            }
        }

        $missing = array_values(array_filter(
            $concepts,
            static fn ($c) => !isset($used[$c])
        ));

        // An invented concept name is worse than a missing one: it looks like
        // coverage and silently maps to nothing.
        $invented = array_values(array_filter(
            array_keys($used),
            static fn ($c) => !in_array($c, $concepts, true)
        ));

        if ($missing && $requireFull) {
            $this->problem('Concepts never covered (' . count($missing) . '): ' . implode(', ', $missing));
        }

        if ($invented) {
            $this->problem('data-concept names not in concepts.json: ' . implode(', ', $invented));
        }

        if (!$invented && (!$missing || !$requireFull)) {
            $covered = count($concepts) - count($missing);
            $this->pass($requireFull
                ? sprintf('All %d concepts covered.', count($concepts))
                : sprintf('Covers %d of %d concepts (selective type - full coverage not required).', $covered, count($concepts)));
        }
    }

    /** @param DOMElement[] $blocks */
    private function checkBloomRange(array $blocks): void
    {
        // Only count levels that are actually in the vocabulary. Counting a
        // misspelling towards the range would let "Analyzing" satisfy the
        // spread requirement that "analyze" already satisfies - the deck would
        // look broader than it is, on the strength of a typo.
        $levels = [];
        foreach ($blocks as $node) {
            $bloom = $node->getAttribute('data-bloom');
            if ($bloom !== '' && in_array($bloom, self::BLOOM, true)) {
                $levels[$bloom] = true;
            }
        }

        $min = (int) $this->option('min-bloom');
        if (count($levels) < $min) {
            $this->problem(sprintf(
                'Bloom range is flat: %d distinct level(s), need %d. Present: %s',
                count($levels),
                $min,
                implode(', ', array_keys($levels)) ?: 'none'
            ));

            return;
        }

        $this->pass(sprintf('Bloom spans %d levels: %s', count($levels), implode(', ', array_keys($levels))));
    }

    private function checkSlides(DOMXPath $xpath, int $cap): void
    {
        $slides = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " slide ")]');
        if ($slides->length === 0) {
            return; // documents have no slides, which is correct
        }


        $noCheck = [];
        $tooLong = [];
        $index = 0;

        foreach ($slides as $slide) {
            $index++;
            if (!$slide instanceof DOMElement) {
                continue;
            }

            if ($xpath->query('.//*[@data-block="check"]', $slide)->length === 0) {
                $noCheck[] = $index;
            }

            // Body text only: callouts carry checks and misconceptions, which
            // are the part of a slide that SHOULD be wordy. The cap exists to
            // stop the explanatory prose becoming a wall of text (Mayer's
            // coherence and segmenting principles).
            $clone = $slide->cloneNode(true);
            $inner = new DOMXPath($slide->ownerDocument);
            foreach (iterator_to_array($inner->query('.//*[contains(concat(" ", normalize-space(@class), " "), " callout ")]', $clone)) as $callout) {
                $callout->parentNode?->removeChild($callout);
            }

            $words = str_word_count(trim(preg_replace('/\s+/', ' ', (string) $clone->textContent)));
            if ($words > $cap) {
                $tooLong[] = $index . ' (' . $words . 'w)';
            }
        }

        if ($noCheck) {
            $this->problem('Slides with no check block: ' . implode(', ', $noCheck));
        } else {
            $this->pass(sprintf('All %d slides ask the student something.', $slides->length));
        }

        if ($tooLong) {
            $this->problem(sprintf('Slides over %d words of body text: %s', $cap, implode(', ', $tooLong)));
        }
    }

    private function checkCallouts(DOMXPath $xpath): void
    {
        $bad = 0;
        foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " callout ")]') as $callout) {
            if (!$callout instanceof DOMElement) {
                continue;
            }
            // Meaning must never be carried by colour alone (WCAG 2.2). The
            // label is what a colour-blind reader - or a greyscale printout -
            // uses to tell a warning from an example.
            if ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " callout-label ")]', $callout)->length === 0) {
                $bad++;
            }
        }

        if ($bad > 0) {
            $this->problem($bad . ' callout(s) missing a .callout-label - meaning would be carried by colour alone.');
        }
    }

    private function checkLists(DOMXPath $xpath): void
    {
        $cap = (int) $this->option('max-bullets');
        $over = 0;
        foreach ($xpath->query('//ul|//ol') as $list) {
            if ($list instanceof DOMElement && $xpath->query('./li', $list)->length > $cap) {
                $over++;
            }
        }

        if ($over > 0) {
            $this->problem($over . ' list(s) exceed ' . $cap . ' bullets.');
        }
    }

    private function checkImages(DOMXPath $xpath): void
    {
        $missing = 0;
        foreach ($xpath->query('//img') as $img) {
            if ($img instanceof DOMElement && trim($img->getAttribute('alt')) === '') {
                $missing++;
            }
        }

        if ($missing > 0) {
            $this->problem($missing . ' image(s) with no alt text.');
        }
    }

    private function checkEmoji(string $html): void
    {
        if (preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html)) {
            $this->problem('Contains emoji - the design system forbids them.');
        }
    }

    private function problem(string $message): void
    {
        $this->failures++;
        $this->line('  <fg=red>FAIL</> ' . $message);
    }

    private function pass(string $message): void
    {
        $this->line('  <fg=green>ok</>   ' . $message);
    }
}
