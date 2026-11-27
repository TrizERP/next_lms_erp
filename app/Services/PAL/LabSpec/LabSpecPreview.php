<?php

namespace App\Services\PAL\LabSpec;

/**
 * What a teacher needs to judge a lab before approving it, computed from the specification itself:
 *   - the expected results: for each setting, the measured value of every option with the other settings at their
 *     defaults, so the teacher can see what the simulation will show students;
 *   - the key: the scientific explanation for each setting, next to what the model actually does;
 *   - the extremes: the combinations that give the smallest and largest results.
 * The browser separately runs the real experiment (the same builder students use) in preview mode.
 */
class LabSpecPreview
{
    /**
     * @param  array<string,mixed>  $spec
     * @return array<string,mixed>
     */
    public static function build(array $spec): array
    {
        $unit = (string) ($spec['measures'][0]['unit'] ?? '');
        $defaults = LabSpecEvaluator::effective($spec, array_column($spec['items'], 'id'), []);
        $baseline = LabSpecEvaluator::value($spec, $defaults);

        $explained = [];
        foreach ((array) ($spec['marker']['expected'] ?? []) as $x) {
            $explained[$x['setting'] ?? ''] = $x;
        }

        $settings = [];
        foreach ($spec['items'] as $item) {
            foreach (($item['props'] ?? []) as $p) {
                $rows = [];
                foreach ($p['options'] as $o) {
                    $rows[] = [
                        'value' => (string) $o['value'], 'label' => $o['label'],
                        'result' => round(LabSpecEvaluator::value($spec, array_merge($defaults, [$p['key'] => (string) $o['value']])), 2),
                    ];
                }
                $key = $explained[$p['key']] ?? null;
                $settings[] = [
                    'key' => $p['key'], 'label' => $p['label'], 'item' => $item['name'], 'default' => (string) $p['default'],
                    'options' => $rows,
                    'key_effect' => $key['effect'] ?? null, 'key_direction' => $key['direction'] ?? null,
                    'key_from' => $key['from'] ?? null, 'key_to' => $key['to'] ?? null,
                ];
            }
        }

        $min = null;
        $max = null;
        foreach (LabSpecEvaluator::combinations($spec, 20000) as $combo) {
            $v = LabSpecEvaluator::value($spec, $combo);
            if ($min === null || $v < $min['value']) {
                $min = ['value' => $v, 'settings' => $combo];
            }
            if ($max === null || $v > $max['value']) {
                $max = ['value' => $v, 'settings' => $combo];
            }
        }
        $label = function (array $combo) use ($spec): array {
            $out = [];
            foreach ($spec['items'] as $item) {
                foreach (($item['props'] ?? []) as $p) {
                    foreach ($p['options'] as $o) {
                        if ((string) $o['value'] === ($combo[$p['key']] ?? null)) {
                            $out[$p['label']] = $o['label'];
                        }
                    }
                }
            }

            return $out;
        };

        return [
            'unit' => $unit,
            'measure' => $spec['measures'][0]['label'] ?? '',
            'baseline' => round($baseline, 2),
            'settings' => $settings,
            'smallest' => $min === null ? null : ['value' => round($min['value'], 2), 'settings' => $label($min['settings'])],
            'largest' => $max === null ? null : ['value' => round($max['value'], 2), 'settings' => $label($max['settings'])],
            'concept_focus' => $spec['marker']['concept_focus'] ?? '',
        ];
    }
}
