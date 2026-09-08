<?php

namespace Tests\Feature\Security;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Track A + Track D / Day 11 — "Regression test suite for the full slice":
 * proves RBAC is enforced server-side, not just hidden behind frontend UI.
 *
 * `App\Http\Middleware\checkPermission` is the actual enforcement point
 * (Track D Issue 2 wired it onto every Fee Demand/Collection/Receipt
 * route). It resolves a menu from the request's route NAME
 * (`tblmenumaster.link`), then checks `tblindividual_rights` /
 * `tblgroupwise_rights` for that menu+profile+institute, and throws
 * `AuthorizationException` (→ HTTP 403) when the right needed for the
 * request's HTTP method (`can_add` for POST, `can_edit` for PUT,
 * `can_delete` for DELETE, `can_view` otherwise) is missing.
 *
 * Like SessionMiddlewareAuthBypassTest, this uses an ad hoc named route +
 * a matching `tblmenumaster` row so the assertion is about the enforcement
 * mechanism itself, isolated from the real (large, shared) production menu
 * tree - a deliberate choice: real `tblmenumaster.link` values are a mix of
 * route names and raw paths depending on when the row was added, so pinning
 * this test to a real fees route name would make it fragile to menu-table
 * changes made for unrelated reasons.
 */
class RbacEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    private int $institute;

    private int $adminProfileId;

    private int $menuId;

    protected function setUp(): void
    {
        parent::setUp();

        Route::name('test.rbac.probe.')->group(function () {
            Route::middleware(['session', 'check_permissions'])
                ->post('/__test/rbac-probe', fn () => response()->json(['ok' => true]))
                ->name('store');
        });

        $this->institute = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'RBAC Test School',
            'ShortCode' => 'RBC' . random_int(1000, 9999),
            'ContactPerson' => 'Test',
            'Mobile' => '9999999999',
            'Email' => 'test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        DB::table('academic_year')->insert([
            'sub_institute_id' => $this->institute,
            'syear' => 2026,
            'term_id' => 1,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'sort_order' => 1,
        ]);

        $this->adminProfileId = (int) DB::table('tbluserprofilemaster')->insertGetId([
            'parent_id' => 0,
            'name' => 'Accountant',
            'description' => 'Accountant',
            'sort_order' => 1,
            'status' => 1,
            'sub_institute_id' => $this->institute,
        ]);

        $this->menuId = (int) DB::table('tblmenumaster')->insertGetId([
            'name' => 'RBAC Test Menu',
            'description' => 'RBAC Test Menu',
            'parent_menu_id' => 0,
            'level' => 1,
            'status' => 1,
            'sort_order' => 1,
            'link' => 'test.rbac.probe.store', // matches the route name above
            'icon' => '',
            'sub_institute_id' => (string) $this->institute,
        ]);
    }

    private function makeUser(): int
    {
        return (int) DB::table('tbluser')->insertGetId([
            'user_name' => 'rbac' . random_int(10000, 99999),
            'password' => bcrypt('secret'),
            'first_name' => 'RBAC',
            'last_name' => 'Probe',
            'email' => 'rbac' . random_int(10000, 99999) . '@example.com',
            'mobile' => '9999999999',
            'user_profile_id' => $this->adminProfileId,
            'join_year' => '2026',
            'sub_institute_id' => $this->institute,
            'is_admin' => 0, // not the session()->get('user_profile_name')=='Super Admin' bypass path.
            'status' => 1,
        ]);
    }

    private function tokenFor(int $userId): string
    {
        $payload = [
            'id' => $userId,
            'sub_institute_id' => $this->institute,
            'user_profile_id' => $this->adminProfileId,
            'is_admin' => 0,
            'is_student' => false,
            'client_id' => null,
        ];

        return JWT::encode($payload, env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));
    }

    public function test_a_user_with_no_rights_row_is_blocked(): void
    {
        $userId = $this->makeUser();
        $token = $this->tokenFor($userId);

        // Deliberately no tblgroupwise_rights/tblindividual_rights row at all.
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/__test/rbac-probe', ['type' => 'API']);

        $response->assertStatus(403);
    }

    public function test_a_user_with_can_add_explicitly_zero_is_blocked(): void
    {
        $userId = $this->makeUser();
        $token = $this->tokenFor($userId);

        DB::table('tblgroupwise_rights')->insert([
            'menu_id' => $this->menuId,
            'profile_id' => $this->adminProfileId,
            'can_view' => 1,
            'can_add' => 0,
            'can_edit' => 0,
            'can_delete' => 0,
            'sub_institute_id' => $this->institute,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/__test/rbac-probe', ['type' => 'API']);

        $response->assertStatus(403);
    }

    public function test_a_user_with_can_add_granted_is_allowed(): void
    {
        $userId = $this->makeUser();
        $token = $this->tokenFor($userId);

        DB::table('tblgroupwise_rights')->insert([
            'menu_id' => $this->menuId,
            'profile_id' => $this->adminProfileId,
            'can_view' => 1,
            'can_add' => 1,
            'can_edit' => 0,
            'can_delete' => 0,
            'sub_institute_id' => $this->institute,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/__test/rbac-probe', ['type' => 'API']);

        $response->assertStatus(200)->assertJson(['ok' => true]);
    }

    /**
     * `tblindividual_rights` is a per-user override on top of the group's
     * `tblgroupwise_rights` row - checkPermission prefers it when present
     * (see checkPermission.php's `!empty($individual) ? $individual : $group`).
     * A group grant must not leak through when this specific user has been
     * individually denied.
     */
    public function test_an_individual_denial_overrides_a_group_grant(): void
    {
        $userId = $this->makeUser();
        $token = $this->tokenFor($userId);

        DB::table('tblgroupwise_rights')->insert([
            'menu_id' => $this->menuId,
            'profile_id' => $this->adminProfileId,
            'can_view' => 1,
            'can_add' => 1,
            'can_edit' => 1,
            'can_delete' => 1,
            'sub_institute_id' => $this->institute,
        ]);

        DB::table('tblindividual_rights')->insert([
            'menu_id' => $this->menuId,
            'profile_id' => $this->adminProfileId,
            'user_id' => $userId,
            'can_view' => 1,
            'can_add' => 0,
            'can_edit' => 0,
            'can_delete' => 0,
            'sub_institute_id' => $this->institute,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/__test/rbac-probe', ['type' => 'API']);

        $response->assertStatus(403);
    }

    /**
     * A different institute's identical menu_id/profile_id must not grant
     * access here - rights are tenant-scoped by sub_institute_id, exactly
     * like the fees data itself.
     */
    public function test_rights_granted_in_a_different_institute_do_not_apply_here(): void
    {
        $otherInstitute = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'RBAC Other Institute',
            'ShortCode' => 'RBO' . random_int(1000, 9999),
            'ContactPerson' => 'Test',
            'Mobile' => '9999999999',
            'Email' => 'test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        DB::table('tblgroupwise_rights')->insert([
            'menu_id' => $this->menuId,
            'profile_id' => $this->adminProfileId,
            'can_view' => 1,
            'can_add' => 1,
            'can_edit' => 1,
            'can_delete' => 1,
            'sub_institute_id' => $otherInstitute, // wrong tenant
        ]);

        $userId = $this->makeUser();
        $token = $this->tokenFor($userId);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/__test/rbac-probe', ['type' => 'API']);

        $response->assertStatus(403);
    }
}
