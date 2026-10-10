<?php

namespace App\Services\StudyDeck\Documents;

/**
 * The PDF of a COMPACT remedial class: one small card per concept, the whole chapter on a few printed pages.
 *
 * The same page as the compact revision notes (title band, two-column grid with the topic as a tag on the first card of
 * each, the bank's own questions with a fixed-height answer area, at most two diagrams), with a card made for a learner
 * who found the chapter hard: what to start from, the idea in plain words, three small steps, the one mistake to
 * avoid and what the learner can now do. Practice is the bank's questions after the cards, easiest first, without
 * hints (a hint is the model's wording for one question; a compact class prints the questions as the bank has them).
 */
class CompactRemedialPdfRenderer extends CompactRevisionPdfRenderer
{
    protected function cxKind(): string
    {
        return 'REMEDIAL CLASS';
    }

    protected function cxFacts(array $d): string
    {
        $units = array_filter($d['sections'], fn ($s) => $s['type'] === 'unit');

        return count($units) . ' units · ' . count($d['outline']) . ' topics · ' . count($this->cxPlaced($d)) . ' practice questions';
    }

    protected function cxOnline(): string
    {
        return 'Every unit\'s steps and every question, with feedback, are in this document\'s "Try it online" tab.';
    }

    protected function cxQuestionsTitle(): string
    {
        return 'Practice, then check yourself';
    }

    protected function cxBody(array $d): string
    {
        return $this->cxGrid($d, 'unit', fn (array $s) => $this->cxUnit($s));
    }

    /** @param array<string,mixed> $s */
    private function cxUnit(array $s): string
    {
        $c = $s['content'];
        $out = '<div class="cx-h"><span class="cx-no">' . (int) $s['n'] . '</span>' . $this->e((string) $s['title']) . '</div>';

        $needs = array_values(array_filter(array_map(fn ($p) => (string) ($p['name'] ?? ''), (array) $c['prerequisites'])));
        if ($needs !== []) {
            $out .= '<div class="cx-r"><span class="cx-rl">Start from</span> ' . $this->e(implode('; ', $needs)) . '</div>';
        }
        $out .= '<div class="cx-s">' . $this->e((string) $c['simple_explanation']) . '</div>';
        foreach (array_values((array) $c['steps']) as $i => $step) {
            // The text alone, one line a step: the label is for the online version, where each step opens on its own.
            $out .= '<div class="cx-st"><span class="cx-sl">' . ($i + 1) . '.</span> ' . $this->e((string) $step['text']) . '</div>';
        }
        foreach (array_slice((array) $c['mistakes'], 0, 1) as $m) {
            $out .= '<div class="cx-mi"><span class="cx-ml">Not quite</span> ' . $this->e(rtrim((string) $m['wrong_idea'], '.')) . '. <span class="cx-ml">Instead</span> ' . $this->e((string) $m['correct_idea']) . '</div>';
        }
        if ((string) $c['win'] !== '') {
            $out .= '<div class="cx-wn">' . $this->e((string) $c['win']) . '</div>';
        }

        return $out;
    }

    protected function compactCss(): string
    {
        return parent::compactCss() . <<<'CSS'

/* Remedial cards */
.cx-r { color: #475569; font-size: 9px; margin-bottom: 1px; }
.cx-rl { color: #0369a1; font-size: 7.5px; letter-spacing: 1px; font-weight: bold; }
.cx-st { margin: 0 0 1px 6px; }
.cx-sl { color: #5b21b6; font-weight: bold; }
.cx-mi { background: #fffbeb; border-left: 3px solid #d97706; padding: 1px 5px; margin-top: 2px; font-size: 9.5px; }
.cx-ml { color: #b45309; font-size: 7.5px; letter-spacing: 1px; font-weight: bold; }
.cx-wn { background: #ecfdf5; border-left: 3px solid #059669; padding: 1px 5px; margin-top: 2px; font-size: 9.5px; color: #064e3b; font-weight: bold; }
CSS;
    }
}
