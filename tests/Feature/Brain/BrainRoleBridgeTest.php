<?php

namespace Tests\Feature\Brain;

use GenTux\Jwt\JwtToken;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Brain has no user table of its own; a token's LMS profile is its role.
 *
 * vivek_erp leaves tbluser.is_admin NULL for every row, so the is_admin branch
 * of the bridge never fires here and an institute's own administrator used to
 * resolve to `viewer` — able to read the Brain but not to refresh it or record a
 * decision. These tests pin the profile-name branch that fixes that, and pin the
 * other half of it too: a teacher must NOT gain those permissions.
 *
 * Profiles are discovered from the database rather than named, because profile
 * ids are per-institute.
 */
class BrainRoleBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('vivek_erp is not reachable: '.$e->getMessage());
        }
    }

    /** @return array{0: string, 1: string}|null [tenantId, profileId] */
    private function findProfile(string $name): ?array
    {
        $row = DB::table('tbluserprofilemaster as p')
            ->join('tbluser as u', 'u.user_profile_id', '=', 'p.id')
            ->whereRaw('LOWER(p.name) = ?', [$name])
            ->whereNotNull('u.sub_institute_id')
            ->first(['p.id as profile_id', 'u.sub_institute_id', 'u.id as user_id']);

        return $row ? [(string) $row->sub_institute_id, (string) $row->profile_id, (string) $row->user_id] : null;
    }

    private function tokenFor(string $tenantId, string $profileId, string $userId): string
    {
        return (string) app(JwtToken::class)->createToken([
            'id' => $userId,
            'sub_institute_id' => $tenantId,
            'is_admin' => null,
            'client_id' => null,
            'user_profile_id' => $profileId,
            'is_student' => false,
        ]);
    }

    public function test_an_lms_admin_profile_is_granted_administration(): void
    {
        $found = $this->findProfile('admin');
        if ($found === null) {
            $this->markTestSkipped('No institute in this database has an "Admin" profile.');
        }

        [$tenantId, $profileId, $userId] = $found;

        $this->withHeaders(['Authorization' => 'Bearer '.$this->tokenFor($tenantId, $profileId, $userId)])
            ->getJson('/api/brain/access')
            ->assertOk()
            ->assertJson(['role' => 'tenant_admin', 'tenantId' => $tenantId]);
    }

    public function test_a_teaching_profile_stays_read_only(): void
    {
        $found = $this->findProfile('teacher');
        if ($found === null) {
            $this->markTestSkipped('No institute in this database has a "Teacher" profile.');
        }

        [$tenantId, $profileId, $userId] = $found;
        $token = $this->tokenFor($tenantId, $profileId, $userId);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/brain/access')
            ->assertOk()
            ->assertJson(['role' => 'viewer']);

        // Read is allowed; refreshing the intelligence is not.
        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/brain/'.$tenantId.'/intelligence/run')
            ->assertStatus(403)
            ->assertJson(['error' => 'brain_forbidden']);
    }

    public function test_an_unknown_profile_falls_back_to_read_only(): void
    {
        $row = DB::table('tbluserprofilemaster as p')
            ->join('tbluser as u', 'u.user_profile_id', '=', 'p.id')
            ->whereNotIn(DB::raw('LOWER(p.name)'), array_keys((array) config('brain.profile_name_roles')))
            ->whereNotNull('u.sub_institute_id')
            ->first(['p.id as profile_id', 'u.sub_institute_id', 'u.id as user_id']);

        if (! $row) {
            $this->markTestSkipped('Every profile in this database is mapped.');
        }

        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->tokenFor(
                (string) $row->sub_institute_id,
                (string) $row->profile_id,
                (string) $row->user_id
            ),
        ])->getJson('/api/brain/access')->assertOk()->assertJson(['role' => 'viewer']);
    }
}
