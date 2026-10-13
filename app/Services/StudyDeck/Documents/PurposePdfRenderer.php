<?php

namespace App\Services\StudyDeck\Documents;

use App\Services\StudyDeck\SlideHtmlRenderer;

/**
 * The PDF of a PURPOSE-BASED study document: revision notes laid out as sheets for exam preparation, or a remedial class laid
 * out as a class to be worked through.
 *
 * It is the study document renderer with a different page laid on it, for the two kinds whose documents are not a card for
 * every concept:
 *
 *   revision notes   a title band, the chapter in brief, then a sheet for each topic (a line for every concept with its key
 *                    terms, a comparison table where the chapter compares things, what not to confuse, facts to recall, a
 *                    checklist), the key terms in one place and a short set of the bank's questions to test yourself
 *   remedial class   a title band with what the class is and how it works, a short check, the table of potential difficulties,
 *                    the lessons (the study document renderer's own unit layout), the common mix-ups to settle, practice on
 *                    your own, the exit check with its readiness rule and, in the copy with answers shown, the teacher's guide
 *
 * There is no cover page and no contents page: a document of five to fifteen pages starts with its first sheet. The page has
 * the running header and footer of every study document, with "Page n of N" from page one.
 *
 * The two copies differ in what answers are shown. The answers-hidden copy has the same questions with lines to write on, no
 * answers or reasons, no explanations of why the other choices do not fit, no corrections of the mix-ups and no teacher's guide.
 * It is written for learners, so what is only for the teacher is not in it.
 */
class PurposePdfRenderer extends StudyDocumentPdfRenderer
{
    /** Bump when the purpose-based layout changes. */
    public const PURPOSE_LAYOUT_VERSION = 1;

    /** A diagram on a revision sheet or in a lesson is smaller than a diagram in a lesson deck: the page is shared with the notes. */
    protected int $diagramWidth = 430;

    protected int $diagramHeight = 170;

    /** Is this a purpose-based document of a kind this renderer lays out? @param array<string,mixed> $deck */
    public static function isPurpose(array $deck): bool
    {
        return ($deck['profile'] ?? '') === 'purpose' && in_array($deck['kind'] ?? '', [DocumentKind::RevisionNotes->value, DocumentKind::Remedial->value], true);
    }

    /**
     * @param array<string,mixed> $deck the stored document
     * @param array{variant?:string, questions?:array<int,array<string,mixed>>} $options
     */
    public function html(array $deck, string $baseCss, array $options = []): string
    {
        $this->variant = ($options['variant'] ?? self::REVISION) === self::PRACTICE ? self::PRACTICE : self::REVISION;
        $this->questions = $options['questions'] ?? [];
        $this->linkBase = null;
        $this->contentId = null;
        $this->kind = DocumentKind::from((string) ($deck['kind'] ?? DocumentKind::RevisionNotes->value));

        $body = $this->band($deck) . ($this->kind === DocumentKind::Remedial ? $this->remedialBody($deck) : $this->revisionBody($deck));

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $baseCss . $this->css() . $this->purposeCss()
            . '</style></head><body class="' . ($this->kind === DocumentKind::Remedial ? 'k-rem' : 'k-rev') . ' k-pp">' . $body . '</body></html>';
    }

    /** The title band the first page opens with. @param array<string,mixed> $d */
    protected function band(array $d): string
    {
        $student = $this->variant === self::PRACTICE;
        $line = $this->kind === DocumentKind::Remedial
            ? ($student ? 'REMEDIAL CLASS · ANSWERS HIDDEN' : 'REMEDIAL CLASS · ANSWERS SHOWN, WITH THE TEACHER GUIDE')
            : ($student ? 'REVISION NOTES · ANSWERS HIDDEN' : 'REVISION NOTES · ANSWERS SHOWN');

        return '<table class="pb"><tr><td class="pb-m"><div class="pb-e">' . $this->e(strtoupper(self::runningSubject($d))) . '</div>'
            . '<div class="pb-t">' . $this->e((string) $d['chapter']['name']) . '</div>'
            . '<div class="pb-k">' . $this->e($line) . '</div>'
            . ((string) $d['lede'] !== '' ? '<div class="pb-l">' . $this->e((string) $d['lede']) . '</div>' : '')
            . '</td></tr></table>';
    }

    // ---------------------------------------------------------------------------------------------------------
    // Revision notes

    /** @param array<string,mixed> $d */
    protected function revisionBody(array $d): string
    {
        $overview = $d['sections'][0]['content'];
        $out = '<div class="keep"><div class="ov-h">The chapter in brief</div><p class="lead">' . $this->e((string) $overview['summary']) . '</p></div>';

        $topic = 0;
        foreach ($d['sections'] as $s) {
            $out .= match ($s['type']) {
                'topic' => $this->sheet($d, $s, ++$topic),
                'glossary' => $this->termsTable($s),
                'check' => $this->testYourself($s),
                default => '',
            };
        }

        return $out;
    }

