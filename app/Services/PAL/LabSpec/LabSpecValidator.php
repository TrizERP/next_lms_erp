<?php

namespace App\Services\PAL\LabSpec;

/**
 * Checks a lab specification before a teacher can approve it (and again when they do).
 *
 * It cannot prove a spec is good science, so it does the part a machine can do and makes the rest the teacher's:
 *   - schema: every required field, the right types, known words only (icons, groups, units, visual hints);
 *   - configuration: every setting has options and a default, every lookup covers every option, every instrument
 *     the measure needs exists, the shelf has distractors, nothing references something that is not there;
 *   - result boundaries: every combination of settings is evaluated, and must be finite, positive, inside the
 *     stated min/max, and differ enough from the others for an experiment to show something;
 *   - the key against the model: for every explanation the marker's key gives ("a warmer place melts it faster") the
 *     model is evaluated and the stated direction must be the one it produces. A key that contradicts its own model
 *     (the usual way an AI draft goes wrong) is an error.
 * Warnings are things a teacher should look at but that do not block approval.
 */
class LabSpecValidator
{
    public const ICONS = [
        'ice', 'container', 'location', 'lid', 'stopwatch', 'thermometer', 'balance', 'magnet', 'torch', 'ruler', 'sugar', 'water',
        'beaker', 'spoon', 'cylinder', 'lens', 'plant', 'pot', 'soil', 'seed', 'lamp', 'battery', 'bulb', 'ball', 'string', 'weight',
        'ramp', 'car', 'flask', 'testtube', 'burner', 'nail', 'salt', 'spring', 'generic',
    ];

    public const GROUPS = ['material', 'container', 'location', 'tool', 'instrument', 'other'];

    public const VISUALS = ['shrink', 'dissolve', 'grow', 'dial'];

    public const TONES = ['clear', 'cream', 'grey', 'brown', 'green', 'blue', 'red'];

    public const GRAINS = ['fine', 'coarse', 'lump'];

    public const UNITS = ['s', 'min', 'h', '°C', 'g', 'kg', 'mL', 'L', 'cm', 'mm', 'm', 'cm/s', 'm/s', 'N', 'V', 'A', 'Hz', '%', 'J', 'W', 'Ω', 'cm³', 'count', 'pH'];

    private const CAP = 20000;

