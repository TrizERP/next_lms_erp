<?php

namespace App\CareerIntelligence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Career Counseling "Suggested mentors": which active staff hold People &
 * Competency skills that HR has explicitly mapped to the O*NET skill and
 * knowledge elements a career (O*NET occupation) requires.
 *
 * The two vocabularies share no key. The ONLY bridge is the HR-confirmed
 * crosswalk table `s_users_skills_onet_map`; this class never infers a
 * mapping from names. Text similarity is used solely to PROPOSE candidates
 * (suggestElements) for an HR user to confirm, and nothing here persists a
 * proposal. Matching read paths are read-only and tenant-scoped.
 *
 * Output exposes staff id, display name and job title only: no contact data.
 *
 * The ranking (rank) and candidate proposal (suggestElements) are pure
 * functions so they can be tested with injected data and no database.
 */
class StaffMentorMatchService
{
    public const MAP_TABLE = 's_users_skills_onet_map';

    /** O*NET scale used as the importance weight (1-5). */
    private const IMPORTANCE_SCALE = 'IM';

    // ------------------------------------------------------------------
    // Mentor suggestions
    // ------------------------------------------------------------------

    /**
     * @return array{
     *   crosswalk_ready: bool, occupation_code: string, reason: string|null,
     *   requirement_count: int, mapped_requirement_count: int, mentors: array<int, array>
     * }
     *   reason: null | 'crosswalk_not_ready' | 'no_requirements' | 'no_mapped_skills' | 'no_matching_staff'
     */
    public function suggestMentors(string $occupationCode, int $subInstituteId, int $limit = 10): array
    {
        $base = [
            'crosswalk_ready' => false,
            'occupation_code' => $occupationCode,
            'reason' => 'crosswalk_not_ready',
            'requirement_count' => 0,
            'mapped_requirement_count' => 0,
            'mentors' => [],
        ];

        if (! $this->tableExists(self::MAP_TABLE)) {
            return $base;
        }
        $base['crosswalk_ready'] = true;

        $requirements = $this->fetchRequirements($occupationCode);
        $base['requirement_count'] = count($requirements);
        if ($requirements === []) {
            return ['reason' => 'no_requirements'] + $base;
        }

        $mappings = $this->fetchMappings($subInstituteId, array_keys($requirements));
        if ($mappings === []) {
            return ['reason' => 'no_mapped_skills'] + $base;
        }
        $base['mapped_requirement_count'] = count(array_unique(array_column($mappings, 'element_id')));

        $skillIds = array_values(array_unique(array_column($mappings, 'skill_id')));
        $skillTitles = $this->fetchSkillTitles($subInstituteId, $skillIds);
        $holders = $this->fetchHolders($subInstituteId, array_keys($skillTitles));
        $jobRoles = $this->fetchJobRoles($subInstituteId, $holders);

        $mentors = self::rank($requirements, $mappings, $skillTitles, $holders, $jobRoles, $limit);

        return ['reason' => $mentors === [] ? 'no_matching_staff' : null, 'mentors' => $mentors] + $base;
    }

