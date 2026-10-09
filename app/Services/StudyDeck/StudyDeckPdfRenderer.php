<?php

namespace App\Services\StudyDeck;

/**
 * The classroom PDF of a study deck, as a study guide.
 *
 * It is drawn from the STORED DECK (the JSON the interactive player reads), never from the presentation markup. That
 * markup is built for slides and leaves out everything a learner opens by clicking, and the concept definitions. A PDF
 * cannot click, so each interaction is written out in full as its static form:
 *
 *   hotspots   the diagram with NUMBERED markers on it, then a legend with each part and what it is
 *   reveal     each card's title and explanation        steps      the steps in order, each explained
 *   timeline   each event with its date                 compare    the things side by side, then how they compare
 *   match      each term with its meaning               order      the sequence in its correct order
 *   scenario   the situation, every decision with every choice, what happens, why, and the conclusion
 *   "Explain this concept"   the explanation and the concept's definition as visible cards
 *   worked example, common mistake and instead, key idea, Think-Pair-Share with its possible answer
 *   practice   each bank question the deck uses, with all options, the correct answer and its explanation
 *
 * and each interactive slide carries a panel that links to that slide in the Study Deck, where it really works.
 * Nothing is written that the deck does not hold. A PDF cannot run the browser player and this does not pretend to.
 *
 * LAYOUT. The document flows: a cover, a contents page, then the lesson. A slide does NOT start a new page. Short
 * things (a card, a question, a diagram with its heading, a slide's heading with its first card) are kept whole; long
 * things (a legend of many rows, a long explanation) are left to run over the page. That is the whole pagination
 * rule: no slide-sized block is ever "avoid break", so no page is left mostly empty to protect one.
 *
 * Dompdf has no flexbox or grid, so layout is tables. Only pictures on the shared store ($imageBase) are drawn.
 */
class StudyDeckPdfRenderer
{
    /** Bump when the layout changes, so a PDF made by an older layout can be told apart. */
    public const LAYOUT_VERSION = 4;

    public const REVISION = 'revision';

    public const PRACTICE = 'practice';

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

    /** What the learner does with each kind of activity in the Study Deck. */
    private const TRY = [
        'hotspots' => 'Select each numbered part of the diagram to see what it is.',
        'reveal' => 'Open each card to read its explanation.',
        'steps' => 'Open each step in turn to follow the process.',
        'timeline' => 'Open each event to see what happened.',
        'compare' => 'Open each item, then compare them.',
        'match' => 'Match every term with its meaning.',
        'order' => 'Put the steps into their correct order.',
        'scenario' => 'Make each decision and see what happens next.',
    ];

    /** Rows a legend may have and still be kept on one page; longer ones are left to run on. */
    private const KEEP_ROWS = 5;

    /** About how much text (characters) a table may hold and still be kept on one page. */
    private const KEEP_CHARS = 600;

    /** @var callable|null fn(string $url): ?string  raw bytes of a stored picture, for the numbered markers */
    private $imageBytes;

    private string $variant = self::REVISION;

    /** @var array<int,array<string,mixed>> */
    private array $questions = [];

    private ?string $linkBase = null;

    private ?int $contentId = null;

    public function __construct(private readonly string $imageBase, ?callable $imageBytes = null, private readonly ?string $markerFont = null)
    {
        $this->imageBytes = $imageBytes;
    }

    /**
     * @param array<string,mixed> $deck the stored deck (version 3)
     * @param string $baseCss the print stylesheet the other generated documents use
     * @param array{variant?:string, questions?:array<int,array<string,mixed>>, link_base?:?string, content_id?:?int} $options
     */
    public function html(array $deck, string $baseCss, array $options = []): string
    {
        $this->variant = ($options['variant'] ?? self::REVISION) === self::PRACTICE ? self::PRACTICE : self::REVISION;
        $this->questions = $options['questions'] ?? [];
        $this->linkBase = isset($options['link_base']) && is_string($options['link_base']) && preg_match('~^https?://~i', $options['link_base']) ? rtrim($options['link_base'], '/') : null;
        $this->contentId = isset($options['content_id']) ? (int) $options['content_id'] : null;

        $body = $this->cover($deck) . $this->contents($deck);
        $lastTopic = null;
        foreach ($deck['slides'] ?? [] as $slide) {
            if (($slide['slide_type'] ?? '') === 'cover') {
                continue;
            }
            $body .= $this->slide($deck, $slide, $lastTopic);
        }

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $baseCss . $this->css() . '</style></head><body>' . $body . '</body></html>';
    }

    /** What the running header and footer say. */
    public static function runningTitle(array $deck): string
    {
        return (string) ($deck['chapter']['name'] ?? 'Study deck');
    }

    public static function runningSubject(array $deck): string
    {
        $ch = $deck['chapter'] ?? [];

        return trim('Class ' . ($ch['standard_name'] ?? '') . ' · ' . ($ch['subject_name'] ?? ''), ' ·');
    }

    // ---------------------------------------------------------------------------------------------------------
    // Cover and contents

