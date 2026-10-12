<?php

namespace Tests\Feature;

use App\Http\Controllers\api\alumni\AlumniAuditApiController as C;
use App\Http\Controllers\api\alumni\AlumniDonationApiController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\AlumniTestHelpers;
use Tests\TestCase;

/**
 * The audit viewer shows who did what (including donation amounts), so it is Admin-only, scoped to the institute and the alumni
 * module, read-only, and truncates oversized detail instead of returning it. In-memory sqlite (see AlumniTestHelpers).
 */
class AlumniAuditTest extends TestCase
{
    use AlumniTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAlumni();
    }

    private function entry(int $tenant, string $module, string $component, string $action, array $extra = []): int
    {
        return (int) DB::table('platform_audit_log')->insertGetId(array_merge([
            'sub_institute_id' => $tenant, 'module' => $module, 'component' => $component, 'action' => $action, 'entity_type' => 'alumni', 'entity_id' => '1',
            'actor_user_id' => 55, 'actor_name' => 'Asha Admin', 'ip' => '10.0.0.1', 'created_at' => now(),
        ], $extra));
    }

    public function test_only_admin_type_profiles_may_read_the_audit_log(): void
    {
        $id = $this->entry(7, 'alumni', 'alumni.master', 'create');
        foreach (['Alumni Coordinator', 'Accountant', 'Teacher', 'Peon'] as $profile) {
            $this->assertSame(403, $this->go(C::class, 'index', [], 7, $profile)[0], $profile);
            $this->assertSame(403, $this->go(C::class, 'show', [], 7, $profile, [$id])[0], $profile);
        }
        foreach (['Admin', 'School Admin', 'Assistant Admin'] as $profile) {
            $this->assertSame(200, $this->go(C::class, 'index', [], 7, $profile)[0], $profile);
        }
    }

    public function test_entries_are_scoped_to_this_institute_and_the_alumni_module(): void
    {
        $mine = $this->entry(7, 'alumni', 'alumni.master', 'create');
        $this->entry(8, 'alumni', 'alumni.master', 'create');
        $other = $this->entry(7, 'fees', 'fees.collect', 'collect');

        [, $b] = $this->go(C::class, 'index');
        $this->assertSame([$mine], array_map('intval', array_column($b['data']['rows'], 'id')));
        $this->assertSame(404, $this->go(C::class, 'show', [], 8, 'Admin', [$mine])[0], 'another institute');
        $this->assertSame(404, $this->go(C::class, 'show', [], 7, 'Admin', [$other])[0], 'another module of the same institute');
        $this->assertSame(404, $this->go(C::class, 'show', [], 7, 'Admin', [99999])[0]);
        $this->assertSame(['alumni.master'], $b['data']['components']);
    }

    public function test_filters_paging_and_detail(): void
    {
        $a = $this->entry(7, 'alumni', 'alumni.donations', 'verify', ['actor_name' => 'Rita Accountant', 'entity_type' => 'alumni_donation', 'entity_id' => '42',
            'before_json' => json_encode(['status' => 'pending_verification']), 'after_json' => json_encode(['status' => 'verified', 'receipt_no' => 'DON-2026-00001']), 'created_at' => '2026-03-10 10:00:00']);
        $this->entry(7, 'alumni', 'alumni.master', 'update', ['created_at' => '2026-03-12 10:00:00']);
        $this->entry(7, 'alumni', 'alumni.master', 'create', ['created_at' => '2026-03-14 10:00:00']);

        $this->assertSame([$a], array_map('intval', array_column($this->go(C::class, 'index', ['component' => 'alumni.donations'])[1]['data']['rows'], 'id')));
        $this->assertCount(1, $this->go(C::class, 'index', ['action' => 'update'])[1]['data']['rows']);
        $this->assertCount(1, $this->go(C::class, 'index', ['q' => 'Rita'])[1]['data']['rows']);
        $this->assertCount(1, $this->go(C::class, 'index', ['q' => '42'])[1]['data']['rows']);
        $this->assertCount(0, $this->go(C::class, 'index', ['q' => '%'])[1]['data']['rows'], 'wildcards are escaped');
        $this->assertCount(2, $this->go(C::class, 'index', ['from' => '2026-03-11', 'to' => '2026-03-14'])[1]['data']['rows'], 'date range is inclusive of the last day');
        $this->assertSame(422, $this->go(C::class, 'index', ['from' => '2026-03-14', 'to' => '2026-03-01'])[0]);
        $this->assertSame(422, $this->go(C::class, 'index', ['per_page' => 500])[0]);

        [, $p] = $this->go(C::class, 'index', ['per_page' => 2]);
        $this->assertCount(2, $p['data']['rows']);
        $this->assertSame([3, 2], [$p['data']['pagination']['total'], $p['data']['pagination']['last_page']]);

        [, $d] = $this->go(C::class, 'show', [], 7, 'Admin', [$a]);
        $this->assertSame('pending_verification', $d['data']['before']['status']);
        $this->assertSame('DON-2026-00001', $d['data']['after']['receipt_no']);
        $this->assertSame('Rita Accountant', $d['data']['actor_name']);
    }

    public function test_oversized_detail_is_truncated(): void
    {
        $id = $this->entry(7, 'alumni', 'alumni.export', 'export', ['after_json' => json_encode(['blob' => str_repeat('x', 20000)])]);
        [, $d] = $this->go(C::class, 'show', [], 7, 'Admin', [$id]);
        $this->assertTrue($d['data']['after']['truncated']);
        $this->assertLessThan(5000, strlen(json_encode($d['data']['after'])));
    }

    public function test_entries_name_who_did_it(): void
    {
        // Regression: AuditTrail read the actor only from the `lms_auth` attribute, which `api.session` routes never set, so every
        // alumni entry said "System". The verified session user must be recorded - as an id, and as a name when the user exists.
        \Illuminate\Support\Facades\Schema::create('tbluser', function ($t) {
            $t->integer('id')->primary();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
        });
        DB::table('tbluser')->insert(['id' => 55, 'first_name' => 'Rita', 'last_name' => 'Accountant']);
        $a = $this->alum('Who');

        $this->go(AlumniDonationApiController::class, 'store', ['alumni_id' => $a->id, 'amount' => 100], 7, 'Accountant');
        $this->go(\App\Http\Controllers\api\alumni\AlumniApiController::class, 'status', ['status' => 'inactive'], 7, 'Admin', [$a->id]);

        $donation = DB::table('platform_audit_log')->where('action', 'record')->first();
        $this->assertSame(55, (int) $donation->actor_user_id);
        $this->assertSame('Rita Accountant', $donation->actor_name);
        $status = DB::table('platform_audit_log')->where('component', 'alumni.master')->where('action', 'status')->orderByDesc('id')->first();
        $this->assertNotNull($status, 'the status change is audited');
        $this->assertSame(55, (int) $status->actor_user_id, 'also when the service, not the controller, writes the entry');
        $this->assertSame(0, DB::table('platform_audit_log')->where('module', 'alumni')->whereNull('actor_user_id')->count(), 'no alumni entry is anonymous');

        [, $b] = $this->go(C::class, 'index', ['q' => 'Rita']);
        $this->assertGreaterThanOrEqual(2, count($b['data']['rows']), 'the viewer can search by who did it');
    }

    public function test_an_unknown_user_still_gets_an_entry_with_their_id(): void
    {
        $a = $this->alum('Nameless');   // no tbluser table in this database at all
        $this->go(AlumniDonationApiController::class, 'store', ['alumni_id' => $a->id, 'amount' => 100], 7, 'Accountant');

        $row = DB::table('platform_audit_log')->where('action', 'record')->first();
        $this->assertNotNull($row, 'a failed name lookup must not drop the audit row');
        $this->assertSame(55, (int) $row->actor_user_id);
        $this->assertSame('User 55', $row->actor_name);
    }

    public function test_real_actions_show_up_and_the_log_is_read_only(): void
    {
        $a = $this->alum('Audited');
        $this->go(AlumniDonationApiController::class, 'store', ['alumni_id' => $a->id, 'amount' => 100, 'payment_mode' => 'cash', 'received_on' => now()->toDateString()], 7, 'Accountant');

        [, $b] = $this->go(C::class, 'index', ['component' => 'alumni.donations']);
        $this->assertSame(['record'], array_column($b['data']['rows'], 'action'), 'a donation recorded by an Accountant is visible to the Admin');

        $writes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/alumni/audit') && array_diff($r->methods(), ['GET', 'HEAD']) !== []);
        $this->assertCount(0, $writes, 'the audit log has no write endpoints');
    }
}
