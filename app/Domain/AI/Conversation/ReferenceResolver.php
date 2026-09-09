<?php

namespace App\Domain\AI\Conversation;

/**
 * Which row of the previous answer the user just pointed at.
 *
 * This is the piece that was missing. The estate could list six admission enquiries and
 * then had no way to read "show the details of the first candidate", because nothing
 * turned an ordinal into a record. The turn fell through to the model planner, the model
 * planner could not invent an enquiry id from the word "first", and the whole thread
 * stopped one question after it started working.
 *
 * Everything here is deterministic, and that is not a stylistic preference. The record a
 * follow-up selects is the record a later turn may update or confirm, so "first" resolving
 * to a different row on a different day is the same defect class as an approval routed by
 * a model. The resolver only ever selects a row the previous answer actually printed, so
 * the worst case is that it declines and the user names the record themselves.
 *
 * Three ways of pointing are recognised, in the order people use them:
 *
 *   1. **A position** — "the first candidate", "the 2nd one", "row 3", "the last".
 *   2. **A name** — the exact title the previous answer displayed.
 *   3. **A pronoun** — "their details", "this candidate", which mean whichever record the
 *      conversation already has open, or the only row there is.
 */
class ReferenceResolver
{
    /** Words that carry a position, mapped to the position they carry. */
    private const ORDINALS = [
        'first' => 1, '1st' => 1, 'second' => 2, '2nd' => 2, 'third' => 3, '3rd' => 3,
        'fourth' => 4, '4th' => 4, 'fifth' => 5, '5th' => 5, 'sixth' => 6, '6th' => 6,
        'seventh' => 7, '7th' => 7, 'eighth' => 8, '8th' => 8, 'ninth' => 9, '9th' => 9,
        'tenth' => 10, '10th' => 10,
    ];

    /**
     * Nouns that may follow a position word without changing what it refers to.
     *
     * The guard matters more than the list. "The first term" and "the first instalment"
     * are positions in something that is not the previous answer, and resolving them to
     * row one would answer a question nobody asked. So a position word only counts when
     * what follows it is a word for a row — either one of these, or the noun the previous
     * answer used for its own rows.
     */
    private const ROW_NOUNS = [
        'one', 'ones', 'record', 'records', 'row', 'rows', 'item', 'items', 'entry',
        'entries', 'result', 'results', 'candidate', 'candidates', 'applicant',
        'applicants', 'person', 'people', 'student', 'students', 'child', 'name',
        'names', 'option', 'listed', 'above', 'there',
    ];

    /** Words that mean "the record we are already talking about". */
    private const PRONOUNS = [
        'this', 'that', 'these', 'those', 'it', 'its', 'their', 'theirs', 'them', 'they',
        'his', 'her', 'hers', 'him', 'she', 'he',
    ];

    /**
     * Resolve what the sentence points at, against what the thread remembers.
     *
     * `explicit` separates the two kinds of pointing, and callers need it. A position or
     * a displayed name identifies a row and could not mean anything else; a bare pronoun
     * is a guess that happens to be right most of the time. Only the first is allowed to
     * overrule what the classifier made of the sentence — see ConversationStore.
     *
     * @param  array<string, mixed>  $memory
     * @return array{
     *   item:array<string, mixed>, set:ResultSet|null, matched_by:string,
     *   position:int|null, explicit:bool
     * }|null
     */
    public function resolve(string $question, array $memory): ?array
    {
        $set = ResultSet::fromArray($memory['last_result_set'] ?? null);
        $selected = is_array($memory['selected_record'] ?? null) ? $memory['selected_record'] : null;

        if ($set !== null) {
            $position = $this->positionIn($question, $set);

            if ($position !== null) {
                $item = $position === -1 ? $set->last() : $set->at($position);

                if ($item !== null) {
                    return [
                        'item' => $item,
                        'set' => $set,
                        'matched_by' => $position === -1
                            ? 'the last row of the previous answer'
                            : sprintf('position %d in the previous answer', $position),
                        'position' => (int) $item['position'],
                        'explicit' => true,
                    ];
                }
            }

            $named = $set->titleMentionedIn($question);

            if ($named !== null) {
                return [
                    'item' => $named,
                    'set' => $set,
                    'matched_by' => 'the name the previous answer displayed',
                    'position' => (int) $named['position'],
                    'explicit' => true,
                ];
            }
        }

        // A pronoun points at whatever is already open. That is the record the thread
        // last selected, and only failing that the single row of a one-row answer —
        // never the first of several, which would silently pick for the user.
        if (! $this->mentionsPronoun($question)) {
            return null;
        }

        if ($selected !== null && is_array($selected['item'] ?? null)) {
            return [
                'item' => $selected['item'],
                'set' => $set,
                'matched_by' => 'the record already open in this conversation',
                'position' => isset($selected['item']['position']) ? (int) $selected['item']['position'] : null,
                'explicit' => false,
            ];
        }

        if ($set !== null && $set->count() === 1) {
            $only = $set->at(1);

            return $only === null ? null : [
                'item' => $only,
                'set' => $set,
                'matched_by' => 'the only row the previous answer returned',
                'position' => 1,
                'explicit' => false,
            ];
        }

        return null;
    }

