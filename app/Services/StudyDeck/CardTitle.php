<?php

namespace App\Services\StudyDeck;

/**
 * Headings for the cards of an interactive slide, derived from what a card actually says. No model is called.
 *
 * A card heading is resolved in this order (the level used is returned, so a weak one can be reported):
 *   1. the source's own short statement of the card (stored intelligence keeps a short `knowledge` line beside the longer
 *      `statement`; when that line is already heading-sized it is the heading);
 *   2. a concise heading compressed from the card's own text, cut at a natural phrase boundary;
 *   3. the concept's name with the kind of knowledge it is ("The rule: <concept>");
 *   4. a neutral description of the concept ("More about <concept>").
 * A numbered placeholder ("Key idea 1", "Card 3", "Item 2") is never produced, and `isGeneric()` recognises one.
 *
 * lms_k12/lib/study-deck/cardTitle.ts holds the same rules for decks stored before this existed; both are checked against the
 * same examples (tests/Fixtures/card-titles.json and lib/study-deck/fixtures/card-titles.json).
 */
final class CardTitle
{
    public const MAX_WORDS = 10;
    public const MIN_WORDS = 2;

    /** Words a heading must not end on. */
    private const TRAILING = ['a', 'an', 'the', 'of', 'to', 'in', 'on', 'at', 'by', 'for', 'with', 'from', 'and', 'or', 'but', 'as', 'that', 'which', 'is', 'are', 'be', 'can', 'may', 'must', 'should', 'will', 'such', 'per', 'than', 'into', 'over', 'using', 'about', 'so', 'when', 'while', 'rather'];

    /** Prepositions: a cut in front of one is refused when it would leave a verb dangling ("... comes | from the Latin word"). */
    private const PREPOSITIONS = ['from', 'to', 'for', 'with', 'by', 'over', 'under', 'as', 'into', 'through', 'across', 'during', 'after', 'before', 'than'];

    /** Words after which a cut would leave the sentence unfinished. */
    private const DANGLING = ['comes', 'goes', 'arises', 'arise', 'depends', 'depend', 'relies', 'results', 'result', 'differs', 'applies', 'moves', 'grows', 'gives', 'leads', 'refers', 'made', 'built', 'used', 'based', 'shaped', 'defined', 'known', 'called', 'found', 'observed', 'expressed', 'described', 'derived', 'related', 'linked', 'connected', 'applied', 'ran', 'run', 'differ', 'vary', 'varies', 'belong', 'belongs', 'come', 'change', 'changes', 'act', 'acts', 'depends'];

    /** What may follow a comma for the part before it to stand alone as a heading. */
    private const CLAUSE_STARTERS = ['so', 'which', 'because', 'while', 'whereas', 'but', 'not', 'although', 'since', 'where', 'thus', 'meaning', 'making', 'helping', 'rather'];

    /** Words a long sentence may be cut in front of. */
    private const BREAK = ['because', 'so', 'which', 'that', 'when', 'while', 'whereas', 'such', 'using', 'by', 'from', 'to', 'for', 'with', 'over', 'under', 'as', 'and', 'or', 'but', 'into', 'through', 'across', 'during', 'after', 'before', 'than', 'can', 'may', 'must', 'should', 'will', 'is', 'are', 'was', 'were', 'helps', 'gives', 'makes', 'provides', 'requires', 'shows', 'allows', 'rather'];

    /** Openers a clause must not be cut off after ("In the rice model, ..."). */
    private const OPENERS = ['in', 'on', 'for', 'when', 'if', 'as', 'at', 'during', 'by', 'with', 'after', 'before', 'while', 'once', 'because', 'although'];

    private const TYPE_PHRASE = [
        'definition' => 'What it means', 'fact' => 'A fact to know', 'process' => 'How it works', 'rule' => 'The rule', 'principle' => 'The principle',
        'relationship' => 'How it connects', 'classification' => 'How it is classified', 'law' => 'The law',
    ];

    /**
     * True for a numbered placeholder: "Key idea 1", "Key points 2", "Point 3", "Item 4", "Card 1", "Idea 2", or a bare number.
     * A meaningful title that merely contains a number ("Newton's 2nd law", "Step 2 of the water cycle") is not generic.
     */
    public static function isGeneric(?string $label): bool
    {
        $s = trim((string) $label);
        if ($s === '') {
            return true;
        }

        return (bool) preg_match('/^(?:key\s+(?:ideas?|points?|takeaways?)|ideas?|points?|items?|cards?|tabs?)\s*[#:.\-–]?\s*\d{1,3}$/iu', $s)
            || (bool) preg_match('/^#?\d{1,3}[.)]?$/u', $s)
            || (bool) preg_match('/^(?:untitled|heading|title)$/iu', $s);
    }