    /**
     * Pure ranking. Score = importance-weighted coverage: the summed O*NET
     * importance of the distinct required elements a person covers through
     * mapped skills, divided by the summed importance of ALL the career's
     * required elements, as a percentage. Ties break on total self-rated
     * skill level, then staff id, so the order is deterministic.
     *
     * @param array<string, array{name:string, importance:float, type:string}> $requirements element_id => ...
     * @param array<int, array{skill_id:int, element_id:string}>                $mappings
     * @param array<int, string>                                                $skillTitles skill_id => title
     * @param array<int, array{staff_id:int, name:string, job_role_id:int|null, skill_id:int, skill_level:int}> $holders
     * @param array<int, string>                                                $jobRoles job_role_id => title
     * @return array<int, array>
     */
    public static function rank(
        array $requirements,
        array $mappings,
        array $skillTitles,
        array $holders,
        array $jobRoles,
        int $limit = 10
    ): array {
        $total = 0.0;
        foreach ($requirements as $req) {
            $total += max((float) $req['importance'], 0.0);
        }
        if ($total <= 0.0) {
            return [];
        }

        // skill_id => [element_id, ...] limited to elements the career requires.
        $elementsBySkill = [];
        foreach ($mappings as $map) {
            $skillId = (int) $map['skill_id'];
            $elementId = (string) $map['element_id'];
            if (isset($requirements[$elementId]) && isset($skillTitles[$skillId])) {
                $elementsBySkill[$skillId][$elementId] = true;
            }
        }

        $byStaff = [];
        foreach ($holders as $holder) {
            $skillId = (int) $holder['skill_id'];
            if (empty($elementsBySkill[$skillId]) || (int) $holder['skill_level'] < 1) {
                continue;
            }
            $staffId = (int) $holder['staff_id'];
            $byStaff[$staffId] ??= [
                'staff_id' => $staffId,
                'name' => (string) $holder['name'],
                'job_title' => isset($holder['job_role_id'], $jobRoles[(int) $holder['job_role_id']])
                    ? (string) $jobRoles[(int) $holder['job_role_id']] : null,
                'elements' => [],
                'matched_skills' => [],
                'level_sum' => 0,
            ];
            $byStaff[$staffId]['level_sum'] += (int) $holder['skill_level'];
            foreach (array_keys($elementsBySkill[$skillId]) as $elementId) {
                $req = $requirements[$elementId];
                $byStaff[$staffId]['elements'][$elementId] = max((float) $req['importance'], 0.0);
                $byStaff[$staffId]['matched_skills'][] = [
                    'skill_id' => $skillId,
                    'skill_title' => $skillTitles[$skillId],
                    'skill_level' => (int) $holder['skill_level'],
                    'element_id' => (string) $elementId,
                    'element_name' => $req['name'],
                    'element_type' => $req['type'],
                    'importance' => (float) $req['importance'],
                ];
            }
        }

        $mentors = [];
        foreach ($byStaff as $row) {
            $covered = array_sum($row['elements']);
            if ($covered <= 0.0) {
                continue;
            }
            usort($row['matched_skills'], static fn ($a, $b) => $b['importance'] <=> $a['importance']
                ?: strcmp($a['skill_title'], $b['skill_title']));
            $mentors[] = [
                'staff_id' => $row['staff_id'],
                'name' => $row['name'],
                'job_title' => $row['job_title'],
                'score' => round(($covered / $total) * 100, 1),
                'matched_element_count' => count($row['elements']),
                'matched_skills' => $row['matched_skills'],
                '_level_sum' => $row['level_sum'],
            ];
        }

        usort($mentors, static fn ($a, $b) => $b['score'] <=> $a['score']
            ?: $b['_level_sum'] <=> $a['_level_sum']
            ?: $a['staff_id'] <=> $b['staff_id']);

        $mentors = array_slice($mentors, 0, max($limit, 0));
        foreach ($mentors as &$mentor) {
            unset($mentor['_level_sum']);
        }

        return $mentors;
    }

    // ------------------------------------------------------------------
    // Crosswalk management
    // ------------------------------------------------------------------

    /**
     * Staff skills (held by at least one active staff member) with no mapping
     * yet, each with PROPOSED O*NET elements. Proposals are never stored.
     *
     * @return array{crosswalk_ready: bool, unmapped: array, mapped: array}
     */
    public function managementView(int $subInstituteId, ?string $search = null, int $limit = 50): array
    {
        if (! $this->tableExists(self::MAP_TABLE)) {
            return ['crosswalk_ready' => false, 'unmapped' => [], 'mapped' => []];
        }
        $unmapped = $this->fetchUnmappedSkills($subInstituteId, $search, $limit);
        $elements = $unmapped === [] ? [] : $this->fetchCandidateElements();
        foreach ($unmapped as &$skill) {
            $skill['suggestions'] = $this->suggestElements(
                trim($skill['title'].' '.($skill['related_skills'] ?? '')),
                $elements
            );
            unset($skill['related_skills']);
        }

        return [
            'crosswalk_ready' => true,
            'unmapped' => $unmapped,
            'mapped' => $this->fetchMapped($subInstituteId),
        ];
    }

