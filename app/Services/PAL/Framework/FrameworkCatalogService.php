<?php

namespace App\Services\PAL\Framework;

class FrameworkCatalogService
{
    public function catalog(): array
    {
        return config('pal_v4', []);
    }

    public function pedagogies(): array
    {
        return array_keys(config('pal_v4.pedagogies', []));
    }

    public function pedagogyDetails(string $tag): array
    {
        $normalized = $this->normalizePedagogy($tag);
        return config("pal_v4.pedagogies.{$normalized}", []);
    }

    public function normalizePedagogy(?string $tag): string
    {
        $clean = strtolower(str_replace([' ', '-'], '_', trim((string) $tag)));
        if ($clean === '') {
            return 'inquiry_based';
        }

        $aliases = config('pal_v4.aliases', []);
        return $aliases[$clean] ?? ($this->isAllowed('pedagogies', $clean) ? $clean : 'inquiry_based');
    }

    public function pedagogyCoverage(string $tag): array
    {
        return config('pal_v4.coverage.' . $this->normalizePedagogy($tag), []);
    }

    public function allowedH5pForPedagogy(string $tag): array
    {
        return $this->pedagogyDetails($tag)['primary_h5p'] ?? [];
    }

    public function normalizeContentMetadata(array $payload): array
    {
        $pedagogyTag = $this->normalizePedagogy($payload['pedagogy_tag'] ?? $payload['pedagogyType'] ?? '');

        $metadata = [
            'pedagogy_tag' => $pedagogyTag,
            'pedagogy_tags' => array_values(array_unique(array_filter(array_map(
                fn ($item) => $this->normalizePedagogy((string) $item),
                array_merge((array) ($payload['pedagogy_tags'] ?? []), [$pedagogyTag])
            )))),
            'casel_domain' => $this->normalizeValue('casel', $payload['casel_domain'] ?? null),
            'ngss_practice' => $this->normalizeValue('ngss', $payload['ngss_practice'] ?? null),
            'ncdg_goal' => $this->normalizeValue('ncdg', $payload['ncdg_goal'] ?? null),
            'music_domain' => $this->normalizeValue('music', $payload['music_domain'] ?? null),
            'sports_domain' => $this->normalizeValue('sports', $payload['sports_domain'] ?? null),
            'finance_domain' => $this->normalizeValue('finance', $payload['finance_domain'] ?? null),
            'riasec_signal' => $this->normalizeValue('riasec', $payload['riasec_signal'] ?? null),
            'gardner_intelligence' => $this->normalizeList('gardner', $payload['gardner_intelligence'] ?? []),
            'h5p_type' => $this->normalizeValue('h5p', $payload['h5p_type'] ?? null),
            'assessment_method' => $payload['assessment_method'] ?? null,
            'cross_curricular_links' => array_values(array_filter((array) ($payload['cross_curricular_links'] ?? []))),
            'framework_metadata' => (array) ($payload['framework_metadata'] ?? []),
            'evidence_requirements' => array_values(array_filter((array) ($payload['evidence_requirements'] ?? []))),
            'pedagogy_profile' => (array) ($payload['pedagogy_profile'] ?? []),
        ];

        if ($metadata['music_domain'] === null && str_contains($pedagogyTag, 'art')) {
            $coverage = $this->pedagogyCoverage($pedagogyTag);
            $metadata['music_domain'] = $coverage['music'][0] ?? null;
        }

        return $metadata;
    }

    public function financeActivation(int $grade, array $signals = []): array
    {
        $riasec = array_map('strtoupper', (array) ($signals['riasec'] ?? []));
        $interest = (bool) ($signals['interest'] ?? false);
        $teacherAssign = (bool) ($signals['teacher_assign'] ?? false);
        $parentRequest = (bool) ($signals['parent_request'] ?? false);
        $allowedSignals = config('pal_v4.finance_activation.riasec', []);

        $active = $grade >= (int) config('pal_v4.finance_activation.grade_min', 5)
            && ($interest || $teacherAssign || $parentRequest || count(array_intersect($riasec, $allowedSignals)) > 0);

        $eligibleLevels = [];
        foreach (config('pal_v4.finance_activation.levels', []) as $tag => $rule) {
            if ($grade >= (int) ($rule['grade_min'] ?? 0) && $grade <= (int) ($rule['grade_max'] ?? 99)) {
                $eligibleLevels[] = $tag;
            }
        }

        return [
            'active' => $active,
            'eligible_levels' => $eligibleLevels,
        ];
    }

    public function normalizeValue(string $group, mixed $value): ?string
    {
        $item = trim((string) $value);
        if ($item === '') {
            return null;
        }

        if ($group === 'riasec') {
            $item = strtoupper($item);
        } else {
            $item = strtolower(str_replace([' ', '-'], '_', $item));
        }

        return $this->isAllowed($group, $item) ? $item : null;
    }

    public function normalizeList(string $group, mixed $values): array
    {
        $normalized = [];
        foreach ((array) $values as $value) {
            $item = $this->normalizeValue($group, $value);
            if ($item !== null) {
                $normalized[] = $item;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function isAllowed(string $group, string $value): bool
    {
        $allowed = $group === 'pedagogies'
            ? array_keys(config('pal_v4.pedagogies', []))
            : config("pal_v4.{$group}", []);

        return in_array($value, $allowed, true);
    }
}
