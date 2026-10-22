<?php

namespace App\Services\PAL\Framework;

use App\Exceptions\GraphUnavailableException;
use App\Services\Neo4jService;
use Throwable;

class FrameworkCatalogService
{
    public function __construct(private ?Neo4jService $neo4j = null)
    {
        $this->neo4j ??= app(Neo4jService::class);
    }

    public function catalog(): array
    {
        return config('pal_v4', []);
    }

    /**
     * Real `(:Framework)-[:MAPS_TO]->(target)` mappings, read live from the
     * K12 Neo4j graph — NOT the static `config('pal_v4')` slugs `catalog()`
     * returns above.
     *
     * As of 2026-10-12, four frameworks are seeded under
     * database/neo4j/frameworks/ (loaded via `php artisan
     * pal:seed-framework-graph`) — each against a DIFFERENT real target
     * label, chosen per-framework rather than forced onto `:Concept`:
     *   - NGSS    -> :Concept (ngss.cypher)
     *   - GARDNER -> :Concept, cross-curricular (gardner.cypher)
     *   - CASEL   -> :CoScholasticArea, the live sync of `result_co_scholastic`
     *     report-card grading areas — real behavioural data, since
     *     `pal_contents.casel_domain` is still 100% null (verified again
     *     2026-10-12) and no academic Concept honestly represents a
     *     social-emotional competency (casel.cypher)
     *   - NCDG    -> :Occupation / :Exam / :Degree, the CAI-CORE career graph
     *     (App\CareerIntelligence\CaiCoreService's seed) — the real anchor for
     *     a career-development framework, not an academic concept (ncdg.cypher)
     *
     * Two frameworks are deliberately NOT seeded:
     *   - RIASEC: Holland Code career-interest theory has no real citable
     *     correspondence to curriculum Concepts (no RIASEC document claims a
     *     specific chapter "is" Investigative, say) and its real home —
     *     `:Occupation`-level RIASEC codes via the `HasInterest`/
     *     `HasWorkValue` edges GraphSchema.php already whitelists — carries no
     *     real data yet (confirmed by a prior agent the same day this file was
     *     last touched). Seeding a Framework->Concept RIASEC file would
     *     duplicate the career-graph work under a different, less honest
     *     label, so it is not done.
     *   - HPC (Holistic Progress Card, NEP 2020/NCF/PARAKH): real, cited, and
     *     already fully implemented — see App\Domain\Exam\HpcBlueprint and
     *     HpcBlueprintPresets — but structurally NOT a Framework->Concept
     *     mapping. Its own curricular-goal/competency chain is deliberately
     *     left blank in HpcBlueprintPresets because the official PARAKH
     *     documents themselves don't publish one; seeding a Concept mapping
     *     here would require fabricating exactly the chain that file refuses
     *     to invent.
     *
     * Because the real anchor differs per framework, the Cypher below does
     * NOT restrict the MAPS_TO target to `:Concept` — it matches any label and
     * reads a generic id/name via COALESCE across every target type's own key
     * property (`uid` for Concept, `occupation_id`/`exam_id`/`degree_id` for
     * the CAI-CORE labels, `coscholasticareaId` for CoScholasticArea).
     * Additive change from the NGSS-only version of this method: NGSS's own
     * rows are unaffected (c.uid/c.name still resolve first via COALESCE).
     *
     * @return array{frameworks: array<int, array{code:string, name:string, fullName:string, sourceUrl:?string, practices: array<int, array{code:string, name:string, concepts: array<int, array{uid:string, name:string, type:?string, rationale:?string}>}>}>}
     */
    public function graphMappings(): array
    {
        try {
            $client = $this->neo4j->getClient();

            $rows = $client->run(
                'MATCH (f:Framework)-[m:MAPS_TO]->(c) '
                .'RETURN f.framework_code AS frameworkCode, f.name AS frameworkName, '
                .'f.full_name AS frameworkFullName, f.source_url AS frameworkSourceUrl, '
                .'m.practice_code AS practiceCode, m.practice_name AS practiceName, '
                .'m.rationale AS rationale, '
                .'COALESCE(c.uid, c.occupation_id, c.exam_id, c.degree_id, c.coscholasticareaId) AS conceptUid, '
                .'COALESCE(c.name, c.title) AS conceptName, '
                .'labels(c)[0] AS conceptType '
                .'ORDER BY f.framework_code, m.practice_code, conceptName'
            );
        } catch (GraphUnavailableException|Throwable $e) {
            // Same "say so, don't force it" rule as every other graph read in
            // this codebase: an unreachable graph returns an honestly empty
            // result, never a fabricated one.
            return ['frameworks' => [], 'available' => false, 'reason' => $e->getMessage()];
        }

        $frameworks = [];
        foreach ($rows as $row) {
            $code = (string) $row->get('frameworkCode');
            if ($code === '') {
                continue;
            }

            $frameworks[$code] ??= [
                'code' => $code,
                'name' => $row->get('frameworkName'),
                'fullName' => $row->get('frameworkFullName'),
                'sourceUrl' => $row->get('frameworkSourceUrl'),
                'practices' => [],
            ];

            $practiceCode = (string) $row->get('practiceCode');
            $frameworks[$code]['practices'][$practiceCode] ??= [
                'code' => $practiceCode,
                'name' => $row->get('practiceName'),
                'concepts' => [],
            ];

            $frameworks[$code]['practices'][$practiceCode]['concepts'][] = [
                'uid' => $row->get('conceptUid'),
                'name' => $row->get('conceptName'),
                'type' => $row->get('conceptType'),
                'rationale' => $row->get('rationale'),
            ];
        }

        foreach ($frameworks as &$framework) {
            $framework['practices'] = array_values($framework['practices']);
        }

        return ['frameworks' => array_values($frameworks), 'available' => true, 'reason' => null];
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

    /**
     * True when the tag is a canonical pedagogy or a known alias -- i.e. when
     * normalizePedagogy() will resolve it rather than fall back to the default.
     * Callers matching free-text labels use this to tell a real hit from the
     * inquiry_based fallback.
     */
    public function isKnownPedagogy(?string $tag): bool
    {
        $clean = strtolower(str_replace([' ', '-'], '_', trim((string) $tag)));
        if ($clean === '') {
            return false;
        }

        return array_key_exists($clean, config('pal_v4.aliases', []))
            || $this->isAllowed('pedagogies', $clean);
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