    /**
     * Pure text-similarity PROPOSALS. Clearly not matches: the caller labels
     * them as suggestions and a human must confirm each one.
     *
     * @param array<int, array{element_id:string, name:string, type:string}> $elements
     * @return array<int, array{element_id:string, element_name:string, element_type:string, similarity:float}>
     */
    public function suggestElements(string $text, array $elements, int $limit = 5, float $minSimilarity = 0.2): array
    {
        $tokenizer = new KnowledgeMatchService();
        $skillTokens = $tokenizer->tokenize($text, 3);
        if ($skillTokens === []) {
            return [];
        }

        $scored = [];
        foreach ($elements as $element) {
            $elementTokens = $tokenizer->tokenize((string) $element['name'], 3);
            if ($elementTokens === []) {
                continue;
            }
            $shared = count(array_intersect_key($skillTokens, $elementTokens));
            if ($shared === 0) {
                continue;
            }
            $jaccard = $tokenizer->jaccard($skillTokens, $elementTokens);
            $containment = $shared / min(count($skillTokens), count($elementTokens));
            $similarity = round(0.5 * $jaccard + 0.5 * $containment, 3);
            if ($similarity >= $minSimilarity) {
                $scored[] = [
                    'element_id' => (string) $element['element_id'],
                    'element_name' => (string) $element['name'],
                    'element_type' => (string) $element['type'],
                    'similarity' => $similarity,
                ];
            }
        }
        usort($scored, static fn ($a, $b) => $b['similarity'] <=> $a['similarity']
            ?: strcmp($a['element_name'], $b['element_name']));

        return array_slice($scored, 0, $limit);
    }

    /**
     * Persist ONE human-confirmed mapping. Returns:
     *   ['ok'=>true,'created'=>bool,'id'=>int] or ['ok'=>false,'error'=>string]
     */
    public function saveMapping(int $subInstituteId, int $skillId, string $elementId, ?int $reviewedBy): array
    {
        if (! $this->tableExists(self::MAP_TABLE)) {
            return ['ok' => false, 'error' => 'crosswalk_not_ready'];
        }
        if (! $this->skillExists($subInstituteId, $skillId)) {
            return ['ok' => false, 'error' => 'skill_not_found'];
        }
        $valid = array_column($this->fetchCandidateElements(), 'element_id');
        if (! in_array($elementId, $valid, true)) {
            return ['ok' => false, 'error' => 'element_not_found'];
        }

        $existing = DB::table(self::MAP_TABLE)
            ->where('sub_institute_id', $subInstituteId)
            ->where('skill_id', $skillId)
            ->where('element_id', $elementId)
            ->value('id');
        if ($existing) {
            return ['ok' => true, 'created' => false, 'id' => (int) $existing];
        }

        $id = DB::table(self::MAP_TABLE)->insertGetId([
            'sub_institute_id' => $subInstituteId,
            'skill_id' => $skillId,
            'element_id' => $elementId,
            'source' => 'manual',
            'reviewed_by' => $reviewedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['ok' => true, 'created' => true, 'id' => (int) $id];
    }

    public function deleteMapping(int $subInstituteId, int $mappingId): bool
    {
        if (! $this->tableExists(self::MAP_TABLE)) {
            return false;
        }

        return DB::table(self::MAP_TABLE)
            ->where('sub_institute_id', $subInstituteId)
            ->where('id', $mappingId)
            ->delete() > 0;
    }

    // ------------------------------------------------------------------
    // Readers (each is tenant-scoped; overridable for tests)
    // ------------------------------------------------------------------

    protected function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    /**
     * The career's skill and knowledge elements with IM importance. The
     * existing readers (KnowledgeMatchService::getOccupationKnowledge and
     * lmsCounsellingController::getSkills/getKnowledge) key on element NAME,
     * but the crosswalk needs element_id, so this reads the same
     * onet_skills / onet_knowledge + onet_content_model_reference tables.
     * O*NET reference data is not tenant data.
     *
     * @return array<string, array{name:string, importance:float, type:string}>
     */
    protected function fetchRequirements(string $occupationCode): array
    {
        $out = [];
        foreach (['onet_skills' => 'skill', 'onet_knowledge' => 'knowledge'] as $table => $type) {
            if (! $this->tableExists($table)) {
                continue;
            }
            $rows = DB::table($table.' as t')
                ->join('onet_content_model_reference as r', 'r.element_id', '=', 't.element_id')
                ->where('t.onetsoc_code', $occupationCode)
                ->where('t.scale_id', self::IMPORTANCE_SCALE)
                ->select('t.element_id', 'r.element_name', 't.data_value')
                ->get();
            foreach ($rows as $row) {
                $out[(string) $row->element_id] = [
                    'name' => (string) $row->element_name,
                    'importance' => (float) $row->data_value,
                    'type' => $type,
                ];
            }
        }

        return $out;
    }

    /** @return array<int, array{skill_id:int, element_id:string}> */
    protected function fetchMappings(int $subInstituteId, array $elementIds): array
    {
        return DB::table(self::MAP_TABLE)
            ->where('sub_institute_id', $subInstituteId)
            ->whereIn('element_id', $elementIds)
            ->select('skill_id', 'element_id')
            ->get()
            ->map(fn ($r) => ['skill_id' => (int) $r->skill_id, 'element_id' => (string) $r->element_id])
            ->all();
    }

    /** @return array<int, string> skill_id => title (live skills of this tenant only) */
    protected function fetchSkillTitles(int $subInstituteId, array $skillIds): array
    {
        if ($skillIds === []) {
            return [];
        }

        // status != 'Inactive', never = 'Active': NULL means "not marked".
        return DB::table('s_users_skills')
            ->where('sub_institute_id', $subInstituteId)
            ->whereIn('id', $skillIds)
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'Inactive'))
            ->pluck('title', 'id')
            ->map(fn ($t) => (string) $t)
            ->all();
    }

