<?php

namespace Tests\Feature\CareerIntelligence;

use App\CareerIntelligence\StaffMentorMatchService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Staff mentor match. Synthetic ids only (tenant -1, fake staff/skill/element
 * ids). Nothing here reads or writes a real row: ranking is a pure function,
 * and the query-shape checks run under DB::pretend(), which records SQL
 * without executing it.
 */
class StaffMentorMatchTest extends TestCase
{
    private const TENANT = -1;

    private function requirements(): array
    {
        return [
            'E.1' => ['name' => 'Alpha', 'importance' => 4.0, 'type' => 'skill'],
            'E.2' => ['name' => 'Beta', 'importance' => 3.0, 'type' => 'knowledge'],
            'E.3' => ['name' => 'Gamma', 'importance' => 1.0, 'type' => 'skill'],
        ];
    }

    public function test_rank_is_importance_weighted_explainable_and_deterministic(): void
    {
        $mappings = [
            ['skill_id' => 10, 'element_id' => 'E.1'],
            ['skill_id' => 11, 'element_id' => 'E.2'],
            ['skill_id' => 12, 'element_id' => 'E.3'],
            ['skill_id' => 12, 'element_id' => 'E.9'], // not required by this career: ignored
        ];
        $titles = [10 => 'Skill A', 11 => 'Skill B', 12 => 'Skill C'];
        $holders = [
            ['staff_id' => 900, 'name' => 'Synthetic One', 'job_role_id' => 5, 'skill_id' => 10, 'skill_level' => 3],
            ['staff_id' => 900, 'name' => 'Synthetic One', 'job_role_id' => 5, 'skill_id' => 11, 'skill_level' => 2],
            ['staff_id' => 901, 'name' => 'Synthetic Two', 'job_role_id' => null, 'skill_id' => 12, 'skill_level' => 5],
            ['staff_id' => 902, 'name' => 'Synthetic Zero', 'job_role_id' => null, 'skill_id' => 10, 'skill_level' => 0], // level 0 = not held
            ['staff_id' => 903, 'name' => 'Synthetic Unmapped', 'job_role_id' => null, 'skill_id' => 99, 'skill_level' => 4],
        ];

        $ranked = StaffMentorMatchService::rank($this->requirements(), $mappings, $titles, $holders, [5 => 'Role X']);

        $this->assertSame([900, 901], array_column($ranked, 'staff_id'));
        $this->assertSame(87.5, $ranked[0]['score']);  // (4+3)/8
        $this->assertSame(12.5, $ranked[1]['score']);  // 1/8
        $this->assertSame('Role X', $ranked[0]['job_title']);
        $this->assertNull($ranked[1]['job_title']);
        $this->assertSame(['skill_id', 'skill_title', 'skill_level', 'element_id', 'element_name', 'element_type', 'importance'],
            array_keys($ranked[0]['matched_skills'][0]));
        $this->assertSame('Skill A', $ranked[0]['matched_skills'][0]['skill_title']);
        $this->assertSame('Alpha', $ranked[0]['matched_skills'][0]['element_name']);
        // Only the contract fields are exposed: no contact data can leak through.
        $this->assertSame(
            ['staff_id', 'name', 'job_title', 'score', 'matched_element_count', 'matched_skills'],
            array_keys($ranked[0])
        );
    }

    public function test_one_element_covered_by_two_skills_counts_once(): void
    {
        $mappings = [['skill_id' => 10, 'element_id' => 'E.1'], ['skill_id' => 11, 'element_id' => 'E.1']];
        $holders = [
            ['staff_id' => 900, 'name' => 'S', 'job_role_id' => null, 'skill_id' => 10, 'skill_level' => 1],
            ['staff_id' => 900, 'name' => 'S', 'job_role_id' => null, 'skill_id' => 11, 'skill_level' => 1],
        ];
        $ranked = StaffMentorMatchService::rank($this->requirements(), $mappings, [10 => 'A', 11 => 'B'], $holders, []);
        $this->assertSame(50.0, $ranked[0]['score']); // 4/8, not 8/8
        $this->assertCount(2, $ranked[0]['matched_skills']);
    }

    public function test_rank_respects_limit_and_empty_inputs(): void
    {
        $this->assertSame([], StaffMentorMatchService::rank([], [], [], [], []));
        $this->assertSame([], StaffMentorMatchService::rank($this->requirements(), [], [], [], []));

        $mappings = [['skill_id' => 10, 'element_id' => 'E.1']];
        $holders = [];
        foreach ([901, 902, 903] as $id) {
            $holders[] = ['staff_id' => $id, 'name' => 'S'.$id, 'job_role_id' => null, 'skill_id' => 10, 'skill_level' => 2];
        }
        $ranked = StaffMentorMatchService::rank($this->requirements(), $mappings, [10 => 'A'], $holders, [], 2);
        $this->assertSame([901, 902], array_column($ranked, 'staff_id')); // tie -> staff id order
    }

