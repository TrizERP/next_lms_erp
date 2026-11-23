<?php

namespace App\Services\PAL\ApplicationLab;

use App\Services\PAL\LabSpec\LabSpecEvaluator;
use App\Services\PAL\LabSpec\LabSpecRepository;

/**
 * The server's view of an experiment model, now built from a lab SPECIFICATION (see LabSpec\*), not from code.
 *
 * Nothing in this class is about ice, sugar or any particular chapter. It turns an approved spec into the shape
 * ExperimentBuilderService works with (items, settings, measure, essentials, the marker's key) and evaluates the
 * spec's result model. The browser shows the simulation; the server decides what counts: it recomputes every trial's
 * result from the settings the student chose, and keeps the marker's key, which is never sent to the browser.
 *
 * `$modelId` is the lab's key. A design remembers the spec VERSION it ran on, so it is marked against that version
 * even if a newer one has been approved since.
 */
class ExperimentModelCatalogue
{
    /** @return array<string,array<string,mixed>> the built-in labs, by key (kept for callers that list models) */
    public static function all(): array
    {
        $out = [];
        foreach (LabSpecRepository::builtins() as $key => $_) {
            $out[$key] = self::get($key);
        }

        return array_filter($out);
    }

    /** @return array<string,mixed>|null */
    public static function get(string $id, ?int $version = null, int $subInstituteId = 0): ?array
    {
        $lab = LabSpecRepository::byKey($id, $version, $subInstituteId);

        return $lab === null ? null : self::fromSpec($lab);
    }

    /** The approved lab for a chapter, or null (then the guided form is used). */
    public static function forChapter(int $chapterId, int $subInstituteId = 0): ?array
    {
        $lab = LabSpecRepository::approvedForChapter($chapterId, $subInstituteId);

        return $lab === null ? null : self::fromSpec($lab);
    }

    /**
     * @param  array<string,mixed>  $lab  a LabSpecRepository record
     * @return array<string,mixed>
     */
    public static function fromSpec(array $lab): array
    {
        $spec = $lab['spec'];
        $items = [];
        foreach ($spec['items'] as $item) {
            $props = [];
            foreach ($item['props'] as $p) {
                $opts = [];
                foreach ($p['options'] as $o) {
                    $opts[(string) $o['value']] = $o['label'];
                }
                $props[$p['key']] = ['label' => $p['label'], 'options' => $opts, 'default' => (string) $p['default']]
                    + (isset($p['absent']) ? ['absent' => (string) $p['absent']] : []);
            }
            $items[$item['id']] = ['group' => $item['group'], 'props' => $props];
        }
        $measure = $spec['measures'][0];

        return [
            'id' => $spec['lab_key'], 'version' => $lab['version'], 'spec' => $spec,
            'chapter_ids' => $spec['chapter_ids'], 'title' => $spec['title'], 'concepts' => $spec['concepts'],
            'items' => $items,
            'measure' => $measure,
            'measures' => [$measure['id'] => $measure],
            'essential' => $spec['essential_groups'], 'irrelevant' => $spec['irrelevant'],
            // For the marker only. What a correct investigation of this lab would find, and the science behind it.
            'expected' => $spec['marker']['expected'], 'concept_focus' => $spec['marker']['concept_focus'],
        ];
    }

    /**
     * The measured value for effective settings.
     *
     * @param  array<string,string>  $s  the effective settings
     */
    public static function seconds(string $modelId, array $s, ?int $version = null): float
    {
        $model = self::get($modelId, $version);

        return $model === null ? 0.0 : LabSpecEvaluator::value($model['spec'], $s);
    }

    /**
     * Every setting for a trial: the student's choice for equipment on the bench, the default (or "absent" value) otherwise.
     *
     * @param  array<string,mixed>  $model
     * @param  array<int,string>  $benchIds
     * @param  array<string,string>  $chosen
     * @return array<string,string>
     */
    public static function effective(array $model, array $benchIds, array $chosen): array
    {
        $out = [];
        foreach ($model['items'] as $id => $item) {
            foreach ($item['props'] as $key => $p) {
                $out[$key] = in_array($id, $benchIds, true) ? ($chosen[$key] ?? $p['default']) : ($p['absent'] ?? $p['default']);
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $model */
    public static function propLabel(array $model, string $key): string
    {
        foreach ($model['items'] as $item) {
            if (isset($item['props'][$key])) {
                return $item['props'][$key]['label'];
            }
        }

        return $key;
    }

    /** @param array<string,mixed> $model */
    public static function valueLabel(array $model, string $key, string $value): string
    {
        foreach ($model['items'] as $item) {
            if (isset($item['props'][$key])) {
                return $item['props'][$key]['options'][$value] ?? $value;
            }
        }

        return $value;
    }

    /** @param array<string,mixed> $model @return array<int,string> */
    public static function propKeys(array $model): array
    {
        $keys = [];
        foreach ($model['items'] as $item) {
            $keys = array_merge($keys, array_keys($item['props']));
        }

        return $keys;
    }
}