    /**
     * Active staff of this tenant holding the given skills. s_skill_matrix has
     * no tenant column, so the tbluser join is what enforces isolation.
     * Selects name and role id only: no contact columns.
     *
     * @return array<int, array{staff_id:int, name:string, job_role_id:int|null, skill_id:int, skill_level:int}>
     */
    protected function fetchHolders(int $subInstituteId, array $skillIds): array
    {
        if ($skillIds === []) {
            return [];
        }

        return DB::table('s_skill_matrix as m')
            ->join('tbluser as u', 'u.id', '=', 'm.user_id')
            ->where('u.sub_institute_id', $subInstituteId)
            ->where('u.status', 1)
            ->whereIn('m.skill_id', $skillIds)
            ->where('m.skill_level', '>=', 1)
            ->select('u.id as staff_id', 'u.first_name', 'u.last_name', 'u.allocated_standards', 'm.skill_id', 'm.skill_level')
            ->get()
            ->map(fn ($r) => [
                'staff_id' => (int) $r->staff_id,
                'name' => trim($r->first_name.' '.$r->last_name),
                'job_role_id' => is_numeric($r->allocated_standards) ? (int) $r->allocated_standards : null,
                'skill_id' => (int) $r->skill_id,
                'skill_level' => (int) $r->skill_level,
            ])
            ->all();
    }

    /** @return array<int, string> job_role_id => job title */
    protected function fetchJobRoles(int $subInstituteId, array $holders): array
    {
        $ids = array_values(array_unique(array_filter(array_column($holders, 'job_role_id'))));
        if ($ids === [] || ! $this->tableExists('s_user_jobrole')) {
            return [];
        }

        return DB::table('s_user_jobrole')
            ->where('sub_institute_id', $subInstituteId)
            ->whereIn('id', $ids)
            ->pluck('jobrole', 'id')
            ->map(fn ($t) => (string) $t)
            ->all();
    }

