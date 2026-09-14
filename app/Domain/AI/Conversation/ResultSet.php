<?php

namespace App\Domain\AI\Conversation;

/**
 * The list one turn showed, in the shape the next turn needs to point at it.
 *
 * "Show the details of the first candidate" is only answerable because the previous
 * answer listed candidates in a particular order. Until now the only list the estate
 * remembered was `last_case_list` — the ranked academic-risk cases — so the ordinal
 * follow-up worked on exactly one screen and nowhere else. A user reading admission
 * enquiries, fee defaulters or a class roster got the same list, in the same order, and
 * then had no way to select a row from it.
 *
 * This is that memory, generalised. It is deliberately *not* the tool payload: it holds
 * the position, the identifier and the fields the reader was actually shown, so a later
 * turn can only select something that was on screen. Remembering the whole payload would
 * let a follow-up address a row the user never saw, which is the same class of mistake as
 * resolving a name the answer never printed.
 *
 * Nothing here is module-specific. The noun, the identifier and the title are all read
 * off the rows, so a tool added tomorrow gets ordinal follow-ups on its first day.
 */
final class ResultSet
{
    /** Rows kept for selection. Beyond this a user is scrolling, not pointing. */
    private const MAX_ITEMS = 25;

    /** Keys that make a good heading for a row, most identifying first. */
    private const TITLE_KEYS = [
        'student_name', 'teacher_name', 'full_name', 'name', 'title', 'label',
        'department', 'subject_name', 'standard_name', 'exam_title', 'display_name',
        'enquiry_no', 'enrollment_no',
    ];

    /**
     * @param  array<int, array{position:int, id:int|string|null, title:string, row:array<string, mixed>}>  $items
     */
    public function __construct(
        public readonly string $module,
        public readonly string $tool,
        /** The plural the payload used — "enquiries", "students". */
        public readonly string $noun,
        /** The same noun for one row — "enquiry", "student". */
        public readonly string $singular,
        /** The column that identifies a row, when the rows carry one. */
        public readonly ?string $idField,
        public readonly array $items,
        /** The question that produced this list, for the trace. */
        public readonly string $question,
        /** The tool's own total, which can exceed what was shown. */
        public readonly int $total,
    ) {
    }

    /**
     * Build a set from the rows a tool returned, or null when they cannot be selected from.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function fromRows(
        string $module,
        string $tool,
        string $key,
        array $rows,
        string $question,
        ?int $total = null
    ): ?self {
        $rows = array_values(array_filter($rows, 'is_array'));

        if ($rows === []) {
            return null;
        }

        $singular = self::singularise($key);
        $idField = self::identifyingField($rows, $singular);
        $items = [];

        foreach (array_slice($rows, 0, self::MAX_ITEMS) as $index => $row) {
            $items[] = [
                'position' => $index + 1,
                'id' => $idField !== null && isset($row[$idField]) && is_scalar($row[$idField])
                    ? (is_numeric($row[$idField]) ? (int) $row[$idField] : (string) $row[$idField])
                    : null,
                'title' => self::titleOf($row, $singular, $index + 1),
                'row' => array_filter($row, static fn ($value) => is_scalar($value) || $value === null),
            ];
        }

        return new self(
            module: $module,
            tool: $tool,
            noun: str_replace('_', ' ', $key),
            singular: $singular,
            idField: $idField,
            items: $items,
            question: $question,
            total: $total ?? count($rows),
        );
    }

    /**
     * The row at a 1-based position, or null when the list is shorter than that.
     *
     * @return array<string, mixed>|null
     */
    public function at(int $position): ?array
    {
        return $this->items[$position - 1] ?? null;
    }

    /** The last row, which is what "the last one" means. @return array<string, mixed>|null */
    public function last(): ?array
    {
        return $this->items === [] ? null : $this->items[count($this->items) - 1];
    }

    /**
     * The row whose displayed title the user typed back, matched exactly.
     *
     * Exact only, deliberately: two students called "Abhi" is a school, not a data
     * problem, and merging them on a partial match would select the wrong record while
     * looking like it understood.
     *
     * @return array<string, mixed>|null
     */
    public function byTitle(string $title): ?array
    {
        $needle = self::normalise($title);

        if ($needle === '') {
            return null;
        }

        foreach ($this->items as $item) {
            if (self::normalise((string) $item['title']) === $needle) {
                return $item;
            }
        }

        return null;
    }

    /**
     * A title the question mentions in full, or null.
     *
     * Used for "tell me about Ravi Sharma" where that name was printed a turn earlier.
     * The longest match wins, so a student called "Ravi Sharma" is preferred over one
     * called "Ravi" when the sentence names both.
     *
     * @return array<string, mixed>|null
     */
    public function titleMentionedIn(string $question): ?array
    {
        $haystack = self::normalise($question);
        $best = null;
        $bestLength = 0;

        foreach ($this->items as $item) {
            $title = self::normalise((string) $item['title']);

            if ($title === '' || mb_strlen($title) < 3 || mb_strlen($title) <= $bestLength) {
                continue;
            }

            if (preg_match('/(?<![a-z0-9])' . preg_quote($title, '/') . '(?![a-z0-9])/u', $haystack)) {
                $best = $item;
                $bestLength = mb_strlen($title);
            }
        }

        return $best;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** True when more rows exist than were kept for selection. */
    public function truncated(): bool
    {
        return $this->total > $this->count();
    }

    /**
     * Values a field actually takes across the rows, lowercased and deduplicated.
     *
     * This is what makes "show candidates with new status" a real suggestion rather than
     * a guess: the value came from the rows the reader was just shown.
     *
     * @return array<int, string>
     */
    public function valuesOf(string $field): array
    {
        $values = [];

        foreach ($this->items as $item) {
            $value = $item['row'][$field] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                $values[mb_strtolower(trim((string) $value))] = true;
            }
        }

        return array_keys($values);
    }