    /**
     * The subset of the previous answer the sentence describes, or null.
     *
     * "Which candidates have a follow-up date?" and "show enquiries with new status" are
     * questions about the answer already on screen, not new queries — so they are
     * answered from the remembered rows rather than by asking the tool for a filter
     * argument it may not have. That is what makes the suggestion honest: the composer
     * only ever proposes a field and a value it read off those rows, so the follow-up it
     * offers is one this method can always resolve.
     *
     * @param  array<string, mixed>  $memory
     * @return array{
     *   set:ResultSet, field:string, value:string|null, mode:string,
     *   items:array<int, array<string, mixed>>
     * }|null
     */
    public function resolveFilter(string $question, array $memory): ?array
    {
        $set = ResultSet::fromArray($memory['last_result_set'] ?? null);

        if ($set === null || $set->count() < 2) {
            return null;
        }

        $squashed = $this->squash($question);
        $normalised = $this->normalise($question);
        $asksForPresence = (bool) preg_match('/\b(have|has|having|with a|with an|carry|carries)\b/i', $question);

        foreach ($set->populatedFields() as $field) {
            if ($field === $set->idField) {
                continue;
            }

            // The *field* is matched on squashed text, so "follow-up date", "followup
            // date" and "followup_date" all find the same column.
            if (! str_contains($squashed, $this->squash($field))) {
                continue;
            }

            // The *value* is matched on whole words, which is the opposite treatment and
            // deliberately so. Squashing a value would let a grade of "D" match the "d"
            // inside "standard" — and a length floor to prevent that threw away every
            // single-character value there is, which is most grades and most class names.
            // Longest first, so "not enrolled" is preferred over "enrolled".
            $values = $set->valuesOf($field);
            usort($values, static fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

            foreach ($values as $value) {
                $pattern = '/(?<![a-z0-9])' . preg_quote($value, '/') . '(?![a-z0-9])/u';

                if (preg_match($pattern, $normalised)) {
                    return $this->filtered($set, $field, $value, 'equals');
                }
            }

            if ($asksForPresence) {
                return $this->filtered($set, $field, null, 'present');
            }
        }

        return null;
    }

    /**
     * True when the sentence asks to see one record in more depth.
     *
     * Kept separate from resolution because the two facts are independent: "show the
     * first candidate" points and asks, "and Ravi Sharma?" points without asking, and
     * "what else can you tell me?" asks without pointing.
     */
    public function asksForDetail(string $question): bool
    {
        return (bool) preg_match(
            '/\b(detail|details|full record|complete record|more about|tell me about|information|'
            . 'info|profile|everything|open|view|show me|show|display|expand|look at|see)\b/i',
            $question
        );
    }

    // ---------------------------------------------------------------- internals

    /**
     * Apply one predicate to the remembered rows.
     *
     * Returns null when nothing matches. An empty subset is not a filter anybody asked
     * for — it is a question the previous answer already refutes — and reporting it as a
     * resolution would produce "0 enquiries with new status" from a list where the word
     * "new" never appeared.
     *
     * @return array<string, mixed>|null
     */
    private function filtered(ResultSet $set, string $field, ?string $value, string $mode): ?array
    {
        $items = [];

        foreach ($set->items as $item) {
            $held = $item['row'][$field] ?? null;
            $present = is_scalar($held) && trim((string) $held) !== '';

            $keep = $mode === 'present'
                ? $present
                : $present && mb_strtolower(trim((string) $held)) === $value;

            if ($keep) {
                $items[] = $item;
            }
        }

        if ($items === []) {
            return null;
        }

        return [
            'set' => $set,
            'field' => $field,
            'value' => $value,
            'mode' => $mode,
            'items' => $items,
        ];
    }

    /** Letters and digits only, so "follow-up date" and "followup_date" compare equal. */
    private function squash(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower($value)) ?? '';
    }