    /** @param array<string,mixed> $deck */
    private function cover(array $deck): string
    {
        $ch = $deck['chapter'] ?? [];
        $cover = null;
        foreach ($deck['slides'] ?? [] as $s) {
            if (($s['slide_type'] ?? '') === 'cover') {
                $cover = $s;
                break;
            }
        }
        $lede = trim((string) ($cover['content']['body'] ?? ''));
        $lessons = count(array_filter($deck['slides'] ?? [], fn ($s) => ($s['slide_type'] ?? '') !== 'cover'));
        $activities = count(array_filter($deck['slides'] ?? [], fn ($s) => !empty($s['interaction'])));
        $practice = $this->practiceCount($deck);

        $stat = fn (string $n, string $label) => '<td class="cv-stat"><span class="cv-n">' . $this->e($n) . '</span><span class="cv-l">' . $this->e($label) . '</span></td>';

        $legend = [
            ['#4f46e5', 'Explanation', 'The idea, in plain words.'],
            ['#2563eb', 'Definition', 'The exact meaning of the concept.'],
            ['#059669', 'Worked example', 'The idea applied, step by step.'],
            ['#d97706', 'Common mistake', 'What goes wrong, and what to think instead.'],
            ['#7c3aed', 'Interactive activity', 'Written out here; open it in the Study Deck to try it.'],
            ['#0284c7', 'Talk about it', 'Think, pair, share, with a possible answer.'],
            ['#334155', 'Practice question', 'All the options' . ($this->variant === self::REVISION ? ', the answer and why.' : '; answers are in the revision copy.')],
        ];

        $rows = '';
        foreach ($legend as [$colour, $name, $what]) {
            $rows .= '<tr><td class="lg-sw" style="background:' . $colour . '"></td><td class="lg-n">' . $this->e($name) . '</td><td class="lg-d">' . $this->e($what) . '</td></tr>';
        }

        return '<div class="cv-wrap"><table class="cv"><tr><td class="cv-main">'
            . '<div class="cv-eyebrow">' . $this->e(strtoupper(self::runningSubject($deck))) . '</div>'
            . '<div class="cv-title">' . $this->e((string) ($ch['name'] ?? '')) . '</div>'
            . '<div class="cv-kind">' . ($this->variant === self::PRACTICE ? 'STUDY GUIDE · PRACTICE COPY' : 'STUDY GUIDE') . '</div>'
            . ($lede !== '' ? '<div class="cv-lede">' . $this->e($lede) . '</div>' : '')
            . '</td></tr><tr><td class="cv-stats"><table class="cv-st"><tr>'
            . $stat((string) $lessons, 'lessons') . $stat((string) count($deck['concepts'] ?? []), 'concepts')
            . $stat((string) $activities, 'interactive activities') . $stat((string) $practice, 'practice questions')
            . '</tr></table></td></tr></table>'
            . '<div class="cv-how"><div class="cv-how-h">How to read this guide</div><table class="lg">' . $rows . '</table></div></div>';
    }

    /** @param array<string,mixed> $deck */
    private function contents(array $deck): string
    {
        $taughtBy = $deck['taught_by'] ?? [];
        $concepts = $deck['concepts'] ?? [];
        // One block per topic, then two columns of blocks with about the same number of rows in each.
        $blocks = [];
        foreach (array_values($deck['outline'] ?? []) as $i => $topic) {
            $rows = '';
            $count = 1;
            foreach ($topic['concept_ids'] ?? [] as $cid) {
                $c = $concepts[(string) $cid] ?? null;
                if (!$c) {
                    continue;
                }
                $count++;
                $slides = $taughtBy[(string) $cid] ?? [];
                $rows .= '<tr><td class="ct-c">' . $this->e((string) $c['name']) . '</td><td class="ct-s">' . ($slides ? $this->e('Slide ' . implode(', ', $slides)) : '') . '</td></tr>';
            }
            $blocks[] = ['rows' => $count, 'html' => '<div class="keep"><table class="ct-topic"><tr><td class="ct-no">' . ($i + 1) . '</td><td class="ct-name">' . $this->e((string) ($topic['name'] ?? '')) . '</td></tr></table><table class="ct-rows">' . $rows . '</table></div>'];
        }
        $total = array_sum(array_column($blocks, 'rows'));
        $cols = ['', ''];
        $seen = 0;
        foreach ($blocks as $b) {
            $cols[$seen < $total / 2 ? 0 : 1] .= $b['html'];
            $seen += $b['rows'];
        }

        return '<div class="ct"><div class="ct-h">Chapter at a glance</div><table class="ct2"><tr><td class="ct2-c">' . $cols[0] . '</td><td class="ct2-g"></td><td class="ct2-c">' . $cols[1] . '</td></tr></table></div>';
    }

    // ---------------------------------------------------------------------------------------------------------
    // One slide

