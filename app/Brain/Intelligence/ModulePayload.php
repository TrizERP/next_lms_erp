<?php

namespace App\Brain\Intelligence;

/**
 * The one place a module's intelligence payload is checked against the contract
 * the screen actually reads.
 *
 * ── WHY THIS EXISTS ─────────────────────────────────────────────────────────
 *
 * `lms_k12/components/intelligence/module/payload.ts` is the contract. Result
 * was written against it directly and conforms. The eleven modules generated
 * after it drifted in small ways that a PHP array will never complain about and
 * that React renders as silence rather than an error:
 *
 *   - `ruleStatus` emitted `{rule, label, status, detail}`. The panel reads
 *     `key`, `checked` and `raised`, so EVERY rule in those modules rendered as
 *     "not run" — the screen claimed no check had been performed against a
 *     year whose checks had all just run. That is the exact dishonesty this
 *     layer exists to prevent.
 *
 *   - `confidence` emitted a bare float. `ConfidencePill` reads
 *     `confidence.band`, so every finding displayed "Medium" regardless of what
 *     the rule actually concluded.
 *
 *   - `dataQuality` emitted a bare list of checks instead of
 *     `{available, reason, checks[]}`. `available` came back undefined, so the
 *     section rendered "Record checks are unavailable" while holding the checks.
 *
 *   - `coverage` omitted `sources` and `counts`, the two fields that let copy
 *     cite what it was computed from.
 *
 * ── WHAT THIS IS NOT ────────────────────────────────────────────────────────
 *
 * IT IS NOT A SECOND PAYLOAD FORMAT. Nothing here invents a figure, softens a
 * reason or fills a null with a zero. It maps a near-miss field name onto the
 * canonical one, supplies the structural defaults the contract declares as
 * nullable, and drops a finding that arrived without evidence — because
 * `payload.ts` rule 3 says an assertion with no figures behind it is a caption,
 * not a finding.
 *
 * Every module controller returns `ModulePayload::normalize([...])`, and
 * `tests/Feature/Brain/ModuleIntelligenceContractTest.php` asserts the result
 * against the same contract, so a module that drifts again fails a test rather
 * than rendering a lie.
 */
final class ModulePayload
{
    /** Severity words the screen knows how to colour. Anything else becomes `info`. */
    private const SEVERITIES = ['critical', 'high', 'medium', 'low', 'info'];

    private const VALUE_FORMATS = [
        'currency', 'currencyExact', 'count', 'percent', 'decimal', 'duration', 'text',
    ];

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public static function normalize(array $payload): array
    {
        $syear = $payload['academicYear']['syear'] ?? null;
        $coverage = self::coverage((array) ($payload['coverage'] ?? []), $syear);

        return [
            'tenantId' => (string) ($payload['tenantId'] ?? ''),
            'organization' => (string) ($payload['organization'] ?? ''),
            'source' => (string) ($payload['source'] ?? ''),
            'academicYear' => ['syear' => $syear === null ? null : (string) $syear],
            'coverage' => $coverage,
            'freshness' => [
                'positionLabel' => (string) ($payload['freshness']['positionLabel'] ?? 'Read live at page load'),
                'findingsRefreshedAt' => $payload['freshness']['findingsRefreshedAt'] ?? null,
                'findingsLabel' => (string) ($payload['freshness']['findingsLabel'] ?? 'Computed on this request'),
            ],
            'execution' => [
                'automated' => (bool) ($payload['execution']['automated'] ?? false),
                'note' => (string) ($payload['execution']['note'] ?? ''),
            ],
            'summary' => self::summary($payload['summary'] ?? null, $coverage),
            'position' => self::position($payload['position'] ?? null, $coverage),
            'breakdowns' => self::breakdowns((array) ($payload['breakdowns'] ?? [])),
            'findings' => self::findings((array) ($payload['findings'] ?? []), $syear),
            'priorities' => self::priorities((array) ($payload['priorities'] ?? []), $syear),
            'recommendations' => array_values((array) ($payload['recommendations'] ?? [])),
            'decisionTrail' => array_values((array) ($payload['decisionTrail'] ?? [])),
            'learning' => self::learning($payload['learning'] ?? null),
            'dataQuality' => self::dataQuality($payload['dataQuality'] ?? null, $coverage),
            'ruleStatus' => self::ruleStatus((array) ($payload['ruleStatus'] ?? [])),
        ] + (isset($payload['extras']) ? ['extras' => $payload['extras']] : []);
    }

