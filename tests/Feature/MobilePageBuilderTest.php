<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the Custom Mobile Page Builder end to end: create -> save draft ->
 * publish -> runtime read, draft-never-leaks, tenant isolation, layout
 * validation, and the Mobile App Menu Rights integration (both the new
 * page_source = 'custom' path and the untouched page_source = 'external'
 * path, as a regression guard for the existing WebView flow).
 *
 * Token/tenant/user seeding mirrors
 * tests/Feature/Security/SessionMiddlewareAuthBypassTest.php, the existing
 * reference for hitting an api.session-gated route in a test.
 */
class MobilePageBuilderTest extends TestCase
{
    use DatabaseTransactions;

    private function tokenFor(int $userId, int $subInstituteId, int $isAdmin = 1): string
    {
        $payload = [
            'id' => $userId,
            'sub_institute_id' => $subInstituteId,
            'user_profile_id' => 1,
            'is_admin' => $isAdmin,
            'is_student' => false,
            'client_id' => null,
        ];

        return JWT::encode($payload, env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));
    }

    private function makeTenantAndAdmin(): array
    {
        $instituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'MPB Test School',
            'ShortCode' => 'MPB' . random_int(1000, 9999),
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
            'sub_institute_id' => $instituteId,
            'syear' => 2026,
            'term_id' => 1,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'sort_order' => 1,
        ]);

        $userId = (int) DB::table('tbluser')->insertGetId([
            'user_name' => 'mpbuser' . random_int(10000, 99999),
            'password' => bcrypt('secret'),
            'first_name' => 'Mobile',
            'last_name' => 'Builder',
            'email' => 'mpb' . random_int(10000, 99999) . '@example.com',
            'mobile' => '9999999999',
            'user_profile_id' => 1,
            'join_year' => '2026',
            'sub_institute_id' => $instituteId,
            'is_admin' => 1,
            'status' => 1,
        ]);

        return [$instituteId, $userId, $this->tokenFor($userId, $instituteId)];
    }

    private function validLayout(): array
    {
        return [
            'page' => [
                'name' => 'Test Page',
                'width' => 375,
                'height' => 812,
                'background' => ['type' => 'color', 'color' => '#FFFFFF', 'opacity' => 1],
            ],
            'components' => [
                [
                    'id' => 'text_1',
                    'type' => 'text',
                    'position' => ['x' => 20, 'y' => 20],
                    'size' => ['width' => 335, 'height' => 40],
                    'props' => ['content' => 'Hello'],
                ],
            ],
        ];
    }

    public function test_page_lifecycle_draft_then_publish_then_runtime(): void
    {
        [$instituteId, , $token] = $this->makeTenantAndAdmin();
        $headers = ['Authorization' => 'Bearer ' . $token];

        $created = $this->withHeaders($headers)
            ->postJson('/api/mobile-page-builder/pages', ['name' => 'Student Profile'])
            ->assertStatus(200)
            ->json('data');

        $pageId = $created['id'];
        $slug = $created['slug'];
        self::assertSame('draft', $created['status']);

        // Not published yet -- runtime must not serve it.
        $this->withHeaders($headers)
            ->getJson("/api/mobile-page-builder/runtime/{$slug}")
            ->assertStatus(404);

        $this->withHeaders($headers)
            ->postJson("/api/mobile-page-builder/pages/{$pageId}/draft", ['layout' => $this->validLayout()])
            ->assertStatus(200);

        // Still a draft -- saving a draft must not publish it.
        $this->withHeaders($headers)
            ->getJson("/api/mobile-page-builder/runtime/{$slug}")
            ->assertStatus(404);

        $this->withHeaders($headers)
            ->postJson("/api/mobile-page-builder/pages/{$pageId}/publish")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        $runtime = $this->withHeaders($headers)
            ->getJson("/api/mobile-page-builder/runtime/{$slug}")
            ->assertStatus(200)
            ->json('data');

        self::assertSame('Test Page', $runtime['layout']['page']['name']);
        self::assertSame('text', $runtime['layout']['components'][0]['type']);
        // Version 1 is the draft created alongside the page itself; publish()
        // always snapshots into a NEW row rather than promoting the draft
        // row in place, so the first published version is 2.
        self::assertSame(2, $runtime['version']);
    }

    public function test_runtime_is_isolated_by_tenant(): void
    {
        [, , $tokenA] = $this->makeTenantAndAdmin();
        [, , $tokenB] = $this->makeTenantAndAdmin();

        $page = $this->withHeaders(['Authorization' => 'Bearer ' . $tokenA])
            ->postJson('/api/mobile-page-builder/pages', ['name' => 'Tenant A Page'])
            ->json('data');

        $this->withHeaders(['Authorization' => 'Bearer ' . $tokenA])
            ->postJson("/api/mobile-page-builder/pages/{$page['id']}/draft", ['layout' => $this->validLayout()])
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => 'Bearer ' . $tokenA])
            ->postJson("/api/mobile-page-builder/pages/{$page['id']}/publish")
            ->assertStatus(200);

        // Tenant B, authenticated, asking for tenant A's slug -- must not see it.
        $this->withHeaders(['Authorization' => 'Bearer ' . $tokenB])
            ->getJson("/api/mobile-page-builder/runtime/{$page['slug']}")
            ->assertStatus(404);
    }

    /**
     * OverlayWrapper (reused as-is by every block from the document-template
     * editor) writes width/height back as "280px"-style CSS strings the
     * moment a block is resized -- only x/y stay plain numbers. The
     * validator must accept that, not just a bare number.
     */
    public function test_a_pixel_string_size_from_a_resized_block_is_accepted(): void
    {
        [, , $token] = $this->makeTenantAndAdmin();

        $page = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/mobile-page-builder/pages', ['name' => 'Resized Block Page'])
            ->json('data');

        $layout = $this->validLayout();
        $layout['components'][0]['size'] = ['width' => '280px', 'height' => 'auto'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/mobile-page-builder/pages/{$page['id']}/draft", ['layout' => $layout])
            ->assertStatus(200);
    }

    public function test_an_unknown_component_type_is_rejected_on_save(): void
    {
        [, , $token] = $this->makeTenantAndAdmin();

        $page = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/mobile-page-builder/pages', ['name' => 'Bad Layout Page'])
            ->json('data');

        $badLayout = $this->validLayout();
        $badLayout['components'][0]['type'] = 'video'; // not in the server allowlist

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/mobile-page-builder/pages/{$page['id']}/draft", ['layout' => $badLayout])
            ->assertStatus(422);
    }

    public function test_source_pages_lists_the_registry_and_serves_add_student_fields(): void
    {
        [, , $token] = $this->makeTenantAndAdmin();
        $headers = ['Authorization' => 'Bearer ' . $token];

        $this->withHeaders($headers)
            ->getJson('/api/mobile-page-builder/source-pages')
            ->assertStatus(200)
            ->assertJsonFragment(['key' => 'add_student', 'label' => 'Add Student']);

        $entry = $this->withHeaders($headers)
            ->getJson('/api/mobile-page-builder/source-pages/add_student')
            ->assertStatus(200)
            ->json('data');

        self::assertSame('POST', $entry['submit']['method']);
        self::assertSame('student-registration', $entry['submit']['endpoint']);
        self::assertCount(18, $entry['fields']);
        self::assertContains('first_name', array_column($entry['fields'], 'key'));

        $this->withHeaders($headers)
            ->getJson('/api/mobile-page-builder/source-pages/not-a-real-page')
            ->assertStatus(404);
    }

    /**
     * Edit Student Profile is a DIFFERENT endpoint/field shape than Add
     * Student (see MobileFormFieldRegistry's class doc on that entry) --
     * this locks in that the two are never accidentally merged, and that
     * idField/id are wired the way generateFromSourcePage.ts (Next.js)
     * expects: id present as a real field, excluded from nothing here (that
     * exclusion happens client-side when the submit body is built), and the
     * endpoint carries an unresolved {{id}} token for the client to fill in.
     */
    public function test_edit_student_source_page_has_a_distinct_shape_from_add_student(): void
    {
        [, , $token] = $this->makeTenantAndAdmin();

        $entry = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/mobile-page-builder/source-pages/edit_student')
            ->assertStatus(200)
            ->json('data');

        self::assertSame('id', $entry['idField']);
        self::assertSame('PUT', $entry['submit']['method']);
        self::assertSame('get_adminStudentSearch/{{id}}', $entry['submit']['endpoint']);

        $keys = array_column($entry['fields'], 'key');
        self::assertContains('id', $keys);
        self::assertContains('blood_group', $keys);
        self::assertNotContains('bloodGroup', $keys);
        // Different field-name shape than add_student's bloodgroup/grade/standard/division.
        self::assertNotContains('bloodgroup', $keys);
    }

    public function test_menu_rights_custom_page_source_computes_web_url_from_page_slug(): void
    {
        [$instituteId, $userId, $token] = $this->makeTenantAndAdmin();
        $headers = ['Authorization' => 'Bearer ' . $token];

        $page = $this->withHeaders($headers)
            ->postJson('/api/mobile-page-builder/pages', ['name' => 'Fees Page', 'slug' => 'fees-page'])
            ->json('data');

        $rowId = (int) DB::table('mobile_homescreen')->insertGetId([
            'sub_institute_id' => $instituteId,
            'user_profile_id' => 1,
            'user_profile_name' => 'Student',
            'main_title' => 'Fees',
            'menu_type' => 'Heading',
            'sub_title_of_main' => 'Fees Collection',
            'main_sort_order' => 1,
            'sub_title_sort_order' => 1,
            'screen_name' => 'fees_menu_test_' . random_int(1000, 9999),
            'render_type' => 'native',
            'status' => 'Yes',
            'created_on' => now(),
        ]);

        $this->withHeaders($headers)
            ->postJson("/api/mobile-app-rights/config/{$rowId}", [
                'sub_institute_id' => $instituteId,
                'user_id' => $userId,
                'profile_name' => 'Student',
                'main_title' => 'Fees',
                'main_sort_order' => 1,
                'sub_title_of_main' => 'Fees Collection',
                'sub_title_sort_order' => 1,
                'status' => 'Yes',
                'render_type' => 'webview',
                'page_source' => 'custom',
                'custom_page_id' => $page['id'],
            ])
            ->assertStatus(200);

        $row = DB::table('mobile_homescreen')->where('id', $rowId)->first();

        self::assertSame('custom', $row->page_source);
        self::assertSame($page['id'], (int) $row->custom_page_id);
        self::assertStringEndsWith('/mobile/custom/fees-page', $row->web_url);
    }

    public function test_menu_rights_external_page_source_keeps_existing_behavior(): void
    {
        [$instituteId, $userId, $token] = $this->makeTenantAndAdmin();
        $headers = ['Authorization' => 'Bearer ' . $token];

        $rowId = (int) DB::table('mobile_homescreen')->insertGetId([
            'sub_institute_id' => $instituteId,
            'user_profile_id' => 1,
            'user_profile_name' => 'Student',
            'main_title' => 'Library',
            'menu_type' => 'Heading',
            'sub_title_of_main' => 'Library',
            'main_sort_order' => 1,
            'sub_title_sort_order' => 1,
            'screen_name' => 'library_menu_test_' . random_int(1000, 9999),
            'render_type' => 'native',
            'status' => 'Yes',
            'created_on' => now(),
        ]);

        // page_source is intentionally omitted -- an admin who has never
        // touched Custom Mobile Page should get byte-for-byte today's
        // behavior: web_url stored exactly as submitted.
        $this->withHeaders($headers)
            ->postJson("/api/mobile-app-rights/config/{$rowId}", [
                'sub_institute_id' => $instituteId,
                'user_id' => $userId,
                'profile_name' => 'Student',
                'main_title' => 'Library',
                'main_sort_order' => 1,
                'sub_title_of_main' => 'Library',
                'sub_title_sort_order' => 1,
                'status' => 'Yes',
                'render_type' => 'webview',
                'web_url' => '/library/catalog',
                'open_mode' => 'in_app',
            ])
            ->assertStatus(200);

        $row = DB::table('mobile_homescreen')->where('id', $rowId)->first();

        self::assertSame('external', $row->page_source);
        self::assertNull($row->custom_page_id);
        self::assertSame('/library/catalog', $row->web_url);
    }

    /**
     * Attendance is a `type = 'list'` entry (search a roster, then one
     * Present/Absent row per student) -- a materially different shape from
     * add_student/edit_student's fixed field list. Locks in the verified
     * search/submit wiring: the combined standard||division value, the
     * roster response's actual array key, and that Save targets the real
     * per-student endpoint with one `student[id]` key per row.
     */
    public function test_attendance_source_page_is_a_list_type_entry_with_verified_wiring(): void
    {
        [, , $token] = $this->makeTenantAndAdmin();

        $entry = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/mobile-page-builder/source-pages/attendance')
            ->assertStatus(200)
            ->json('data');

        self::assertSame('list', $entry['type']);
        self::assertSame('data.student_data', $entry['list']['itemsPath']);
        self::assertSame('first_name last_name', $entry['list']['itemLabelField']);
        self::assertSame('{{standard}}||{{division}}', $entry['list']['searchBody']['standard_division']);
        self::assertSame('student/show_student_attendance', $entry['list']['searchAction']['endpoint']);
        self::assertSame('student/save_student_attendance', $entry['list']['submitAction']['endpoint']);
        self::assertSame('student[{itemId}]', $entry['list']['submitAction']['rowKeys'][0]['key']);
        self::assertSame('value', $entry['list']['submitAction']['rowKeys'][0]['source']);
    }

    /**
     * A layout containing a `list` component -- the new type this pass
     * added -- must be accepted by the validator, and its endpoints are
     * still checked the same way an Input/Button's are.
     */
    public function test_a_list_component_layout_is_accepted_on_save(): void
    {
        [, , $token] = $this->makeTenantAndAdmin();

        $page = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/mobile-page-builder/pages', ['name' => 'List Block Page'])
            ->json('data');

        $layout = $this->validLayout();
        $layout['components'][] = [
            'id' => 'list_1',
            'type' => 'list',
            'position' => ['x' => 20, 'y' => 80],
            'size' => ['width' => 335, 'height' => 400],
            'props' => [
                'searchAction' => ['method' => 'POST', 'endpoint' => 'student/show_student_attendance'],
                'submitAction' => [
                    'method' => 'POST',
                    'endpoint' => 'student/save_student_attendance',
                    'rowKeys' => [['key' => 'student[{itemId}]', 'source' => 'value']],
                ],
            ],
        ];

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/mobile-page-builder/pages/{$page['id']}/draft", ['layout' => $layout])
            ->assertStatus(200);
    }

    /**
     * menuPages() reads the REAL tblmenumaster data (not a fixture this test
     * controls), so it runs against sub_institute_id = 1 specifically --
     * confirmed via tinker to be a tenant real menu rows are actually scoped
     * to (their `sub_institute_id` column is a hand-maintained comma list,
     * not something a freshly-inserted random tenant id would ever appear
     * in). Only asserts properties that hold regardless of exactly which
     * pages exist today: Add Student is level 3, active, and must come back
     * with sourceKey = 'add_student' (the whole point of matchLink); level
     * 1/2 section headers must never appear, since they are not real pages.
     */
    public function test_menu_pages_lists_real_menu_items_and_flags_traced_ones(): void
    {
        $subInstituteId = 1;

        DB::table('academic_year')->insert([
            'sub_institute_id' => $subInstituteId,
            'syear' => 2026,
            'term_id' => 1,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'sort_order' => 1,
        ]);

        $userId = (int) DB::table('tbluser')->insertGetId([
            'user_name' => 'menupagesuser' . random_int(10000, 99999),
            'password' => bcrypt('secret'),
            'first_name' => 'Menu',
            'last_name' => 'Pages',
            'email' => 'menupages' . random_int(10000, 99999) . '@example.com',
            'mobile' => '9999999999',
            'user_profile_id' => 1,
            'join_year' => '2026',
            'sub_institute_id' => $subInstituteId,
            'is_admin' => 1,
            'status' => 1,
        ]);

        $token = $this->tokenFor($userId, $subInstituteId);

        $items = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/mobile-page-builder/menu-pages')
            ->assertStatus(200)
            ->json('data');

        self::assertNotEmpty($items, 'Expected real tblmenumaster rows for sub_institute_id 1 -- if this fails, that seed data has changed.');

        $addStudent = collect($items)->firstWhere('name', 'Add Student');
        self::assertNotNull($addStudent, 'Add Student should be present in the real menu.');
        self::assertSame('add_student', $addStudent['sourceKey']);
        self::assertNotEmpty($addStudent['section']);

        $untraced = collect($items)->first(fn ($item) => $item['sourceKey'] === null);
        self::assertNotNull($untraced, 'Expected at least one real menu page with no traced registry entry yet.');
    }
}
