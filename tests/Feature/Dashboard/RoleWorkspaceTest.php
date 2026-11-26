<?php

namespace Tests\Feature\Dashboard;

use App\Http\Controllers\api\RoleWorkspaceApiController;
use App\Services\Dashboard\CustomWidgetCatalog;
use App\Services\Dashboard\QueryWidget;
use App\Services\Dashboard\WidgetLibrary;
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

    public function test_assigning_follow_ups_refuses_a_request_with_no_token(): void
    {
        $this->postJson('api/role-workspace/follow-up')->assertStatus(401);
        $this->postJson('api/role-workspace/staff-search')->assertStatus(401);
    }

    public function test_only_management_roles_may_assign_follow_ups(): void
    {
        $roles = (new ReflectionClass(RoleWorkspaceApiController::class))->getReflectionConstant('FOLLOWUP_ROLES')->getValue();

        $this->assertEqualsCanonicalizing(['admin', 'assistant_admin', 'principal', 'vice_principal'], $roles);
        foreach (['trustee', 'clerk', 'teacher', 'student', 'peon', 'librarian', 'counsellor'] as $role) {
            $this->assertNotContains($role, $roles, "{$role} must not assign follow-ups");
        }
    }

    public function test_teachers_and_students_only_get_their_own_widgets(): void
    {
        $widgets = (new ReflectionClass(RoleWorkspaceApiController::class))->getReflectionConstant('WIDGETS')->getValue();

        foreach (['teacher', 'student'] as $role) {
            foreach (['fees_today', 'fees_period', 'fee_trend', 'staff_total', 'leaves_pending', 'pending_approvals', 'petty_cash_month', 'intelligence_loop', 'recent_fee_receipts'] as $schoolWide) {
                $this->assertNotContains($schoolWide, $widgets[$role], "{$role} must not receive {$schoolWide}");
            }
        }
        // A student sees nothing about other students.
        foreach (['absent_today', 'chronic_absentees', 'support_students', 'class_mastery', 'birthdays', 'students_total', 'signals'] as $others) {
            $this->assertNotContains($others, $widgets['student'], "student must not receive {$others}");
        }
    }

    public function test_setting_a_target_refuses_a_request_with_no_token(): void
    {
        $this->postJson('api/role-workspace/targets')->assertStatus(401);
    }

    public function test_only_the_roles_that_own_the_goals_may_set_targets(): void
    {
        $roles = (new ReflectionClass(RoleWorkspaceApiController::class))->getReflectionConstant('TARGET_ROLES')->getValue();

        $this->assertEqualsCanonicalizing(['trustee', 'admin', 'principal'], $roles);
        foreach (['clerk', 'teacher', 'student', 'peon', 'librarian', 'counsellor', 'assistant_admin'] as $role) {
            $this->assertNotContains($role, $roles, "{$role} must not set targets");
        }
    }

    public function test_every_target_metric_has_a_sane_range(): void
    {
        $metrics = (new ReflectionClass(RoleWorkspaceApiController::class))->getReflectionConstant('TARGET_METRICS')->getValue();

        foreach ($metrics as $key => $m) {
            $this->assertLessThan($m['max'], $m['min'], $key);
            if ($m['unit'] === '%') {
                $this->assertSame(100, $m['max'], "{$key} is a percentage");
            }
        }
    }

    public function test_the_board_view_is_for_the_trustee_only(): void
    {
        $widgets = (new ReflectionClass(RoleWorkspaceApiController::class))->getReflectionConstant('WIDGETS')->getValue();

        foreach (['health', 'decisions_for_me', 'management_delivery', 'data_trust'] as $board) {
            $this->assertContains($board, $widgets['trustee']);
            foreach (['peon', 'clerk', 'teacher', 'student', 'librarian', 'workspace'] as $role) {
                $this->assertNotContains($board, $widgets[$role], "{$role} must not receive {$board}");
            }
        }
    }

    public function test_salary_totals_are_for_the_owners_of_the_budget_only(): void
    {
        $widgets = (new ReflectionClass(RoleWorkspaceApiController::class))->getReflectionConstant('WIDGETS')->getValue();

        foreach (['trustee', 'admin', 'principal'] as $role) {
            $this->assertContains('payroll', $widgets[$role], "{$role} should see salary totals");
        }
        foreach (['vice_principal', 'assistant_admin', 'clerk', 'assistant_clerk', 'teacher', 'student', 'peon', 'librarian', 'store_keeper', 'counsellor', 'exam_department', 'workspace'] as $role) {
            $this->assertNotContains('payroll', $widgets[$role], "{$role} must not receive salary figures");
        }
    }

    public function test_the_dashboard_assistant_refuses_a_request_with_no_token(): void
    {
        foreach (['assistant', 'custom-widgets', 'custom-widgets/remove'] as $path) {
            $this->postJson("api/role-workspace/{$path}")->assertStatus(401);
        }
    }

    public function test_custom_widgets_only_come_from_the_fixed_catalog_and_the_role(): void
    {
        // Only what is in the catalog can be built, whatever the model asks for.
        $this->expectException(\InvalidArgumentException::class);
        CustomWidgetCatalog::validate(['metric' => 'salary'], 'trustee');
    }

    public function test_a_role_cannot_build_a_widget_it_was_not_given(): void
    {
        $this->assertSame([], CustomWidgetCatalog::forRole('teacher'));
        $this->assertSame([], CustomWidgetCatalog::forRole('student'));
        $this->assertArrayNotHasKey('fees', CustomWidgetCatalog::forRole('librarian'));
        $this->assertArrayHasKey('fees', CustomWidgetCatalog::forRole('clerk'));
        $this->assertArrayNotHasKey('attendance', CustomWidgetCatalog::forRole('clerk'));

        $this->expectException(\InvalidArgumentException::class);
        CustomWidgetCatalog::validate(['metric' => 'fees', 'dim' => 'month'], 'teacher');
    }

    public function test_what_a_role_may_ask_the_assistant_for_stays_inside_its_limits(): void
    {
        // A trustee may ask for school-wide counts such as birthdays, but never for lists of named people or the fee receipts.
        $trustee = RoleWorkspaceApiController::askableFor('trustee');
        $this->assertContains('birthdays', $trustee);
        foreach (['absent_today', 'chronic_absentees', 'recent_fee_receipts', 'library_overdue', 'staff_on_leave_list'] as $named) {
            $this->assertNotContains($named, $trustee, "a trustee must not be offered {$named}");
        }

        // Front-line roles get no more than their own dashboard has.
        foreach (['clerk', 'teacher', 'student', 'peon', 'librarian', 'store_keeper', 'counsellor'] as $role) {
            $this->assertSame(RoleWorkspaceApiController::widgetsFor($role), RoleWorkspaceApiController::askableFor($role), "{$role} must not be offered extras");
        }
        $this->assertNotContains('payroll', RoleWorkspaceApiController::askableFor('vice_principal'));
        $this->assertNotContains('payroll', RoleWorkspaceApiController::askableFor('clerk'));
    }

    public function test_new_figures_from_school_data_are_for_leadership_only(): void
    {
        $this->assertEqualsCanonicalizing(['admin', 'assistant_admin', 'principal', 'vice_principal', 'trustee'], QueryWidget::ROLES);
        foreach (['clerk', 'teacher', 'student', 'peon', 'librarian', 'counsellor'] as $role) {
            $this->assertFalse(QueryWidget::allowedFor($role), "{$role} must not build figures from raw school data");
            try {
                QueryWidget::validate(['table' => 'tblstudent', 'measure' => ['fn' => 'count']], $role);
                $this->fail("{$role} was allowed");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_new_figure_refuses_personal_columns_blocked_tables_and_odd_names(): void
    {
        $refused = [
            ['table' => 'tblstudent', 'measure' => ['fn' => 'count'], 'group' => ['column' => 'mobile']],
            ['table' => 'tblstudent', 'measure' => ['fn' => 'count'], 'group' => ['column' => 'first_name']],
            ['table' => 'tblstudent', 'measure' => ['fn' => 'count'], 'filters' => [['column' => 'email', 'op' => '=', 'value' => 'x']]],
            ['table' => 'employee_salary_structures', 'measure' => ['fn' => 'count']],
            ['table' => 'tblstudent; drop table x', 'measure' => ['fn' => 'count']],
            ['table' => 'tblstudent', 'measure' => ['fn' => 'count'], 'group' => ['column' => 'gender; drop table x']],
            ['table' => 'tblstudent', 'measure' => ['fn' => 'sum', 'column' => 'gender']],
        ];
        foreach ($refused as $q) {
            try {
                QueryWidget::validate($q, 'trustee');
                $this->fail('should have been refused: '.json_encode($q));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_every_related_figure_and_subject_entry_exists_in_the_library(): void
    {
        foreach (WidgetLibrary::RELATED as $key => $list) {
            $this->assertTrue(WidgetLibrary::exists($key), "RELATED source {$key} is not a library entry");
            foreach ($list as $target) {
                $this->assertTrue(WidgetLibrary::exists($target), "{$key} points at {$target}, which is not a library entry");
            }
        }
        foreach (WidgetLibrary::TOPICS as $topic => $def) {
            foreach ($def['entries'] as $key) {
                $this->assertTrue(WidgetLibrary::exists($key), "subject {$topic} lists {$key}, which is not a library entry");
            }
            foreach ($def['core'] as $key) {
                $this->assertContains($key, $def['entries'], "core card {$key} of {$topic} must also be one of its entries");
            }
        }
    }

    public function test_every_library_entry_reads_from_a_real_dashboard_widget(): void
    {
        foreach (array_keys(WidgetLibrary::entries()) as $key) {
            $this->assertTrue(method_exists(RoleWorkspaceApiController::class, 'w_'.WidgetLibrary::widgetOf($key)), "{$key} has no widget behind it");
        }
    }

    public function test_a_chart_the_breakdown_does_not_support_falls_back_to_one_it_does(): void
    {
        $spec = CustomWidgetCatalog::validate(['metric' => 'attendance', 'dim' => 'class', 'chart' => 'donut', 'title' => '<b>x</b>'], 'principal');

        $this->assertSame('bar', $spec['chart']);
        $this->assertSame('x', $spec['title'], 'markup is stripped from titles');
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
            'Teacher' => 'teacher',
            'LMS Teacher' => 'teacher',
            'Class Teacher' => 'teacher',
            'Student' => 'student',
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

        // Brain signals, progress and history are for the roles that own an outcome, not for the front line.
        foreach (['peon', 'workspace'] as $role) {
            foreach (['signals', 'intelligence_loop', 'progress', 'history'] as $intel) {
                $this->assertNotContains($intel, $widgets[$role], "{$role} must not receive {$intel}");
            }
        }

        // Fee totals, receipts and leave figures are for leadership and the fees desk only.
        foreach (['peon', 'librarian', 'store_keeper', 'workspace'] as $role) {
            foreach (['fees_today', 'fee_trend', 'recent_fee_receipts', 'petty_cash_month', 'leaves_pending', 'staff_on_leave_today', 'staff_total', 'fees_period', 'absent_today', 'followups_due', 'pending_approvals', 'staff_on_leave_list', 'exam_results'] as $sensitive) {
                $this->assertNotContains($sensitive, $widgets[$role], "{$role} must not receive {$sensitive}");
            }
        }
    }

    public function test_every_role_given_signals_has_a_relevance_rule(): void
    {
        $widgets = (new ReflectionClass(RoleWorkspaceApiController::class))->getReflectionConstant('WIDGETS')->getValue();

        foreach ($widgets as $role => $list) {
            if (in_array('signals', $list, true)) {
                $this->assertNotEmpty(config("role_dashboards.signals.{$role}"), "{$role} gets signals but has no relevance rule");
            }
        }

        // Trustees see material issues only.
        $this->assertSame('high', config('role_dashboards.signals.trustee.min_severity'));
    }

    public function test_every_role_in_the_blueprint_has_a_dashboard_label(): void
    {
        foreach (config('role_dashboards.roles') as $key => $role) {
            $this->assertNotEmpty($role['label'], $key);
            $this->assertNotEmpty($role['profiles'], $key);
        }
    }
}