    public function test_missing_crosswalk_table_degrades_without_any_query(): void
    {
        $service = new class extends StaffMentorMatchService {
            protected function tableExists(string $table): bool
            {
                return false;
            }
        };

        $queries = DB::pretend(function () use ($service, &$mentors, &$view) {
            $mentors = $service->suggestMentors('00-0000.00', self::TENANT);
            $view = $service->managementView(self::TENANT);
        });

        $this->assertSame([], $queries);
        $this->assertFalse($mentors['crosswalk_ready']);
        $this->assertSame('crosswalk_not_ready', $mentors['reason']);
        $this->assertSame([], $mentors['mentors']);
        $this->assertFalse($view['crosswalk_ready']);
        $this->assertSame([], $view['unmapped']);
        $this->assertFalse($service->saveMapping(self::TENANT, 1, 'E.1', 1)['ok']);
        $this->assertFalse($service->deleteMapping(self::TENANT, 1));
    }

    public function test_empty_crosswalk_returns_honest_reasons(): void
    {
        $noRequirements = new class extends StaffMentorMatchService {
            protected function tableExists(string $table): bool { return true; }
            protected function fetchRequirements(string $occupationCode): array { return []; }
        };
        $r = $noRequirements->suggestMentors('00-0000.00', self::TENANT);
        $this->assertTrue($r['crosswalk_ready']);
        $this->assertSame('no_requirements', $r['reason']);

        $noMappings = new class extends StaffMentorMatchService {
            protected function tableExists(string $table): bool { return true; }
            protected function fetchRequirements(string $occupationCode): array
            {
                return ['E.1' => ['name' => 'Alpha', 'importance' => 4.0, 'type' => 'skill']];
            }
            protected function fetchMappings(int $subInstituteId, array $elementIds): array { return []; }
        };
        $r = $noMappings->suggestMentors('00-0000.00', self::TENANT);
        $this->assertSame('no_mapped_skills', $r['reason']);
        $this->assertSame([], $r['mentors']);
    }

    public function test_every_tenant_read_is_scoped_to_the_callers_institute(): void
    {
        $probe = new class extends StaffMentorMatchService {
            public function run(int $tenant): void
            {
                $this->fetchMappings($tenant, ['E.1']);
                $this->fetchSkillTitles($tenant, [10]);
                $this->fetchHolders($tenant, [10]);
                $this->fetchUnmappedSkills($tenant, 'x', 10);
                $this->fetchMapped($tenant);
                $this->skillExists($tenant, 10);
            }
            protected function tableExists(string $table): bool { return true; }
        };

        $queries = DB::pretend(fn () => $probe->run(self::TENANT));

        $this->assertNotEmpty($queries);
        foreach ($queries as $q) {
            $sql = strtolower($q['query']);
            if (! str_contains($sql, 's_users_skills') && ! str_contains($sql, 's_skill_matrix') && ! str_contains($sql, 'onet_map')) {
                continue;
            }
            $this->assertStringStartsWith('select', $sql, 'read paths must be read-only');
            $this->assertStringContainsString('sub_institute_id', $sql, $q['query']);
            $this->assertContains(self::TENANT, $q['bindings'], $q['query']);
        }

        // The skill-matrix read has no tenant column of its own: it must be
        // constrained through tbluser, active staff only.
        $holders = array_values(array_filter($queries, fn ($q) => str_contains($q['query'], 's_skill_matrix')));
        $this->assertNotEmpty($holders);
        $this->assertStringContainsString('`tbluser`', $holders[0]['query']);
        $this->assertStringContainsString('`u`.`status`', $holders[0]['query']);
        $this->assertStringNotContainsStringIgnoringCase('email', $holders[0]['query']);
        $this->assertStringNotContainsStringIgnoringCase('mobile', $holders[0]['query']);
    }

    public function test_suggestions_are_labelled_proposals_and_never_persisted(): void
    {
        $service = new class extends StaffMentorMatchService {
            protected function tableExists(string $table): bool { return true; }
            protected function fetchUnmappedSkills(int $subInstituteId, ?string $search, int $limit): array
            {
                return [['id' => 10, 'title' => 'Data analysis', 'category' => null, 'skill_code' => null,
                    'related_skills' => 'statistics', 'holder_count' => 2]];
            }
            protected function fetchCandidateElements(): array
            {
                return [
                    ['element_id' => 'E.1', 'name' => 'Mathematics', 'type' => 'knowledge'],
                    ['element_id' => 'E.2', 'name' => 'Data Analysis Skills', 'type' => 'skill'],
                ];
            }
            protected function fetchMapped(int $subInstituteId): array { return []; }
        };

        $view = null;
        $queries = DB::pretend(function () use ($service, &$view) {
            $view = $service->managementView(self::TENANT);
        });

        $this->assertSame([], $queries, 'building suggestions must not touch the database');
        $this->assertSame('E.2', $view['unmapped'][0]['suggestions'][0]['element_id']);
        $this->assertArrayNotHasKey('related_skills', $view['unmapped'][0]);
        $this->assertSame([], $view['mapped']);
    }

    public function test_suggest_elements_needs_real_token_overlap(): void
    {
        $service = new StaffMentorMatchService();
        $elements = [['element_id' => 'E.1', 'name' => 'Mathematics', 'type' => 'knowledge']];

        $this->assertSame([], $service->suggestElements('Welding', $elements));
        $this->assertSame([], $service->suggestElements('', $elements));
        $this->assertSame('E.1', $service->suggestElements('Applied Mathematics', $elements)[0]['element_id']);
    }
}