    /**
     * Fields at least one row carries a value for.
     *
     * @return array<int, string>
     */
    public function populatedFields(): array
    {
        $fields = [];

        foreach ($this->items as $item) {
            foreach ($item['row'] as $field => $value) {
                if (is_scalar($value) && trim((string) $value) !== '') {
                    $fields[(string) $field] = true;
                }
            }
        }

        return array_keys($fields);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'tool' => $this->tool,
            'noun' => $this->noun,
            'singular' => $this->singular,
            'id_field' => $this->idField,
            'items' => $this->items,
            'question' => $this->question,
            'total' => $this->total,
        ];
    }

    /**
     * Rebuild a set from conversation memory, tolerating a row written by an older build.
     */
    public static function fromArray(mixed $raw): ?self
    {
        if (! is_array($raw) || ! is_array($raw['items'] ?? null) || $raw['items'] === []) {
            return null;
        }

        $items = [];

        foreach (array_values($raw['items']) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $items[] = [
                'position' => (int) ($item['position'] ?? $index + 1),
                'id' => $item['id'] ?? null,
                'title' => (string) ($item['title'] ?? ''),
                'row' => is_array($item['row'] ?? null) ? $item['row'] : [],
            ];
        }

        if ($items === []) {
            return null;
        }

        return new self(
            module: (string) ($raw['module'] ?? 'general'),
            tool: (string) ($raw['tool'] ?? 'a tool'),
            noun: (string) ($raw['noun'] ?? 'records'),
            singular: (string) ($raw['singular'] ?? 'record'),
            idField: is_string($raw['id_field'] ?? null) ? $raw['id_field'] : null,
            items: $items,
            question: (string) ($raw['question'] ?? ''),
            total: (int) ($raw['total'] ?? count($items)),
        );
    }

    // ---------------------------------------------------------------- internals

    /**
     * The column that names a row, ranked rather than guessed.
     *
     * Order matters. Enquiry rows carry both enquiry_id and standard_id, and a rule that
     * took the first id-shaped column would offer to open a class when the user asked
     * for a candidate. So the singular noun of the list decides first, a plain id
     * second, and only then does uniqueness across the rows get a say.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function identifyingField(array $rows, string $singular): ?string
    {
        $named = str_replace(' ', '_', $singular) . '_id';

        if (self::isUsableIdField($rows, $named)) {
            return $named;
        }

        if (self::isUsableIdField($rows, 'id')) {
            return 'id';
        }

        // Nothing was named after the list, so fall back to a column that identifies
        // rows by behaving like one: present everywhere and distinct. With a single row
        // nothing can be distinguished, so nothing is claimed.
        if (count($rows) < 2) {
            return null;
        }

        $distinct = [];

        foreach (array_keys($rows[0]) as $field) {
            $field = (string) $field;

            if ($field !== 'id' && ! str_ends_with($field, '_id')) {
                continue;
            }

            if (! self::isUsableIdField($rows, $field)) {
                continue;
            }

            $values = array_map(static fn (array $row) => (string) $row[$field], $rows);

            if (count(array_unique($values)) === count($values)) {
                $distinct[] = $field;
            }
        }

        return count($distinct) === 1 ? $distinct[0] : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function isUsableIdField(array $rows, string $field): bool
    {
        foreach ($rows as $row) {
            $value = $row[$field] ?? null;

            if (! is_scalar($value) || trim((string) $value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function titleOf(array $row, string $singular, int $position): string
    {
        foreach (self::TITLE_KEYS as $key) {
            if (isset($row[$key]) && is_scalar($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return sprintf('%s %d', ucfirst($singular), $position);
    }

    /**
     * English plural to singular, for the handful of shapes tool payloads use.
     *
     * Kept small on purpose. It only has to produce a word that reads correctly in
     * "the first {singular}", and an unrecognised noun passing through unchanged reads
     * as slightly clumsy rather than as wrong.
     */
    public static function singularise(string $noun): string
    {
        $noun = str_replace('_', ' ', trim($noun));

        /*
         * A qualified plural is singularised on its head, not on its tail.
         *
         * Tool payloads name their collections after the whole phrase — `fees.arrears`
         * returns `students_with_arrears` — and singularising the last word produced
         * "students with arrear", which the composer then read out as "show the details
         * of the first students with arrear". The head noun is the thing there is one
         * of, and the qualifier describes the list rather than the row, so it is dropped
         * here and kept in `noun` where it still reads correctly in the plural.
         *
         * It also restores the identifying column: identifyingField() looks for
         * "{singular}_id", so the head noun turns "students with arrears" into the
         * `student_id` these rows actually carry.
         */
        foreach ([' with ', ' without ', ' of ', ' in ', ' for ', ' by ', ' on ', ' at '] as $preposition) {
            $head = strstr($noun, $preposition, true);

            if ($head !== false && trim($head) !== '') {
                return self::singularise(trim($head));
            }
        }

        return match (true) {
            $noun === '' => 'record',
            str_ends_with($noun, 'ies') => substr($noun, 0, -3) . 'y',
            (bool) preg_match('/(ses|xes|zes|ches|shes)$/i', $noun) => substr($noun, 0, -2),
            str_ends_with($noun, 'ss') => $noun,
            str_ends_with($noun, 's') => substr($noun, 0, -1),
            default => $noun,
        };
    }

    private static function normalise(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(trim($value))) ?? '');
    }
}