    /**
     * @param  array<string,mixed>  $spec
     * @return array{errors:array<int,string>,warnings:array<int,string>,stats:array<string,mixed>}
     */
    public static function validate(array $spec): array
    {
        $e = [];
        $w = [];
        $stats = [];

        $slug = fn ($v) => is_string($v) && preg_match('/^[a-z][a-z0-9_]{1,31}$/', $v) === 1;
        $text = fn ($v, int $min, int $max) => is_string($v) && mb_strlen(trim($v)) >= $min && mb_strlen($v) <= $max;

        if (($spec['schema'] ?? null) !== 1) {
            $e[] = 'schema: this must be a version 1 lab specification.';
        }
        if (! $slug($spec['lab_key'] ?? null)) {
            $e[] = 'lab_key: use lowercase letters, digits and underscores (2–32 characters), starting with a letter.';
        }
        if (! $text($spec['title'] ?? null, 5, 120)) {
            $e[] = 'title: 5–120 characters.';
        }
        if (($spec['subject'] ?? null) !== 'Science') {
            $e[] = 'subject: only Science labs are supported.';
        }
        $chapters = (array) ($spec['chapter_ids'] ?? []);
        if ($chapters === [] || count($chapters) > 5 || array_filter($chapters, fn ($c) => ! is_int($c) || $c <= 0)) {
            $e[] = 'chapter_ids: one to five chapter ids.';
        }
        $concepts = (array) ($spec['concepts'] ?? []);
        if ($concepts === [] || count($concepts) > 12 || array_filter($concepts, fn ($c) => ! $text($c, 3, 120))) {
            $e[] = 'concepts: name between one and twelve concepts of the chapter this lab assesses.';
        }
        if (isset($spec['scope_level']) && ! in_array($spec['scope_level'], ['concepts', 'chapter'], true)) {
            $e[] = 'scope_level: "concepts" (offered for any scope that touches its concepts) or "chapter" (only when the whole chapter is studied).';
        }
        if (! $text($spec['scenario'] ?? null, 60, 900)) {
            $e[] = 'scenario: a real-life problem of 60–900 characters.';
        } elseif (preg_match('/\b(step\s*\d|first,? (pour|put|take|add)|then (pour|put|add|measure))\b/i', (string) $spec['scenario'])) {
            $e[] = 'scenario: it must state a problem, never a method or set-up.';
        }
        $outcomes = (array) ($spec['outcomes'] ?? []);
        if (count($outcomes) < 3 || count($outcomes) > 6 || array_filter($outcomes, fn ($o) => ! $text($o, 10, 200))) {
            $e[] = 'outcomes: three to six outcomes, each 10–200 characters.';
        }

        $v = (array) ($spec['visual'] ?? []);
        if (! in_array($v['kind'] ?? '', self::VISUALS, true)) {
            $e[] = 'visual.kind: one of '.implode(', ', self::VISUALS).'.';
        }
        if (! is_string($v['emoji'] ?? null) || mb_strlen($v['emoji']) < 1 || mb_strlen($v['emoji']) > 8) {
            $e[] = 'visual.emoji: a short emoji for the material.';
        }
        if (! is_numeric($v['exponent'] ?? null) || $v['exponent'] < 0.5 || $v['exponent'] > 6) {
            $e[] = 'visual.exponent: between 0.5 and 6.';
        }

        // ---- items and settings ----------------------------------------------------------------
        $items = (array) ($spec['items'] ?? []);
        $itemIds = [];
        $propKeys = [];
        $options = [];   // setting => [value => option]
        $numeric = [];   // setting => bool, every option has a number
        if (count($items) < 6 || count($items) > 24) {
            $e[] = 'items: six to twenty-four pieces of equipment (including some that are not needed).';
        }
        foreach ($items as $i => $item) {
            $item = (array) $item;
            $label = 'items['.$i.']';
            $id = $item['id'] ?? null;
            if (! $slug($id) || isset($itemIds[$id])) {
                $e[] = "$label.id: a unique lowercase id.";
                continue;
            }
            $itemIds[$id] = $item;
            $label = "item '$id'";
            if (! $text($item['name'] ?? null, 2, 40)) {
                $e[] = "$label: name 2–40 characters.";
            }
            if (! in_array($item['icon'] ?? '', self::ICONS, true)) {
                $e[] = "$label: icon must be one of the drawings in the library.";
            }
            if (! in_array($item['group'] ?? '', self::GROUPS, true)) {
                $e[] = "$label: group must be one of ".implode(', ', self::GROUPS).'.';
            }
            if (! $text($item['about'] ?? null, 3, 120)) {
                $e[] = "$label: about (what it is) 3–120 characters.";
            }
            foreach ((array) ($item['props'] ?? []) as $p) {
                $p = (array) $p;
                $key = $p['key'] ?? null;
                if (! $slug($key) || isset($propKeys[$key])) {
                    $e[] = "$label: each setting needs a unique lowercase key.";
                    continue;
                }
                $propKeys[$key] = $id;
                if (! $text($p['label'] ?? null, 3, 60)) {
                    $e[] = "setting '$key': label 3–60 characters.";
                }
                $opts = (array) ($p['options'] ?? []);
                if (count($opts) < 2 || count($opts) > 6) {
                    $e[] = "setting '$key': two to six options.";
                    continue;
                }
                $allNum = true;
                foreach ($opts as $o) {
                    $o = (array) $o;
                    $val = (string) ($o['value'] ?? '');
                    if ($val === '' || mb_strlen($val) > 20 || isset($options[$key][$val])) {
                        $e[] = "setting '$key': every option needs a unique value (up to 20 characters).";
                        continue;
                    }
                    if (! $text($o['label'] ?? null, 1, 50)) {
                        $e[] = "setting '$key', option '$val': label 1–50 characters.";
                    }
                    $options[$key][$val] = $o;
                    $allNum = $allNum && (isset($o['num']) ? is_numeric($o['num']) : is_numeric($val));
                    foreach ((array) ($o['vis'] ?? []) as $vk => $vv) {
                        $ok = match ($vk) {
                            'scale' => is_numeric($vv) && $vv >= 0.2 && $vv <= 3,
                            'heat' => is_numeric($vv) && $vv >= -30 && $vv <= 150,
                            'spin' => is_numeric($vv) && $vv >= 0 && $vv <= 1,
                            'tone' => in_array($vv, self::TONES, true),
                            'grains' => in_array($vv, self::GRAINS, true),
                            'cover' => is_bool($vv),
                            default => false,
                        };
                        if (! $ok) {
                            $e[] = "setting '$key', option '$val': vis.$vk is not a valid hint.";
                        }
                    }
                }
                $numeric[$key] = $allNum;
                if (! isset($options[$key][(string) ($p['default'] ?? '')])) {
                    $e[] = "setting '$key': the default must be one of its options.";
                }
                if (isset($p['absent']) && ! isset($options[$key][(string) $p['absent']])) {
                    $e[] = "setting '$key': the \"absent\" value must be one of its options.";
                }
            }
        }

        $groups = array_unique(array_map(fn ($i) => $i['group'] ?? '', $itemIds));
        $essential = (array) ($spec['essential_groups'] ?? []);
        if ($essential === [] || array_diff($essential, self::GROUPS) || array_diff($essential, $groups)) {
            $e[] = 'essential_groups: groups that exist on the shelf, which must be on the bench before anything runs.';
        }
        $irrelevant = (array) ($spec['irrelevant'] ?? []);
        if (count($irrelevant) < 2) {
            $e[] = 'irrelevant: at least two shelf items with nothing to do with the problem, so choosing equipment is a real decision.';
        }
        foreach ($irrelevant as $id) {
            if (! isset($itemIds[$id])) {
                $e[] = "irrelevant: '$id' is not on the shelf.";
            } elseif (! empty($itemIds[$id]['props'] ?? []) || in_array($itemIds[$id]['group'] ?? '', $essential, true)) {
                $e[] = "irrelevant: '$id' cannot be an essential item or have settings.";
            }
        }
        foreach ($itemIds as $id => $item) {
            $reads = $item['reads'] ?? null;
            if ($reads !== null) {
                $kind = $reads['kind'] ?? '';
                if (! in_array($kind, ['clock', 'setting'], true) || ($kind === 'setting' && ! isset($propKeys[$reads['setting'] ?? ''])) || ($item['group'] ?? '') !== 'instrument') {
                    $e[] = "item '$id': reads must be {kind:clock} or {kind:setting, setting:<a setting>}, on an instrument.";
                }
            }
        }

        // ---- measure -----------------------------------------------------------------------------
        $measures = (array) ($spec['measures'] ?? []);
        if (count($measures) !== 1) {
            $e[] = 'measures: exactly one measure (what the experiment measures).';
        } else {
            $m = (array) $measures[0];
            if (! $slug($m['id'] ?? null) || ! $text($m['label'] ?? null, 5, 80)) {
                $e[] = 'measures[0]: an id and a label.';
            }
            if (! in_array($m['unit'] ?? '', self::UNITS, true)) {
                $e[] = 'measures[0].unit: one of '.implode(' ', self::UNITS).'.';
            }
            $inst = $m['instrument'] ?? null;
            if (! isset($itemIds[$inst]) || ($itemIds[$inst]['group'] ?? '') !== 'instrument') {
                $e[] = 'measures[0].instrument: an instrument on the shelf that measures it.';
            } elseif (($itemIds[$inst]['reads']['kind'] ?? '') !== 'clock' && ! isset($itemIds[$inst]['reads'])) {
                $w[] = 'measures[0].instrument has no "reads"; its readout will show nothing while a trial runs.';
            }
            if (isset($m['decimals']) && (! is_int($m['decimals']) || $m['decimals'] < 0 || $m['decimals'] > 3)) {
                $e[] = 'measures[0].decimals: 0–3.';
            }
        }

        // ---- model -------------------------------------------------------------------------------
        $model = (array) ($spec['model'] ?? []);
        $terms = (array) ($model['terms'] ?? []);
        if (! is_numeric($model['base'] ?? null) || $model['base'] <= 0 || $model['base'] > 1.0e7) {
            $e[] = 'model.base: a positive number.';
        }
        if (isset($model['min']) && (! is_numeric($model['min']) || $model['min'] < 0)) {
            $e[] = 'model.min: zero or more.';
        }
        if (isset($model['max']) && (! is_numeric($model['max']) || $model['max'] <= ($model['min'] ?? 0))) {
            $e[] = 'model.max: more than model.min.';
        }
        if ($terms === [] || count($terms) > 12) {
            $e[] = 'model.terms: one to twelve terms.';
        }
        $used = [];
        foreach ($terms as $i => $t) {
            $t = (array) $t;
            $type = $t['type'] ?? '';
            $at = "model.terms[$i] ($type)";
            if (! in_array($type, LabSpecEvaluator::TERM_TYPES, true)) {
                $e[] = "model.terms[$i]: type must be one of ".implode(', ', LabSpecEvaluator::TERM_TYPES).'.';
                continue;
            }
            if ($type === 'rule') {
                if (! is_numeric($t['multiply'] ?? null) || $t['multiply'] <= 0 || $t['multiply'] > 1000) {
                    $e[] = "$at: multiply must be a positive number.";
                }
                foreach ((array) ($t['when'] ?? []) as $c) {
                    $s = $c['setting'] ?? '';
                    if (! isset($options[$s])) {
                        $e[] = "$at: unknown setting '$s'.";
                        continue;
                    }
                    $used[$s] = true;
                    foreach (array_merge(isset($c['eq']) ? [$c['eq']] : [], (array) ($c['in'] ?? [])) as $val) {
                        if (! isset($options[$s][(string) $val])) {
                            $e[] = "$at: '$val' is not an option of '$s'.";
                        }
                    }
                }
                if (empty($t['when'])) {
                    $e[] = "$at: needs at least one condition.";
                }
                continue;
            }
            $s = (string) ($t['setting'] ?? '');
            if (! isset($options[$s])) {
                $e[] = "$at: unknown setting '$s'.";
                continue;
            }
            $used[$s] = true;
            if ($type === 'lookup') {
                $table = (array) ($t['table'] ?? []);
                foreach (array_keys($options[$s]) as $val) {
                    if (! array_key_exists((string) $val, $table)) {
                        $e[] = "$at: missing configuration: the table has no value for option '$val' of '$s'.";
                    }
                }
                foreach ($table as $k => $mult) {
                    if (! isset($options[$s][(string) $k])) {
                        $e[] = "$at: '$k' is not an option of '$s'.";
                    }
                    if (! is_numeric($mult) || $mult <= 0 || $mult > 1000) {
                        $e[] = "$at: every multiplier must be a positive number.";
                    }
                }
                continue;
            }
            if (empty($numeric[$s])) {
                $e[] = "$at: '$s' must have a number for every option (num) to be used in a formula.";
            }
            if ($type === 'power') {
                if (! is_numeric($t['exp'] ?? null) || abs($t['exp']) > 4 || ! is_numeric($t['ref'] ?? null) || $t['ref'] == 0) {
                    $e[] = "$at: ref (not zero) and exp (up to ±4) are required.";
                }
                if (! empty($t['per'])) {
                    if (! isset($options[$t['per']]) || empty($numeric[$t['per']])) {
                        $e[] = "$at: per must be another numeric setting.";
                    } else {
                        $used[$t['per']] = true;
                    }
                }
                foreach ($options[$s] as $o) {
                    if (((float) ($o['num'] ?? $o['value'])) <= 0) {
                        $e[] = "$at: '$s' options must be positive for a power term.";
                        break;
                    }
                }
            } elseif ($type === 'exp') {
                if (! is_numeric($t['k'] ?? null) || abs($t['k']) > 1 || ! is_numeric($t['ref'] ?? null)) {
                    $e[] = "$at: k (up to ±1) and ref are required.";
                }
            } elseif ($type === 'linear') {
                if (! is_numeric($t['slope'] ?? null) || ! is_numeric($t['ref'] ?? null)) {
                    $e[] = "$at: slope and ref are required.";
                }
            } elseif ($type === 'interp') {
                $pts = (array) ($t['points'] ?? []);
                if (count($pts) < 2 || count($pts) > 12) {
                    $e[] = "$at: two to twelve [x, multiplier] points.";
                } else {
                    $xs = array_map(fn ($p) => $p[0] ?? null, $pts);
                    if (array_filter($pts, fn ($p) => ! is_array($p) || count($p) !== 2 || ! is_numeric($p[0]) || ! is_numeric($p[1]) || $p[1] <= 0) || count(array_unique($xs)) !== count($xs)) {
                        $e[] = "$at: points must be [number, positive multiplier] with distinct x.";
                    }
                }
            }
        }
        foreach (array_keys($options) as $s) {
            if (! isset($used[$s])) {
                $w[] = "setting '$s' does not affect the result. That is fine if it is meant to show no effect; otherwise it is a missing configuration.";
            }
        }

        // ---- result boundaries (every combination) ---------------------------------------------------
        if ($e === []) {
            $min = INF;
            $max = -INF;
            $n = 0;
            $clamped = 0;
            $bad = false;
            foreach (LabSpecEvaluator::combinations($spec, self::CAP) as $combo) {
                $raw = (float) $model['base'];
                foreach ($terms as $t) {
                    $raw *= LabSpecEvaluator::term($spec, (array) $t, $combo);
                }
                $val = LabSpecEvaluator::value($spec, $combo);
                $n++;
                if (! is_finite($raw) || $raw <= 0) {
                    $bad = true;
                    break;
                }
                if (abs($val - $raw) > 1e-9 * $raw) {
                    $clamped++;
                }
                $min = min($min, $val);
                $max = max($max, $val);
            }
            $stats = ['combinations' => $n, 'min' => $bad ? null : round($min, 3), 'max' => $bad ? null : round($max, 3), 'clamped' => $clamped];
            if ($bad) {
                $e[] = 'result boundaries: some combination of settings gives a result that is not a positive finite number.';
            } else {
                if ($max > 86400) {
                    $e[] = 'result boundaries: a result is longer than a day, which no student could read.';
                }
                if ($min < 0.5 && ($measures[0]['unit'] ?? '') === 's') {
                    $w[] = 'result boundaries: some results are under half a second.';
                }
                if ($max / max($min, 1e-9) < 1.5) {
                    $e[] = 'result boundaries: the settings change the result by less than 50 %, so an experiment could not show anything.';
                }
                if ($clamped > 0) {
                    $w[] = "result boundaries: $clamped of $n combinations hit the min/max limit and are cut off.";
                }
            }

            // ---- the key against the model ------------------------------------------------------------
            $marker = (array) ($spec['marker'] ?? []);
            $expected = (array) ($marker['expected'] ?? []);
            if (! $text($marker['concept_focus'] ?? null, 20, 400)) {
                $e[] = 'marker.concept_focus: 20–400 characters on the ideas this lab assesses.';
            }
            if (count($expected) < 2) {
                $e[] = 'marker.expected: at least two explanations (the scientific key).';
            }
            $defaults = LabSpecEvaluator::effective($spec, array_keys($itemIds), []);
            $covered = [];
            foreach ($expected as $i => $x) {
                $x = (array) $x;
                $s = (string) ($x['setting'] ?? '');
                if (! isset($options[$s])) {
                    $e[] = "marker.expected[$i]: unknown setting '$s'.";
                    continue;
                }
                $vals = array_keys($options[$s]);
                $from = (string) ($x['from'] ?? $vals[0]);
                $to = (string) ($x['to'] ?? $vals[count($vals) - 1]);
                if (! isset($options[$s][$from]) || ! isset($options[$s][$to]) || $from === $to) {
                    $e[] = "marker.expected[$i]: from and to must be two different options of '$s'.";
                    continue;
                }
                if (! in_array($x['direction'] ?? '', ['increase', 'decrease'], true) || ! $text($x['effect'] ?? null, 20, 400)) {
                    $e[] = "marker.expected[$i]: a direction (increase or decrease) and an explanation of 20–400 characters.";
                    continue;
                }
                $covered[$s] = true;
                $a = LabSpecEvaluator::value($spec, array_merge($defaults, [$s => $from]));
                $b = LabSpecEvaluator::value($spec, array_merge($defaults, [$s => $to]));
                $actual = abs($b - $a) < 1e-9 * max($a, $b) ? 'no change' : ($b > $a ? 'increase' : 'decrease');
                if ($actual !== $x['direction']) {
                    $e[] = "marker.expected[$i]: the key says the result will {$x['direction']} when '$s' goes from '$from' to '$to', but the model gives: $actual. The key and the model disagree.";
                }
            }
            foreach ($used as $s => $_) {
                if (! isset($covered[$s])) {
                    $w[] = "setting '$s' changes the result but the key has no explanation for it.";
                }
            }
        }

        return ['errors' => $e, 'warnings' => $w, 'stats' => $stats];
    }
}