    /* ------------------------------------------------------------- coverage */

    /**
     * L0. `sources` and `counts` are what the copy below cites, so a module that
     * only reported `{sourceTable, totalRows, usableRows}` is translated into
     * them verbatim rather than being given invented keys.
     *
     * @param  array<string,mixed>  $raw
     * @return array<string,mixed>
     */
    private static function coverage(array $raw, mixed $syear): array
    {
        $sources = (array) ($raw['sources'] ?? []);
        $counts = (array) ($raw['counts'] ?? []);

        if ($sources === [] && isset($raw['sourceTable'])) {
            $table = (string) $raw['sourceTable'];
            $total = (int) ($raw['totalRows'] ?? 0);
            $usable = (int) ($raw['usableRows'] ?? $total);
            $sources = [$table => $total > 0];
            $counts = ['rows' => $total, 'usableRows' => $usable] + $counts;
        }

        return [
            'available' => (bool) ($raw['available'] ?? false),
            'reason' => $raw['reason'] ?? null,
            'syear' => $syear === null ? null : (string) $syear,
            'sources' => array_map(static fn ($v) => (bool) $v, $sources),
            'counts' => array_map(static fn ($v) => (int) $v, $counts),
        ];
    }

    /* -------------------------------------------------------------- summary */

    /** @return array<string,mixed> */
    private static function summary(mixed $raw, array $coverage): array
    {
        if (! is_array($raw)) {
            return [
                'available' => false,
                'reason' => $coverage['reason'] ?? 'This module did not compose a summary for this year.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = array_values(array_filter(
            array_map(static fn ($s) => trim((string) $s), (array) ($raw['sentences'] ?? [])),
            static fn ($s) => $s !== '',
        ));

        return [
            'available' => (bool) ($raw['available'] ?? ($sentences !== [])),
            'reason' => $raw['reason'] ?? null,
            'headline' => isset($raw['headline']) && $raw['headline'] !== '' ? (string) $raw['headline'] : null,
            'sentences' => $sentences,
        ];
    }

    /* ------------------------------------------------------------- position */

    /** @return array<string,mixed>|null */
    private static function position(mixed $raw, array $coverage): ?array
    {
        if (! is_array($raw)) {
            return ['available' => false, 'reason' => $coverage['reason'] ?? null, 'metrics' => []];
        }

        $metrics = [];
        foreach ((array) ($raw['metrics'] ?? []) as $metric) {
            $metric = (array) $metric;
            if (! isset($metric['key'])) {
                continue;
            }
            $metrics[] = [
                'key' => (string) $metric['key'],
                'label' => (string) ($metric['label'] ?? $metric['key']),
                // NEVER coerced to 0 — a missing figure is unknown, and the
                // formatter renders unknown as an em dash.
                'value' => self::numberOrNull($metric['value'] ?? null),
                'format' => self::format($metric['format'] ?? null),
                'display' => isset($metric['display']) ? (string) $metric['display'] : null,
                'hint' => isset($metric['hint']) ? (string) $metric['hint'] : null,
                'tone' => self::tone($metric['tone'] ?? null),
                'currency' => $metric['currency'] ?? null,
            ];
        }

        return [
            'available' => (bool) ($raw['available'] ?? ($metrics !== [])),
            'reason' => $raw['reason'] ?? null,
            'metrics' => $metrics,
        ];
    }

    /* ----------------------------------------------------------- breakdowns */