    /** One topic's sheet. @param array<string,mixed> $s */
    protected function sheet(array $d, array $s, int $number): string
    {
        $c = $s['content'];

        // The key terms follow the sentence on the same line: a line of their own for each concept would cost a page.
        $rows = [];
        foreach ($c['rows'] as $r) {
            $terms = $r['terms'] ? ' ' . implode(' ', array_map(fn ($t) => '<span class="kw">' . $this->e((string) $t) . '</span>', $r['terms'])) : '';
            $rows[] = '<tr><td class="rv-n">' . $this->e((string) $r['name']) . '</td><td class="rv-t">' . $this->e((string) $r['essential']) . $terms . '</td></tr>';
        }
        $chunks = $this->tableOf('', $rows, 'rv');
        $first = array_shift($chunks);

        $out = '<div class="sheet"><div class="keep"><table class="tb"><tr><td class="tb-n">TOPIC ' . $number . '</td><td class="tb-t">' . $this->e((string) $s['title']) . '</td></tr></table>'
            . '<div class="bi"><span class="bi-l">BIG IDEA</span> ' . $this->e((string) $c['big_idea']) . '</div>' . $first . '</div>' . implode('', $chunks);

        if ($c['compare']) {
            $cmp = $c['compare'];
            $head = implode('', array_map(fn ($h) => '<th>' . $this->e((string) $h) . '</th>', $cmp['columns']));
            $body = implode('', array_map(fn ($row) => '<tr>' . implode('', array_map(fn ($cell) => '<td>' . $this->e((string) $cell) . '</td>', $row)) . '</tr>', $cmp['rows']));
            $out .= '<div class="keep"><div class="cx-h"><span class="ix-tag">COMPARE</span> ' . $this->e((string) $cmp['title']) . '</div><table class="cmp cx"><tr>' . $head . '</tr>' . $body . '</table></div>';
        }

        $out .= $this->figure($s) . $this->interaction($s);

        // What not to confuse: one table, a row for each pair, the wrong idea beside what is true.
        if ($c['mixups']) {
            $pairs = implode('', array_map(fn ($m) => '<tr><td class="mx-w"><span class="mx-l">NOT QUITE</span> ' . $this->e((string) $m['wrong_idea']) . '</td><td class="mx-r"><span class="mx-l">INSTEAD</span> ' . $this->e((string) $m['correct']) . '</td></tr>', $c['mixups']));
            $out .= '<div class="keep"><table class="mx">' . $pairs . '</table></div>';
        }

        // Facts to recall and the checklist, side by side where there are both.
        $recall = $c['recall'] ? '<div class="lb lb-def">RECALL</div><ul class="pts">' . implode('', array_map(fn ($r) => '<li>' . $this->e((string) $r) . '</li>', $c['recall'])) . '</ul>' : '';
        $check = $c['checklist'] ? '<div class="lb lb-ex">BEFORE THE EXAM, I CAN</div><table class="ck">' . implode('', array_map(fn ($x) => '<tr><td class="ck-b"><div class="bx"></div></td><td>' . $this->e((string) preg_replace('/^I can\s+/i', '', (string) $x)) . '</td></tr>', $c['checklist'])) . '</table>' : '';
        if ($recall !== '' && $check !== '') {
            $out .= '<div class="keep"><table class="two2"><tr><td class="two2-l">' . $recall . '</td><td class="two2-r">' . $check . '</td></tr></table></div>';
        } elseif ($recall . $check !== '') {
            $out .= '<div class="keep">' . $recall . $check . '</div>';
        }

        return $out . '</div>';
    }

    /** The key terms in one table, alphabetical. @param array<string,mixed> $s */
    protected function termsTable(array $s): string
    {
        $rows = array_map(fn ($t) => '<tr><td class="tm">' . $this->e((string) $t['term']) . '</td><td>' . $this->e((string) $t['meaning']) . '</td></tr>', $s['content']['terms']);
        if ($rows === []) {
            return '';
        }
        $chunks = $this->tableOf('<tr><th>Term</th><th>Meaning</th></tr>', $rows);
        $first = array_shift($chunks);

        return '<div class="sec"><div class="keep"><div class="sec-h">Key terms</div>' . $first . '</div>' . implode('', $chunks) . '</div>';
    }