    /** @param array<string,mixed> $slide */
    private function slide(array $deck, array $slide, ?int &$lastTopic): string
    {
        $c = $slide['content'] ?? [];
        $n = (int) ($slide['n'] ?? 0);
        $concepts = $deck['concepts'] ?? [];
        $name = fn ($id) => (string) ($concepts[(string) $id]['name'] ?? '');

        // The heading is kept with whatever comes first under it, so a title is never left alone at the foot of a page.
        $lead = $this->topicBand($deck, $slide, $lastTopic);
        $lead .= '<table class="sh"><tr><td class="sh-n">' . $n . '</td><td class="sh-t"><div class="sh-e">' . $this->e(strtoupper(self::STAGE[$slide['slide_type'] ?? ''] ?? 'Lesson')) . '</div>'
            . '<div class="sh-h">' . $this->e((string) ($slide['title'] ?? '')) . '</div></td></tr></table>';
        $taught = array_values(array_filter(array_map($name, $slide['taught_concept_ids'] ?? [])));
        if ($taught) {
            $lead .= '<div class="sub">Concept: <strong>' . $this->e(implode(' and ', $taught)) . '</strong></div>';
        }

        $cards = [];
        $explanations = $c['explanations'] ?? [];
        $multi = count($slide['taught_concept_ids'] ?? []) > 1;
        foreach ($explanations as $e) {
            $cards[] = $this->card('exp', 'EXPLANATION' . ($multi && $name($e['concept_id'] ?? 0) !== '' ? ' · ' . strtoupper($name($e['concept_id'])) : ''), (string) ($e['text'] ?? ''));
        }
        if (!$explanations && trim((string) ($c['body'] ?? '')) !== '') {
            $cards[] = '<p class="lead">' . $this->e((string) $c['body']) . '</p>';
        }
        $said = array_map(fn ($e) => $this->norm((string) ($e['text'] ?? '')), $explanations);
        foreach ($slide['taught_concept_ids'] ?? [] as $id) {
            $def = trim((string) ($concepts[(string) $id]['definition'] ?? ''));
            if ($def !== '' && !in_array($this->norm($def), $said, true)) {
                $cards[] = $this->card('def', 'DEFINITION' . ($multi ? ' · ' . strtoupper($name($id)) : ''), $def);
            }
        }
        // The first card travels with the heading; the rest follow on their own.
        $first = array_shift($cards);
        $out = '<div class="sl"><div class="keep">' . $lead . ($first ?? '') . '</div>' . implode('', $cards);

        if (!empty($c['bullets'])) {
            $out .= '<ul class="pts">' . implode('', array_map(fn ($b) => '<li>' . $this->e((string) $b) . '</li>', $c['bullets'])) . '</ul>';
        }

        $out .= $this->figure($slide);
        $out .= $this->interaction($slide);

        if (!empty($c['example'])) {
            $out .= $this->card('ex', 'WORKED EXAMPLE', (string) $c['example']);
        }
        if (!empty($c['misconception'])) {
            $m = $c['misconception'];
            $out .= '<div class="keep"><table class="pair"><tr>'
                . '<td class="wrong"><div class="lb lb-w">COMMON MISTAKE - NOT QUITE</div>' . $this->e((string) ($m['wrong_idea'] ?? '')) . '</td>'
                . '<td class="right"><div class="lb lb-r">INSTEAD</div>' . $this->e((string) ($m['correction'] ?? '')) . '</td></tr></table></div>';
        }
        if (!empty($c['relationship_note'])) {
            $out .= $this->card('exp', 'HOW THESE CONNECT', (string) $c['relationship_note']);
        }
        $key = trim((string) ($c['key_idea'] ?? ''));
        if ($key !== '') {
            $out .= '<div class="keep"><table class="key"><tr><td class="key-l">KEY IDEA</td><td class="key-t">' . $this->e($key) . '</td></tr></table></div>';
        }
        if (!empty($c['discussion'])) {
            $d = $c['discussion'];
            $answer = trim((string) ($d['answer'] ?? ''));
            $out .= '<div class="keep card talk"><div class="lb lb-t">TALK ABOUT IT · THINK, PAIR, SHARE</div>'
                . '<p class="q"><strong>' . $this->e((string) ($d['prompt'] ?? '')) . '</strong></p>'
                . '<p class="tps"><strong>Think.</strong> On your own for a minute. &nbsp;<strong>Pair.</strong> Compare with the person next to you. &nbsp;<strong>Share.</strong> Tell the class what you decided.</p>'
                . ($answer !== '' && $this->variant === self::REVISION ? '<p class="ans"><strong>Possible answer:</strong> ' . $this->e($answer) . '</p>' : '')
                . '</div>';
        }
        $out .= $this->practice($deck, $slide);

        return $out . '</div>';
    }

    /** A topic band at the first slide of each topic. Returns '' when the topic has not changed. */
    private function topicBand(array $deck, array $slide, ?int &$lastTopic): string
    {
        $cid = ($slide['taught_concept_ids'][0] ?? $slide['concept_ids'][0] ?? null);
        $topicId = $cid !== null ? ($deck['concepts'][(string) $cid]['topic_id'] ?? null) : null;
        if ($topicId === null || $topicId === $lastTopic) {
            return '';
        }
        $lastTopic = (int) $topicId;
        foreach (array_values($deck['outline'] ?? []) as $i => $t) {
            if ((int) ($t['topic_id'] ?? 0) === (int) $topicId) {
                return '<table class="tb"><tr><td class="tb-n">TOPIC ' . ($i + 1) . '</td><td class="tb-t">' . $this->e((string) ($t['name'] ?? '')) . '</td></tr></table>';
            }
        }

        return '';
    }

    /** One labelled card. Whole on a page: every card is short. */
    private function card(string $kind, string $label, string $text): string
    {
        return '<div class="keep card c-' . $kind . '"><div class="lb lb-' . $kind . '">' . $this->e($label) . '</div><p>' . $this->e($text) . '</p></div>';
    }