    /**
     * @param  array<int,mixed>  $raw
     * @return array<int,array<string,mixed>>
     */
    private static function breakdowns(array $raw): array
    {
        $out = [];
        foreach ($raw as $index => $breakdown) {
            $breakdown = self::translateBreakdownDialect((array) $breakdown);
            $columns = [];
            foreach ((array) ($breakdown['columns'] ?? []) as $column) {
                $column = (array) $column;
                if (! isset($column['key'])) {
                    continue;
                }
                $columns[] = [
                    'key' => (string) $column['key'],
                    'label' => (string) ($column['label'] ?? $column['key']),
                    'format' => self::format($column['format'] ?? null),
                    'currency' => $column['currency'] ?? null,
                ];
            }

            $rows = [];
            foreach ((array) ($breakdown['rows'] ?? []) as $rowIndex => $row) {
                $row = (array) $row;
                $values = [];
                foreach ((array) ($row['values'] ?? []) as $k => $v) {
                    $values[(string) $k] = self::numberOrNull($v);
                }
                $rows[] = [
                    'key' => (string) ($row['key'] ?? "row-{$rowIndex}"),
                    'label' => (string) ($row['label'] ?? ''),
                    'values' => $values,
                    'tone' => self::tone($row['tone'] ?? null),
                    'note' => isset($row['note']) ? (string) $row['note'] : null,
                ];
            }

            $out[] = [
                'key' => (string) ($breakdown['key'] ?? "breakdown-{$index}"),
                'label' => (string) ($breakdown['label'] ?? ''),
                'description' => isset($breakdown['description']) ? (string) $breakdown['description'] : null,
                'available' => (bool) ($breakdown['available'] ?? ($rows !== [])),
                'reason' => $breakdown['reason'] ?? null,
                'columns' => $columns,
                'primaryColumn' => $breakdown['primaryColumn'] ?? null,
                'rows' => $rows,
            ];
        }

        return $out;
    }

    /**
     * Admissions, Communication and Inventory describe a breakdown as a TITLE
     * and a list of ITEMS — `{name, value, percentage, status, detail}` — rather
     * than as columns and rows.
     *
     * TRANSLATION, NOT INVENTION. Every figure below already exists in those
     * payloads. Without this they arrived with no `rows` key at all, so three
     * modules rendered "Nothing to break down for this year" over four thousand
     * parent inquiries, five thousand SMS and a full admissions funnel — the
     * data was computed, sent, and thrown away by the renderer because it could
     * not find it.
     *
     * @param  array<string,mixed>  $breakdown
     * @return array<string,mixed>
     */
    private static function translateBreakdownDialect(array $breakdown): array
    {
        if (! isset($breakdown['items']) || isset($breakdown['rows'])) {
            return $breakdown;
        }

        $items = (array) $breakdown['items'];

        $breakdown['label'] ??= (string) ($breakdown['title'] ?? $breakdown['key'] ?? '');
        $breakdown['columns'] = [
            ['key' => 'count', 'label' => 'Count', 'format' => 'count'],
            ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
        ];
        $breakdown['primaryColumn'] = 'count';
        $breakdown['available'] = $items !== [];
        $breakdown['reason'] ??= $items === []
            ? 'This module reported no rows for this slice.'
            : null;

        $rows = [];
        foreach ($items as $index => $item) {
            $item = (array) $item;
            $name = (string) ($item['name'] ?? "Row {$index}");
            $rows[] = [
                'key' => substr(md5($name), 0, 12),
                'label' => $name,
                'values' => [
                    'count' => $item['value'] ?? null,
                    // A percentage that was never supplied stays unknown; it is
                    // not recomputed here, because this layer does not know what
                    // the denominator was meant to be.
                    'share' => $item['percentage'] ?? null,
                ],
                'note' => isset($item['detail']) ? (string) $item['detail'] : null,
                'tone' => $item['status'] ?? null,
            ];
        }
        $breakdown['rows'] = $rows;

        return $breakdown;
    }

    /* ------------------------------------------------------------- findings */

    /**
     * @param  array<int,mixed>  $raw
     * @return array<int,array<string,mixed>>
     */
    private static function findings(array $raw, mixed $syear): array
    {
        $out = [];
        foreach ($raw as $finding) {
            $normalized = self::finding((array) $finding, $syear);
            // payload.ts rule 3: nothing reaches `findings` without evidence.
            if ($normalized !== null) {
                $out[] = $normalized;
            }
        }

        return $out;
    }

