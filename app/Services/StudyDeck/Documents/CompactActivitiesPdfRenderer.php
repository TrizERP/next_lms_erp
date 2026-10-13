<?php

namespace App\Services\StudyDeck\Documents;

use App\Services\StudyDeck\Documents\Writers\ActivityWriter;

/**
 * The PDF of COMPACT classroom activities: a run sheet and a card for each activity, on a few printed pages.
 *
 * The same page as the other compact documents (title band, the bank's own quiz questions with a fixed-height answer
 * area, at most two diagrams), with a card a teacher can run from: what the students do on the left, what the teacher
 * does and looks for on the right. The two copies are the TEACHER EDITION (the right half is the teacher's steps, what
 * to look for and the misconception to surface) and the STUDENT HANDOUT (the right half is a box for the student's own
 * notes). Both halves of a card have one fixed height in both copies, so the copies paginate identically, and the
 * writer's limits keep the text of each half within it (see `halfLines`).
 */
class CompactActivitiesPdfRenderer extends CompactRevisionPdfRenderer
{
    /** The most wrapped lines a half of a card holds. The validator holds the document to it. */
    public const HALF_LINES = 15;

    /** Characters that fit on one line of a half at this width (about 62 measured; conservative, because the wrap is estimated, never measured). The box is HALF_LINES lines of about 17px. */
    public const LINE_CHARS = 56;

    protected function cxKind(): string
    {
        return 'CLASSROOM ACTIVITIES';
    }

    protected function cxCopy(): string
    {
        return $this->variant === self::PRACTICE ? 'STUDENT HANDOUT' : 'TEACHER EDITION';
    }

    protected function cxFacts(array $d): string
    {
        $acts = array_filter($d['sections'], fn ($s) => $s['type'] === 'activity');
        $minutes = (int) ($d['sections'][0]['content']['total_minutes'] ?? 0);

        return count($acts) . ' activities · about ' . $minutes . ' minutes · ' . count($this->cxPlaced($d)) . ' quiz questions';
    }

    protected function cxOnline(): string
    {
        return 'Every quiz question, with feedback, is in this document\'s "Try it online" tab.';
    }

    protected function cxQuestionsTitle(): string
    {
        return 'Quiz questions';
    }

    protected function cxPracticeNote(): string
    {
        return $this->variant === self::PRACTICE
            ? 'Write each answer in its box. The answers are in the teacher edition.'
            : 'Use these in the activities where the steps ask for a quiz. Cover the answer box, ask, then check.';
    }

    // ---------------------------------------------------------------------------------------------------------

    /**
     * The paragraphs of the STUDENT half of a card, as printed: [label or null, text]. One list serves the drawing and the
     * budget, so what is counted is what is drawn.
     *
     * @param array<string,mixed> $c an activity section's content
     * @return array<int,array{0:?string,1:string}>
     */
    public static function studentParts(array $c): array
    {
        $parts = [];
        if (!empty($c['objectives'][0]['text'])) {
            $parts[] = ['AIM', (string) $c['objectives'][0]['text']];
        }
        if (!empty($c['materials'])) {
            $parts[] = ['YOU NEED', implode(', ', array_map('strval', $c['materials']))];
        }
        $parts[] = ['WHAT YOU DO', ''];
        foreach (array_values((array) ($c['student_steps'] ?? [])) as $i => $step) {
            $parts[] = [null, ($i + 1) . '. ' . $step];
        }
        if (!empty($c['reflection'][0])) {
            $parts[] = ['THINK ABOUT IT', (string) $c['reflection'][0]];
        }

        return $parts;
    }

    /**
     * The paragraphs of the TEACHER half, as printed. In the student handout the same box holds the student's own notes.
     *
     * @param array<string,mixed> $c an activity section's content
     * @return array<int,array{0:?string,1:string}>
     */
    public static function teacherParts(array $c): array
    {
        $parts = [['TEACHER', '']];
        foreach (array_values((array) ($c['teacher_steps'] ?? [])) as $i => $step) {
            $parts[] = [null, ($i + 1) . '. (' . (int) $step['minutes'] . ' min) ' . $step['text']];
        }
        foreach (array_slice((array) ($c['assessment'] ?? []), 0, 2) as $a) {
            $parts[] = ['LOOK FOR', $a['criterion'] . ': ' . $a['evidence']];
        }
        if (!empty($c['misconception'])) {
            $parts[] = ['SURFACE', rtrim((string) $c['misconception']['wrong_idea'], '.') . '. ' . $c['misconception']['correction']];
        }

        return $parts;
    }

    /**
     * How many lines a half of a card takes when printed: every paragraph starts a line, and wraps at LINE_CHARS (its
     * label counts, as it is printed with it). The writer's limits and the validator keep a card within HALF_LINES, so a
     * card that would overflow its box is a validation error, never a clipped line.
     *
     * @param array<string,mixed> $content an activity section's content
     */
    public static function halfLines(array $content, bool $teacher): int
    {
        $lines = 0;
        foreach ($teacher ? self::teacherParts($content) : self::studentParts($content) as [$label, $text]) {
            $lines += max(1, (int) ceil(mb_strlen(trim(($label !== null ? $label . ' ' : '') . $text)) / self::LINE_CHARS));
        }

        return $lines;
    }