    // ---------------------------------------------------------------------------------------------------------
    // Interactions, as static content

    /** @param array<string,mixed> $slide */
    private function interaction(array $slide): string
    {
        $i = $slide['interaction'] ?? null;
        if (!is_array($i) || empty($i['kind'])) {
            return '';
        }
        $kind = (string) $i['kind'];
        $n = (int) $slide['n'];
        $revision = $this->variant === self::REVISION;

        $head = '<div class="ix-h"><span class="ix-tag">INTERACTIVE</span> ' . $this->e(self::HEADING[$kind] ?? 'Explore') . '</div>';
        $intro = $this->intro($i);
        $note = $this->activityNote($slide, self::TRY[$kind] ?? 'Explore this activity.');

        // Each case gives the content as CHUNKS: the first travels with the heading, the rest follows on its own.
        switch ($kind) {
            case 'hotspots':
                $chunks = $this->legend(array_map(fn ($s) => [(string) ($s['label'] ?? ''), (string) ($s['text'] ?? '')], $i['spots'] ?? []));
                break;
            case 'reveal':
                $chunks = $this->legend(array_map(fn ($s) => [(string) ($s['label'] ?? ''), (string) ($s['text'] ?? '')], $i['items'] ?? []));
                break;
            case 'steps':
                $chunks = $this->legend(array_map(fn ($s) => [(string) ($s['label'] ?? ''), (string) ($s['text'] ?? '')], $i['items'] ?? []), 'Step ');
                break;
            case 'timeline':
                $chunks = $this->legend(array_map(fn ($s) => [trim(($s['when'] ?? '') . ' ' . ($s['label'] ?? '')), (string) ($s['text'] ?? '')], $i['items'] ?? []));
                break;
            case 'compare':
                $chunks = [$this->compare($i['items'] ?? [])];
                break;
            case 'match':
                $rows = array_map(fn ($p) => '<tr><td class="tm">' . $this->e((string) ($p['term'] ?? '')) . '</td><td>' . $this->e((string) ($p['meaning'] ?? '')) . '</td></tr>', $i['pairs'] ?? []);
                $chunks = $this->tableOf('<tr><th>Term</th><th>Meaning</th></tr>', $rows);
                break;
            case 'order':
                $steps = array_map(fn ($s, $k) => '<tr><td class="no">' . ($k + 1) . '</td><td>' . $this->e((string) ($s['text'] ?? '')) . '</td></tr>', $i['items'] ?? [], array_keys($i['items'] ?? []));
                $chunks = $this->tableOf('', $steps);
                $intro .= '<div class="note">The correct order:</div>';
                break;
            case 'scenario':
                $chunks = $this->scenario($i, $revision);
                break;
            default:
                $chunks = [];
        }
        $firstChunk = array_shift($chunks);

        $wrap = trim((string) ($i['wrapup'] ?? $i['conclusion'] ?? ''));
        $wrapLabel = $kind === 'compare' ? 'HOW THEY COMPARE' : ($kind === 'scenario' ? 'IN CONCLUSION' : 'PUTTING IT TOGETHER');

        // The heading, its instruction and the start of the content stay together; a long legend runs on.
        return '<div class="keep">' . $head . $intro . ($firstChunk ?? '') . '</div>' . implode('', $chunks) . $note . ($wrap !== '' ? $this->card('exp', $wrapLabel, $wrap) : '');
    }

    /** @param array<string,mixed> $i */
    private function intro(array $i): string
    {
        $text = trim((string) ($i['intro'] ?? $i['situation'] ?? ''));
        // "Select each quantity..." tells a learner to click; on paper it is noise. A scenario's situation is content and stays.
        if (empty($i['situation']) && preg_match('/^(select|open|click|tap|choose|pick|explore|match|put|drag)\b/i', $text)) {
            return '';
        }

        return $text !== '' ? '<div class="note">' . $this->e($text) . '</div>' : '';
    }

    /** The panel that points at this slide in the Study Deck. */
    private function activityNote(array $slide, string $instruction): string
    {
        $url = $this->linkTo((int) $slide['n']);

        return '<div class="keep act"><table><tr><td class="act-i">&#9654;</td><td><span class="act-h">Try it in the Study Deck.</span> ' . $this->e($instruction)
            . ($url !== null ? '<div class="act-u"><a href="' . $this->e($url) . '">' . $this->e($url) . '</a></div>' : '') . '</td></tr></table></div>';
    }

    private function linkTo(int $slide): ?string
    {
        if ($this->linkBase === null) {
            return null;
        }

        return $this->linkBase . '?' . ($this->contentId ? 'content=' . $this->contentId . '&slide=' : 'slide=') . $slide;
    }

    /**
     * A table of rows, as chunks. A short table is one chunk. A long one is its first rows (which travel with the
     * heading above it) and the rest, which runs over the page; its rows are never split.
     *
     * @param array<int,string> $rows
     * @return array<int,string>
     */
    private function tableOf(string $header, array $rows, string $class = 'lgd'): array
    {
        $table = fn (array $r, bool $withHeader) => '<table class="' . $class . '">' . ($withHeader ? $header : '') . implode('', $r) . '</table>';
        if (count($rows) <= self::KEEP_ROWS) {
            return [$table($rows, true)];
        }

        return [$table(array_slice($rows, 0, 2), true), $table(array_slice($rows, 2), false)];
    }