    /** @return array<string,mixed>|null */
    private static function finding(array $f, mixed $syear): ?array
    {
        $f = self::translateDialect($f);
        $evidence = self::evidence($f['evidence'] ?? []);
        if ($evidence === []) {
            return null;
        }

        $severity = self::severity($f['severity'] ?? null);
        $whyItMatters = isset($f['whyItMatters']) ? trim((string) $f['whyItMatters']) : '';

        // A rule that described its impact in prose rather than as a figure has
        // no Impact to render: `{value, display, label}` is a number and its
        // noun. The prose is kept — appended to "why it matters", where it was
        // always a consequence sentence — rather than dropped or forced into a
        // shape that would make the screen print "undefined undefined".
        $impact = self::impact($f['impact'] ?? null);
        if ($impact === null && is_string($f['impact'] ?? null)) {
            $prose = trim($f['impact']);
            if ($prose !== '' && ! str_contains($whyItMatters, $prose)) {
                $whyItMatters = $whyItMatters === '' ? $prose : $whyItMatters.' '.$prose;
            }
        }

        return [
            'id' => (string) ($f['id'] ?? 'finding-'.substr(md5(json_encode($f) ?: ''), 0, 12)),
            'severity' => $severity,
            'severityLabel' => (string) ($f['severityLabel'] ?? ucfirst($severity)),
            'title' => (string) ($f['title'] ?? ''),
            'whatHappened' => (string) ($f['whatHappened'] ?? ''),
            'whyItMatters' => $whyItMatters === '' ? null : $whyItMatters,
            'evidence' => $evidence,
            'likelyCause' => isset($f['likelyCause']) && $f['likelyCause'] !== '' ? (string) $f['likelyCause'] : null,
            'causeConfirmed' => (bool) ($f['causeConfirmed'] ?? false),
            'recommendation' => isset($f['recommendation']) && $f['recommendation'] !== ''
                ? (string) $f['recommendation']
                : null,
            'owner' => (string) ($f['owner'] ?? 'Unassigned'),
            'priority' => (string) ($f['priority'] ?? $severity),
            'confidence' => self::confidence($f['confidence'] ?? null),
            'affected' => self::affected($f['affected'] ?? null),
            'raisedAt' => (string) ($f['raisedAt'] ?? now()->toIso8601String()),
            'impact' => $impact,
            'syear' => $f['syear'] ?? ($syear === null ? null : (string) $syear),
            'status' => (string) ($f['status'] ?? 'open'),
            // Kept so evidence stays traceable to the rule that raised it.
            'rule' => isset($f['rule']) ? (string) $f['rule'] : null,
        ];
    }

    /**
     * Admissions, Communication and Inventory were generated from an earlier
     * template that named the same ideas differently — `summary` for what
     * happened, `cause` for the unconfirmed cause, `affectedCount` for the
     * count, and evidence as a `{key: value}` map rather than labelled points.
     *
     * TRANSLATION, NOT INVENTION. Every field below already exists in those
     * payloads; this only gives it the name the contract uses. Without it the
     * evidence map has no `label` on any entry, so those findings were dropped
     * by the no-evidence rule and the modules rendered zero findings while their
     * rules were firing.
     *
     * @param  array<string,mixed>  $f
     * @return array<string,mixed>
     */
    private static function translateDialect(array $f): array
    {
        if (isset($f['summary']) && ! isset($f['whatHappened'])) {
            $f['whatHappened'] = $f['summary'];
        }
        if (isset($f['cause']) && ! isset($f['likelyCause'])) {
            $f['likelyCause'] = $f['cause'];
        }
        if (isset($f['ruleId']) && ! isset($f['rule'])) {
            $f['rule'] = $f['ruleId'];
        }
        if (isset($f['affectedCount']) && ! isset($f['affected'])) {
            $f['affected'] = ['count' => $f['affectedCount'], 'total' => null, 'unit' => null];
        }

        // `['totalApplications' => 836]` → `[['label' => 'Total applications',
        // 'value' => '836']]`. A list of points is left exactly as it is.
        $evidence = $f['evidence'] ?? null;
        if (is_array($evidence) && $evidence !== [] && ! array_is_list($evidence)) {
            $points = [];
            foreach ($evidence as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                $points[] = [
                    'label' => self::humanize((string) $key),
                    'value' => is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value,
                ];
            }
            $f['evidence'] = $points;
        }

        return $f;
    }

    /** `unrepliedRate` → `Unreplied rate`. */
    private static function humanize(string $key): string
    {
        $spaced = trim(preg_replace('/(?<!^)[A-Z]/', ' $0', str_replace('_', ' ', $key)) ?? $key);

        return ucfirst(strtolower($spaced));
    }

