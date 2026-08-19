<?php

namespace App\Services\PAL\Coherence\Concerns;

/**
 * The one tokeniser the coherence matchers share.
 *
 * ChapterAligner and ConceptTagger BOTH decide what matches what. If they
 * tokenise differently they disagree about the same pair of strings, and the
 * symptom is a chapter that aligns but whose content then tags to nothing. One
 * implementation, two stopword lists (each class supplies its own via
 * stopwords()), is what keeps them honest.
 */
trait TokenisesTitles
{
    /**
     * Words this matcher ignores. Supplied by the using class because what is
     * decorative differs: a chapter title is padded with narration
     * ("Orienting Yourself"), a content title with format nouns ("Revision
     * Notes.pdf").
     *
     * @return array<int, string>
     */
    abstract protected function stopwords(): array;

    /**
     * Tokenise BOTH ways a hyphen can be read.
     *
     * "Co-ordinate Geometry" must match "The Use of Coordinates". Splitting on
     * the hyphen yields {ordinate, geometry} and matches nothing; deleting it
     * yields {coordinate, geometry} and matches. Which reading is right depends
     * on the word, so both are emitted and the intersection decides. The same
     * pattern covers Sub-topic, Re-arrange, Non-linear.
     *
     * @return array<int, string>
     */
    protected function tokenise(string $value): array
    {
        $lower = strtolower($value);
        $stopwords = $this->stopwords();

        $variants = [
            // hyphen as a word break
            preg_replace('/[^a-z0-9\s]+/i', ' ', $lower) ?? '',
            // hyphen as a join
            preg_replace('/[^a-z0-9\s]+/i', '', str_replace([' - ', '-'], ['  ', ''], $lower)) ?? '',
        ];

        $kept = [];

        foreach ($variants as $normalised) {
            foreach (preg_split('/\s+/', trim($normalised), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                // Single and double characters are variable names, list markers
                // or the tail of a split hyphenation ("co"), never subject terms.
                if (strlen($word) < 3 || in_array($word, $stopwords, true)) {
                    continue;
                }

                $kept[] = $this->stem($word);
            }
        }

        return array_values(array_unique($kept));
    }

    /**
     * Crude suffix stripping - enough to make "fractions"/"fraction" and
     * "expressions"/"expression" one token, which covers the overwhelming
     * majority of near-misses in this corpus.
     *
     * THE 'es' RULE IS THE SUBTLE ONE. Stripping a blanket "es" turns
     * "coordinates" into "coordinat" while "coordinate" stays whole, so the two
     * forms of the same word never match - which is precisely how
     * "Co-ordinate Geometry" failed to pair with "The Use of Coordinates".
     * English only doubles the vowel after a sibilant (boxes, matches,
     * classes), so "es" is stripped only there and everything else loses just
     * the "s".
     */
    protected function stem(string $word): string
    {
        // identities -> identity
        if (strlen($word) > 5 && str_ends_with($word, 'ies')) {
            return substr($word, 0, -3) . 'y';
        }

        // exploring -> explor
        if (strlen($word) > 5 && str_ends_with($word, 'ing')) {
            return substr($word, 0, -3);
        }

        if (strlen($word) > 4 && str_ends_with($word, 'es')) {
            $beforeEs = $word[strlen($word) - 3];

            // boxes -> box, matches -> match, classes -> class
            if (in_array($beforeEs, ['s', 'x', 'z', 'c', 'h'], true)) {
                return substr($word, 0, -2);
            }

            // coordinates -> coordinate, variables -> variable
            return substr($word, 0, -1);
        }

        // numbers -> number
        if (strlen($word) > 3 && str_ends_with($word, 's')) {
            return substr($word, 0, -1);
        }

        return $word;
    }
}
