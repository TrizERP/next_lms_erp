<?php

namespace App\Services\StudyDeck\Documents;

use App\Services\StudyDeck\Documents\Writers\ActivityWriter;
use App\Services\StudyDeck\SlideHtmlRenderer;

/**
 * A study document as the content design system's markup: `.cover`, `.callout`, tables, and the five `data-*`
 * attributes on every block.
 *
 * This is NOT what the PDF is drawn from (the PDF is drawn from the structured document, which keeps everything a
 * learner can open). It is what the content row's `description` column holds, so the document is readable straight
 * out of the database, it is the baseline text a later lesson can improve on, and `lms:validate-content` and the
 * LMS's own HTML view can read it like any other generated document. It says everything the document says, in the
 * vocabulary the other generated documents use.
 *
 * Only tags and attributes the sanitiser keeps are used (no style, no id, no link), and no picture is embedded:
 * a diagram is described by its alt text, which is true by construction.
 */
class DocumentHtmlRenderer
{
    /**
     * @param array<string,mixed> $document
     * @param array<int,array<string,mixed>> $questions normalised bank questions by id (QuestionSelector::normalise)
     */
    public function render(array $document, array $questions = []): string
    {
        $kind = DocumentKind::from($document['kind']);
        $ch = $document['chapter'];
        $concepts = $document['concepts'];
        $out = '<section class="cover" data-block="intro"><p class="eyebrow">' . $this->e(strtoupper('Class ' . $ch['standard_name'] . ' · ' . $ch['subject_name'])) . '</p>'
            . '<h2>' . $this->e($ch['name']) . ' - ' . $this->e(strtolower($kind->label())) . '</h2>'
            . '<p class="lede">' . $this->e((string) $document['lede']) . '</p></section>';

        $lastTopic = null;
        foreach ($document['sections'] as $s) {
            $taught = $s['taught_concept_ids'][0] ?? null;
            $name = $taught !== null ? (string) ($concepts[(string) $taught]['name'] ?? '') : '';
            $meta = fn (array $c = []) => $this->attrs($name, $c['bloom'] ?? 'understand', (int) ($c['dok'] ?? 2), (int) ($c['minutes'] ?? 3));

            $topicId = $s['topic_id'] ?? null;
            if ($topicId !== null && $topicId !== $lastTopic && $s['type'] !== 'overview') {
                $lastTopic = $topicId;
                foreach ($document['outline'] as $t) {
                    if ((int) $t['topic_id'] === (int) $topicId) {
                        $out .= '<h2>' . $this->e($t['name']) . '</h2>';
                    }
                }
            }

            $out .= match ($s['type']) {
                'overview' => $this->overview($kind, $s, $document),
                'note' => $this->note($s, $meta),
                'unit' => $this->unit($s, $meta, $questions, $document),
                'activity' => $this->activity($s, $document, $questions),
                default => '',
            };

            if ($s['type'] === 'note') {
                $out .= $this->questions($s, $questions, $meta($s['content']), 'Important question');
            }
        }

        if ($kind === DocumentKind::RevisionNotes) {
            $out .= $this->glossary($document);
        }

        return $out;
    }

    // ---------------------------------------------------------------------------------------------------------

