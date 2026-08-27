<?php

namespace App\Domain\AI\Workspace;

/**
 * What the page itself says it is showing.
 *
 * The route tells the workspace which module and record the user is on. It cannot tell
 * it that the list is filtered to Standard 8, that a search for "Patel" is active, that
 * three rows are ticked, or that the collection-rate tile reads 64%. Only the page knows
 * that, so the page sends it.
 *
 * Two rules govern this object, and both come from the same place — none of this is
 * trusted:
 *
 *  1. It is *descriptive only*. Nothing here widens what the caller may read. Every
 *     lookup still runs through the scoped resolvers, exactly as with the route. At
 *     worst a forged snapshot produces irrelevant suggestions.
 *  2. It is *bounded*. A page must not be able to push its entire grid into a prompt,
 *     so every collection is capped and every value is flattened to a scalar here
 *     rather than wherever it is eventually read. Large datasets are the retrieval
 *     layer's job; this is a summary.
 */
final class PageSnapshot
{
    /** Deliberately small. These land in a prompt, and a prompt is not a report. */
    private const MAX_FILTERS = 20;
    private const MAX_METRICS = 12;
    private const MAX_RECORDS = 25;
    private const MAX_ACTIONS = 24;
    private const MAX_TEXT = 200;
    private const MAX_FACETS = 4;
    private const MAX_FACET_VALUES = 8;

    /**
     * @param  array<int, array{key:string, label:string, value:string}>  $filters
     * @param  array<int, array{key:string, label:string, value:string, unit:string|null, trend:string|null}>  $metrics
     * @param  array<int, array{id:mixed, label:string|null, attributes:array<string,scalar>}>  $records
     * @param  array<int, array{key:string, label:string}>  $availableActions
     * @param  array<int, array{key:string, label:string, values:array<int,string>, question:string|null}>  $facets
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $type = null,
        public readonly array $filters = [],
        public readonly ?string $searchQuery = null,
        public readonly array $metrics = [],
        public readonly array $records = [],
        public readonly int $recordCount = 0,
        public readonly array $availableActions = [],
        public readonly array $facets = [],
    ) {
    }

    /**
     * Build from whatever the page sent, discarding anything unrecognised.
     *
     * Permissive about shape on purpose: 56 route folders written by different hands
     * will not agree on a schema, and a page that gets one key wrong should lose that
     * key rather than its whole snapshot.
     */
    public static function fromArray(array $pageData): self
    {
        $records = self::normalizeRecords($pageData['records'] ?? []);

        // A list page usually shows a window onto a larger result set. If it reports
        // the total, that is the number worth talking about — "these 25" is wrong when
        // the filter matched 300.
        $reportedTotal = $pageData['record_count'] ?? $pageData['total'] ?? null;

        return new self(
            title: self::text($pageData['page_title'] ?? $pageData['title'] ?? null),
            type: self::pageType($pageData['page_type'] ?? $pageData['type'] ?? null),
            filters: self::normalizeFilters($pageData['filters'] ?? []),
            searchQuery: self::text($pageData['search_query'] ?? $pageData['search'] ?? null),
            metrics: self::normalizeMetrics($pageData['metrics'] ?? $pageData['kpis'] ?? []),
            records: $records,
            recordCount: is_numeric($reportedTotal) ? max(0, (int) $reportedTotal) : count($records),
            availableActions: self::normalizeActions($pageData['available_actions'] ?? $pageData['actions'] ?? []),
            facets: self::normalizeFacets($pageData['facets'] ?? []),
        );
    }

    public function isEmpty(): bool
    {
        return $this->title === null
            && $this->type === null
            && $this->filters === []
            && $this->searchQuery === null
            && $this->metrics === []
            && $this->records === []
            && $this->availableActions === []
            && $this->facets === [];
    }

    /** True when the page is showing a collection rather than one record. */
    public function hasRecords(): bool
    {
        return $this->recordCount > 0 || $this->records !== [];
    }

    public function isFiltered(): bool
    {
        return $this->filters !== [] || ($this->searchQuery !== null && $this->searchQuery !== '');
    }