    /**
     * @param  array<int,mixed>  $raw
     * @return array<int,array<string,mixed>>
     */
    private static function priorities(array $raw, mixed $syear): array
    {
        $out = [];
        foreach ($raw as $p) {
            $p = (array) $p;
            $evidence = self::evidence($p['evidence'] ?? []);
            if ($evidence === []) {
                continue;
            }
            $severity = self::severity($p['severity'] ?? null);
            $out[] = [
                'id' => (string) ($p['id'] ?? ''),
                'severity' => $severity,
                'severityLabel' => (string) ($p['severityLabel'] ?? ucfirst($severity)),
                'title' => (string) ($p['title'] ?? ''),
                'whatHappened' => (string) ($p['whatHappened'] ?? ''),
                'whyItMatters' => isset($p['whyItMatters']) && $p['whyItMatters'] !== ''
                    ? (string) $p['whyItMatters']
                    : null,
                'evidence' => $evidence,
                'impact' => self::impact($p['impact'] ?? null),
                'nextStep' => isset($p['nextStep']) && $p['nextStep'] !== '' ? (string) $p['nextStep'] : null,
                'owner' => (string) ($p['owner'] ?? 'Unassigned'),
                'confidence' => self::confidence($p['confidence'] ?? null),
            ];
        }

        return $out;
    }

    /* ---------------------------------------------------------- rule status */

    /**
     * Which checks ran, and which fired.
     *
     * `checked` is true for any rule the module reported at all: a rule appears
     * in this list because `run()` executed it. `raised` is the older `status`
     * word — 'fired' — read correctly, so a panel that used to say "not run" for
     * every rule now says what actually happened.
     *
     * @param  array<int,mixed>  $raw
     * @return array<int,array<string,mixed>>
     */
    private static function ruleStatus(array $raw): array
    {
        $out = [];
        foreach ($raw as $rule) {
            $rule = (array) $rule;
            $key = (string) ($rule['key'] ?? $rule['rule'] ?? '');
            if ($key === '') {
                continue;
            }
            $status = strtolower((string) ($rule['status'] ?? ''));
            $out[] = [
                'key' => $key,
                'label' => (string) ($rule['label'] ?? $key),
                'checked' => (bool) ($rule['checked'] ?? ($status !== '' && $status !== 'skipped' && $status !== 'not_run')),
                'raised' => (bool) ($rule['raised'] ?? in_array($status, ['fired', 'raised'], true)),
            ];
        }

        return $out;
    }

    /* --------------------------------------------------------- data quality */

    /** @return array<string,mixed> */
    private static function dataQuality(mixed $raw, array $coverage): array
    {
        if ($raw === null) {
            return [
                'available' => false,
                'reason' => $coverage['reason'] ?? 'This module did not report record checks for this year.',
                'checks' => [],
            ];
        }

        // A bare list of checks is the pre-contract shape. Wrap it rather than
        // letting `available` come back undefined and the section claim the
        // checks were never run.
        $isList = is_array($raw) && ! array_key_exists('available', $raw) && ! array_key_exists('checks', $raw);
        $checksRaw = $isList ? $raw : (array) ($raw['checks'] ?? []);

        $checks = [];
        foreach ($checksRaw as $check) {
            $check = (array) $check;
            // Communication names its checks `id` and describes them with
            // `label`/`detail`; the contract wants `key`/`label`/`note`. Without
            // the fallback every one of its checks was dropped and the section
            // reported "unavailable" over checks that had all run.
            $key = $check['key'] ?? $check['id'] ?? null;
            if ($key === null) {
                continue;
            }
            $state = strtolower((string) ($check['state'] ?? $check['status'] ?? 'ok'));
            $checks[] = [
                'key' => (string) $key,
                'label' => (string) ($check['label'] ?? $check['key']),
                'value' => self::numberOrNull($check['value'] ?? $check['count'] ?? null),
                'format' => self::format($check['format'] ?? null),
                'secondary' => isset($check['secondary']) && is_array($check['secondary'])
                    ? [
                        'value' => self::numberOrNull($check['secondary']['value'] ?? null),
                        'format' => self::format($check['secondary']['format'] ?? null),
                        'currency' => $check['secondary']['currency'] ?? null,
                    ]
                    : null,
                'sharePercent' => self::numberOrNull($check['sharePercent'] ?? null),
                'shareLabel' => isset($check['shareLabel']) ? (string) $check['shareLabel'] : null,
                // 'fail' and 'error' belong on the attention side. Omitting
                // them silently turned a failed check into a clear one.
                'state' => in_array($state, ['attention', 'warning', 'fired', 'fail', 'failed', 'error'], true)
                    ? 'attention'
                    : 'ok',
                'note' => (string) ($check['note'] ?? $check['description'] ?? $check['detail'] ?? ''),
            ];
        }

        // A block with nothing to show must say WHY in words. `null` here would
        // render as the deliberately-vague fallback in `Unavailable`, which is a
        // bug report rather than an explanation.
        $noChecksReason = $coverage['reason']
            ?? 'This module has not declared any record checks, so nothing has been verified about the rows behind '
                .'the figures above. That is not the same as the records being clean.';

        if ($isList) {
            return [
                'available' => $checks !== [],
                'reason' => $checks === [] ? $noChecksReason : null,
                'checks' => $checks,
            ];
        }

        $available = (bool) ($raw['available'] ?? ($checks !== []));

        return [
            'available' => $available,
            'reason' => $available ? ($raw['reason'] ?? null) : ($raw['reason'] ?? $noChecksReason),
            'checks' => $checks,
        ];
    }

