<?php

namespace App\Services\Remap\Support;

/**
 * Unicode-safe normalization and tokenization.
 *
 * Deliberately script-aware: the std-10 corpus spans Latin (Science,
 * Maths, English, Social Science) and Devanagari (Hindi-A, Hindi-B,
 * Hindi Grammar). Porter-style suffix stripping corrupts Devanagari, so
 * the light suffix strip is applied ONLY to pure-ASCII-alpha tokens.
 */
class TextNormalizer
{
    /** English stopwords plus question-stem noise that carries no topic signal. */
    private const STOP_EN = [
        'the','and','for','are','but','not','you','all','any','can','had','her','was','one','our','out',
        'day','get','has','him','his','how','man','new','now','old','see','two','way','who','did','its',
        'let','put','say','she','too','use','that','this','with','from','they','have','what','were','when',
        'your','said','each','which','their','will','other','about','many','then','them','these','would',
        'into','more','than','some','could','following','question','answer','write','give','name',
        'define','explain','describe','choose','correct','option','options','mark','marks',
        'given','below','above','also','such','only','very','most','much','being','does','doing','done',
    ];

    /** Romanised Hindi function words found in transliterated question stems. */
    private const STOP_HI_TRANSLIT = [
        'aur','hai','hain','tha','kya','kyo','kyon','kaise','karo','kare','karen','liye','apne',
        'nimn','likhiye','bataiye','uttar','prashn','shabd','arth',
    ];

    /** Devanagari function words. */
    private const STOP_DEV = [
        'और','है','हैं','था','थे','थी','क्या','कैसे','लिए','अपने','निम्न','लिखिए','बताइए','उत्तर','प्रश्न',
        'का','की','के','को','में','से','पर','यह','वह','एक','हो','कर','तथा','अथवा','या','नहीं',
    ];

    /**
     * Strip markup and lowercase. Does not tokenize.
     */
    public static function clean(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        // Decode first, then strip: encoded tags would otherwise survive.
        $text = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $text = str_replace(["\xc2\xa0", "\u{200b}", "\u{feff}"], ' ', $text);
        $text = mb_strtolower($text, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Tokenize into topic-bearing terms.
     *
     * @return string[]
     */
    public static function tokens(?string $raw, int $minLen = 3): array
    {
        $clean = self::clean($raw);
        if ($clean === '') {
            return [];
        }

        $parts = preg_split('/[^\p{L}\p{N}]+/u', $clean, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }

        $stop = self::stopSet();
        $out  = [];

        foreach ($parts as $token) {
            // Pure digits are question numbering or marks, never topic signal.
            if (preg_match('/^\p{N}+$/u', $token)) {
                continue;
            }
            if (mb_strlen($token, 'UTF-8') < $minLen) {
                continue;
            }

            $token = self::stem($token);

            if (mb_strlen($token, 'UTF-8') < $minLen || isset($stop[$token])) {
                continue;
            }

            $out[] = $token;
        }

        return $out;
    }

    /**
     * Light English-only suffix strip, applied solely to ASCII-alpha
     * tokens so Devanagari is never mangled.
     */
    public static function stem(string $token): string
    {
        if (!preg_match('/^[a-z]+$/', $token)) {
            return $token;
        }

        $len = strlen($token);

        foreach (['ies' => 'y', 'ing' => '', 'ers' => 'er', 'es' => '', 'ed' => '', 's' => ''] as $suffix => $repl) {
            $slen = strlen($suffix);
            if ($len - $slen >= 4 && substr($token, -$slen) === $suffix) {
                return substr($token, 0, $len - $slen) . $repl;
            }
        }

        return $token;
    }

    /**
     * Adjacent token bigrams, e.g. "power sharing".
     *
     * @param  string[] $tokens
     * @return string[]
     */
    public static function bigrams(array $tokens): array
    {
        $out = [];
        $n   = count($tokens);

        for ($i = 0; $i + 1 < $n; $i++) {
            $out[] = $tokens[$i] . ' ' . $tokens[$i + 1];
        }

        return $out;
    }

    /**
     * Fraction of letter characters that are Devanagari. Used to gate
     * cross-subject candidates by measured script rather than assumption.
     */
    public static function devanagariRatio(?string $raw): float
    {
        $clean = self::clean($raw);
        if ($clean === '') {
            return 0.0;
        }

        $letters = preg_match_all('/\p{L}/u', $clean);
        if (!$letters) {
            return 0.0;
        }

        return preg_match_all('/\p{Devanagari}/u', $clean) / $letters;
    }

    /**
     * Character trigrams of the cleaned string, for near-duplicate Dice.
     *
     * @return array<string,true>
     */
    public static function trigrams(?string $raw): array
    {
        $clean = self::flatten($raw);
        $len   = mb_strlen($clean, 'UTF-8');

        if ($len < 3) {
            return $clean === '' ? [] : [$clean => true];
        }

        $out = [];
        for ($i = 0; $i + 3 <= $len; $i++) {
            $out[mb_substr($clean, $i, 3, 'UTF-8')] = true;
        }

        return $out;
    }

    /**
     * Sorensen-Dice over character trigram sets.
     */
    public static function diceFromTrigrams(array $a, array $b): float
    {
        $na = count($a);
        $nb = count($b);

        if ($na === 0 || $nb === 0) {
            return 0.0;
        }

        return (2 * count(array_intersect_key($a, $b))) / ($na + $nb);
    }

    /**
     * Token-set Jaccard, used for concept-label equivalence.
     */
    public static function jaccard(?string $a, ?string $b): float
    {
        $ta = array_unique(self::tokens($a, 2));
        $tb = array_unique(self::tokens($b, 2));

        if (!$ta || !$tb) {
            return 0.0;
        }

        $inter = count(array_intersect($ta, $tb));
        $union = count(array_unique(array_merge($ta, $tb)));

        return $union === 0 ? 0.0 : $inter / $union;
    }

    /**
     * similar_text ratio on cleaned strings, truncated to guard against
     * the quadratic blow-up on long inputs.
     */
    public static function similarity(?string $a, ?string $b): float
    {
        $ca = mb_substr(self::clean($a), 0, 255, 'UTF-8');
        $cb = mb_substr(self::clean($b), 0, 255, 'UTF-8');

        if ($ca === '' || $cb === '') {
            return 0.0;
        }
        if ($ca === $cb) {
            return 1.0;
        }

        similar_text($ca, $cb, $percent);

        return $percent / 100;
    }

    /**
     * True when two concept labels denote the same concept.
     */
    public static function labelsMatch(?string $a, ?string $b, float $jaccardMin, float $similarMin): bool
    {
        if ($a === null || $b === null) {
            return false;
        }

        $ca = self::clean($a);
        $cb = self::clean($b);

        if ($ca === '' || $cb === '') {
            return false;
        }
        if ($ca === $cb) {
            return true;
        }

        return self::jaccard($a, $b) >= $jaccardMin
            || self::similarity($a, $b) >= $similarMin;
    }

    /**
     * Collapse whitespace, for verbatim-substring evidence checks.
     */
    public static function flatten(?string $raw): string
    {
        return trim(preg_replace('/\s+/u', ' ', self::clean($raw)));
    }

    /** @return array<string,true> */
    private static function stopSet(): array
    {
        static $set = null;

        if ($set === null) {
            $set = [];
            foreach ([self::STOP_EN, self::STOP_HI_TRANSLIT, self::STOP_DEV] as $list) {
                foreach ($list as $word) {
                    $set[$word] = true;
                    $set[self::stem($word)] = true;
                }
            }
        }

        return $set;
    }
}