    /**
     * A numbered list of (title, explanation): the static form of "select one to see it".
     *
     * @param array<int,array{0:string,1:string}> $rows
     */
    private function legend(array $rows, string $numberPrefix = ''): array
    {
        $html = [];
        foreach (array_values($rows) as $index => [$label, $text]) {
            $html[] = '<tr><td class="no">' . $this->e($numberPrefix . ($index + 1)) . '</td><td class="tm">' . $this->e($label) . '</td><td>' . $this->e($text) . '</td></tr>';
        }

        return $this->tableOf('', $html);
    }

    /** @param array<int,array<string,mixed>> $items */
    private function compare(array $items): string
    {
        if (!$items) {
            return '';
        }
        $cells = fn (callable $f, string $tag) => implode('', array_map(fn ($it) => "<$tag>" . $f($it) . "</$tag>", $items));

        return '<table class="cmp"><tr>' . $cells(fn ($it) => $this->e((string) ($it['label'] ?? '')), 'th') . '</tr>'
            . '<tr>' . $cells(fn ($it) => $this->e((string) ($it['text'] ?? '')), 'td') . '</tr></table>';
    }

    /**
     * @param array<string,mixed> $i
     * @return array<int,string> one chunk per decision
     */
    private function scenario(array $i, bool $revision): array
    {
        $chunks = [];
        foreach (array_values($i['nodes'] ?? []) as $index => $node) {
            $prompt = '<div class="dec"><strong>Decision ' . ($index + 1) . '.</strong> ' . $this->e((string) ($node['prompt'] ?? '')) . '</div>';
            $header = '<tr><th>Choice</th>' . ($revision ? '<th>What happens</th><th>Why</th>' : '') . '</tr>';
            $rows = [];
            $size = 0;
            foreach ($node['choices'] ?? [] as $ch) {
                $size += mb_strlen((string) ($ch['text'] ?? '')) + ($revision ? mb_strlen((string) ($ch['outcome'] ?? '')) + mb_strlen((string) ($ch['why'] ?? '')) : 0);
                $rows[] = '<tr><td class="tm">' . $this->e((string) ($ch['text'] ?? '')) . ($revision && !empty($ch['sound']) ? '<div class="sound">&#10003; Sound choice</div>' : '') . '</td>'
                    . ($revision ? '<td>' . $this->e((string) ($ch['outcome'] ?? '')) . '</td><td>' . $this->e((string) ($ch['why'] ?? '')) . '</td>' : '') . '</tr>';
            }
            $table = fn (array $r, bool $head) => '<table class="lgd">' . ($head ? $header : '') . implode('', $r) . '</table>';
            if ($size <= self::KEEP_CHARS || count($rows) < 2) {
                // A short decision is one block.
                $chunks[] = $prompt . $table($rows, true);
            } else {
                // A long one: the question with its first choice stay together; the other choices follow, row by row.
                $chunks[] = $prompt . $table(array_slice($rows, 0, 1), true);
                $chunks[] = $table(array_slice($rows, 1), false);
            }
        }

        return $chunks;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Practice questions from the bank

    /** @param array<string,mixed> $deck */
    private function practiceCount(array $deck): int
    {
        $n = 0;
        foreach (StudyDeckQuestions::idsIn($deck) as $id) {
            if (isset($this->questions[$id])) {
                $n++;
            }
        }

        return $n;
    }

    /** @param array<string,mixed> $slide */
    private function practice(array $deck, array $slide): string
    {
        $out = '';
        $shown = [];
        foreach ($slide['activities'] ?? [] as $a) {
            $id = (int) ($a['question_id'] ?? 0);
            if (($a['source'] ?? '') !== 'bank' || !isset($this->questions[$id]) || isset($shown[$id])) {
                continue;
            }
            $shown[$id] = true;
            $out .= $this->question($this->questions[$id], (string) ($a['label'] ?? 'Practice'), (int) $slide['n']);
        }

        return $out;
    }

    /** @param array<string,mixed> $q */
    private function question(array $q, string $label, int $slideNo): string
    {
        $revision = $this->variant === self::REVISION;
        $choice = !empty($q['options']);
        $out = '<div class="qc"><div class="keep"><div class="qc-h"><span class="qc-tag">PRACTICE QUESTION</span> ' . $this->e($label) . '</div>'
            . '<div class="qc-s">' . (new SlideHtmlRenderer())->stem((string) ($q['stem'] ?? '')) . '</div>';

        if ($choice) {
            $out .= '<table class="op">';
            foreach ($q['options'] as $o) {
                $ok = $revision && (string) $o['label'] === (string) $q['correct_label'];
                $out .= '<tr class="' . ($ok ? 'op-ok' : '') . '"><td class="op-l">' . $this->e((string) $o['label']) . '</td><td class="op-t">' . $this->e((string) $o['text']) . ($ok ? ' <span class="op-c">&#10003; Correct</span>' : '') . '</td></tr>';
            }
            $out .= '</table>';
        } elseif (!$revision) {
            $out .= '<div class="rule"></div><div class="rule"></div><div class="rule"></div>';
        }
        $out .= '</div>';

        if ($revision) {
            $answer = '';
            if ($choice) {
                foreach ($q['options'] as $o) {
                    if ((string) $o['label'] === (string) $q['correct_label']) {
                        $answer = $o['label'] . '. ' . $o['text'];
                    }
                }
            } else {
                $answer = (string) ($q['answer_text'] ?? '');
            }
            if ($answer !== '') {
                $out .= '<div class="qa"><span class="qa-l">' . ($choice ? 'ANSWER' : 'MODEL ANSWER') . '</span> ' . $this->e($answer) . '</div>';
            }
            $why = trim((string) ($q['explanation'] ?? ''));
            if ($why !== '' && $why !== $answer) {
                $out .= '<div class="qe"><span class="qa-l">WHY</span> ' . $this->e($why) . '</div>';
            }
        }

        $url = $this->linkTo($slideNo);

        return $out . ($url !== null ? '<div class="keep qc-u">Answer it with feedback in the Study Deck: <a href="' . $this->e($url) . '">slide ' . $slideNo . '</a></div>' : '') . '</div>';
    }

    // ---------------------------------------------------------------------------------------------------------
    // Pictures

    /** @param array<string,mixed> $slide */
    private function figure(array $slide): string
    {
        $img = $slide['image'] ?? null;
        $url = (string) ($img['url'] ?? '');
        if (!is_array($img) || $url === '' || !str_starts_with($url, $this->imageBase)) {
            return '';
        }
        // A diagram is a teaching element: as wide as the page allows, in its own proportions. A tall photo is limited in
        // height so what it illustrates stays beside it.
        $w = max(1, (int) ($img['width'] ?? 1280));
        $h = max(1, (int) ($img['height'] ?? 720));
        $isDiagram = ($img['type'] ?? 'photo') === 'diagram';
        $width = (int) min($isDiagram ? 560 : 440, ($isDiagram ? 300 : 330) * $w / $h);
        $credit = !$isDiagram ? trim((($img['creator'] ?? '') ?: 'Openverse') . ', ' . ($img['licence'] ?? '')) : '';
        $caption = trim(rtrim((string) ($img['caption'] ?? ''), '.') . ($credit !== '' ? '. ' . $credit : ''));

        $src = $this->markedPicture($slide, $url) ?? $url;

        $attribution = '';
        if (!$isDiagram) {
            $attribution = trim((string) (($img['attribution'] ?? '') ?: trim(($img['title'] ?? '') . ' by ' . ($img['creator'] ?? '') . ' (' . ($img['licence'] ?? '') . ')')));
            $attribution .= !empty($img['source_url']) ? ' Source: ' . $img['source_url'] : '';
        }

        return '<div class="keep fig"><img src="' . $this->e($src) . '" width="' . $width . '" alt="' . $this->e((string) ($img['alt'] ?? '')) . '">'
            . ($caption !== '' ? '<div class="cap">' . $this->e($caption) . '</div>' : '')
            . ($attribution !== '' ? '<div class="cr">' . $this->e($attribution) . '</div>' : '') . '</div>';
    }

    /**
     * A hotspot diagram with its parts numbered on the picture, so the legend below can be read against it. Done with GD
     * from the stored picture; where that is not possible the plain picture is used.
     *
     * @param array<string,mixed> $slide
     */
    private function markedPicture(array $slide, string $url): ?string
    {
        $i = $slide['interaction'] ?? null;
        if (!$this->imageBytes || !$this->markerFont || !is_file($this->markerFont) || !is_array($i) || ($i['kind'] ?? '') !== 'hotspots' || empty($i['spots'])) {
            return null;
        }
        if (!function_exists('imagecreatefromstring') || !function_exists('imagettftext')) {
            return null;
        }
        try {
            $bytes = ($this->imageBytes)($url);
            $im = $bytes ? @imagecreatefromstring($bytes) : false;
            if (!$im) {
                return null;
            }
            $W = imagesx($im);
            $H = imagesy($im);
            imagealphablending($im, true);
            imageantialias($im, true);
            $d = (int) max(26, round($W * 0.034));
            $indigo = imagecolorallocate($im, 79, 70, 229);
            $white = imagecolorallocate($im, 255, 255, 255);
            foreach (array_values($i['spots']) as $k => $s) {
                // (x, y) is the box centre, as a share of the picture. The marker sits on the box's top-left corner.
                $cx = (int) round(($s['x'] - $s['w'] / 2) / 100 * $W) + (int) ($d * 0.15);
                $cy = (int) round(($s['y'] - $s['h'] / 2) / 100 * $H) + (int) ($d * 0.15);
                $cx = max($d, min($W - $d, $cx));
                $cy = max($d, min($H - $d, $cy));
                imagefilledellipse($im, $cx, $cy, $d + 6, $d + 6, $white);
                imagefilledellipse($im, $cx, $cy, $d, $d, $indigo);
                $label = (string) ($k + 1);
                $size = $d * 0.42;
                $box = imagettfbbox($size, 0, $this->markerFont, $label);
                $tx = (int) round($cx - ($box[2] - $box[0]) / 2 - $box[0]);
                $ty = (int) round($cy + ($box[1] - $box[7]) / 2 - $box[1]);
                imagettftext($im, $size, 0, $tx, $ty, $white, $this->markerFont, $label);
            }
            ob_start();
            imagepng($im);
            $png = (string) ob_get_clean();
            imagedestroy($im);

            return $png !== '' ? 'data:image/png;base64,' . base64_encode($png) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ---------------------------------------------------------------------------------------------------------

    private function css(): string
    {
        return <<<'CSS'

@page { margin: 58px 42px 54px 42px; }
body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 12px; line-height: 1.5; }
.keep { page-break-inside: avoid; }
p { margin: 0 0 6px; }

/* Cover */
.cv-wrap { page-break-after: always; }
.cv { width: 100%; border-collapse: collapse; margin-top: 6px; }
.cv-main { background: #4338ca; color: #ffffff; padding: 44px 38px 34px; height: 400px; vertical-align: top; border-radius: 14px 14px 0 0; }
.cv-eyebrow { color: #c7d2fe; font-size: 11px; letter-spacing: 2px; font-weight: bold; margin-bottom: 70px; }
.cv-title { color: #ffffff; font-size: 34px; line-height: 1.18; font-weight: bold; margin-bottom: 18px; }
.cv-kind { color: #a5b4fc; font-size: 11px; letter-spacing: 3px; font-weight: bold; margin-bottom: 26px; }
.cv-lede { color: #e0e7ff; font-size: 14px; line-height: 1.55; }
.cv-stats { background: #312e81; padding: 14px 18px; border-radius: 0 0 14px 14px; }
.cv-st { width: 100%; border-collapse: collapse; }
.cv-stat { text-align: center; padding: 4px 6px; }
.cv-n { display: block; color: #ffffff; font-size: 22px; font-weight: bold; }
.cv-l { display: block; color: #c7d2fe; font-size: 9.5px; letter-spacing: 0.5px; }
.cv-how { margin-top: 26px; }
.cv-how-h { color: #3730a3; font-size: 14px; font-weight: bold; margin-bottom: 8px; padding-bottom: 5px; border-bottom: 2px solid #c7d2fe; }
.lg { width: 100%; border-collapse: collapse; }
.lg td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 11.5px; }
.lg-sw { width: 8px; padding: 0 !important; }
.lg-n { width: 24%; font-weight: bold; }
.lg-d { color: #475569; }

/* Contents */
.ct { margin-bottom: 4px; }
.ct2 { width: 100%; border-collapse: collapse; }
.ct2 td { border: 0; padding: 0; }
.ct2-c { width: 49%; vertical-align: top; }
.ct2-g { width: 2%; }
.ct-h { color: #3730a3; font-size: 20px; font-weight: bold; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 3px solid #4f46e5; }
.ct-topic { width: 100%; border-collapse: collapse; margin-top: 9px; }
.ct-no { width: 28px; background: #4f46e5; color: #ffffff; font-weight: bold; text-align: center; padding: 4px 0; }
.ct-name { background: #eef2ff; color: #312e81; font-weight: bold; padding: 4px 10px; font-size: 12px; }
.ct-rows { width: 100%; border-collapse: collapse; }
.ct-rows td { padding: 2px 10px; border-bottom: 1px solid #eef2f7; font-size: 11px; line-height: 1.35; }
.ct-s { text-align: right; color: #64748b; white-space: nowrap; width: 62px; font-size: 10px; }

/* Slides */
.sl { margin-top: 18px; }
.tb { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
.tb-n { width: 78px; background: #1e1b4b; color: #c7d2fe; font-size: 9.5px; letter-spacing: 1.5px; font-weight: bold; padding: 6px 10px; }
.tb-t { background: #312e81; color: #ffffff; font-size: 13px; font-weight: bold; padding: 6px 12px; }
.sh { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
.sh-n { width: 38px; background: #4f46e5; color: #ffffff; font-size: 17px; font-weight: bold; text-align: center; padding: 6px 0; border-radius: 8px 0 0 8px; }
.sh-t { background: #eef2ff; padding: 5px 12px; border-radius: 0 8px 8px 0; }
.sh-e { color: #6366f1; font-size: 8.5px; letter-spacing: 1.5px; font-weight: bold; }
.sh-h { color: #1e1b4b; font-size: 15px; font-weight: bold; line-height: 1.25; }
.sub { color: #64748b; font-size: 10.5px; margin: 0 0 7px; }
.lead { font-size: 13px; margin: 0 0 7px; }
.pts { margin: 0 0 8px 18px; padding: 0; }
.pts li { margin: 0 0 2px; }
.note { color: #475569; font-size: 11px; margin: 0 0 5px; }

/* Cards */
.card { border-left: 4px solid #4f46e5; background: #f8fafc; padding: 7px 12px 4px; margin: 7px 0; border-radius: 0 6px 6px 0; }
.card p { margin: 0 0 4px; font-size: 12px; }
.c-exp { background: #eef2ff; border-left-color: #4f46e5; }
.c-def { background: #eff6ff; border-left-color: #2563eb; }
.c-ex { background: #ecfdf5; border-left-color: #059669; }
.talk { background: #f0f9ff; border-left-color: #0284c7; }
.lb { font-size: 8.5px; letter-spacing: 1.2px; font-weight: bold; margin-bottom: 2px; }
.lb-exp { color: #4338ca; } .lb-def { color: #1d4ed8; } .lb-ex { color: #047857; } .lb-t { color: #0369a1; } .lb-w { color: #b45309; } .lb-r { color: #047857; }
.q { font-size: 12.5px; }
.tps { color: #475569; font-size: 10.5px; }
.ans { font-size: 12px; }
.pair { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 7px 0 7px -6px; }
.pair td { width: 50%; vertical-align: top; padding: 7px 11px; font-size: 12px; }
.wrong { background: #fffbeb; border: 1px solid #fcd34d; border-top: 4px solid #d97706; }
.right { background: #ecfdf5; border: 1px solid #6ee7b7; border-top: 4px solid #059669; }
.key { width: 100%; border-collapse: collapse; margin: 7px 0; }
.key-l { width: 66px; background: #4338ca; color: #ffffff; font-size: 8.5px; letter-spacing: 1.2px; font-weight: bold; padding: 7px 10px; border-radius: 6px 0 0 6px; }
.key-t { background: #e0e7ff; color: #1e1b4b; font-weight: bold; padding: 7px 12px; border-radius: 0 6px 6px 0; font-size: 12px; }

/* Pictures */
.fig { text-align: center; margin: 6px 0 7px; }
.fig img { border: 1px solid #cbd5e1; border-radius: 6px; }
.cap { color: #64748b; font-size: 9.5px; font-style: italic; margin-top: 3px; }
.cr { color: #94a3b8; font-size: 7.5px; margin-top: 1px; }

/* Interactive activities */
.ix-h { color: #5b21b6; font-size: 12px; font-weight: bold; margin: 9px 0 3px; }
.ix-tag { background: #7c3aed; color: #ffffff; font-size: 8px; letter-spacing: 1.2px; padding: 2px 6px; border-radius: 3px; }
.lgd { width: 100%; border-collapse: collapse; margin: 3px 0 6px; }
.lgd th { background: #f5f3ff; color: #5b21b6; font-size: 9.5px; text-align: left; border: 1px solid #ddd6fe; padding: 4px 7px; }
.lgd td { border: 1px solid #e5e7eb; padding: 4px 8px; vertical-align: top; font-size: 11.5px; }
.lgd tr { page-break-inside: avoid; }
.no { width: 40px; color: #6d28d9; font-weight: bold; text-align: center; background: #faf5ff; white-space: nowrap; }
.tm { width: 25%; font-weight: bold; color: #1e293b; background: #faf5ff; }
.sound { color: #047857; font-size: 9.5px; font-weight: bold; }
.dec { margin: 6px 0 3px; font-size: 12px; }
.cmp { width: 100%; border-collapse: collapse; margin: 3px 0 6px; table-layout: fixed; }
.cmp th { background: #f5f3ff; color: #5b21b6; border: 1px solid #ddd6fe; padding: 5px 8px; text-align: left; }
.cmp td { border: 1px solid #e5e7eb; padding: 6px 8px; vertical-align: top; font-size: 11.5px; }
.act { margin: 2px 0 8px; }
.act table { width: 100%; border-collapse: collapse; background: #f5f3ff; border: 1px dashed #a78bfa; }
.act td { padding: 5px 9px; font-size: 10.5px; color: #4c1d95; vertical-align: top; }
.act-i { width: 14px; color: #7c3aed; }
.act-h { font-weight: bold; }
.act-u { font-size: 8.5px; color: #6d28d9; margin-top: 1px; }
.act-u a, .qc-u a { color: #6d28d9; text-decoration: underline; }

/* Practice questions */
.qc { border: 1px solid #cbd5e1; border-top: 4px solid #334155; border-radius: 6px; padding: 7px 12px 6px; margin: 8px 0; background: #ffffff; }
.qc-h { font-size: 11px; font-weight: bold; color: #334155; margin-bottom: 4px; }
.qc-tag { background: #334155; color: #ffffff; font-size: 8px; letter-spacing: 1.2px; padding: 2px 6px; border-radius: 3px; }
.qc-s p { font-size: 12.5px; margin: 0 0 4px; }
.op { width: 100%; border-collapse: collapse; margin: 3px 0 5px; }
.op td { padding: 3px 6px; border-bottom: 1px solid #f1f5f9; font-size: 11.5px; vertical-align: top; }
.op-l { width: 22px; font-weight: bold; color: #4338ca; }
.op-ok td { background: #ecfdf5; }
.op-ok .op-l { color: #047857; }
.op-c { color: #047857; font-weight: bold; font-size: 10px; }
.qa { background: #ecfdf5; border-left: 4px solid #059669; padding: 4px 9px; margin: 4px 0 3px; font-size: 11.5px; }
.qe { background: #f8fafc; border-left: 4px solid #94a3b8; padding: 4px 9px; margin: 0 0 3px; font-size: 11.5px; }
.qa-l { font-size: 8px; letter-spacing: 1.2px; font-weight: bold; color: #475569; }
.qc-u { font-size: 9px; color: #64748b; margin-top: 3px; }
.rule { border-bottom: 1px solid #94a3b8; height: 20px; }
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