    /** @param array<string,mixed> $s */
    private function overview(DocumentKind $kind, array $s, array $document): string
    {
        $c = $s['content'];
        $out = '';
        if ($kind === DocumentKind::RevisionNotes) {
            // A table, not a list: a chapter can have more topics than the design system allows bullets in one list.
            $out .= '<h2>Chapter at a glance</h2><p data-block="explain">' . $this->e((string) $c['summary']) . '</p><table><tr><th>Topic</th><th>What it covers</th></tr>';
            foreach ($c['topics'] as $t) {
                $out .= '<tr><td><strong>' . $this->e($t['name']) . '</strong></td><td>' . $this->e((string) $t['gist']) . '</td></tr>';
            }

            return $out . '</table>';
        }
        if ($kind === DocumentKind::Remedial) {
            $out .= '<h2>How this remedial class works</h2><ol>';
            foreach ($c['method'] as $m) {
                $out .= '<li><strong>' . $this->e($m['label']) . '.</strong> ' . $this->e($m['text']) . '</li>';
            }

            return $out . '</ol>';
        }

        $out .= '<h2>Run sheet</h2><table class="doc-num"><tr><th>Activity</th><th>Format</th><th>Groups</th><th>Minutes</th></tr>';
        foreach ($c['run_sheet'] as $r) {
            $out .= '<tr><td>' . $this->e($r['n'] . '. ' . $r['title']) . '</td><td>' . $this->e(ActivityWriter::FORMATS[$r['format']] ?? $r['format']) . '</td><td>'
                . $this->e(ActivityWriter::GROUPINGS[$r['grouping']] ?? $r['grouping']) . '</td><td>' . (int) $r['minutes'] . '</td></tr>';
        }

        return $out . '</table><p class="doc-num">About ' . (int) $c['total_minutes'] . ' minutes in all.</p>';
    }

