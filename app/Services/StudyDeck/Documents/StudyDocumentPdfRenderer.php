<?php

namespace App\Services\StudyDeck\Documents;

use App\Services\StudyDeck\Documents\Writers\ActivityWriter;
use App\Services\StudyDeck\StudyDeckPdfRenderer;

/**
 * The PDF of a study document: revision notes, a remedial class, or classroom activities.
 *
 * It is the study deck's PDF engine with a different document laid on it. Everything that makes a study-deck PDF what
 * it is - the print stylesheet, the cover and contents, the numbered, explained diagram, a bank question with all its
 * options and its answer, an interaction written out in full, the two copies (answers shown / answers hidden), the
 * rule that nothing is split awkwardly and no page is left mostly empty - is the parent's, and is used unchanged.
 * This class only decides WHAT goes where for each kind:
 *
 *   revision notes   one compact card per concept (gist, key points, definition, rules, example, "don't confuse",
 *                    a line to remember), then the key terms, the important questions with their answers, and a
 *                    revision checklist
 *   remedial class   one unit per concept: what you need first, the idea in plain words, small steps, a picture, a
 *                    worked example, the mistakes learners make, practice that gets harder, what to revisit
 *   activities       a run sheet, then each activity as a card a teacher can run (objectives, materials, who does
 *                    what and for how long, what to expect, discussion, how to assess), as a TEACHER EDITION or as
 *                    a STUDENT HANDOUT without the teacher's pages
 *
 * A PDF cannot click, run or score anything, and this does not pretend to. Each interactive part is written out in
 * full in print form, and says plainly where the online version is: the document's own "Try it online" tab.
 */
class StudyDocumentPdfRenderer extends StudyDeckPdfRenderer
{
    /** Bump when the layout changes, so a PDF made by an older layout can be told apart. */
    public const DOCUMENT_LAYOUT_VERSION = 1;

    /** The two copies of a document: the stored one, and the one drawn on request. */
    public const TEACHER = StudyDeckPdfRenderer::REVISION;

    public const STUDENT = StudyDeckPdfRenderer::PRACTICE;

    /** Where a reader finds the interactive version of what is printed: a tab of this same item, not a page of its own. */
    protected const ONLINE_WHERE = 'Open this document in the app and choose "Try it online".';

    protected DocumentKind $kind = DocumentKind::RevisionNotes;

    protected function unitName(): string
    {
        return $this->kind->unitName();
    }

    /**
     * @param array<string,mixed> $deck the stored document
     * @param string $baseCss the print stylesheet the other generated documents use
     * @param array{variant?:string, questions?:array<int,array<string,mixed>>} $options
     */
    public function html(array $deck, string $baseCss, array $options = []): string
    {
        $this->variant = ($options['variant'] ?? self::REVISION) === self::PRACTICE ? self::PRACTICE : self::REVISION;
        $this->questions = $options['questions'] ?? [];
        // A document has no player route to link to: its online version is a tab of the same item.
        $this->linkBase = null;
        $this->contentId = null;
        $this->kind = DocumentKind::from((string) ($deck['kind'] ?? 'revision_notes'));

        $body = $this->documentCover($deck) . $this->contents($deck);
        $body .= match ($this->kind) {
            DocumentKind::RevisionNotes => $this->revisionNotes($deck),
            DocumentKind::Remedial => $this->remedialClass($deck),
            DocumentKind::Activities => $this->classroomActivities($deck),
        };

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $baseCss . $this->css() . '</style></head><body class="'
            . ['revision_notes' => 'k-rev', 'remedial' => 'k-rem', 'activities' => 'k-act'][$this->kind->value] . '">' . $body . '</body></html>';
    }

    /** What the running footer says on every page. */
    public static function footerLabel(array $deck): string
    {
        $kind = DocumentKind::tryFrom((string) ($deck['kind'] ?? ''));

        return $kind ? $kind->label() : 'Study document';
    }

    // ---------------------------------------------------------------------------------------------------------
    // Cover