    /* ------------------------------------------------------------- learning */

    /** @return array<string,mixed> */
    private static function learning(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [
                'available' => false,
                'reason' => 'The decision loop is not wired for this module, so nothing has been learnt from it yet.',
                'entries' => [],
            ];
        }

        return [
            'available' => (bool) ($raw['available'] ?? false),
            'reason' => $raw['reason'] ?? null,
            'entries' => array_values((array) ($raw['entries'] ?? [])),
        ];
    }

    /* ------------------------------------------------------------- scalars */

    /**
     * @param  mixed  $raw
     * @return array<int,array<string,mixed>>
     */
    private static function evidence(mixed $raw): array
    {
        $out = [];
        foreach ((array) $raw as $point) {
            $point = (array) $point;
            $label = trim((string) ($point['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $out[] = [
                'label' => $label,
                'value' => (string) ($point['value'] ?? ''),
                'note' => isset($point['note']) && $point['note'] !== '' ? (string) $point['note'] : null,
            ];
        }

        return $out;
    }

    /**
     * Confidence always travels with its word — "High", never a bare 0.85.
     *
     * @return array{band:string,value:float}
     */
    private static function confidence(mixed $raw): array
    {
        if (is_array($raw) && isset($raw['band'])) {
            return [
                'band' => (string) $raw['band'],
                'value' => round((float) ($raw['value'] ?? 0.0), 2),
            ];
        }

        $value = is_numeric($raw) ? (float) $raw : 0.5;
        $band = $value >= 0.8 ? 'High' : ($value >= 0.55 ? 'Medium' : 'Low');

        return ['band' => $band, 'value' => round($value, 2)];
    }

    /** @return array{value:?float,display:string,label:string}|null */
    private static function impact(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        if (! isset($raw['display'], $raw['label'])) {
            return null;
        }

        return [
            'value' => self::numberOrNull($raw['value'] ?? null),
            'display' => (string) $raw['display'],
            'label' => (string) $raw['label'],
        ];
    }

    /** @return array{count:?int,total:?int,unit:?string} */
    private static function affected(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];

        return [
            'count' => isset($raw['count']) && is_numeric($raw['count']) ? (int) $raw['count'] : null,
            'total' => isset($raw['total']) && is_numeric($raw['total']) ? (int) $raw['total'] : null,
            'unit' => isset($raw['unit']) && $raw['unit'] !== '' ? (string) $raw['unit'] : null,
        ];
    }

    private static function severity(mixed $raw): string
    {
        $key = strtolower(trim((string) $raw));

        return in_array($key, self::SEVERITIES, true) ? $key : 'info';
    }

    private static function format(mixed $raw): string
    {
        $key = (string) $raw;

        return in_array($key, self::VALUE_FORMATS, true) ? $key : 'count';
    }

    /** Presentation tone, or null. An unknown word is dropped, never guessed at. */
    private static function tone(mixed $raw): ?string
    {
        $key = strtolower(trim((string) $raw));
        $known = [
            'critical', 'high', 'medium', 'low', 'positive',
            'neutral', 'info', 'warning', 'attention', 'good',
        ];

        return in_array($key, $known, true) ? $key : null;
    }

    /** NULL IS NOT ZERO. An absent or non-numeric figure stays unknown. */
    private static function numberOrNull(mixed $value): int|float|null
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        $number = $value + 0;

        return is_float($number) ? round($number, 4) : $number;
    }
}
