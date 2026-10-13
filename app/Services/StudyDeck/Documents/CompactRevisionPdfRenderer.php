<?php

namespace App\Services\StudyDeck\Documents;

use App\Services\StudyDeck\SlideHtmlRenderer;

/**
 * The PDF of a COMPACT study document: the whole chapter on a few printed pages.
 *
 * It is the study document renderer with a denser page laid on it, not a smaller copy of the long one: the document it
 * draws (`profile: compact`) was WRITTEN short (for revision notes a one-sentence gist, two or three points and the
 * chapter's own definition for every concept), and this class decides how that is set out:
 *
 *   - a title band, then the body of the kind: every concept in the order of the document, two to a row, with the topic
 *     as a tag on the first concept of each (revision notes here; `CompactRemedialPdfRenderer` and
 *     `CompactActivitiesPdfRenderer` lay out their own cards);
 *   - at most two diagrams, side by side, where a picture helps recall;
 *   - a small, representative set of the question bank's own questions, each with its options exactly as the bank has
 *     them, and an answer area that is the SAME height in both copies.
 *
 * The two copies (answers shown / answers hidden) differ only in what is inside the answer areas and in which option is
 * tinted, never in any text that could wrap differently, so both copies paginate identically and a pack that is five
 * pages in one is five pages in the other. Nothing is drawn that the structured document does not hold.
 */
class CompactRevisionPdfRenderer extends StudyDocumentPdfRenderer
{
    /** Bump when the compact layout changes. */
    public const COMPACT_LAYOUT_VERSION = 1;

    /** Longest answer-and-reason text, in characters, the fixed answer area holds (three lines at this width). */
    public const ANSWER_CHARS = 270;

    /** The most diagrams drawn, side by side. */
    public const MAX_DIAGRAMS = 2;

    /** Is this a compact study document (of any kind)? @param array<string,mixed> $deck */
    public static function isCompact(array $deck): bool
    {
        return ($deck['profile'] ?? '') === 'compact' && DocumentKind::tryFrom((string) ($deck['kind'] ?? '')) !== null;
    }

    /**
     * The renderer for a compact document: the one laid out for its kind.
     *
     * @param array<string,mixed> $deck
     */
    public static function for(array $deck, ?callable $imageBytes = null, ?string $markerFont = null): self
    {
        return match ($deck['kind'] ?? '') {
            DocumentKind::Remedial->value => new CompactRemedialPdfRenderer($imageBytes, $markerFont),
            DocumentKind::Activities->value => new CompactActivitiesPdfRenderer($imageBytes, $markerFont),
            default => new self($imageBytes, $markerFont),
        };
    }

    /**
     * The number of pages in PDF bytes drawn by Dompdf: its page objects. Cheap enough to call in a loop, and checked
     * against a real reader in the tests.
     */
    public static function pageCount(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page(?![s\w])#', $pdf);
    }

    /**
     * Can the answer area hold this question's answer and reason? A question that cannot is never chosen for a
     * compact pack: the bank's text is printed whole or not at all, never cut.
     *
     * @param array<string,mixed> $q a normalised bank question
     */
    public static function fits(array $q): bool
    {
        $choice = !empty($q['options']);
        if ($choice && (count($q['options']) < 2 || count($q['options']) > 6)) {
            return false;
        }
        [, $answer, $why] = self::answerOf($q);
        // A choice question is printed with its stored reason (the bank's own rule for one); a short-answer question with its model answer.
        if ($answer === '' || ($choice && $why === '')) {
            return false;
        }
        $longest = $choice ? max(array_map(fn ($o) => mb_strlen((string) $o['text']), $q['options'])) : 0;

        return mb_strlen($answer) + mb_strlen($why) <= self::ANSWER_CHARS
            && mb_strlen(trim((string) ($q['stem'] ?? ''))) <= 240
            && $longest <= 110;
    }

