<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The alumni menu + rights migrations write into shared tables (tblmenumaster,
 * tblgroupwise_rights), so they are proven here on in-memory sqlite: they create the tree,
 * grant only the intended profiles, and are safe to run twice.
 */
class AlumniMenuMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a real database');

        Schema::create('tblmenumaster', function ($t) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->string('menu_title')->nullable();
            $t->string('description')->nullable();
            $t->integer('parent_menu_id')->default(0);
            $t->integer('level')->default(1);
            $t->integer('status')->default(1);
            $t->integer('sort_order')->default(0);
            $t->string('link')->nullable();
            $t->string('icon')->nullable();
            $t->string('sub_institute_id')->nullable();
            $t->string('client_id')->nullable();
            $t->string('menu_type')->nullable();
            $t->string('site_map_name')->nullable();
            $t->string('menu_path')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('school_detail', function ($t) {
            $t->increments('id');
        });
        Schema::create('tbluserprofilemaster', function ($t) {
            $t->increments('id');
            $t->string('name');
            $t->integer('status')->default(1);
            $t->integer('sub_institute_id');
        });
        Schema::create('tblgroupwise_rights', function ($t) {
            $t->increments('id');
            $t->integer('menu_id');
            $t->integer('profile_id');
            $t->integer('sub_institute_id');
            $t->integer('can_view')->default(0);
            $t->integer('can_add')->default(0);
            $t->integer('can_edit')->default(0);
            $t->integer('can_delete')->default(0);
            $t->timestamp('created_at')->nullable();
        });

        DB::table('school_detail')->insert([['id' => 1], ['id' => 7]]);
        DB::table('tblmenumaster')->insert(['id' => 1, 'name' => 'Institute ERP', 'level' => 1, 'parent_menu_id' => 0, 'client_id' => '5', 'sub_institute_id' => '1,7', 'link' => 'x.index']);
        DB::table('tblmenumaster')->insert(['name' => 'Hostel', 'level' => 2, 'parent_menu_id' => 1, 'sort_order' => 7, 'link' => 'javascript:void(0);']);
        DB::table('tbluserprofilemaster')->insert([
            ['id' => 1, 'name' => 'Admin', 'sub_institute_id' => 7],
            ['id' => 2, 'name' => 'Teacher', 'sub_institute_id' => 7],
            ['id' => 3, 'name' => 'Alumni Coordinator', 'sub_institute_id' => 7],
            ['id' => 4, 'name' => 'Student', 'sub_institute_id' => 7],
            ['id' => 5, 'name' => 'Accountant', 'sub_institute_id' => 7],
        ]);
    }

    private function migrate(): void
    {
        (require base_path('database/migrations/2026_10_10_100100_add_alumni_management_menu.php'))->up();
        (require base_path('database/migrations/2026_10_10_100200_grant_alumni_management_menu_rights.php'))->up();
        (require base_path('database/migrations/2026_10_10_100400_move_alumni_menu_under_institute_erp.php'))->up();
        (require base_path('database/migrations/2026_10_10_100500_add_alumni_batches_directory_menu.php'))->up();
        (require base_path('database/migrations/2026_10_11_100100_add_alumni_phase_c_menu.php'))->up();
        (require base_path('database/migrations/2026_10_12_100300_add_alumni_phase_d_menu.php'))->up();
        (require base_path('database/migrations/2026_10_13_100100_add_alumni_donations_menu.php'))->up();
        (require base_path('database/migrations/2026_10_13_100300_add_alumni_phase_f_menu.php'))->up();
    }

    public function test_menu_tree_is_created_with_rights_for_admin_profiles_only(): void
    {
        $this->migrate();

        $module = DB::table('tblmenumaster')->where('name', 'Alumni Management')->first();
        $this->assertNotNull($module);
        $this->assertSame(2, (int) $module->level);               // a module inside Institute ERP...
        $this->assertSame(1, (int) $module->parent_menu_id);
        $this->assertSame('javascript:void(0);', $module->link);
        $this->assertSame(8, (int) $module->sort_order);          // ...after Hostel (7)
        $this->assertSame('1,7', $module->sub_institute_id);
        $this->assertSame('5', $module->client_id);
        $this->assertSame([3], DB::table('tblmenumaster')->where('parent_menu_id', $module->id)->distinct()->pluck('level')->map(fn ($l) => (int) $l)->all());

        $children = DB::table('tblmenumaster')->where('parent_menu_id', $module->id)->pluck('link')->all();
        $this->assertEqualsCanonicalizing(['alumni/dashboard', 'alumni/list', 'alumni/add', 'alumni/pending_verification', 'alumni/directory', 'alumni/batches', 'alumni/engagement', 'alumni/mentorship', 'alumni/opportunities', 'alumni/communication', 'alumni/events', 'alumni/surveys', 'alumni/donations', 'alumni/reports', 'alumni/import', 'alumni/audit'], $children);

        $granted = DB::table('tblgroupwise_rights')->distinct()->pluck('profile_id')->all();
        $this->assertEqualsCanonicalizing([1, 3, 5], $granted);          // Admin + Alumni Coordinator + Accountant (donations only); never Teacher or Student

        // the student page has no menu entry: students reach it from a link on their dashboard, so a Student profile gets no rights at all
        $this->assertSame(0, DB::table('tblmenumaster')->where('link', 'alumni/student')->count());
        $this->assertSame(0, DB::table('tblgroupwise_rights')->where('profile_id', 4)->count());
        $this->assertSame(2 * 13 + 2 + 1 + 2 + 2 * 2 + 1 - 2, DB::table('tblgroupwise_rights')->count());   // (module + 6 earlier screens + 6 later staff screens) x 2 staff profiles, plus the Accountant's 2, the Admin's donations row and audit row
    }

    public function test_donations_menu_goes_only_to_finance_profiles(): void
    {
        $this->migrate();

        $menu = (int) DB::table('tblmenumaster')->where('link', 'alumni/donations')->value('id');
        $this->assertGreaterThan(0, $menu);
        $this->assertEqualsCanonicalizing([1, 5], DB::table('tblgroupwise_rights')->where('menu_id', $menu)->pluck('profile_id')->map(fn ($i) => (int) $i)->all());   // Admin + Accountant
        $this->assertSame(0, DB::table('tblgroupwise_rights')->where('menu_id', $menu)->whereIn('profile_id', [2, 3, 4])->count(), 'Teacher, Alumni Coordinator and Student never see it');
        $this->assertSame(0, (int) DB::table('tblgroupwise_rights')->where('menu_id', $menu)->sum('can_delete'));
        // the Accountant sees the module row (to reach the child) but none of the other alumni screens
        $module = (int) DB::table('tblmenumaster')->where('name', 'Alumni Management')->value('id');
        $this->assertEqualsCanonicalizing([$module, $menu], DB::table('tblgroupwise_rights')->where('profile_id', 5)->pluck('menu_id')->map(fn ($i) => (int) $i)->all());
    }

    public function test_audit_log_menu_goes_only_to_admin_type_profiles(): void
    {
        $this->migrate();

        $audit = (int) DB::table('tblmenumaster')->where('link', 'alumni/audit')->value('id');
        $this->assertGreaterThan(0, $audit);
        $rows = DB::table('tblgroupwise_rights')->where('menu_id', $audit)->get();
        $this->assertSame([1], $rows->pluck('profile_id')->map(fn ($i) => (int) $i)->all(), 'Admin only - not the coordinator, accountant, teacher or student');
        $this->assertSame([1, 0, 0, 0], [(int) $rows[0]->can_view, (int) $rows[0]->can_add, (int) $rows[0]->can_edit, (int) $rows[0]->can_delete], 'view only');
        // reports and import follow the Alumni List rights (Admin + Coordinator), never the Accountant or the Student
        foreach (['alumni/reports', 'alumni/import'] as $link) {
            $id = (int) DB::table('tblmenumaster')->where('link', $link)->value('id');
            $this->assertEqualsCanonicalizing([1, 3], DB::table('tblgroupwise_rights')->where('menu_id', $id)->pluck('profile_id')->map(fn ($i) => (int) $i)->all(), $link);
        }
    }

    public function test_super_admin_gets_exactly_the_two_missing_screens_and_nothing_else_changes(): void
    {
        DB::table('tbluserprofilemaster')->insert([
            ['id' => 6, 'name' => 'Super Admin', 'sub_institute_id' => 0, 'status' => 1],      // platform-level, as in production
            ['id' => 7, 'name' => 'SUPER ADMIN', 'sub_institute_id' => 0, 'status' => 1],
            ['id' => 8, 'name' => 'Super Admin', 'sub_institute_id' => 0, 'status' => 0],      // inactive: skipped
            ['id' => 9, 'name' => 'Super Admin Assistant', 'sub_institute_id' => 0, 'status' => 1],   // a different profile: skipped
        ]);
        $this->migrate();
        $donations = (int) DB::table('tblmenumaster')->where('link', 'alumni/donations')->value('id');
        $audit = (int) DB::table('tblmenumaster')->where('link', 'alumni/audit')->value('id');
        $this->assertSame(0, DB::table('tblgroupwise_rights')->whereIn('profile_id', [6, 7])->whereIn('menu_id', [$donations, $audit])->count(), 'the earlier migrations really skipped them');
        $beforeAll = DB::table('tblgroupwise_rights')->count();
        $beforeOthers = DB::table('tblgroupwise_rights')->whereNotIn('profile_id', [6, 7])->orderBy('id')->get()->toJson();

        $run = fn () => (require base_path('database/migrations/2026_10_14_100100_grant_super_admin_donations_audit_menu.php'))->up();
        $run();

        $rows = DB::table('tblgroupwise_rights')->whereIn('profile_id', [6, 7])->whereIn('menu_id', [$donations, $audit])->orderBy('profile_id')->orderBy('menu_id')->get();
        $this->assertCount(4, $rows, '2 active Super Admin profiles x 2 screens');
        foreach ($rows as $r) {
            $this->assertSame(0, (int) $r->sub_institute_id, 'stored with the profile\'s own institute id, like their other rights');
            $expected = (int) $r->menu_id === $donations ? [1, 1, 1, 0] : [1, 0, 0, 0];
            $this->assertSame($expected, [(int) $r->can_view, (int) $r->can_add, (int) $r->can_edit, (int) $r->can_delete]);
        }
        $this->assertSame($beforeAll + 4, DB::table('tblgroupwise_rights')->count());
        $this->assertSame($beforeOthers, DB::table('tblgroupwise_rights')->whereNotIn('profile_id', [6, 7])->orderBy('id')->get()->toJson(), 'no other profile\'s rights changed');
        $this->assertSame(0, DB::table('tblgroupwise_rights')->whereIn('profile_id', [8, 9])->count(), 'inactive and look-alike profiles got nothing');

        $run();   // idempotent
        $this->assertSame($beforeAll + 4, DB::table('tblgroupwise_rights')->count());
    }

    public function test_running_twice_creates_no_duplicates(): void
    {
        $this->migrate();
        $menus = DB::table('tblmenumaster')->count();
        $rights = DB::table('tblgroupwise_rights')->count();

        $this->migrate();

        $this->assertSame($menus, DB::table('tblmenumaster')->count());
        $this->assertSame($rights, DB::table('tblgroupwise_rights')->count());
    }

    public function test_reorder_puts_screens_in_workflow_order_adds_conversion_and_changes_nothing_else(): void
    {
        $this->migrate();
        $before = DB::table('tblmenumaster')->where('level', 3)->get()->keyBy('link');
        $listRights = DB::table('tblgroupwise_rights')->where('menu_id', $before['alumni/list']->id)->get(['profile_id', 'can_view', 'can_add', 'can_edit', 'can_delete'])->map(fn ($r) => (array) $r)->all();
        $module = (int) DB::table('tblmenumaster')->where('name', 'Alumni Management')->value('id');

        $run = fn () => (require base_path('database/migrations/2026_10_15_100000_reorder_alumni_menu_and_add_conversion.php'))->up();
        $run();
        $run();   // idempotent

        $this->assertSame(
            ['alumni/dashboard', 'alumni/list', 'alumni/add', 'alumni/pending_verification', 'alumni/conversion', 'alumni/import', 'alumni/directory', 'alumni/batches', 'alumni/mentorship',
                'alumni/opportunities', 'alumni/engagement', 'alumni/events', 'alumni/communication', 'alumni/surveys', 'alumni/donations', 'alumni/reports', 'alumni/audit'],
            DB::table('tblmenumaster')->where('parent_menu_id', $module)->orderBy('sort_order')->pluck('link')->all(),
        );
        $this->assertSame(1, DB::table('tblmenumaster')->where('link', 'alumni/conversion')->count(), 'no duplicate on re-run');
        $this->assertSame(17, DB::table('tblmenumaster')->where('parent_menu_id', $module)->distinct()->count('sort_order'), 'every sort_order unique');

        // the new screen gets exactly the Alumni List rights; nothing else about existing rows changed
        $conv = (int) DB::table('tblmenumaster')->where('link', 'alumni/conversion')->value('id');
        $this->assertEqualsCanonicalizing($listRights, DB::table('tblgroupwise_rights')->where('menu_id', $conv)->get(['profile_id', 'can_view', 'can_add', 'can_edit', 'can_delete'])->map(fn ($r) => (array) $r)->all());
        $this->assertSame(0, DB::table('tblgroupwise_rights')->where('menu_id', $conv)->whereIn('profile_id', [2, 4, 5])->count(), 'never Teacher, Student or Accountant');
        foreach ($before as $link => $row) {
            $after = DB::table('tblmenumaster')->where('id', $row->id)->first();
            $this->assertSame([$row->name, $row->link, $row->parent_menu_id, $row->status], [$after->name, $after->link, $after->parent_menu_id, $after->status], $link);
        }
    }
}