    /** @param array<string,mixed> $s */
    private function note(array $s, callable $meta): string
    {
        $c = $s['content'];
        $m = $meta($c);
        $out = '<h3>' . $this->e($s['title']) . '</h3><p data-block="explain"' . $m . '>' . $this->e($c['summary']) . '</p>';

        if ($c['key_points']) {
            $out .= '<ul>' . implode('', array_map(fn ($p) => '<li>' . $this->e($p) . '</li>', $c['key_points'])) . '</ul>';
        }
        if ($c['definition']) {
            $out .= $this->callout('key', 'summary', 'Definition', '<p><strong>' . $this->e($c['definition']['term']) . '.</strong> ' . $this->e($c['definition']['text']) . '</p>', $m);
        }
        foreach ($c['rules'] as $r) {
            $out .= $this->callout('key', 'summary', 'Rule to remember', '<p><strong>' . $this->e($r['label']) . '.</strong> ' . $this->e($r['statement']) . '</p>', $m);
        }
        if ($c['example']) {
            $out .= $this->callout('example', 'example', 'Worked example', '<p>' . $this->e($c['example']) . '</p>', $m);
        }
        if ($c['misconception']) {
            $out .= $this->callout('warn', 'misconception', 'Common misconception', '<p>' . $this->e($c['misconception']['wrong_idea']) . '</p><p>' . $this->e($c['misconception']['correction']) . '</p>', $m);
        }
        if ($c['remember']) {
            $out .= $this->callout('key', 'summary', 'Remember', '<p>' . $this->e($c['remember']) . '</p>', $m);
        }
        $out .= $this->figure($s, $m);
        $out .= $this->interaction($s, $m);
        if ($c['checklist']) {
            $out .= $this->callout('try', 'check', 'Revision checklist', '<ul>' . implode('', array_map(fn ($i) => '<li>' . $this->e($i) . '</li>', $c['checklist'])) . '</ul>', $m);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $s
     * @param array<int,array<string,mixed>> $questions
     */
    private function unit(array $s, callable $meta, array $questions, array $document): string
    {
        $c = $s['content'];
        $m = $meta($c);
        $out = '<h3>' . $this->e($s['title']) . '</h3>';

        if ($c['prerequisites']) {
            $out .= $this->callout('key', 'check', 'Before you start', '<ul>' . implode('', array_map(fn ($p) => '<li><strong>' . $this->e($p['name']) . '.</strong> ' . $this->e((string) $p['refresher']) . '</li>', $c['prerequisites'])) . '</ul>', $m);
        }
        $out .= '<p data-block="explain"' . $m . '>' . $this->e($c['simple_explanation']) . '</p>';
        if ($c['steps']) {
            $out .= '<ol>' . implode('', array_map(fn ($st) => '<li><strong>' . $this->e($st['label']) . '.</strong> ' . $this->e($st['text']) . '</li>', $c['steps'])) . '</ol>';
        }
        if ($c['real_life']) {
            $out .= $this->callout('example', 'real-world', 'In real life', '<p>' . $this->e($c['real_life']) . '</p>', $m);
        }
        if ($c['worked_example']) {
            $we = $c['worked_example'];
            $body = '<p>' . $this->e($we['problem']) . '</p><ol>' . implode('', array_map(fn ($st) => '<li>' . $this->e($st['text']) . ($st['why'] !== '' ? ' ' . $this->e($st['why']) : '') . '</li>', $we['steps'])) . '</ol>'
                . '<p><strong>Answer:</strong> ' . $this->e($we['answer']) . '</p>';
            $out .= $this->callout('example', 'example', 'Worked example', $body, $m);
        }
        foreach ($c['mistakes'] as $mi) {
            $out .= $this->callout('warn', 'misconception', 'Common mistake', '<p>' . $this->e($mi['wrong_idea']) . '</p><p>' . $this->e($mi['why_wrong']) . '</p><p><strong>Instead:</strong> ' . $this->e($mi['correct_idea']) . '</p>', $m);
        }
        $out .= $this->figure($s, $m) . $this->interaction($s, $m);

        foreach ($c['guided'] as $g) {
            $q = $questions[$g['question_id']] ?? null;
            if ($q === null) {
                continue;
            }
            $extra = ($g['hint'] !== '' ? '<p><strong>Hint:</strong> ' . $this->e($g['hint']) . '</p>' : '');
            $out .= $this->question($q, 'Level <span class="doc-num">' . (int) $g['level'] . '</span>: ' . $this->e($g['label']), $m, $extra, $g['not_options'], true);
        }
        if ($c['follow_up']) {
            $out .= $this->callout('key', 'check', 'If this was hard', '<ul>' . implode('', array_map(fn ($f) => '<li>' . $this->e($f['why']) . ' (' . $this->e($f['name']) . ')</li>', $c['follow_up'])) . '</ul>', $m);
        }
        if ($c['win']) {
            $out .= $this->callout('key', 'summary', 'You can now', '<p>' . $this->e($c['win']) . '</p>', $m);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $s
     * @param array<int,array<string,mixed>> $questions
     */
    private function activity(array $s, array $document, array $questions): string
    {
        $c = $s['content'];
        $concepts = $document['concepts'];
        $first = $concepts[(string) ($s['concept_ids'][0] ?? 0)]['name'] ?? '';
        $m = $this->attrs($first, $c['bloom'], (int) $c['dok'], (int) $c['minutes']);
        $out = '<h3><span class="doc-num">' . (int) $s['n'] . '.</span> ' . $this->e($s['title']) . '</h3><p class="meta doc-num"><strong>Format:</strong> ' . $this->e(ActivityWriter::FORMATS[$c['format']] ?? $c['format'])
            . ' <strong>Groups:</strong> ' . $this->e(ActivityWriter::GROUPINGS[$c['grouping']] ?? $c['grouping']) . ' <strong>Time:</strong> ' . (int) $c['minutes'] . ' minutes</p>'
            . '<p data-block="explain"' . $m . '>' . $this->e($c['focus']) . '</p>';

        foreach ($c['objectives'] as $o) {
            $name = $concepts[(string) $o['concept_id']]['name'] ?? $first;
            $out .= $this->callout('key', 'activity', 'Objective', '<p>' . $this->e($o['text']) . '</p>', $this->attrs($name, $c['bloom'], (int) $c['dok'], (int) $c['minutes']));
        }
        $out .= $this->callout('example', 'activity', 'You need', '<ul class="doc-proc">' . implode('', array_map(fn ($x) => '<li>' . $this->e($x) . '</li>', $c['materials'])) . '</ul>' . ($c['setup'] ? '<p class="doc-proc">' . $this->e($c['setup']) . '</p>' : ''), $m);
        $out .= $this->callout('try', 'activity', 'What the teacher does', '<ol class="doc-proc">' . implode('', array_map(fn ($x) => '<li>(' . (int) $x['minutes'] . ' min) ' . $this->e($x['text']) . '</li>', $c['teacher_steps'])) . '</ol>', $m);
        $out .= $this->callout('try', 'activity', 'What students do', '<ol class="doc-proc">' . implode('', array_map(fn ($x) => '<li>' . $this->e($x) . '</li>', $c['student_steps'])) . '</ol>', $m);
        $out .= $this->callout('example', 'check', 'What to look for', '<ul>' . implode('', array_map(fn ($x) => '<li>' . $this->e($x) . '</li>', $c['expected_outcomes'])) . '</ul>', $m);
        $out .= $this->interaction($s, $m);
        foreach ($s['question_ids'] as $qid) {
            if (isset($questions[$qid])) {
                $out .= $this->question($questions[$qid], 'Quiz question', $m);
            }
        }
        if ($c['discussion']) {
            $out .= $this->callout('try', 'check', 'Discuss', implode('', array_map(fn ($d) => '<p><strong>' . $this->e($d['prompt']) . '</strong></p><p>Possible answer: ' . $this->e($d['answer']) . '</p>', $c['discussion'])), $m);
        }
        if ($c['misconception']) {
            $out .= $this->callout('warn', 'misconception', 'Misconception to surface', '<p>' . $this->e($c['misconception']['wrong_idea']) . '</p><p>' . $this->e($c['misconception']['correction']) . '</p>', $m);
        }
        $out .= '<table><tr><th>Assessment criterion</th><th>What it looks like</th></tr>'
            . implode('', array_map(fn ($a) => '<tr><td>' . $this->e($a['criterion']) . '</td><td>' . $this->e($a['evidence']) . '</td></tr>', $c['assessment'])) . '</table>';
        if ($c['differentiation']) {
            $d = $c['differentiation'];
            $out .= $this->callout('key', 'activity', 'Support and extension', '<p><strong>Support:</strong> ' . $this->e($d['support']) . '</p><p><strong>Extension:</strong> ' . $this->e($d['extension']) . '</p>', $m);
        }
        $out .= $this->callout('try', 'check', 'Reflect', '<ul>' . implode('', array_map(fn ($x) => '<li>' . $this->e($x) . '</li>', $c['reflection'])) . '</ul>', $m);

        return $out;
    }

    /** The key terms of a set of revision notes, in one place. @param array<string,mixed> $document */
    private function glossary(array $document): string
    {
        $rows = '';
        foreach ($document['sections'] as $s) {
            $d = $s['content']['definition'] ?? null;
            if ($s['type'] === 'note' && $d) {
                $rows .= '<tr><td>' . $this->e($d['term']) . '</td><td>' . $this->e($d['text']) . '</td></tr>';
            }
        }

        return $rows === '' ? '' : '<h2>Key terms</h2><table><tr><th>Term</th><th>Meaning</th></tr>' . $rows . '</table>';
    }

    // ---------------------------------------------------------------------------------------------------------

    /**
     * @param array<string,mixed> $s
     * @param array<int,array<string,mixed>> $questions
     */
    private function questions(array $s, array $questions, string $meta, string $label): string
    {
        $out = '';
        foreach ($s['question_ids'] as $qid) {
            if (isset($questions[$qid])) {
                $out .= $this->question($questions[$qid], $label, $meta);
            }
        }

        return $out;
    }

    /**
     * One bank question with its options, answer and stored explanation: the bank's own words, unchanged.
     *
     * @param array<string,mixed> $q
     * @param array<string,string> $notOptions why a wrong option does not fit, by option label
     */
    private function question(array $q, string $label, string $meta, string $extra = '', array $notOptions = [], bool $labelIsHtml = false): string
    {
        $body = (new SlideHtmlRenderer())->stem((string) $q['stem']);
        if ($q['options']) {
            $body .= '<ul>' . implode('', array_map(fn ($o) => '<li>' . $this->e($o['label'] . '. ' . $o['text']) . '</li>', $q['options'])) . '</ul>';
            $correct = array_values(array_filter($q['options'], fn ($o) => (string) $o['label'] === (string) $q['correct_label']))[0] ?? null;
            $answer = $correct ? $correct['label'] . '. ' . $correct['text'] : (string) $q['correct_label'];
        } else {
            $answer = (string) $q['answer_text'];
        }
        $body .= $extra . '<p><strong>Answer:</strong> ' . $this->e($answer) . '</p>';
        if ($q['explanation'] !== '' && $q['explanation'] !== $answer) {
            $body .= '<p><strong>Why:</strong> ' . $this->e($q['explanation']) . '</p>';
        }
        if ($notOptions) {
            $body .= '<p><strong>Why the others do not fit:</strong></p><ul>' . implode('', array_map(fn ($l, $w) => '<li>' . $this->e($l . '. ' . $w) . '</li>', array_keys($notOptions), $notOptions)) . '</ul>';
        }

        return $this->callout('try', 'check', $label, $body, $meta, $labelIsHtml);
    }

    /** @param array<string,mixed> $s */
    private function figure(array $s, string $meta): string
    {
        $img = $s['image'] ?? null;

        return is_array($img) && ($img['alt'] ?? '') !== ''
            ? $this->callout('example', 'visual', 'Diagram', '<p>' . $this->e((string) $img['alt']) . '</p>', $meta)
            : '';
    }

    /** @param array<string,mixed> $s */
    private function interaction(array $s, string $meta): string
    {
        $i = $s['interaction'] ?? null;
        if (!is_array($i)) {
            return '';
        }
        $label = ['hotspots' => 'Parts of the diagram', 'reveal' => 'Discover', 'steps' => 'Step by step', 'timeline' => 'Timeline', 'compare' => 'Compare', 'match' => 'Matching exercise', 'order' => 'Put in order'][$i['kind']] ?? 'Explore';
        $pair = fn ($l, $t) => '<li><strong>' . $this->e($l) . '.</strong> ' . $this->e($t) . '</li>';
        $body = match ($i['kind']) {
            'hotspots' => '<ul>' . implode('', array_map(fn ($x) => $pair($x['label'], $x['text']), $i['spots'])) . '</ul>',
            'match' => '<ul>' . implode('', array_map(fn ($x) => $pair($x['term'], $x['meaning']), $i['pairs'])) . '</ul>',
            'order' => '<ol>' . implode('', array_map(fn ($x) => '<li>' . $this->e($x['text']) . '</li>', $i['items'])) . '</ol>',
            default => '<ul>' . implode('', array_map(fn ($x) => $pair($x['label'], $x['text']), $i['items'] ?? [])) . '</ul>',
        };
        if (!empty($i['wrapup'])) {
            $body .= '<p>' . $this->e($i['wrapup']) . '</p>';
        }

        return $this->callout('try', 'activity', $label, $body, $meta);
    }

    private function callout(string $tone, string $block, string $label, string $body, string $meta, bool $labelIsHtml = false): string
    {
        return '<section class="callout callout-' . $tone . '" data-block="' . $block . '"' . $meta . '><span class="callout-label">' . ($labelIsHtml ? $label : $this->e($label)) . '</span>' . $body . '</section>';
    }

    private function attrs(string $concept, string $bloom, int $dok, int $minutes): string
    {
        return ($concept !== '' ? ' data-concept="' . $this->e($concept) . '"' : '') . ' data-bloom="' . $this->e($bloom) . '" data-dok="' . $dok . '" data-minutes="' . $minutes . '"';
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