    /** @param array<string,mixed> $d */
    protected function documentCover(array $d): string
    {
        $ch = $d['chapter'];
        $student = $this->variant === self::PRACTICE;
        $sections = array_filter($d['sections'], fn ($s) => $s['type'] !== 'overview');
        $questions = $this->practiceCount($d);

        [$kindLine, $stats, $legend] = match ($this->kind) {
            DocumentKind::RevisionNotes => [
                $student ? 'REVISION NOTES · PRACTICE COPY' : 'REVISION NOTES',
                [[count($sections), 'revision notes'], [$this->termCount($d), 'key terms'], [$questions, 'practice questions'], [$this->checklistCount($d), 'checklist points']],
                [
                    ['#4f46e5', 'Gist and key points', 'What the idea is, and the points to remember.'],
                    ['#2563eb', 'Definition', 'The exact meaning, in the chapter\'s words.'],
                    ['#059669', 'Worked example', 'The idea applied.'],
                    ['#d97706', 'Common misconception', 'What not to mix up, and what is true.'],
                    ['#334155', 'Important question', 'All the options' . ($student ? '; answers are in the full copy.' : ', the answer and why.')],
                    ['#7c3aed', 'Diagram', 'The parts of an idea, numbered and explained.'],
                    ['#0e7490', 'Try it online', 'The key terms as flashcards, every question with feedback, and the checklist to tick off. ' . self::ONLINE_WHERE],
                ],
            ],
            DocumentKind::Remedial => [
                $student ? 'REMEDIAL CLASS · PRACTICE COPY' : 'REMEDIAL CLASS · LEARN STEP BY STEP',
                [[count($sections), 'units'], [$this->workedExampleCount($d), 'worked examples'], [$questions, 'practice questions'], [$this->minutes($d), 'minutes in all']],
                [
                    ['#0284c7', 'Before you start', 'What you need to know first.'],
                    ['#4f46e5', 'In simple words', 'The idea, without the difficult wording.'],
                    ['#7c3aed', 'Step by step', 'The idea in small steps, in order.'],
                    ['#059669', 'Worked example', 'Done slowly, with the reason for each step.'],
                    ['#d97706', 'Watch out', 'A mistake many learners make.'],
                    ['#334155', 'Practice', 'Level 1 with a hint, then less help, then on your own.'],
                    ['#0e7490', 'Try it online', 'Each unit\'s steps, worked example and practice, with your answers checked as you go. ' . self::ONLINE_WHERE],
                ],
            ],
            DocumentKind::Activities => [
                $student ? 'CLASSROOM ACTIVITIES · STUDENT HANDOUT' : 'CLASSROOM ACTIVITIES · TEACHER EDITION',
                [[count($sections), 'activities'], [(int) ($d['sections'][0]['content']['total_minutes'] ?? 0), 'minutes in all'], [$questions, 'quiz questions'], [count($d['scope']['concept_ids'] ?? []), 'concepts']],
                $student ? [
                    ['#059669', 'What you do', 'The steps, in order.'],
                    ['#334155', 'Questions', 'Write your answers in the spaces.'],
                    ['#7c3aed', 'Matching and ordering', 'Pair, or number, as asked.'],
                    ['#0284c7', 'Reflect', 'Say what you learned.'],
                    ['#0e7490', 'Try it online', 'The quiz and the matching and ordering exercises work on screen. ' . self::ONLINE_WHERE],
                ] : [
                    ['#059669', 'Teacher steps', 'What to do and say, with times.'],
                    ['#0284c7', 'Student steps', 'What students do.'],
                    ['#334155', 'Quiz', 'Bank questions with answers and reasons.'],
                    ['#d97706', 'Misconception', 'The idea to bring out on purpose.'],
                    ['#4f46e5', 'Assessment', 'What to look for, and what it shows.'],
                    ['#7c3aed', 'Support and extension', 'For learners who need either.'],
                    ['#0e7490', 'Try it online', 'The quiz, the matching and ordering exercises and the reflection prompts work on screen, so a class can do them together. ' . self::ONLINE_WHERE],
                ],
            ],
        };

        $stat = fn (int|string $n, string $label) => '<td class="cv-stat"><span class="cv-n">' . $this->e((string) $n) . '</span><span class="cv-l">' . $this->e($label) . '</span></td>';
        $rows = '';
        foreach ($legend as [$colour, $name, $what]) {
            $rows .= '<tr><td class="lg-sw" style="background:' . $colour . '"></td><td class="lg-n">' . $this->e($name) . '</td><td class="lg-d">' . $this->e($what) . '</td></tr>';
        }
        $how = ['revision_notes' => 'How to use these notes', 'remedial' => 'How to use this class', 'activities' => $student ? 'How to use this handout' : 'How to use this edition'][$this->kind->value];

        return '<div class="cv-wrap"><table class="cv"><tr><td class="cv-main">'
            . '<div class="cv-eyebrow">' . $this->e(strtoupper(self::runningSubject($d))) . '</div>'
            . '<div class="cv-title">' . $this->e((string) $ch['name']) . '</div>'
            . '<div class="cv-kind">' . $this->e($kindLine) . '</div>'
            . ((string) $d['lede'] !== '' ? '<div class="cv-lede">' . $this->e((string) $d['lede']) . '</div>' : '')
            . '</td></tr><tr><td class="cv-stats"><table class="cv-st"><tr>'
            . implode('', array_map(fn ($x) => $stat($x[0], $x[1]), $stats))
            . '</tr></table></td></tr></table>'
            . '<div class="cv-how"><div class="cv-how-h">' . $this->e($how) . '</div><table class="lg">' . $rows . '</table></div></div>';
    }

    // ---------------------------------------------------------------------------------------------------------
    // Revision notes

    /** @param array<string,mixed> $d */
    protected function revisionNotes(array $d): string
    {
        $overview = $d['sections'][0]['content'];
        // One block: the heading, the summary and the topic table are never parted by a page break.
        $out = '<div class="ov keep"><div class="ov-h">The chapter in brief</div><p class="lead">' . $this->e((string) $overview['summary']) . '</p>';
        $rows = '';
        foreach ($overview['topics'] as $t) {
            $rows .= '<tr><td class="tm">' . $this->e((string) $t['name']) . '</td><td>' . $this->e((string) $t['gist']) . '</td></tr>';
        }
        $out .= $rows !== '' ? '<table class="lgd">' . $rows . '</table>' : '';
        $out .= '</div>';

        $lastTopic = null;
        foreach ($d['sections'] as $s) {
            if ($s['type'] === 'note') {
                $out .= $this->note($d, $s, $lastTopic);
            }
        }

        $out .= $this->keyTerms($d) . $this->importantQuestions($d) . $this->checklist($d);

        return $out;
    }

