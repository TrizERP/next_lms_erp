<?php

namespace Tests\Feature\Dashboard;

use App\Http\Controllers\api\RoleWorkspaceApiController;
use ReflectionClass;
use Tests\TestCase;

/**
 * The role workspace decides which figures a role may see, on the server. These tests write and
 * read nothing: the endpoint is checked without a token, and the role rules are checked on the
 * pure resolver and the fixed widget lists.
 */
class RoleWorkspaceTest extends TestCase
{
    public function test_endpoint_refuses_a_request_with_no_token(): void
    {
        $this->postJson('api/role-workspace/summary')->assertStatus(401);
    }

    public function test_fees_panel_refuses_a_request_with_no_token(): void
    {
        $this->postJson('api/role-workspace/fees')->assertStatus(401);
    }

    public function test_only_money_roles_may_open_the_fees_panel(): void
    {
        $roles = (new ReflectionClass(RoleWorkspaceApiController::class))->getReflectionConstant('FEES_ROLES')->getValue();

        foreach (['peon', 'librarian', 'store_keeper', 'counsellor', 'exam_department', 'workspace'] as $role) {
            $this->assertNotContains($role, $roles, "{$role} must not open the fees panel");
        }
    }

    public function test_profile_names_resolve_to_their_dashboard(): void
    {
        $cases = [
            'Principal' => 'principal',
            'Vice Principal' => 'vice_principal',
            'Clerk' => 'clerk',
            'Sr.Clerk' => 'clerk',
            'JR. CLERK' => 'clerk',
            'Assistant Clerk' => 'assistant_clerk',
            'Assistant Admin' => 'assistant_admin',
            'Counseller' => 'counsellor',
            'Librarian' => 'librarian',
            'LIBRERIAN' => 'librarian',
            'Store Keeper' => 'store_keeper',
            'STORE-KEEPER' => 'store_keeper',
            'Exam Department' => 'exam_department',
            'Trustee' => 'trustee',
            'Admin' => 'admin',
            'School admin' => 'admin',
            'Peon' => 'peon',
            // Unknown names get the plain workspace, never a wider dashboard.
            'Watchman' => 'workspace',
            '' => 'workspace',
        ];

        foreach ($cases as $name => $role) {
            $this->assertSame($role, RoleWorkspaceApiController::roleKeyFor($name), "profile \"{$name}\"");
        }
    }

    public function test_every_widget_a_role_is_given_exists(): void
    {
        $widgets = (new ReflectionClass(RoleWorkspaceApiController::class))->getReflectionConstant('WIDGETS')->getValue();

        foreach ($widgets as $role => $list) {
            foreach ($list as $widget) {
                $this->assertTrue(
                    method_exists(RoleWorkspaceApiController::class, 'w_'.$widget),
                    "role {$role} asks for widget {$widget}, which has no provider",
                );
            }
        }
    }

    public function test_money_and_staff_figures_stay_out_of_the_front_line_roles(): void
    {
        $widgets = (new ReflectionClass(RoleWorkspaceApiController::class))->getReflectionConstant('WIDGETS')->getValue();

        // Fee totals, receipts and leave figures are for leadership and the fees desk only.
        foreach (['peon', 'librarian', 'store_keeper', 'workspace'] as $role) {
            foreach (['fees_today', 'fee_trend', 'recent_fee_receipts', 'petty_cash_month', 'leaves_pending', 'staff_on_leave_today', 'staff_total', 'fees_period', 'absent_today', 'followups_due', 'pending_approvals', 'staff_on_leave_list', 'exam_results'] as $sensitive) {
                $this->assertNotContains($sensitive, $widgets[$role], "{$role} must not receive {$sensitive}");
            }
        }
    }

    public function test_every_role_in_the_blueprint_has_a_dashboard_label(): void
    {
        foreach (config('role_dashboards.roles') as $key => $role) {
            $this->assertNotEmpty($role['label'], $key);
            $this->assertNotEmpty($role['profiles'], $key);
        }
    }
}