    /**
     * What a question's answer area says: the name of the answer, the answer and the reason (empty when the bank has
     * none, or when it only repeats the answer). A choice question's answer is its correct option; any other
     * question's is the model answer the bank stores.
     *
     * @param array<string,mixed> $q a normalised bank question
     * @return array{0:string,1:string,2:string} [label, answer, reason]
     */
    public static function answerOf(array $q): array
    {
        $why = trim((string) ($q['explanation'] ?? ''));
        if (!empty($q['options'])) {
            $answer = '';
            foreach ($q['options'] as $o) {
                if ((string) $o['label'] === (string) ($q['correct_label'] ?? '')) {
                    $answer = $o['label'] . '. ' . $o['text'];
                }
            }

            return ['ANSWER', $answer, $why];
        }
        $answer = trim((string) ($q['answer_text'] ?? ''));

        return ['MODEL ANSWER', $answer, $why === $answer ? '' : $why];
    }

    /**
     * @param array<string,mixed> $deck the stored compact document
     * @param array{variant?:string, questions?:array<int,array<string,mixed>>} $options
     */
    public function html(array $deck, string $baseCss, array $options = []): string
    {
        $this->variant = ($options['variant'] ?? self::REVISION) === self::PRACTICE ? self::PRACTICE : self::REVISION;
        $this->questions = $options['questions'] ?? [];
        $this->linkBase = null;
        $this->contentId = null;
        $this->kind = DocumentKind::from((string) ($deck['kind'] ?? DocumentKind::RevisionNotes->value));

        $body = $this->cxBand($deck) . $this->cxBody($deck) . $this->cxDiagrams($deck) . $this->cxPractice($deck);

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $baseCss . $this->css() . $this->compactCss()
            . '</style></head><body class="k-rev k-cx">' . $body . '</body></html>';
    }

    // ---------------------------------------------------------------------------------------------------------
    // What a kind says about itself: the three renderers differ only here and in cxBody()

    /** The kind on the title band, and what the copies are called. */
    protected function cxKind(): string
    {
        return 'REVISION NOTES';
    }

    /** The counts on the title band. @param array<string,mixed> $d */
    protected function cxFacts(array $d): string
    {
        $notes = array_filter($d['sections'], fn ($s) => $s['type'] === 'note');

        return count($notes) . ' concepts · ' . count($d['outline']) . ' topics · ' . count($this->cxPlaced($d)) . ' practice questions';
    }

    /** What the title band says about the online version. */
    protected function cxOnline(): string
    {
        return 'The key terms as flashcards, every question with feedback and a checklist are in this document\'s "Try it online" tab.';
    }

    /** The heading over the questions. */
    protected function cxQuestionsTitle(): string
    {
        return 'Practice questions';
    }

    /** The one line under the questions heading, for this copy. */
    protected function cxPracticeNote(): string
    {
        return $this->variant === self::PRACTICE
            ? 'Write each answer in its box. The answers are in the answers-shown copy of this pack.'
            : 'Cover the answer box, answer the question, then check.';
    }

    /** The name of the two copies on the band. */
    protected function cxCopy(): string
    {
        return $this->variant === self::PRACTICE ? 'ANSWERS HIDDEN' : 'ANSWERS SHOWN';
    }

    /** The body between the band and the questions: here, every concept. @param array<string,mixed> $d */
    protected function cxBody(array $d): string
    {
        return $this->cxGrid($d, 'note', fn (array $s) => $this->cxConcept($s));
    }

    // ---------------------------------------------------------------------------------------------------------

    /** @param array<string,mixed> $d */
    protected function cxBand(array $d): string
    {
        return '<table class="cx-t"><tr><td class="cx-tl">'
            . '<div class="cx-eb">' . $this->e(strtoupper(self::runningSubject($d))) . ' · ' . $this->cxKind() . ' · ' . $this->cxCopy() . '</div>'
            . '<div class="cx-ti">' . $this->e((string) $d['chapter']['name']) . '</div>'
            . ((string) $d['lede'] !== '' ? '<div class="cx-le">' . $this->e((string) $d['lede']) . '</div>' : '')
            . '</td><td class="cx-tr"><div class="cx-fa">' . $this->e($this->cxFacts($d)) . '</div>'
            . '<div class="cx-fb">' . $this->e($this->cxOnline()) . '</div></td></tr></table>';
    }