    // ---------------------------------------------------------------------------------------------------------

    protected function cxBody(array $d): string
    {
        $acts = array_values(array_filter($d['sections'], fn ($s) => $s['type'] === 'activity'));

        return $this->cxRunSheet($d, $acts) . implode('', array_map(fn ($s) => $this->cxCard($d, $s), $acts));
    }

    /** One row for each activity: what it is, how it is done, and for how long. @param array<int,array<string,mixed>> $acts */
    private function cxRunSheet(array $d, array $acts): string
    {
        $rows = '';
        foreach ($acts as $i => $s) {
            $c = $s['content'];
            $rows .= '<tr><td>' . ($i + 1) . '</td><td>' . $this->e((string) $s['title']) . '</td><td>' . $this->e(ActivityWriter::FORMATS[$c['format']] ?? (string) $c['format'])
                . '</td><td>' . $this->e(ActivityWriter::GROUPINGS[$c['grouping']] ?? (string) $c['grouping']) . '</td><td>' . (int) $c['minutes'] . ' min</td><td>'
                . $this->e($this->cxConceptNames($d, $s)) . '</td></tr>';
        }

        return '<div class="keep"><div class="cx-sec">Run sheet</div><table class="cx-rs"><tr><th width="4%">#</th><th width="22%">Activity</th><th width="11%">Format</th><th width="11%">Done in</th><th width="7%">Time</th><th width="45%">Concepts covered</th></tr>' . $rows . '</table></div>';
    }

    /** The names of every concept the activity covers: the run sheet is how a teacher sees which activity covers which. @param array<string,mixed> $s */
    private function cxConceptNames(array $d, array $s): string
    {
        return implode('; ', array_values(array_filter(array_map(fn ($id) => (string) ($d['concepts'][$id]['name'] ?? ''), $s['concept_ids']))));
    }

    /** @param array<string,mixed> $s */
    private function cxCard(array $d, array $s): string
    {
        $c = $s['content'];
        $teacher = $this->variant === self::REVISION;

        $left = $this->half(self::studentParts($c));
        $right = $teacher ? $this->half(self::teacherParts($c)) : '<div><span class="cx-lb">YOUR NOTES</span></div>';

        $meta = ActivityWriter::FORMATS[$c['format']] ?? (string) $c['format'];
        $meta .= ' · ' . (ActivityWriter::GROUPINGS[$c['grouping']] ?? (string) $c['grouping']) . ' · ' . (int) $c['minutes'] . ' min';

        return '<div class="cx-card"><table class="cx-ac"><tr><td class="cx-ah"><span class="cx-no">' . (int) $s['n'] . '</span>' . $this->e((string) $s['title'])
            . ' <span class="cx-ahm">' . $this->e($meta) . '</span></td></tr></table>'
            . '<table class="cx-ac"><tr><td class="cx-as" width="49%"><div class="cx-fx">' . $left . '</div></td><td class="cx-gap" width="2%"></td><td class="cx-at' . ($teacher ? '' : ' cx-nt') . '" width="49%"><div class="cx-fx">' . $right . '</div></td></tr></table></div>';
    }

    /** @param array<int,array{0:?string,1:string}> $parts */
    private function half(array $parts): string
    {
        $out = '';
        foreach ($parts as [$label, $text]) {
            $out .= '<div' . ($label === null ? ' class="cx-sp"' : '') . '>' . ($label !== null ? '<span class="cx-lb">' . $this->e($label) . '</span> ' : '') . $this->e($text) . '</div>';
        }

        return $out;
    }

    protected function compactCss(): string
    {
        return parent::compactCss() . <<<'CSS'

/* Activities: run sheet and cards. Both halves of a card have one fixed height in both copies. */
.cx-rs { width: 100%; border-collapse: collapse; margin: 1px 0 5px; }
.cx-rs th { background: #f1f5f9; color: #334155; font-size: 8.5px; text-align: left; padding: 2px 5px; border: 0; }
.cx-rs td { padding: 1px 5px; font-size: 9px; border: 0; border-bottom: 1px solid #e2e8f0; }
.cx-card { margin: 0 0 5px; page-break-inside: avoid; }
.cx-ac { width: 100%; border-collapse: collapse; margin: 0; }
.cx-ac td { border: 0; }
.cx-ah { background: #ecfdf5; border-top: 3px solid #059669; padding: 2px 7px; font-size: 10.5px; font-weight: bold; color: #064e3b; }
.cx-ahm { color: #047857; font-size: 8.5px; font-weight: normal; }
.cx-ac .cx-as, .cx-ac .cx-at { vertical-align: top; padding: 3px 7px; border: 1px solid #e2e8f0; }
.cx-fx { height: 252px; overflow: hidden; }
.cx-ac .cx-nt { background: #ffffff; border: 1px solid #cbd5e1; }
.cx-lb { font-size: 7.5px; letter-spacing: 1px; font-weight: bold; color: #475569; }
.cx-sp { margin: 0 0 1px 6px; }
CSS;
    }
}