    /** The bank's questions, a few from every topic. @param array<string,mixed> $s */
    protected function testYourself(array $s): string
    {
        $note = $this->variant === self::PRACTICE
            ? 'Answer on paper from memory. The answers are in the copy with answers shown.'
            : 'Cover the answer, answer from memory, then check. The reason is given under each question.';
        $out = '<div class="sec"><div class="sec-h">Test yourself</div><div class="note">' . $this->e($note) . '</div></div>';

        $n = 0;
        foreach ($s['activities'] as $a) {
            $q = $this->questions[(int) ($a['question_id'] ?? 0)] ?? null;
            if ($q !== null) {
                $out .= $this->pq($q, 'QUESTION ' . ++$n, (string) $a['label']);
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Remedial class

    /** @param array<string,mixed> $d */
    protected function remedialBody(array $d): string
    {
        $student = $this->variant === self::PRACTICE;
        $overview = $d['sections'][0]['content'];

        $method = '';
        foreach ($overview['method'] as $i => $m) {
            $method .= '<tr><td class="no">' . ($i + 1) . '</td><td class="tm">' . $this->e((string) $m['label']) . '</td><td>' . $this->e((string) $m['text']) . '</td></tr>';
        }
        $out = '<div class="ov keep"><div class="ov-h">How this class works</div><table class="lgd">' . $method . '</table></div>';

        if (!empty($overview['objectives'])) {
            $rows = array_map(fn ($o) => '<tr><td class="tm">' . $this->e((string) $o['name']) . '</td><td>' . $this->e((string) $o['text']) . '</td></tr>', $overview['objectives']);
            $chunks = $this->tableOf('<tr><th>Topic</th><th>By the end of the class</th></tr>', $rows);
            $first = array_shift($chunks);
            $out .= '<div class="keep"><div class="sec-h">What you will be able to do</div>' . $first . '</div>' . implode('', $chunks);
        }

        $lastTopic = null;
        foreach ($d['sections'] as $s) {
            $out .= match ($s['type']) {
                'diagnostic' => $this->shortCheck($d, $s),
                'gaps' => $this->gapsTable($s),
                'unit' => $this->lesson($d, $s, $lastTopic),
                'clinic' => $this->mixUps($d, $s),
                'independent' => $this->onYourOwn($s),
                'exit' => $this->exitCheck($d, $s),
                'teacher' => $student ? '' : $this->teacherGuide($d, $s),
                default => '',
            };
        }

        return $out;
    }

    /**
     * A table whose header and first two rows stay together (so a header is never left alone at the foot of a page) and whose
     * remaining rows flow on, never split. Every cell of every row carries its own width class, so a table that runs over a page
     * keeps its columns (the rest has no header row to take them from).
     *
     * @param array<int,string> $rows
     * @return array{0:string,1:string} [the head of the table, the rest of it ('' when it is all in the head)]
     */
    protected function flow(string $header, array $rows, string $class = 'lgd'): array
    {
        $table = fn (array $r, bool $withHeader) => '<table class="' . $class . '">' . ($withHeader ? $header : '') . implode('', $r) . '</table>';

        return [$table(array_slice($rows, 0, 2), true), count($rows) > 2 ? $table(array_slice($rows, 2), false) : ''];
    }

    /** A dark bar that opens a part: a small label and the title. */
    protected function bar(string $label, string $title): string
    {
        return '<table class="tb"><tr><td class="tb-n">' . $this->e($label) . '</td><td class="tb-t">' . $this->e($title) . '</td></tr></table>';
    }

    /** The short check: one question for each topic, each saying where to go if it was missed. @param array<string,mixed> $s */
    protected function shortCheck(array $d, array $s): string
    {
        $c = $s['content'];
        $out = '<div class="sec"><div class="keep">' . $this->bar('PART ' . (int) $s['n'], (string) $s['title']) . '<p class="note">' . $this->e((string) $c['intro']) . ' ' . $this->e((string) $c['scoring']) . '</p></div></div>';

        foreach ($c['items'] as $i => $item) {
            $q = $this->questions[(int) $item['question_id']] ?? null;
            if ($q === null) {
                continue;
            }
            $where = $item['if_missed']
                ? '<div class="miss"><span class="qa-l">IF YOU MISSED IT</span> take ' . implode(' or ', array_map(fn ($u) => $this->e('Unit ' . (int) $u['n'] . ': ' . (string) $u['title']), $item['if_missed'])) . '</div>'
                : '<div class="miss"><span class="qa-l">IF YOU MISSED IT</span> no unit in this class for this idea: read it again in the chapter.</div>';
            $out .= $this->pq($q, 'CHECK ' . ($i + 1), $this->topicNameById($d, (int) $item['topic_id']), $where);
        }

        return $out;
    }

    /** The ideas that may be hard, and why. @param array<string,mixed> $s */
    protected function gapsTable(array $s): string
    {
        $c = $s['content'];
        // The data of a chapter is often alike for most of its ideas (all rated medium, all asking for reasoning); what every row
        // shares is said once, above the table, and each row keeps what sets it apart.
        $common = [];
        if (count($c['rows']) >= 3) {
            $common = array_values(array_intersect(...array_map(fn ($r) => $r['reasons'], $c['rows'])));
        }
        $rows = [];
        foreach ($c['rows'] as $r) {
            $r['reasons'] = array_values(array_diff($r['reasons'], $common));
            $rows[] = '<tr><td class="gp-a gp-n"><strong>' . $this->e((string) $r['name']) . '</strong><div class="gp-p gp-' . $this->e((string) $r['priority']) . '">' . $this->e(strtoupper((string) $r['priority'])) . ' PRIORITY</div></td>'
                . '<td class="gp-b">' . $this->e(implode(' ', $r['reasons'])) . '</td>'
                . '<td class="gp-c">' . $this->e($r['check_first'] ? implode('; ', $r['check_first']) : 'Nothing earlier in this chapter.') . '</td>'
                . '<td class="gp-d gp-w">' . $this->e((string) $r['covered_in']) . '</td></tr>';
        }
        $head = '<tr><th class="gp-a">Idea</th><th class="gp-b">Why it may be hard</th><th class="gp-c">Check first</th><th class="gp-d">Dealt with in</th></tr>';
        [$first, $rest] = $this->flow($head, $rows, 'lgd gp');

        $out = '<div class="sec"><div class="keep">' . $this->bar('PART ' . (int) $s['n'], (string) $s['title'])
            . '<div class="keep card c-ex"><div class="lb lb-ex">POTENTIAL DIFFICULTIES, NOT MEASURED RESULTS</div><p>' . $this->e((string) $c['note']) . '</p>'
            . ($common ? '<p><strong>True of every idea below:</strong> ' . $this->e(implode(' ', $common)) . '</p>' : '') . '</div>' . $first . '</div>' . $rest;
        if ($c['others']) {
            $out .= '<div class="note">Lower priority, not retaught in this class: ' . $this->e(implode('; ', $c['others'])) . '.</div>';
        }

        return $out . '</div>';
    }

    /**
     * One lesson, laid out to fit about a page: what it builds on, the idea in plain words beside a picture from real life, the
     * steps, the worked example, one or two guided questions with their hints, and what to do if it was hard beside what the
     * learner can now do. The same parts as a study document's unit, set closer together.
     *
     * @param array<string,mixed> $s
     */
    protected function lesson(array $d, array $s, ?int &$lastTopic): string
    {
        $c = $s['content'];
        $shown = $this->variant === self::REVISION;
        $out = '<div class="dp"><div class="keep">' . $this->partHeader((string) $s['n'], $this->topicName($d, $s), (string) $s['title'], $this->chip('about ' . (int) $c['minutes'] . ' min'));

        if ($c['prerequisites']) {
            $out .= '<div class="bf"><span class="lb lb-info">BEFORE YOU START</span> ' . implode(' ', array_map(fn ($p) => '<strong>' . $this->e((string) $p['name']) . '.</strong> ' . $this->e((string) $p['refresher']), $c['prerequisites'])) . '</div>';
        }

        $words = '<div class="lb lb-exp">IN SIMPLE WORDS</div>' . $this->e((string) $c['simple_explanation']);
        if ($c['real_life']) {
            $out .= '<table class="two2 les"><tr><td class="two2-l les-l">' . $words . '</td><td class="two2-r les-r"><div class="lb lb-ex">PICTURE IT</div>' . $this->e((string) $c['real_life']) . '</td></tr></table></div>';
        } else {
            $out .= '<div class="card c-exp">' . $words . '</div></div>';
        }

        if ($c['steps']) {
            $rows = [];
            foreach (array_values($c['steps']) as $i => $x) {
                $rows[] = '<tr><td class="no">' . ($i + 1) . '</td><td class="tm">' . $this->e((string) $x['label']) . '</td><td>' . $this->e((string) $x['text']) . '</td></tr>';
            }
            [$first, $rest] = $this->flow('', $rows, 'lgd');
            $out .= '<div class="keep"><div class="ix-h"><span class="ix-tag">STEP BY STEP</span> One small step at a time</div>' . $first . '</div>' . $rest;
        }
        $out .= $this->figure($s) . $this->interaction($s);

        if ($c['worked_example']) {
            $we = $c['worked_example'];
            $rows = [];
            foreach ($we['steps'] as $i => $st) {
                $rows[] = '<tr><td class="no">' . ($i + 1) . '</td><td class="we-a">' . $this->e((string) $st['text']) . '</td><td class="we-b">' . $this->e((string) $st['why']) . '</td></tr>';
            }
            [$first, $rest] = $this->flow('<tr><th class="no">#</th><th class="we-a">What to notice or do</th><th class="we-b">Why</th></tr>', $rows, 'lgd we');
            $so = '<div class="qa"><span class="qa-l">SO</span> ' . $this->e((string) $we['answer']) . '</div>';
            $out .= '<div class="keep"><div class="ix-h"><span class="ix-tag ix-st">WORKED EXAMPLE</span> ' . $this->e((string) $we['problem']) . '</div>' . $first . ($rest === '' ? $so : '') . '</div>'
                . ($rest !== '' ? $rest . '<div class="keep">' . $so . '</div>' : '');
        }

        foreach ($c['guided'] as $g) {
            $q = $this->questions[(int) $g['question_id']] ?? null;
            if ($q === null) {
                continue;
            }
            $hint = trim((string) $g['hint']) !== '' ? '<div class="hint"><span class="qa-l">HINT</span> ' . $this->e((string) $g['hint']) . '</div>' : '';
            $others = '';
            if ($shown && $g['not_options']) {
                $others = '<div class="qe"><span class="qa-l">WHY THE OTHER CHOICES DO NOT FIT</span> '
                    . implode(' ', array_map(fn ($l, $t) => '<strong>' . $this->e((string) $l) . '.</strong> ' . $this->e((string) $t), array_keys($g['not_options']), $g['not_options'])) . '</div>';
            }
            $out .= $this->pq($q, 'LEVEL ' . (int) $g['level'], (string) $g['label'], $others, $hint);
        }

        $back = '';
        if ($c['follow_up']) {
            $back = '<div class="lb lb-info">IF THAT WAS HARD</div>' . implode('<br>', array_map(fn ($f) => $this->e('Unit ' . (int) $f['section'] . ': ' . (string) $f['name'] . ' - ' . (string) $f['why']), $c['follow_up']));
        }
        $win = $c['win'] ? '<div class="win-l">YOU CAN NOW</div>' . $this->e((string) preg_replace('/^You can now\s*/i', '', (string) $c['win'])) : '';
        if ($back !== '' && $win !== '') {
            $out .= '<div class="keep"><table class="two2 ft"><tr><td class="two2-l ft-l">' . $back . '</td><td class="two2-r ft-r">' . $win . '</td></tr></table></div>';
        } elseif ($back . $win !== '') {
            $out .= '<div class="keep win">' . $back . $win . '</div>';
        }

        return $out . '</div>';
    }

    /** The wrong ideas to settle: judge first, then read what is true. @param array<string,mixed> $s */
    protected function mixUps(array $d, array $s): string
    {
        $c = $s['content'];
        $shown = $this->variant === self::REVISION;
        $out = '<div class="sec"><div class="keep">' . $this->bar('PART ' . (int) $s['n'], (string) $s['title']) . '<p class="note">' . $this->e((string) $c['intro']) . '</p></div></div>';

        foreach ($c['items'] as $i => $it) {
            $name = (string) ($d['concepts'][(string) $it['concept_id']]['name'] ?? '');
            $left = '<div class="lb lb-w">MIX-UP ' . ($i + 1) . ' · ' . $this->e(strtoupper($name)) . '</div><div class="mu-i">' . $this->e((string) $it['wrong_idea']) . '</div>'
                . '<div class="mu-d"><span class="bx2"></span> Right <span class="bx2"></span> Wrong</div>';
            $right = $shown
                ? '<div class="lb lb-ex">WHY IT SEEMS RIGHT</div>' . $this->e((string) $it['why_it_seems_true'])
                    . '<div class="lb lb-ex mu-g">WHAT IS TRUE</div>' . $this->e((string) $it['correction'])
                    . '<div class="lb lb-ex mu-g">TEST IT</div>' . $this->e((string) $it['check_it'])
                : '<div class="lb lb-ex">YOUR REASON</div><div class="rule"></div><div class="rule"></div><div class="lb lb-ex mu-g">WHAT IS TRUE, IN YOUR WORDS</div><div class="rule"></div>';
            $out .= '<div class="keep"><table class="mu"><tr><td class="mu-l">' . $left . '</td><td class="mu-r">' . $right . '</td></tr></table></div>';
        }

        return $out;
    }

    /** Practice without hints. @param array<string,mixed> $s */
    protected function onYourOwn(array $s): string
    {
        $out = '<div class="sec"><div class="keep">' . $this->bar('PART ' . (int) $s['n'], (string) $s['title']) . '<p class="note">' . $this->e((string) $s['content']['intro']) . '</p></div></div>';
        $n = 0;
        foreach ($s['activities'] as $a) {
            $q = $this->questions[(int) ($a['question_id'] ?? 0)] ?? null;
            if ($q !== null) {
                $out .= $this->pq($q, 'ON YOUR OWN ' . ++$n, (string) $a['label']);
            }
        }

        return $out;
    }

    /** The exit check and what ready looks like. @param array<string,mixed> $s */
    protected function exitCheck(array $d, array $s): string
    {
        $c = $s['content'];
        $out = '<div class="sec"><div class="keep">' . $this->bar('PART ' . (int) $s['n'], (string) $s['title']) . '<p class="note">' . $this->e((string) $c['intro']) . '</p></div></div>';
        $n = 0;
        foreach ($s['activities'] as $a) {
            $q = $this->questions[(int) ($a['question_id'] ?? 0)] ?? null;
            if ($q !== null) {
                $out .= $this->pq($q, 'EXIT ' . ++$n, $this->conceptName($d, (int) ($a['concept_id'] ?? 0)));
            }
        }

        $items = implode('', array_map(fn ($x) => '<tr><td class="ck-b"><div class="bx"></div></td><td>' . $this->e((string) $x['text']) . '</td></tr>', $c['criteria']));
        $back = implode('; ', array_map(fn ($r) => $this->e('Unit ' . (int) $r['n'] . ': ' . (string) $r['name']), $c['revisit']));
        $out .= '<div class="keep"><div class="lb lb-ex">YOU ARE READY WHEN</div><table class="ck">' . $items . '</table>'
            . '<div class="win"><span class="win-l">READY</span> ' . (int) $c['ready_at'] . ' of ' . (int) $c['total'] . ' exit questions correct without help, and the statements above are true for you.</div>'
            . '<div class="miss"><span class="qa-l">IF NOT YET</span> go back to the unit for the question you missed: ' . $back . '.</div></div>';

        return $out;
    }

    /** The teacher's guide: timing, how to run the class, what to look for. @param array<string,mixed> $s */
    protected function teacherGuide(array $d, array $s): string
    {
        $c = $s['content'];
        $out = '<div class="sec"><div class="keep">' . $this->bar('FOR THE TEACHER', (string) $s['title']) . '<p class="note">' . $this->e((string) $c['purpose']) . '</p></div></div>';

        $run = implode('', array_map(fn ($x, $i) => '<tr><td class="no">' . ($i + 1) . '</td><td>' . $this->e((string) $x) . '</td></tr>', $c['how_to_run'], array_keys($c['how_to_run'])));
        $out .= '<div class="keep"><div class="lb lb-info">HOW TO RUN THE CLASS</div><table class="lgd">' . $run . '</table></div>';

        $time = implode(' · ', array_map(fn ($p) => $this->e((string) $p['title']) . ' <strong>' . (int) $p['minutes'] . '</strong>', $c['pacing']));
        $out .= '<div class="keep"><div class="lb lb-info">TIMING, IN MINUTES</div><div class="note">' . $time . ' · In all <strong>' . (int) $c['total_minutes'] . '</strong></div></div>';

        $rows = array_map(fn ($iv) => '<tr><td class="tm">' . $this->e('Unit ' . (int) $iv['n'] . ': ' . (string) $iv['name']) . '</td><td>' . $this->e((string) $iv['look_for']) . '</td><td>' . $this->e((string) $iv['try_this']) . '</td><td>' . $this->e((string) $iv['if_still_stuck']) . '</td></tr>', $c['interventions']);
        $chunks = $this->tableOf('<tr><th class="iv-a">Unit</th><th class="iv-b">You may notice</th><th class="iv-c">Try this</th><th class="iv-d">If still stuck</th></tr>', $rows, 'lgd iv');
        $first = array_shift($chunks);

        return $out . '<div class="keep"><div class="lb lb-info">WHAT TO LOOK FOR AND TRY IN EACH UNIT</div>' . $first . '</div>' . implode('', $chunks);
    }

    // ---------------------------------------------------------------------------------------------------------
    // Shared pieces

    /**
     * A bank question as one block: the question with all its options, then (answers shown) the correct option marked and the bank's
     * reason (a written question's model answer too), or (answers hidden) lines to write on. The bank's text is printed exactly as
     * it is stored.
     *
     * @param array<string,mixed> $q a normalised bank question
     * @param string $below markup that belongs under the question (where to go if it was missed)
     * @param string $hint markup between the question and its options (a lesson's hint)
     */
    protected function pq(array $q, string $tag, string $label, string $below = '', string $hint = ''): string
    {
        $shown = $this->variant === self::REVISION;
        $choice = !empty($q['options']);
        $out = '<div class="qc"><div class="keep"><div class="qc-h"><span class="qc-tag">' . $this->e($tag) . '</span> ' . $this->e($label) . '</div>'
            . '<div class="qc-s">' . (new SlideHtmlRenderer())->stem((string) ($q['stem'] ?? '')) . '</div>' . $hint;

        if ($choice) {
            $cell = function (array $o) use ($shown, $q): string {
                $ok = $shown && (string) $o['label'] === (string) $q['correct_label'];

                return '<td class="op-l' . ($ok ? ' op-k' : '') . '">' . $this->e((string) $o['label']) . '</td><td class="op-t' . ($ok ? ' op-k' : '') . '">' . $this->e((string) $o['text']) . ($ok ? ' <span class="op-c">&#10003; Correct</span>' : '') . '</td>';
            };
            // Short options sit two to a row: four lines of choices would otherwise cost more than the question does.
            $short = max(array_map(fn ($o) => mb_strlen((string) $o['text']), $q['options'])) <= 48;
            if ($short && count($q['options']) >= 4) {
                $out .= '<table class="op op2">';
                foreach (array_chunk(array_values($q['options']), 2) as $pair) {
                    $out .= '<tr>' . $cell($pair[0]) . (isset($pair[1]) ? $cell($pair[1]) : '<td class="op-l"></td><td class="op-t"></td>') . '</tr>';
                }
            } else {
                $out .= '<table class="op">';
                foreach ($q['options'] as $o) {
                    $out .= '<tr>' . $cell($o) . '</tr>';
                }
            }
            $out .= '</table>';
        } elseif (!$shown) {
            $out .= '<div class="rule"></div><div class="rule"></div>';
        }
        $out .= '</div>';

        if ($shown) {
            // A choice question's answer is the option marked above; only a written question needs its model answer printed.
            [$name, $answer, $why] = CompactRevisionPdfRenderer::answerOf($q);
            $lines = (!$choice && $answer !== '' ? '<div><span class="qa-l">' . $this->e($name) . '</span> ' . $this->e($answer) . '</div>' : '')
                . ($why !== '' ? '<div><span class="qa-l">WHY</span> ' . $this->e($why) . '</div>' : '');
            $out .= $lines !== '' ? '<div class="qa">' . $lines . '</div>' : '';
        }

        return $out . $below . '</div>';
    }

    /**
     * A hotspot diagram is numbered on the picture so that its legend can be read against it. This layout prints no legend, so the
     * numbers would point at nothing: the plain picture is printed (every part is labelled on it).
     *
     * @param array<string,mixed> $slide
     */
    protected function markedPicture(array $slide, string $url): ?string
    {
        return null;
    }

    /**
     * The numbered legend that goes with a hotspot diagram is the explanation of each part, for selecting online. On a page that is
     * shared with notes it only repeats the picture and the sheet, so a diagram is printed as a picture (its parts are still in
     * the document, and in the online version); any other interaction is written out as usual.
     *
     * @param array<string,mixed> $slide
     */
    protected function interaction(array $slide): string
    {
        if (($slide['interaction']['kind'] ?? '') === 'hotspots' && is_array($slide['image'] ?? null)) {
            return '';
        }

        return parent::interaction($slide);
    }

    /** @param array<string,mixed> $d */
    protected function topicNameById(array $d, int $topicId): string
    {
        foreach ($d['outline'] as $t) {
            if ((int) $t['topic_id'] === $topicId) {
                return (string) $t['name'];
            }
        }

        return '';
    }

    /** @param array<string,mixed> $d */
    protected function conceptName(array $d, int $conceptId): string
    {
        return (string) ($d['concepts'][(string) $conceptId]['name'] ?? '');
    }

    protected function purposeCss(): string
    {
        return <<<'CSS'

/* Purpose-based documents */
@page { margin: 50px 38px 46px 38px; }
body.k-pp { font-size: 11.5px; line-height: 1.42; }
.k-pp .tb { margin-bottom: 5px; }
.k-pp .tb-n { padding: 4px 9px; } .k-pp .tb-t { padding: 4px 11px; font-size: 12.5px; }
.k-pp .card { margin: 5px 0; padding: 5px 11px 2px; }
.k-pp .qc { margin: 6px 0; padding: 5px 11px 4px; }
.k-pp .qc-s p { font-size: 11.5px; margin: 0 0 3px; }
.k-pp .op td { padding: 2px 6px; font-size: 11px; }
.op2 { table-layout: fixed; } .op2 .op-l { width: 5%; } .op2 .op-t { width: 45%; }
.op-k { background: #ecfdf5; }
.k-pp .qa, .k-pp .qe { font-size: 11px; line-height: 1.35; padding: 3px 8px; margin: 3px 0 2px; }
.k-pp .hint { font-size: 11px; padding: 3px 8px; margin: 3px 0; }
.bf { background: #f0f9ff; border-left: 4px solid #0284c7; padding: 4px 9px; margin: 4px 0; font-size: 11px; line-height: 1.35; }
.les { margin: 4px 0 4px -6px; }
.les-l { width: 56%; background: #eef2ff; border-left: 3px solid #4f46e5; font-size: 11px; line-height: 1.4; }
.les-r { width: 44%; background: #ecfdf5; border-left: 3px solid #059669; font-size: 11px; line-height: 1.4; }
.ft { margin: 4px 0 4px -6px; }
.ft-l { width: 62%; background: #f0f9ff; border-left: 3px solid #0284c7; font-size: 11px; line-height: 1.35; }
.ft-r { width: 38%; background: #ecfdf5; border-left: 3px solid #059669; font-size: 11px; font-weight: bold; color: #064e3b; line-height: 1.35; }
.k-pp .pts { margin: 0 0 3px 16px; } .k-pp .pts li { font-size: 11px; margin: 0 0 1px; }
.pb { width: 100%; border-collapse: collapse; margin: 0 0 8px; }
.cx { table-layout: fixed; margin: 2px 0 5px; }
.cx th { padding: 3px 7px; font-size: 10.5px; } .cx td { padding: 3px 7px; font-size: 11px; line-height: 1.35; }
.mx { width: 100%; border-collapse: separate; border-spacing: 0 3px; margin: 2px 0 3px; }
.mx td { vertical-align: top; padding: 3px 8px; font-size: 11px; line-height: 1.35; }
.mx-w { width: 48%; background: #fffbeb; border-left: 3px solid #d97706; }
.mx-r { width: 52%; background: #ecfdf5; border-left: 3px solid #059669; }
.mx-l { font-size: 7.5px; letter-spacing: 1px; font-weight: bold; color: #475569; }
.two2 { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 3px 0 3px -6px; }
.two2 td { vertical-align: top; padding: 4px 9px; }
.two2-l { width: 58%; background: #eff6ff; border-left: 3px solid #2563eb; }
.two2-r { width: 42%; background: #ecfdf5; border-left: 3px solid #059669; }
.two2 .ck td { border: 0; padding: 1px 4px; font-size: 11px; }
.pb-m { background: #4338ca; padding: 15px 22px 13px; }
.pb-e { color: #c7d2fe; font-size: 9px; letter-spacing: 2px; font-weight: bold; margin-bottom: 7px; }
.pb-t { color: #ffffff; font-size: 21px; line-height: 1.2; font-weight: bold; margin-bottom: 6px; }
.pb-k { color: #a5b4fc; font-size: 9px; letter-spacing: 2px; font-weight: bold; margin-bottom: 6px; }
.pb-l { color: #e0e7ff; font-size: 11.5px; line-height: 1.45; }
.k-rem .pb-m { background: #0369a1; } .k-rem .pb-e { color: #bae6fd; } .k-rem .pb-k { color: #7dd3fc; } .k-rem .pb-l { color: #e0f2fe; }
.k-rem .tb-n { background: #0c4a6e; color: #bae6fd; } .k-rem .tb-t { background: #0369a1; }
.sheet { margin-top: 12px; }
.bi { background: #eef2ff; border-left: 4px solid #4f46e5; padding: 5px 10px; margin: 0 0 5px; font-size: 12px; font-weight: bold; color: #1e1b4b; }
.bi-l { color: #4338ca; font-size: 8px; letter-spacing: 1.2px; }
.rv { width: 100%; border-collapse: collapse; margin: 2px 0 5px; table-layout: fixed; }
.rv td { border-bottom: 1px solid #e2e8f0; padding: 3px 7px; font-size: 11px; line-height: 1.35; vertical-align: top; }
.rv-n { width: 25%; font-weight: bold; color: #312e81; background: #f5f7ff; }
.rv-k { margin-top: 2px; }
.kw { background: #fef9c3; color: #713f12; padding: 0 4px; font-size: 9.5px; margin-right: 3px; }
.cx-h { color: #5b21b6; font-size: 11.5px; font-weight: bold; margin: 6px 0 2px; }
.miss { background: #f0f9ff; border-left: 4px solid #0284c7; padding: 4px 9px; margin: 4px 0 2px; font-size: 11px; }
.gp { table-layout: fixed; }
.gp-a { width: 21%; } .gp-b { width: 40%; } .gp-c { width: 23%; } .gp-d { width: 16%; }
.we { table-layout: fixed; } .we .no { width: 34px; } .we-a { width: 50%; } .we-b { width: 42%; }
.gp td { font-size: 11px; line-height: 1.35; }
.gp-n { background: #f0f9ff; }
.gp-p { font-size: 7.5px; letter-spacing: 1px; font-weight: bold; margin-top: 2px; color: #475569; }
.gp-higher { color: #b45309; }
.gp-w { font-weight: bold; color: #075985; }
.mu { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 4px 0 4px -6px; }
.mu td { vertical-align: top; padding: 5px 9px; font-size: 11px; line-height: 1.35; }
.mu-l { width: 38%; background: #fffbeb; border: 1px solid #fcd34d; border-top: 4px solid #d97706; }
.mu-r { width: 62%; background: #f8fafc; border: 1px solid #cbd5e1; border-top: 4px solid #0284c7; }
.mu-i { font-weight: bold; color: #1e293b; margin: 2px 0 5px; }
.mu-d { color: #475569; font-size: 11px; }
.mu-g { margin-top: 5px; }
.bx2 { display: inline-block; width: 9px; height: 9px; border: 1.3px solid #0369a1; margin: 0 3px 0 6px; }
.tt { width: 60%; }
.tt-m { width: 14%; }
.iv { table-layout: fixed; }
.iv-a { width: 17%; } .iv-b { width: 27%; } .iv-c { width: 30%; } .iv-d { width: 26%; }
.iv td { font-size: 11px; line-height: 1.35; }
.ov-h { color: #3730a3; font-size: 16px; font-weight: bold; margin: 8px 0 5px; padding-bottom: 4px; border-bottom: 3px solid #4f46e5; }
.k-rem .ov-h { color: #075985; border-bottom-color: #0284c7; }
.sec-h { font-size: 16px; }
CSS;
    }
}