    /** "Standard 8, Division A, Term 2" — for a sentence, not a table. */
    public function describeFilters(): ?string
    {
        if ($this->filters === []) {
            return null;
        }

        $parts = array_map(
            fn (array $filter) => $filter['label'] . ': ' . $filter['value'],
            array_slice($this->filters, 0, 6)
        );

        return implode(', ', $parts);
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'type' => $this->type,
            'filters' => $this->filters,
            'search_query' => $this->searchQuery,
            'metrics' => $this->metrics,
            'records' => $this->records,
            'record_count' => $this->recordCount,
            'available_actions' => $this->availableActions,
            'facets' => $this->facets,
        ];
    }

    // ---------------------------------------------------------------- internals

    /**
     * @return array<int, array{key:string, label:string, value:string}>
     */
    private static function normalizeFilters(mixed $filters): array
    {
        if (! is_array($filters)) {
            return [];
        }

        $normalized = [];

        foreach ($filters as $key => $filter) {
            if (count($normalized) >= self::MAX_FILTERS) {
                break;
            }

            // Accept both ["standard" => "8"] and [["key"=>…, "label"=>…, "value"=>…]].
            if (is_scalar($filter)) {
                $value = self::text($filter);

                if ($value === null || ! is_string($key) || self::isUnsetFilter($value)) {
                    continue;
                }

                $normalized[] = ['key' => $key, 'label' => self::humanize($key), 'value' => $value];

                continue;
            }

            if (! is_array($filter)) {
                continue;
            }

            $value = self::text($filter['value'] ?? null);
            $filterKey = self::text($filter['key'] ?? (is_string($key) ? $key : null));

            if ($value === null || $filterKey === null || self::isUnsetFilter($value)) {
                continue;
            }

            $normalized[] = [
                'key' => $filterKey,
                'label' => self::text($filter['label'] ?? null) ?? self::humanize($filterKey),
                'value' => $value,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, array{key:string, label:string, value:string, unit:string|null, trend:string|null}>
     */
    private static function normalizeMetrics(mixed $metrics): array
    {
        if (! is_array($metrics)) {
            return [];
        }

        $normalized = [];

        foreach ($metrics as $key => $metric) {
            if (count($normalized) >= self::MAX_METRICS) {
                break;
            }

            if (is_scalar($metric)) {
                $value = self::text($metric);

                if ($value === null || ! is_string($key)) {
                    continue;
                }

                $normalized[] = [
                    'key' => $key,
                    'label' => self::humanize($key),
                    'value' => $value,
                    'unit' => null,
                    'trend' => null,
                ];

                continue;
            }

            if (! is_array($metric)) {
                continue;
            }

            $value = self::text($metric['value'] ?? null);
            $metricKey = self::text($metric['key'] ?? (is_string($key) ? $key : null));

            if ($value === null || $metricKey === null) {
                continue;
            }

            $normalized[] = [
                'key' => $metricKey,
                'label' => self::text($metric['label'] ?? null) ?? self::humanize($metricKey),
                'value' => $value,
                'unit' => self::text($metric['unit'] ?? null),
                'trend' => self::text($metric['trend'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, array{id:mixed, label:string|null, attributes:array<string,scalar>}>
     */
    private static function normalizeRecords(mixed $records): array
    {
        if (! is_array($records)) {
            return [];
        }

        $normalized = [];

        foreach ($records as $record) {
            if (count($normalized) >= self::MAX_RECORDS) {
                break;
            }

            if (is_scalar($record)) {
                $normalized[] = ['id' => $record, 'label' => null, 'attributes' => []];

                continue;
            }

            if (! is_array($record)) {
                continue;
            }

            $attributes = [];

            foreach ($record as $attribute => $value) {
                if (in_array($attribute, ['id', 'label'], true) || ! is_scalar($value)) {
                    continue;
                }

                if (count($attributes) >= 8) {
                    break;
                }

                $attributes[(string) $attribute] = self::text($value) ?? '';
            }

            $normalized[] = [
                'id' => $record['id'] ?? null,
                'label' => self::text($record['label'] ?? $record['name'] ?? null),
                'attributes' => $attributes,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, array{key:string, label:string}>
     */
    private static function normalizeActions(mixed $actions): array
    {
        if (! is_array($actions)) {
            return [];
        }

        $normalized = [];

        foreach ($actions as $action) {
            if (count($normalized) >= self::MAX_ACTIONS) {
                break;
            }

            if (is_string($action) && $action !== '') {
                $normalized[] = ['key' => $action, 'label' => self::humanize($action)];

                continue;
            }

            if (! is_array($action)) {
                continue;
            }

            $key = self::text($action['key'] ?? $action['action'] ?? null);

            if ($key === null) {
                continue;
            }

            $normalized[] = [
                'key' => $key,
                'label' => self::text($action['label'] ?? null) ?? self::humanize($key),
            ];
        }

        return $normalized;
    }

    /**
     * The choices the page offers, as distinct from the ones currently applied.
     *
     * This is the difference between "explain this filtered view" and "what courses are
     * available for Grade 5?". A filter says what the user has already narrowed to; a
     * facet says what they *could* ask about, which on a catalogue page — where nothing
     * is filtered and nothing is selected — is the only page-specific material there is.
     *
     * `question` lets the page supply its own phrasing, because a generic template
     * cannot know that a grade reads as "Grade 5" and a category reads as
     * "under STEM Resources". Omit it and a serviceable default is used.
     *
     * @return array<int, array{key:string, label:string, values:array<int,string>, question:string|null}>
     */
    private static function normalizeFacets(mixed $facets): array
    {
        if (! is_array($facets)) {
            return [];
        }

        $normalized = [];

        foreach ($facets as $key => $facet) {
            if (count($normalized) >= self::MAX_FACETS) {
                break;
            }

            // Accept ["grade" => ["1","2"]] as well as the fuller labelled form.
            if (is_array($facet) && ! isset($facet['values']) && array_is_list($facet)) {
                $facet = ['key' => is_string($key) ? $key : null, 'values' => $facet];
            }

            if (! is_array($facet)) {
                continue;
            }

            $facetKey = self::text($facet['key'] ?? (is_string($key) ? $key : null));
            $values = $facet['values'] ?? [];

            if ($facetKey === null || ! is_array($values)) {
                continue;
            }

            $cleanValues = [];

            foreach ($values as $value) {
                if (count($cleanValues) >= self::MAX_FACET_VALUES) {
                    break;
                }

                $text = self::text($value);

                // "All" is the absence of a choice, not a choice — a suggestion built
                // from it would read "courses available for Grade All".
                if ($text === null || preg_match('/^(all|any|none|-)$/i', $text)) {
                    continue;
                }

                if (! in_array($text, $cleanValues, true)) {
                    $cleanValues[] = $text;
                }
            }

            if ($cleanValues === []) {
                continue;
            }

            $normalized[] = [
                'key' => $facetKey,
                'label' => self::text($facet['label'] ?? null) ?? self::humanize($facetKey),
                'values' => $cleanValues,
                'question' => self::text($facet['question'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * A filter left at its "everything" setting is not a filter.
     *
     * Dropped here as well as in the browser, because describing an unfiltered
     * catalogue as "filtered by Grade: all" is worse than saying nothing — it makes the
     * assistant sound like it is looking at a narrowed view when it is not.
     */
    private static function isUnsetFilter(string $value): bool
    {
        return (bool) preg_match('/^(all|any|none|-|\*)$/i', $value);
    }

    private static function pageType(mixed $type): ?string
    {
        $value = self::text($type);

        if ($value === null) {
            return null;
        }

        $value = strtolower($value);

        // A closed set, because suggestions branch on it. An unrecognised type is
        // dropped rather than passed through, so a typo degrades to "no page type"
        // instead of silently disabling every type-specific suggestion.
        return in_array($value, ['dashboard', 'list', 'detail', 'form', 'report', 'settings'], true)
            ? $value
            : null;
    }

    private static function text(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        return mb_substr($string, 0, self::MAX_TEXT);
    }

    private static function humanize(string $value): string
    {
        return ucfirst(trim(preg_replace('/[_\-]+/', ' ', $value) ?? $value));
    }
}