    /**
     * A concise heading for a sentence, or null when it cannot be made reliably.
     *
     * @return string|null at most MAX_WORDS words, sentence case, no final punctuation
     */
    public static function compress(string $text): ?string
    {
        $s = trim((string) preg_replace('/\s+/u', ' ', preg_replace('/\([^)]*\)/u', '', $text)));
        $s = trim($s, " \t\n\r\0\x0B\"'“”‘’");
        $s = rtrim($s, ".!?;: ");
        if ($s === '') {
            return null;
        }

        // A first clause (up to a comma, semicolon, colon or dash) is the heading, unless it is only an opener ("In the rice model,").
        if (preg_match('/^(.+?)\s*(?:[,;:]|\s[—–-]\s)\s*(\S+)/u', $s, $m)) {
            $first = self::words($m[1]);
            $next = mb_strtolower($m[2]);
            $list = (bool) preg_match('/\b(?:such as|for example|including|like)\b/iu', $m[1]);
            if (count($first) >= 3 && !$list && !in_array(mb_strtolower($first[0]), self::OPENERS, true) && (in_array($next, self::CLAUSE_STARTERS, true) || preg_match('/[;:]|\s[—–-]\s/u', $m[0]))) {
                $s = $m[1];
            }
        }

        $w = self::words($s);
        if (count($w) > self::MAX_WORDS) {
            $cut = null;
            for ($i = self::MAX_WORDS; $i >= 5; $i--) {
                $next = isset($w[$i]) ? mb_strtolower($w[$i]) : '';
                if (!in_array($next, self::BREAK, true)) {
                    continue;
                }
                if (in_array($next, self::PREPOSITIONS, true) && in_array(mb_strtolower(rtrim($w[$i - 1], ',;:')), self::DANGLING, true)) {
                    continue;
                }
                $cut = $i;
                break;
            }
            // No break word: cut after the last clause comma that leaves a real phrase, else keep a little more rather than end on a verb.
            if ($cut === null) {
                for ($j = self::MAX_WORDS + 3; $j >= 4; $j--) {
                    if (isset($w[$j]) && str_ends_with($w[$j], ',')) {
                        $cut = $j + 1;
                        break;
                    }
                }
            }
            $w = array_slice($w, 0, $cut ?? min(count($w), self::MAX_WORDS + 4));
            // A cut through a list or a second clause leaves a stub after the last comma ("..., close"): drop it.
            $head = implode(' ', $w);
            if (preg_match('/^(.{12,}),\s*(\S+(?:\s+\S+)?)$/u', $head, $m) && count(self::words($m[1])) >= 3) {
                $w = self::words($m[1]);
            }
        }
        $ragged = array_merge(self::TRAILING, count(self::words($s)) > count($w) ? array_merge(self::DANGLING, ['exactly', 'nearly']) : []);
        while ($w && in_array(mb_strtolower(rtrim(end($w), ',;:')), $ragged, true)) {
            array_pop($w);
        }
        if (count($w) < self::MIN_WORDS) {
            return null;
        }
        $h = rtrim(implode(' ', $w), ',;:');

        return mb_strtoupper(mb_substr($h, 0, 1)) . mb_substr($h, 1);
    }

    /**
     * The heading of one card, and which level of the fallback order produced it (1 best, 4 weakest).
     *
     * @param array{statement?:string,knowledge?:string,type?:string} $card the stored statement, the short stored line and the kind of knowledge
     * @return array{title:string,level:int}
     */
    public static function resolve(array $card, string $concept = '', array $taken = []): array
    {
        $statement = trim((string) ($card['statement'] ?? ''));
        $short = trim((string) ($card['knowledge'] ?? ''));
        $free = fn (?string $t) => $t !== null && $t !== '' && !self::isGeneric($t) && !in_array(self::key($t), $taken, true);

        // 1. the source's own short line, when it is already heading-sized
        if ($short !== '' && count(self::words(rtrim($short, '.'))) <= self::MAX_WORDS && self::key($short) !== self::key($statement)) {
            $t = self::compress($short);
            if ($free($t)) {
                return ['title' => $t, 'level' => 1];
            }
        }
        // 2. compressed from the card's own text (the short line first: it is worded differently from the body)
        foreach ([$short, $statement] as $source) {
            $t = $source !== '' ? self::compress($source) : null;
            if ($free($t)) {
                return ['title' => $t, 'level' => 2];
            }
        }
        // 3. the concept, with the kind of knowledge the card is
        $phrase = self::TYPE_PHRASE[mb_strtolower(trim((string) ($card['type'] ?? '')))] ?? null;
        if ($concept !== '' && $phrase !== null && $free("$phrase: $concept")) {
            return ['title' => "$phrase: $concept", 'level' => 3];
        }
        // 4. neutral
        $t = $concept !== '' ? "More about $concept" : 'More about this idea';
        if (!$free($t)) {
            $t .= ' (continued)';
        }

        return ['title' => $t, 'level' => 4];
    }

    /**
     * Headings for a slide's cards, all different from each other.
     *
     * @param array<int,array<string,mixed>> $cards
     * @return array<int,array{title:string,level:int}>
     */
    public static function resolveAll(array $cards, string $concept = ''): array
    {
        $out = [];
        $taken = [];
        foreach ($cards as $i => $card) {
            $r = self::resolve($card, $concept, $taken);
            $taken[] = self::key($r['title']);
            $out[$i] = $r;
        }

        return $out;
    }

    /** @return array<int,string> */
    private static function words(string $s): array
    {
        return preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private static function key(string $s): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($s)));
    }
}