    /**
     * The parts of one type (notes, units), in the order of the document, two to a row. Topics do not break the grid (a
     * topic of three concepts would otherwise leave a half-empty row and a band of its own): the first part of each
     * topic carries the topic as a tag across the top of its cell.
     *
     * @param array<string,mixed> $d
     * @param callable(array<string,mixed>):string $cell the markup of one part
     */
    protected function cxGrid(array $d, string $type, callable $cell): string
    {
        $topicNo = [];
        foreach ($d['outline'] as $i => $t) {
            $topicNo[(int) $t['topic_id']] = [$i + 1, (string) $t['name']];
        }

        $cells = [];
        $last = null;
        foreach ($d['sections'] as $s) {
            if ($s['type'] !== $type) {
                continue;
            }
            $topic = (int) $s['topic_id'];
            $tag = '';
            if ($topic !== $last) {
                [$no, $name] = $topicNo[$topic] ?? [0, ''];
                $tag = '<div class="cx-tp"><span class="cx-tn">TOPIC ' . $no . '</span> ' . $this->e($name) . '</div>';
                $last = $topic;
            }
            $cells[] = $tag . $cell($s);
        }

        $out = '';
        foreach (array_chunk($cells, 2) as $pair) {
            $out .= '<table class="cx-g"><tr><td class="cx-c">' . $pair[0] . '</td><td class="cx-gap"></td><td class="cx-c">' . ($pair[1] ?? '') . '</td></tr></table>';
        }

        return $out;
    }

    /** @param array<string,mixed> $s */
    protected function cxConcept(array $s): string
    {
        $c = $s['content'];
        $out = '<div class="cx-h"><span class="cx-no">' . (int) $s['n'] . '</span>' . $this->e((string) $s['title']) . '</div>'
            . '<div class="cx-s">' . $this->e((string) $c['summary']) . '</div>';
        if ($c['key_points']) {
            $out .= '<ul class="cx-p">' . implode('', array_map(fn ($p) => '<li>' . $this->e((string) $p) . '</li>', $c['key_points'])) . '</ul>';
        }
        if ($c['definition']) {
            // "Term: meaning". Without the colon a term that is also the first word of its meaning reads as a stutter.
            $out .= '<div class="cx-d"><span class="cx-dt">' . $this->e(rtrim((string) $c['definition']['term'], ':.')) . ':</span> ' . $this->e((string) $c['definition']['text']) . '</div>';
        }

        return $out;
    }

    /** At most two diagrams, side by side, plain (their labels are drawn on them). @param array<string,mixed> $d */
    protected function cxDiagrams(array $d): string
    {
        $cells = [];
        foreach ($d['sections'] as $s) {
            $img = $s['image'] ?? null;
            if (!is_array($img) || count($cells) >= self::MAX_DIAGRAMS) {
                continue;
            }
            $picture = $this->picture((string) ($img['url'] ?? ''));
            if ($picture === null) {
                continue;
            }
            $w = max(1, (int) ($img['width'] ?? 1280));
            $h = max(1, (int) ($img['height'] ?? 720));
            $width = (int) min(340, 196 * $w / $h);
            $src = 'data:' . $picture['mime'] . ';base64,' . base64_encode($picture['bytes']);
            $cells[] = '<td class="cx-f"><img src="' . $this->e($src) . '" width="' . $width . '" alt="' . $this->e((string) ($img['alt'] ?? '')) . '">'
                . '<div class="cx-fc">' . $this->e((string) $s['n'] . '. ' . $s['title']) . '</div></td>';
        }
        if ($cells === []) {
            return '';
        }
        if (count($cells) === 1) {
            $cells[] = '<td class="cx-f"></td>';
        }

        return '<div class="keep"><div class="cx-sec">Key diagrams</div><table class="cx-fg"><tr>' . implode('', $cells) . '</tr></table></div>';
    }