    /** @param array<string,mixed> $s */
    protected function note(array $d, array $s, ?int &$lastTopic): string
    {
        $c = $s['content'];
        $topic = $this->topicName($d, $s);
        $lead = $this->topicBand($d, $s, $lastTopic) . $this->partHeader((string) $s['n'], $topic, (string) $s['title'], $this->chip('about ' . (int) $c['minutes'] . ' min'));

        $out = '<div class="dp"><div class="keep">' . $lead . '<p class="lead">' . $this->e((string) $c['summary']) . '</p></div>';
        if ($c['key_points']) {
            $out .= '<ul class="pts">' . implode('', array_map(fn ($p) => '<li>' . $this->e((string) $p) . '</li>', $c['key_points'])) . '</ul>';
        }
        if ($c['definition']) {
            $out .= '<div class="keep card c-def"><div class="lb lb-def">DEFINITION · ' . $this->e(strtoupper((string) $c['definition']['term'])) . '</div><p>' . $this->e((string) $c['definition']['text']) . '</p></div>';
        }
        foreach ($c['rules'] as $r) {
            $out .= '<div class="keep rb"><div class="lb lb-exp">RULE TO REMEMBER</div><span class="rb-l">' . $this->e((string) $r['label']) . '.</span> <span class="rb-s">' . $this->e((string) $r['statement']) . '</span></div>';
        }
        $out .= $this->figure($s) . $this->interaction($s);
        if ($c['example']) {
            $out .= $this->card('ex', 'WORKED EXAMPLE', (string) $c['example']);
        }
        $out .= $this->mistakePair($c['misconception'] ? $c['misconception']['wrong_idea'] : '', $c['misconception'] ? $c['misconception']['correction'] : '', 'DO NOT CONFUSE');
        if ($c['remember']) {
            $out .= '<div class="keep"><table class="key"><tr><td class="key-l">REMEMBER</td><td class="key-t">' . $this->e((string) $c['remember']) . '</td></tr></table></div>';
        }

        return $out . '</div>';
    }

    /** The definitions of the notes, in one table, in alphabetical order. @param array<string,mixed> $d */
    protected function keyTerms(array $d): string
    {
        $terms = [];
        foreach ($d['sections'] as $s) {
            if ($s['type'] === 'note' && $s['content']['definition']) {
                $terms[] = [(string) $s['content']['definition']['term'], (string) $s['content']['definition']['text']];
            }
        }
        if ($terms === []) {
            return '';
        }
        usort($terms, fn ($a, $b) => strcasecmp($a[0], $b[0]));
        $rows = array_map(fn ($t) => '<tr><td class="tm">' . $this->e($t[0]) . '</td><td>' . $this->e($t[1]) . '</td></tr>', $terms);
        $chunks = $this->tableOf('<tr><th>Term</th><th>Meaning</th></tr>', $rows);
        $first = array_shift($chunks);

        return '<div class="sec"><div class="keep"><div class="sec-h">Key terms</div>' . $first . '</div>' . implode('', $chunks) . '</div>';
    }

    /** The bank's questions with their answers, by topic. @param array<string,mixed> $d */
    protected function importantQuestions(array $d): string
    {
        $byTopic = [];
        foreach ($d['sections'] as $s) {
            if ($s['type'] === 'note') {
                $byTopic[(int) $s['topic_id']][] = $s;
            }
        }
        $out = '';
        $count = 0;
        foreach ($d['outline'] as $i => $t) {
            $cards = '';
            foreach ($byTopic[(int) $t['topic_id']] ?? [] as $s) {
                foreach ($s['activities'] as $a) {
                    $q = $this->questions[(int) ($a['question_id'] ?? 0)] ?? null;
                    if ($q === null) {
                        continue;
                    }
                    $count++;
                    $cards .= $this->question($q, 'Question ' . $count . ' · ' . $a['label'] . ' · ' . $s['title'], 0);
                }
            }
            if ($cards !== '') {
                $out .= '<div class="keep"><table class="tb"><tr><td class="tb-n">TOPIC ' . ($i + 1) . '</td><td class="tb-t">' . $this->e((string) $t['name']) . '</td></tr></table></div>' . $cards;
            }
        }
        if ($out === '') {
            return '';
        }
        $note = $this->variant === self::PRACTICE
            ? 'Answer on paper, then check with the full copy of these notes.'
            : 'The answer and the reason are given under each question. Cover them, answer, then check.';

        return '<div class="sec"><div class="sec-h">Important questions and answers</div><div class="note">' . $this->e($note) . '</div></div>' . $out;
    }