    /** @return array<int, array{id:int, title:string, category:?string, skill_code:?string, related_skills:?string, holder_count:int}> */
    protected function fetchUnmappedSkills(int $subInstituteId, ?string $search, int $limit): array
    {
        $query = DB::table('s_users_skills as us')
            ->join('s_skill_matrix as m', 'm.skill_id', '=', 'us.id')
            ->join('tbluser as u', 'u.id', '=', 'm.user_id')
            ->where('us.sub_institute_id', $subInstituteId)
            ->where('u.sub_institute_id', $subInstituteId)
            ->where('u.status', 1)
            ->where('m.skill_level', '>=', 1)
            ->whereNull('us.deleted_at')
            ->where(fn ($q) => $q->whereNull('us.status')->orWhere('us.status', '!=', 'Inactive'))
            ->whereNotExists(function ($q) use ($subInstituteId) {
                $q->select(DB::raw(1))
                    ->from(self::MAP_TABLE.' as mp')
                    ->whereColumn('mp.skill_id', 'us.id')
                    ->where('mp.sub_institute_id', $subInstituteId);
            });

        if ($search !== null && trim($search) !== '') {
            $query->where('us.title', 'like', '%'.trim($search).'%');
        }

        return $query
            ->groupBy('us.id', 'us.title', 'us.category', 'us.skill_code', 'us.related_skills')
            ->selectRaw('us.id, us.title, us.category, us.skill_code, us.related_skills, COUNT(DISTINCT m.user_id) as holder_count')
            ->orderByDesc('holder_count')
            ->orderBy('us.title')
            ->limit(max(1, min($limit, 200)))
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'title' => (string) $r->title,
                'category' => $r->category,
                'skill_code' => $r->skill_code,
                'related_skills' => $r->related_skills,
                'holder_count' => (int) $r->holder_count,
            ])
            ->all();
    }

    /** @return array<int, array{id:int, skill_id:int, skill_title:?string, element_id:string, element_name:?string, element_type:string}> */
    protected function fetchMapped(int $subInstituteId): array
    {
        $rows = DB::table(self::MAP_TABLE)
            ->where('sub_institute_id', $subInstituteId)
            ->orderBy('skill_id')
            ->limit(500)
            ->get(['id', 'skill_id', 'element_id']);
        if ($rows->isEmpty()) {
            return [];
        }

        $titles = DB::table('s_users_skills')
            ->where('sub_institute_id', $subInstituteId)
            ->whereIn('id', $rows->pluck('skill_id')->unique()->all())
            ->pluck('title', 'id');
        $elements = [];
        foreach ($this->fetchCandidateElements() as $element) {
            $elements[$element['element_id']] = $element;
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'skill_id' => (int) $r->skill_id,
            'skill_title' => $titles[$r->skill_id] ?? null,
            'element_id' => (string) $r->element_id,
            'element_name' => $elements[$r->element_id]['name'] ?? null,
            'element_type' => $elements[$r->element_id]['type'] ?? 'unknown',
        ])->all();
    }

    /**
     * Every O*NET skill and knowledge element a career can require.
     *
     * @return array<int, array{element_id:string, name:string, type:string}>
     */
    protected function fetchCandidateElements(): array
    {
        $out = [];
        foreach (['onet_skills' => 'skill', 'onet_knowledge' => 'knowledge'] as $table => $type) {
            if (! $this->tableExists($table)) {
                continue;
            }
            $ids = DB::table($table)->distinct()->pluck('element_id')->all();
            if ($ids === []) {
                continue;
            }
            $names = DB::table('onet_content_model_reference')->whereIn('element_id', $ids)->pluck('element_name', 'element_id');
            foreach ($ids as $id) {
                if (isset($names[$id])) {
                    $out[(string) $id] = ['element_id' => (string) $id, 'name' => (string) $names[$id], 'type' => $type];
                }
            }
        }

        return array_values($out);
    }

    protected function skillExists(int $subInstituteId, int $skillId): bool
    {
        return DB::table('s_users_skills')
            ->where('sub_institute_id', $subInstituteId)
            ->where('id', $skillId)
            ->whereNull('deleted_at')
            ->exists();
    }
}