    /** The bank questions of the document, in order, each with the note it sits on. @return array<int,array{0:array<string,mixed>,1:array<string,mixed>,2:string}> */
    protected function cxPlaced(array $d): array
    {
        $out = [];
        foreach ($d['sections'] as $s) {
            foreach ($s['activities'] ?? [] as $a) {
                $q = $this->questions[(int) ($a['question_id'] ?? 0)] ?? null;
                if ($q !== null && ($a['source'] ?? '') === 'bank') {
                    $out[] = [$q, $s, (string) ($a['label'] ?? 'Practice')];
                }
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $d */
    protected function cxPractice(array $d): string
    {
        $placed = $this->cxPlaced($d);
        if ($placed === []) {
            return '';
        }
        $note = $this->cxPracticeNote();
        $out = '<div class="keep"><div class="cx-sec">' . $this->e($this->cxQuestionsTitle()) . '</div><div class="cx-sn">' . $this->e($note) . '</div>'
            . $this->cxQuestion($placed[0][0], 1, $placed[0][1], $placed[0][2]) . '</div>';
        foreach (array_slice($placed, 1, null, true) as $i => [$q, $s, $label]) {
            $out .= $this->cxQuestion($q, $i + 1, $s, $label);
        }

        return $out;
    }

    /**
     * One bank question exactly as the bank has it, with a FIXED-HEIGHT answer area: the answer and the reason in the
     * copy that shows them, an empty box to write in in the copy that hides them. Nothing else differs.
     *
     * @param array<string,mixed> $q
     * @param array<string,mixed> $s
     */
    protected function cxQuestion(array $q, int $no, array $s, string $label): string
    {
        $shown = $this->variant === self::REVISION;
        $cells = [];
        foreach (array_values((array) ($q['options'] ?? [])) as $o) {
            $ok = $shown && (string) $o['label'] === (string) $q['correct_label'];
            $cells[] = '<td class="cx-ol' . ($ok ? ' cx-ok' : '') . '">' . $this->e((string) $o['label']) . '</td><td class="cx-ot' . ($ok ? ' cx-ok' : '') . '">' . $this->e((string) $o['text']) . '</td>';
        }
        $rows = '';
        foreach (array_chunk($cells, 2) as $pair) {
            $rows .= '<tr>' . $pair[0] . ($pair[1] ?? '<td class="cx-ol"></td><td class="cx-ot"></td>') . '</tr>';
        }

        [$name, $answer, $why] = self::answerOf($q);
        $box = $shown
            ? '<div class="cx-a"><span class="cx-al">' . $name . '</span> ' . $this->e($answer) . ($why !== '' ? ' <span class="cx-al">WHY</span> ' . $this->e($why) : '') . '</div>'
            : '<div class="cx-a cx-w"><span class="cx-al">YOUR ANSWER</span></div>';

        return '<div class="cx-q keep"><div class="cx-qh"><span class="cx-qn">Q' . $no . '</span> ' . $this->e((string) $s['title']) . ' <span class="cx-ql">' . $this->e($label) . '</span></div>'
            . '<div class="cx-qs">' . (new SlideHtmlRenderer())->stem((string) ($q['stem'] ?? '')) . '</div>'
            . ($rows !== '' ? '<table class="cx-o">' . $rows . '</table>' : '') . $box . '</div>';
    }

    // ---------------------------------------------------------------------------------------------------------

    protected function compactCss(): string
    {
        return <<<'CSS'

@page { margin: 54px 34px 50px 34px; }
body.k-cx { font-size: 10px; line-height: 1.28; color: #1e293b; }
.k-cx p { margin: 0; }

/* Title band */
.cx-t { width: 100%; border-collapse: collapse; margin: 0 0 4px; }
.cx-t td { border: 0; }
.cx-tl { background: #4338ca; color: #ffffff; padding: 7px 14px 6px; }
.cx-eb { color: #c7d2fe; font-size: 8px; letter-spacing: 1.4px; font-weight: bold; }
.cx-ti { color: #ffffff; font-size: 15px; line-height: 1.2; font-weight: bold; margin: 2px 0 2px; }
.cx-le { color: #e0e7ff; font-size: 9.5px; }
.cx-tr { background: #312e81; width: 190px; padding: 6px 11px; vertical-align: top; }
.cx-fa { color: #ffffff; font-size: 9px; font-weight: bold; margin-bottom: 4px; }
.cx-fb { color: #c7d2fe; font-size: 8px; line-height: 1.3; }

/* Topic band and concepts */
.cx-tp { background: #312e81; color: #ffffff; font-size: 9.5px; font-weight: bold; padding: 2px 6px; margin: 0 -7px 3px; }
.cx-tn { color: #a5b4fc; font-size: 7.5px; letter-spacing: 1.2px; margin-right: 4px; }
.cx-g { width: 100%; border-collapse: collapse; table-layout: fixed; page-break-inside: avoid; }
.cx-g td { border: 0; }
.cx-c { width: 49.5%; vertical-align: top; padding: 3px 7px 4px; border-bottom: 1px solid #e2e8f0 !important; }
.cx-gap { width: 1%; }
.cx-h { font-size: 10.5px; font-weight: bold; color: #1e1b4b; margin-bottom: 1px; }
.cx-no { display: inline-block; background: #4f46e5; color: #ffffff; font-size: 8px; padding: 0 4px; margin-right: 5px; border-radius: 3px; }
.cx-s { color: #334155; margin-bottom: 1px; }
.cx-p { margin: 1px 0 1px 11px; padding: 0; }
.cx-p li { margin: 0 0 1px; }
.cx-d { background: #eff6ff; border-left: 3px solid #2563eb; padding: 1px 5px; margin-top: 2px; font-size: 9.5px; }
.cx-dt { color: #1d4ed8; font-weight: bold; }

/* Section headings and diagrams */
.cx-sec { color: #3730a3; font-size: 12px; font-weight: bold; margin: 9px 0 3px; padding-bottom: 2px; border-bottom: 2px solid #c7d2fe; }
.cx-sn { color: #475569; font-size: 9px; margin: 0 0 3px; }
.cx-fg { width: 100%; border-collapse: collapse; table-layout: fixed; }
.cx-f { width: 50%; text-align: center; vertical-align: top; padding: 2px 4px; }
.cx-f img { border: 1px solid #cbd5e1; }
.cx-fc { color: #64748b; font-size: 8.5px; margin-top: 1px; }

/* Questions: the answer area has one fixed height in both copies */
.cx-q { border: 1px solid #cbd5e1; border-top: 3px solid #334155; padding: 3px 7px 4px; margin: 5px 0; }
.cx-qh { font-size: 9px; font-weight: bold; color: #334155; margin-bottom: 2px; }
.cx-qn { background: #334155; color: #ffffff; padding: 0 4px; border-radius: 3px; }
.cx-ql { color: #64748b; font-weight: normal; }
.cx-qs p { font-size: 10px; margin: 0 0 2px; }
.cx-o { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 1px 0 3px; }
.cx-o td { padding: 1px 4px; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-size: 9.5px; }
.cx-ol { width: 14px; font-weight: bold; color: #4338ca; }
.cx-ot { width: 46%; }
.cx-o .cx-ok { background: #ecfdf5; }
.cx-o .cx-ol.cx-ok { color: #047857; }
.cx-a { height: 42px; background: #f8fafc; border-left: 3px solid #94a3b8; padding: 2px 6px; font-size: 9.5px; line-height: 1.28; }
.cx-a.cx-w { background: #ffffff; border: 1px solid #cbd5e1; border-left: 3px solid #cbd5e1; }
.cx-al { font-size: 7.5px; letter-spacing: 1px; font-weight: bold; color: #475569; }
CSS;
    }
}