    /** "I can" statements, by topic, to tick off. @param array<string,mixed> $d */
    protected function checklist(array $d): string
    {
        $byTopic = [];
        foreach ($d['sections'] as $s) {
            if ($s['type'] === 'note') {
                foreach ($s['content']['checklist'] as $item) {
                    $byTopic[(int) $s['topic_id']][] = [$item, (string) $s['title']];
                }
            }
        }
        $out = '';
        foreach ($d['outline'] as $i => $t) {
            $items = $byTopic[(int) $t['topic_id']] ?? [];
            if ($items === []) {
                continue;
            }
            $rows = implode('', array_map(fn ($x) => '<tr><td class="ck-b"><div class="bx"></div></td><td>' . $this->e((string) $x[0]) . '</td><td class="ck-c">' . $this->e($x[1]) . '</td></tr>', $items));
            $out .= '<div class="keep"><table class="tb"><tr><td class="tb-n">TOPIC ' . ($i + 1) . '</td><td class="tb-t">' . $this->e((string) $t['name']) . '</td></tr></table><table class="ck">' . $rows . '</table></div>';
        }

        return $out === '' ? '' : '<div class="sec"><div class="sec-h">Quick revision checklist</div><div class="note">Tick each one when you can do it without looking.</div></div>' . $out;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Remedial class

    /** @param array<string,mixed> $d */
    protected function remedialClass(array $d): string
    {
        $overview = $d['sections'][0]['content'];
        $rows = '';
        foreach ($overview['method'] as $i => $m) {
            $rows .= '<tr><td class="no">' . ($i + 1) . '</td><td class="tm">' . $this->e((string) $m['label']) . '</td><td>' . $this->e((string) $m['text']) . '</td></tr>';
        }
        $out = '<div class="ov keep"><div class="ov-h">How this remedial class works</div>'
            . '<p class="lead">Each unit is about one idea. Work through the units in order: what each one needs comes before it. Take your time, and go back whenever you want to.</p>'
            . '<table class="lgd">' . $rows . '</table></div>';

        $lastTopic = null;
        foreach ($d['sections'] as $s) {
            if ($s['type'] === 'unit') {
                $out .= $this->unit($d, $s, $lastTopic);
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $s */
    protected function unit(array $d, array $s, ?int &$lastTopic): string
    {
        $c = $s['content'];
        $student = $this->variant === self::PRACTICE;
        $lead = $this->topicBand($d, $s, $lastTopic) . $this->partHeader((string) $s['n'], $this->topicName($d, $s), (string) $s['title'], $this->chip('about ' . (int) $c['minutes'] . ' min'));

        $out = '<div class="dp"><div class="keep">' . $lead;
        if ($c['prerequisites']) {
            $rows = implode('', array_map(
                fn ($p) => '<tr><td class="tm">' . $this->e((string) $p['name']) . '</td><td>' . $this->e((string) $p['refresher']) . '</td></tr>',
                $c['prerequisites']
            ));
            $out .= '<div class="lb lb-info">BEFORE YOU START · WHAT THIS BUILDS ON</div><table class="lgd">' . $rows . '</table>';
        }
        $out .= '</div>';

        $out .= $this->card('exp', 'IN SIMPLE WORDS', (string) $c['simple_explanation']);

        if ($c['steps']) {
            $chunks = $this->legend(array_map(fn ($x) => [(string) $x['label'], (string) $x['text']], $c['steps']), 'Step ');
            $first = array_shift($chunks);
            $out .= '<div class="keep"><div class="ix-h"><span class="ix-tag">STEP BY STEP</span> Take the idea one step at a time</div>' . $first . '</div>' . implode('', $chunks);
        }
        if ($c['real_life']) {
            $out .= $this->card('ex', 'PICTURE IT', (string) $c['real_life']);
        }
        $out .= $this->figure($s) . $this->interaction($s);

        if ($c['worked_example']) {
            $we = $c['worked_example'];
            $rows = [];
            foreach ($we['steps'] as $i => $st) {
                $rows[] = '<tr><td class="no">' . ($i + 1) . '</td><td>' . $this->e((string) $st['text']) . '</td><td>' . $this->e((string) $st['why']) . '</td></tr>';
            }
            // The problem, the table's header and its first rows are one block, so a header is never left alone at the
            // foot of a page; a long table runs on after them, and the answer follows the last rows.
            $chunks = $this->tableOf('<tr><th>#</th><th>What to do</th><th>Why</th></tr>', $rows);
            $first = array_shift($chunks);
            $answer = '<div class="keep qa"><span class="qa-l">ANSWER</span> ' . $this->e((string) $we['answer']) . '</div>';
            $out .= '<div class="keep"><div class="card c-ex"><div class="lb lb-ex">WORKED EXAMPLE</div><p><strong>' . $this->e((string) $we['problem']) . '</strong></p></div>' . $first . ($chunks === [] ? $answer : '') . '</div>'
                . implode('', $chunks) . ($chunks === [] ? '' : $answer);
        }

        foreach ($c['mistakes'] as $m) {
            $out .= $this->mistakePair((string) $m['wrong_idea'], (string) $m['correct_idea'], 'WATCH OUT', (string) $m['why_wrong']);
        }

        foreach ($c['guided'] as $g) {
            $q = $this->questions[(int) $g['question_id']] ?? null;
            if ($q === null) {
                continue;
            }
            $hint = ((int) $g['level'] < 3 && trim((string) $g['hint']) !== '')
                ? '<div class="keep hint"><span class="qa-l">HINT</span> ' . $this->e((string) $g['hint']) . '</div>'
                : '';
            $out .= $this->question($q, 'Practice', 0, '<div class="lvl"><span class="lvl-t">LEVEL ' . (int) $g['level'] . '</span> ' . $this->e((string) $g['label']) . '</div>' . $hint);
            if (!$student && $g['not_options']) {
                $why = implode(' ', array_map(fn ($l, $t) => '<strong>' . $this->e((string) $l) . '.</strong> ' . $this->e((string) $t), array_keys($g['not_options']), $g['not_options']));
                $out .= '<div class="qe"><span class="qa-l">WHY THE OTHER CHOICES DO NOT FIT</span> ' . $why . '</div>';
            }
        }

        if ($c['follow_up']) {
            $rows = implode('', array_map(
                fn ($f) => '<tr><td class="tm">' . $this->e($this->unitName() . ' ' . (int) $f['section'] . ': ' . (string) $f['name']) . '</td><td>' . $this->e((string) $f['why']) . '</td></tr>',
                $c['follow_up']
            ));
            $out .= '<div class="keep"><div class="lb lb-info">IF THAT WAS HARD</div><table class="lgd">' . $rows . '</table></div>';
        }
        if ($c['win']) {
            $out .= '<div class="keep win"><span class="win-l">YOU CAN NOW</span> ' . $this->e((string) preg_replace('/^You can now\s*/i', '', (string) $c['win'])) . '</div>';
        }

        return $out . '</div>';
    }

    // ---------------------------------------------------------------------------------------------------------
    // Classroom activities

    /** @param array<string,mixed> $d */
    protected function classroomActivities(array $d): string
    {
        $overview = $d['sections'][0]['content'];
        $concepts = $d['concepts'];
        $rows = '';
        foreach ($overview['run_sheet'] as $r) {
            // The first two concepts by name, then how many more: each activity lists all of its own.
            $all = array_map(fn ($id) => (string) ($concepts[(string) $id]['name'] ?? ''), $r['concept_ids']);
            $names = implode(', ', array_slice($all, 0, 2)) . (count($all) > 2 ? ' +' . (count($all) - 2) . ' more' : '');
            $rows .= '<tr><td class="no">' . (int) $r['n'] . '</td><td class="tm">' . $this->e((string) $r['title']) . '</td><td>' . $this->e(ActivityWriter::FORMATS[$r['format']] ?? (string) $r['format']) . '</td><td>'
                . $this->e(ActivityWriter::GROUPINGS[$r['grouping']] ?? (string) $r['grouping']) . '</td><td class="mn">' . (int) $r['minutes'] . '</td><td>' . $this->e($names) . '</td></tr>';
        }
        $out = '<div class="ov keep"><div class="ov-h">Run sheet</div>'
            . '<table class="lgd rs"><tr><th class="rs-n">#</th><th class="rs-a">Activity</th><th class="rs-f">Format</th><th class="rs-g">Groups</th><th class="rs-m">Min</th><th>Concepts</th></tr>' . $rows
            . '<tr><td></td><td class="tm">In all</td><td></td><td></td><td class="mn"><strong>' . (int) $overview['total_minutes'] . '</strong></td><td></td></tr></table></div>';

        foreach ($d['sections'] as $s) {
            if ($s['type'] === 'activity') {
                $out .= $this->activity($d, $s);
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $s */
    protected function activity(array $d, array $s): string
    {
        $c = $s['content'];
        $teacher = $this->variant !== self::PRACTICE;
        $concepts = $d['concepts'];
        $chips = $this->chip(ActivityWriter::FORMATS[$c['format']] ?? (string) $c['format'])
            . $this->chip(ActivityWriter::GROUPINGS[$c['grouping']] ?? (string) $c['grouping'])
            . $this->chip((int) $c['minutes'] . ' min');

        $out = '<div class="dp"><div class="keep">' . $this->partHeader((string) $s['n'], $this->topicsOf($d, $s), (string) $s['title'], $chips)
            . '<p class="lead">' . $this->e((string) $c['focus']) . '</p></div>';

        // Objectives, and what is needed, side by side.
        $obj = implode('', array_map(fn ($o) => '<li>' . $this->e((string) $o['text']) . '</li>', $c['objectives']));
        // How to arrange the room is an instruction to the teacher: the handout lists only what a student needs.
        $need = implode('', array_map(fn ($m) => '<li>' . $this->e((string) $m) . '</li>', $c['materials'])) . ($teacher && $c['setup'] ? '</ul><p class="sub">' . $this->e((string) $c['setup']) . '</p><ul>' : '');
        if ($teacher) {
            $out .= '<div class="keep"><table class="two"><tr><td class="two-c"><div class="card c-exp"><div class="lb lb-exp">LEARNING OBJECTIVES</div><ul class="pts">' . $obj . '</ul></div></td>'
                . '<td class="two-g"></td><td class="two-c"><div class="card c-ex"><div class="lb lb-ex">YOU NEED</div><ul class="pts">' . $need . '</ul></div></td></tr></table></div>';

            $steps = array_map(fn ($t) => '<tr><td class="mn">' . (int) $t['minutes'] . '</td><td>' . $this->e((string) $t['text']) . '</td></tr>', $c['teacher_steps']);
            $chunks = $this->tableOf('<tr><th>Min</th><th>What the teacher does and says</th></tr>', $steps);
            $first = array_shift($chunks);
            $out .= '<div class="keep"><div class="ix-h"><span class="ix-tag">TEACHER</span> What you do</div>' . $first . '</div>' . implode('', $chunks);
        } else {
            $out .= '<div class="keep"><div class="lb lb-ex">YOU NEED</div><ul class="pts">' . $need . '</ul></div>';
        }

        $studentSteps = implode('', array_map(fn ($t, $i) => '<tr><td class="no">' . ($i + 1) . '</td><td>' . $this->e((string) $t) . '</td></tr>', $c['student_steps'], array_keys($c['student_steps'])));
        $out .= '<div class="keep"><div class="ix-h"><span class="ix-tag ix-st">STUDENTS</span> What you do</div><table class="lgd">' . $studentSteps . '</table></div>';

        $out .= $this->activityExercise($s, $teacher);

        foreach ($s['activities'] as $i => $a) {
            $q = $this->questions[(int) ($a['question_id'] ?? 0)] ?? null;
            if ($q !== null) {
                $out .= $this->question($q, 'Quiz question ' . ($i + 1), 0);
            }
        }

        if ($c['discussion']) {
            $rows = implode('', array_map(
                fn ($x) => '<tr><td class="tm">' . $this->e((string) $x['prompt']) . '</td>' . ($teacher ? '<td>' . $this->e((string) $x['answer']) . '</td>' : '<td><div class="rule"></div><div class="rule"></div></td>') . '</tr>',
                $c['discussion']
            ));
            $out .= '<div class="keep"><div class="ix-h"><span class="ix-tag ix-st">TALK ABOUT IT</span> Discuss</div><table class="lgd"><tr><th>Question</th><th>' . ($teacher ? 'A possible answer' : 'Our answer') . '</th></tr>' . $rows . '</table></div>';
        }

        if ($teacher) {
            if ($c['expected_outcomes']) {
                $out .= '<div class="keep card c-ex"><div class="lb lb-ex">WHAT TO LOOK FOR</div><ul class="pts">' . implode('', array_map(fn ($x) => '<li>' . $this->e((string) $x) . '</li>', $c['expected_outcomes'])) . '</ul></div>';
            }
            $out .= $this->mistakePair($c['misconception'] ? $c['misconception']['wrong_idea'] : '', $c['misconception'] ? $c['misconception']['correction'] : '', 'BRING THIS OUT ON PURPOSE');
            if ($c['assessment']) {
                $rows = implode('', array_map(fn ($a) => '<tr><td class="tm">' . $this->e((string) $a['criterion']) . '</td><td>' . $this->e((string) $a['evidence']) . '</td></tr>', $c['assessment']));
                $out .= '<div class="keep"><div class="ix-h"><span class="ix-tag">ASSESS</span> What it shows</div><table class="lgd"><tr><th>Criterion</th><th>What it looks like</th></tr>' . $rows . '</table></div>';
            }
            if ($c['differentiation']) {
                $dif = $c['differentiation'];
                $out .= '<div class="keep"><table class="two"><tr><td class="two-c"><div class="card c-exp"><div class="lb lb-exp">SUPPORT</div><p>' . $this->e((string) $dif['support']) . '</p></div></td><td class="two-g"></td>'
                    . '<td class="two-c"><div class="card c-ex"><div class="lb lb-ex">EXTENSION</div><p>' . $this->e((string) $dif['extension']) . '</p></div></td></tr></table></div>';
            }
        }

        if ($c['reflection']) {
            $lines = $teacher
                ? '<ul class="pts">' . implode('', array_map(fn ($x) => '<li>' . $this->e((string) $x) . '</li>', $c['reflection'])) . '</ul>'
                : implode('', array_map(fn ($x) => '<p class="q"><strong>' . $this->e((string) $x) . '</strong></p><div class="rule"></div><div class="rule"></div>', $c['reflection']));
            $out .= '<div class="keep card talk"><div class="lb lb-t">REFLECT</div>' . $lines . '</div>';
        }

        return $out . '</div>';
    }

    /**
     * The matching or ordering exercise of an activity, as a printed exercise.
     *
     * The teacher's copy shows it solved (the deck's own static form: each pair, the sequence in order). The student's
     * copy shows the same items out of order with a place to answer, so the printed handout is something to DO.
     *
     * @param array<string,mixed> $s
     */
    protected function activityExercise(array $s, bool $teacher): string
    {
        $i = $s['interaction'] ?? null;
        if (!is_array($i)) {
            return '';
        }
        if ($teacher || !in_array($i['kind'], ['match', 'order'], true)) {
            return $this->interaction($s);
        }

        if ($i['kind'] === 'match') {
            $pairs = array_values($i['pairs']);
            $meanings = $this->rotated($pairs);
            $left = '';
            $right = '';
            foreach ($pairs as $k => $p) {
                $left .= '<tr><td class="no">' . ($k + 1) . '</td><td class="tm">' . $this->e((string) $p['term']) . '</td><td class="ans">Answer: ______</td></tr>';
            }
            foreach ($meanings as $k => $p) {
                $right .= '<tr><td class="no">' . chr(65 + $k) . '</td><td>' . $this->e((string) $p['meaning']) . '</td></tr>';
            }

            return '<div class="keep"><div class="ix-h"><span class="ix-tag ix-st">MATCH</span> Pair each term with its meaning</div><div class="note">Write the letter of the meaning next to each term.</div>'
                . '<table class="two"><tr><td class="two-c"><table class="lgd">' . $left . '</table></td><td class="two-g"></td><td class="two-c"><table class="lgd">' . $right . '</table></td></tr></table></div>';
        }

        $items = $this->rotated(array_values($i['items']));
        $rows = implode('', array_map(fn ($x) => '<tr><td class="ck-b"><div class="bx"></div></td><td>' . $this->e((string) $x['text']) . '</td><td class="ans">Place: ____</td></tr>', $items));

        return '<div class="keep"><div class="ix-h"><span class="ix-tag ix-st">ORDER</span> Put these in the right order</div><div class="note">Write 1, 2, 3 ... beside each one.</div><table class="lgd">' . $rows . '</table></div>';
    }

    /** The same items in a fixed, different order (the first moves to the end), so a handout never prints its own key. @param array<int,mixed> $items @return array<int,mixed> */
    private function rotated(array $items): array
    {
        if (count($items) > 1) {
            $items[] = array_shift($items);
        }

        return $items;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Shared pieces

    protected function partHeader(string $no, string $eyebrow, string $title, string $chips = ''): string
    {
        return '<table class="dh"><tr><td class="dh-n">' . $this->e($no) . '</td><td class="dh-t"><div class="dh-e">' . $this->e(strtoupper($eyebrow)) . '</div>'
            . '<div class="dh-h">' . $this->e($title) . '</div>' . ($chips !== '' ? '<div class="chips">' . $chips . '</div>' : '') . '</td></tr></table>';
    }

    protected function chip(string $text): string
    {
        return '<span class="chip">' . $this->e($text) . '</span>';
    }

    /** A wrong idea beside what is true instead; nothing when there is no wrong idea. */
    protected function mistakePair(string $wrong, string $right, string $label, string $why = ''): string
    {
        if (trim($wrong) === '' || trim($right) === '') {
            return '';
        }

        return '<div class="keep"><table class="pair"><tr>'
            . '<td class="wrong"><div class="lb lb-w">' . $this->e($label) . ' · NOT QUITE</div>' . $this->e($wrong) . ($why !== '' ? '<div class="sub">' . $this->e($why) . '</div>' : '') . '</td>'
            . '<td class="right"><div class="lb lb-r">INSTEAD</div>' . $this->e($right) . '</td></tr></table></div>';
    }

    /**
     * The study deck's note beside an interactive part says where to play it in the player; a document has no player.
     * Its online version is a tab of the same item, and the cover says where (so the page that ends a document is never
     * a lone note); beside the part, the note says only what to do there.
     */
    protected function activityNote(array $slide, string $instruction): string
    {
        return '<div class="keep act"><table><tr><td class="act-i">&#9654;</td><td><span class="act-h">Try it online.</span> ' . $this->e($instruction) . '</td></tr></table></div>';
    }

    /**
     * An interactive part written out in full. The wrap-up line the assembler gives every diagram ("You have read about
     * every part.") is the closing message of the online version; on paper it is a sentence about nothing, so it is left out.
     */
    protected function interaction(array $slide): string
    {
        if (($slide['interaction']['wrapup'] ?? null) === DocumentAssembler::STOCK_WRAPUP) {
            $slide['interaction']['wrapup'] = '';
        }

        return parent::interaction($slide);
    }

    /**
     * The topics an activity draws on, for its heading: the first two by name, then how many more.
     *
     * @param array<string,mixed> $d @param array<string,mixed> $s
     */
    protected function topicsOf(array $d, array $s): string
    {
        $names = [];
        foreach ($s['concept_ids'] as $id) {
            $topic = (int) ($d['concepts'][(string) $id]['topic_id'] ?? 0);
            foreach ($d['outline'] as $t) {
                if ((int) $t['topic_id'] === $topic) {
                    $names[(string) $t['name']] = true;
                }
            }
        }
        $names = array_keys($names);

        return implode(' · ', array_slice($names, 0, 2)) . (count($names) > 2 ? ' · +' . (count($names) - 2) . ' more' : '');
    }

    /** @param array<string,mixed> $d @param array<string,mixed> $s */
    protected function topicName(array $d, array $s): string
    {
        foreach ($d['outline'] as $t) {
            if ((int) $t['topic_id'] === (int) ($s['topic_id'] ?? 0)) {
                return (string) $t['name'];
            }
        }

        return '';
    }

    /** @param array<string,mixed> $d */
    private function termCount(array $d): int
    {
        return count(array_filter($d['sections'], fn ($s) => $s['type'] === 'note' && $s['content']['definition']));
    }

    /** @param array<string,mixed> $d */
    private function checklistCount(array $d): int
    {
        return array_sum(array_map(fn ($s) => $s['type'] === 'note' ? count($s['content']['checklist']) : 0, $d['sections']));
    }

    /** @param array<string,mixed> $d */
    private function workedExampleCount(array $d): int
    {
        return count(array_filter($d['sections'], fn ($s) => $s['type'] === 'unit' && $s['content']['worked_example']));
    }

    /** @param array<string,mixed> $d */
    private function minutes(array $d): int
    {
        return array_sum(array_map(fn ($s) => (int) ($s['content']['minutes'] ?? 0), $d['sections']));
    }

    // ---------------------------------------------------------------------------------------------------------

    protected function css(): string
    {
        return parent::css() . <<<'CSS'

/* Study documents */
.dp { margin-top: 16px; }
.dh { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
.dh-n { width: 40px; background: #4f46e5; color: #ffffff; font-size: 17px; font-weight: bold; text-align: center; padding: 6px 0; border-radius: 8px 0 0 8px; }
.dh-t { background: #eef2ff; padding: 5px 12px; border-radius: 0 8px 8px 0; }
.dh-e { color: #6366f1; font-size: 8.5px; letter-spacing: 1.5px; font-weight: bold; }
.dh-h { color: #1e1b4b; font-size: 15px; font-weight: bold; line-height: 1.25; }
.chips { margin-top: 3px; }
.chip { background: #ffffff; color: #3730a3; border: 1px solid #c7d2fe; border-radius: 9px; padding: 1px 7px; font-size: 8.5px; margin-right: 4px; }
.k-rem .dh-n { background: #0284c7; } .k-rem .dh-t { background: #f0f9ff; } .k-rem .dh-e { color: #0284c7; } .k-rem .chip { color: #075985; border-color: #bae6fd; }
.k-act .dh-n { background: #059669; } .k-act .dh-t { background: #ecfdf5; } .k-act .dh-e { color: #059669; } .k-act .chip { color: #065f46; border-color: #a7f3d0; }

.ov { margin: 4px 0 6px; }
.ov-h, .sec-h { color: #3730a3; font-size: 18px; font-weight: bold; margin: 12px 0 6px; padding-bottom: 5px; border-bottom: 3px solid #4f46e5; }
.k-rem .ov-h, .k-rem .sec-h { color: #075985; border-bottom-color: #0284c7; }
.k-act .ov-h, .k-act .sec-h { color: #065f46; border-bottom-color: #059669; }
.sec { margin-top: 18px; }
.sub { color: #475569; font-size: 10.5px; margin-top: 3px; }

.rb { border: 1px solid #c7d2fe; border-left: 4px solid #4f46e5; background: #ffffff; padding: 6px 12px; margin: 7px 0; border-radius: 0 6px 6px 0; }
.rb-l { font-weight: bold; color: #1e1b4b; }
.rb-s { font-weight: bold; color: #3730a3; }
.lb-info { color: #0369a1; margin-top: 4px; }

.kt, .ck { width: 100%; border-collapse: collapse; margin: 3px 0 6px; }
.ck td { padding: 3px 7px; border-bottom: 1px solid #e2e8f0; font-size: 11.5px; vertical-align: top; }
.ck-b { width: 18px; text-align: center; }
.bx { width: 9px; height: 9px; border: 1.3px solid #4f46e5; border-radius: 2px; margin: 3px auto 0; }
.ck-c { width: 28%; color: #64748b; font-size: 9.5px; text-align: right; }

.lvl { margin: 9px 0 3px; font-size: 11.5px; font-weight: bold; color: #0c4a6e; }
.lvl-t { background: #0284c7; color: #ffffff; font-size: 8px; letter-spacing: 1.2px; padding: 2px 6px; border-radius: 3px; }
.hint { background: #f0f9ff; border-left: 4px solid #0284c7; padding: 4px 9px; margin: 3px 0; font-size: 11.5px; }
.win { background: #ecfdf5; border-left: 4px solid #059669; padding: 6px 11px; margin: 8px 0; font-size: 12px; font-weight: bold; color: #064e3b; }
.win-l { font-size: 8.5px; letter-spacing: 1.2px; color: #047857; }

.mn { width: 34px; text-align: center; font-weight: bold; color: #065f46; background: #f0fdf4; white-space: nowrap; }
.rs { table-layout: fixed; }
.rs-n { width: 5%; } .rs-a { width: 27%; } .rs-f { width: 13%; } .rs-g { width: 13%; } .rs-m { width: 7%; }
.qc { page-break-inside: avoid; }
.two { width: 100%; border-collapse: separate; border-spacing: 0; margin: 4px 0; }
.two td { border: 0; padding: 0; }
.two-c { width: 49%; vertical-align: top; }
.two-g { width: 2%; }
.ix-st { background: #059669; }
.ans { width: 92px; color: #64748b; font-size: 10px; white-space: nowrap; }
CSS;
    }
}
