<?php

namespace App\Services\PAL\LabSpec;

/**
 * The deterministic result model of a lab specification.
 *
 *   result = clamp( base × Π term(settings), min, max )
 *
 * A term is one of:
 *   lookup  {setting, table:{option: multiplier}, default}          the multiplier for the chosen option
 *   power   {setting, [per], ref, exp}                              ((x [/ y]) / ref) ^ exp          (formulas such as t ∝ 1/T, T ∝ √L)
 *   exp     {setting, ref, k}                                       e^(k (x − ref))                  (rates that double every so many degrees)
 *   linear  {setting, ref, slope, [floor]}                          max(floor, 1 + slope (x − ref))
 *   interp  {setting, points:[[x, multiplier], …]}                  piecewise linear, held flat beyond the ends (bounded)
 *   rule    {when:[{setting, eq}|{setting, in}], multiply}          the multiplier when every condition holds (rule-based)
 *
 * x is the number the chosen option stands for (its `num`, else its value read as a number). Nothing here is random and
 * nothing depends on the clock: identical settings always give an identical result. The browser has the same evaluator
 * (lib/experiment-builder/spec.ts); both are tested against the same vectors.
 */
class LabSpecEvaluator
{
    public const TERM_TYPES = ['lookup', 'power', 'exp', 'linear', 'interp', 'rule'];

    /**
     * The number an option of a setting stands for.
     *
     * @param  array<string,mixed>  $spec
     */
    public static function num(array $spec, string $setting, string $value): ?float
    {
        foreach ($spec['items'] as $item) {
            foreach ($item['props'] as $p) {
                if ($p['key'] !== $setting) {
                    continue;
                }
                foreach ($p['options'] as $o) {
                    if ((string) $o['value'] === $value) {
                        if (isset($o['num']) && is_numeric($o['num'])) {
                            return (float) $o['num'];
                        }

                        return is_numeric($value) ? (float) $value : null;
                    }
                }
            }
        }

        return null;
    }

    /**
     * One term's multiplier.
     *
     * @param  array<string,mixed>  $spec
     * @param  array<string,mixed>  $term
     * @param  array<string,string>  $s  effective settings
     */
    public static function term(array $spec, array $term, array $s): float
    {
        $type = $term['type'] ?? '';
        if ($type === 'rule') {
            foreach ((array) ($term['when'] ?? []) as $c) {
                $v = $s[$c['setting'] ?? ''] ?? '';
                if (isset($c['eq']) && (string) $c['eq'] !== $v) {
                    return 1.0;
                }
                if (isset($c['in']) && ! in_array($v, array_map('strval', (array) $c['in']), true)) {
                    return 1.0;
                }
            }

            return (float) ($term['multiply'] ?? 1);
        }

        $setting = (string) ($term['setting'] ?? '');
        $value = $s[$setting] ?? '';

        if ($type === 'lookup') {
            $table = (array) ($term['table'] ?? []);

            return (float) ($table[$value] ?? ($term['default'] ?? 1));
        }

        $x = self::num($spec, $setting, $value);
        if ($x === null) {
            return 1.0;
        }

        switch ($type) {
            case 'power':
                if (! empty($term['per'])) {
                    $y = self::num($spec, (string) $term['per'], $s[$term['per']] ?? '');
                    if ($y === null || $y == 0.0) {
                        return 1.0;
                    }
                    $x = $x / $y;
                }
                $ref = (float) ($term['ref'] ?? 1) ?: 1.0;

                return (($x / $ref) ** (float) ($term['exp'] ?? 1));
            case 'exp':
                return exp((float) ($term['k'] ?? 0) * ($x - (float) ($term['ref'] ?? 0)));
            case 'linear':
                return max((float) ($term['floor'] ?? 0.05), 1 + (float) ($term['slope'] ?? 0) * ($x - (float) ($term['ref'] ?? 0)));
            case 'interp':
                $pts = array_values((array) ($term['points'] ?? []));
                usort($pts, fn ($a, $b) => $a[0] <=> $b[0]);
                if ($pts === []) {
                    return 1.0;
                }
                if ($x <= $pts[0][0]) {
                    return (float) $pts[0][1];
                }
                for ($i = 1, $n = count($pts); $i < $n; $i++) {
                    if ($x <= $pts[$i][0]) {
                        $f = ($x - $pts[$i - 1][0]) / (($pts[$i][0] - $pts[$i - 1][0]) ?: 1);

                        return (float) ($pts[$i - 1][1] + $f * ($pts[$i][1] - $pts[$i - 1][1]));
                    }
                }

                return (float) $pts[count($pts) - 1][1];
        }

        return 1.0;
    }

    /**
     * The measured value for effective settings, unrounded.
     *
     * @param  array<string,mixed>  $spec
     * @param  array<string,string>  $s
     */
    public static function value(array $spec, array $s): float
    {
        $m = $spec['model'];
        $v = (float) $m['base'];
        foreach ($m['terms'] as $t) {
            $v *= self::term($spec, $t, $s);
        }
        if (isset($m['min'])) {
            $v = max((float) $m['min'], $v);
        }
        if (isset($m['max'])) {
            $v = min((float) $m['max'], $v);
        }

        return $v;
    }

    /**
     * Every setting for a trial: the student's choice for equipment on the bench, otherwise the default (or the
     * "absent" value for a piece that is not there).
     *
     * @param  array<string,mixed>  $spec
     * @param  array<int,string>  $benchIds
     * @param  array<string,string>  $chosen
     * @return array<string,string>
     */
    public static function effective(array $spec, array $benchIds, array $chosen): array
    {
        $out = [];
        foreach ($spec['items'] as $item) {
            foreach ($item['props'] as $p) {
                $out[$p['key']] = in_array($item['id'], $benchIds, true)
                    ? (string) ($chosen[$p['key']] ?? $p['default'])
                    : (string) ($p['absent'] ?? $p['default']);
            }
        }

        return $out;
    }

    /**
     * Every combination of settings (capped), for the validator and the teacher's results table.
     *
     * @param  array<string,mixed>  $spec
     * @return \Generator<array<string,string>>
     */
    public static function combinations(array $spec, int $cap = 20000): \Generator
    {
        $props = [];
        foreach ($spec['items'] as $item) {
            foreach ($item['props'] as $p) {
                $props[$p['key']] = array_map(fn ($o) => (string) $o['value'], $p['options']);
            }
        }
        $keys = array_keys($props);
        $counts = array_map('count', $props);
        $total = array_product($counts) ?: 0;
        $n = 0;
        // Odometer over the options; stops at the cap so a large spec cannot hang the validator.
        $idx = array_fill(0, count($keys), 0);
        while ($n < min($total, $cap)) {
            $combo = [];
            foreach ($keys as $i => $k) {
                $combo[$k] = $props[$k][$idx[$i]];
            }
            yield $combo;
            $n++;
            for ($i = count($keys) - 1; $i >= 0; $i--) {
                if (++$idx[$i] < $counts[$keys[$i]]) {
                    break;
                }
                $idx[$i] = 0;
            }
        }
    }
}