    /**
     * The position the sentence names, -1 for "the last", or null.
     *
     * Digit forms are read first because "#2" and "row 2" are unambiguous, then the
     * ordinal words, which need the noun guard described on ROW_NOUNS.
     */
    private function positionIn(string $question, ResultSet $set): ?int
    {
        $normalised = $this->normalise($question);

        // "#3", "no. 3", "number 3", "row 3", "item 3", "record 3", "option 3"
        if (preg_match('/(?:\bnumber|\bno\.?|\brow|\bitem|\brecord|\bentry|\boption|#)\s*#?(\d{1,2})\b/i', $question, $m)) {
            $position = (int) $m[1];

            if ($position >= 1 && $position <= $set->count()) {
                return $position;
            }
        }

        $nouns = $this->rowNounsFor($set);

        foreach (self::ORDINALS as $word => $position) {
            // Capture the word that follows, so "the first candidate" resolves and "the
            // first term" does not. An ordinal at the end of the sentence — "show me the
            // first" — has nothing following it and is accepted.
            $pattern = '/(?<![a-z0-9])' . preg_quote($word, '/') . '(?![a-z0-9])\s*([a-z\']+)?/i';

            if (! preg_match($pattern, $normalised, $m)) {
                continue;
            }

            $following = isset($m[1]) ? mb_strtolower($m[1]) : '';

            if ($following === '' || in_array($following, $nouns, true)) {
                return $position <= $set->count() ? $position : null;
            }
        }

        if (preg_match('/(?<![a-z])(last|final|bottom)(?![a-z])\s*([a-z\']+)?/i', $normalised, $m)) {
            $following = isset($m[2]) ? mb_strtolower($m[2]) : '';

            if ($following === '' || in_array($following, $nouns, true)) {
                return -1;
            }
        }

        return null;
    }

    /**
     * The words that may follow a position, including the ones this answer used itself.
     *
     * @return array<int, string>
     */
    private function rowNounsFor(ResultSet $set): array
    {
        $own = [];

        foreach ([$set->singular, $set->noun] as $noun) {
            foreach (explode(' ', mb_strtolower($noun)) as $word) {
                if ($word !== '') {
                    $own[] = $word;
                }
            }
        }

        return array_values(array_unique([...self::ROW_NOUNS, ...$own]));
    }

    private function mentionsPronoun(string $question): bool
    {
        $normalised = $this->normalise($question);

        foreach (self::PRONOUNS as $pronoun) {
            if (preg_match('/(?<![a-z])' . preg_quote($pronoun, '/') . '(?![a-z])/i', $normalised)) {
                return true;
            }
        }

        // "the candidate", "the student" with no name is the same act of pointing.
        return (bool) preg_match(
            '/\bthe\s+(candidate|applicant|student|enquiry|record|person|one)\b/i',
            $normalised
        );
    }

    private function normalise(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['’', '‘'], "'", $value);
        $value = preg_replace('/[^\p{L}\p{N}\'\-\s#]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
