<?php

namespace Tests\Feature;

use App\Http\Controllers\api\FeesMenuCategoryApiController;
use App\Http\Controllers\api\ModuleMenuCategoryApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The category navigation feed every module reads.
 *
 * `fees_menu_categories` has carried a category bar for 64 modules — each row
 * naming its own `/modules/<module_name>/<category_key>` route and the level-2
 * `tblmenumaster` row the module IS — while only two modules could read it,
 * because Fees and Teach/Learn were the only ones with a controller of their
 * own. ModuleMenuCategoryApiController takes the module from the request
 * instead. These assertions are what stop that regressing to a class per
 * module, and what stop the two existing controllers being broken by the
 * shared code they now hand down.
 *
 * READ-ONLY BY CONSTRUCTION. Nothing here writes, so there is no transaction
 * wrapper and nothing to roll back; the configuration rows are read exactly as
 * a request would read them.
 */
class ModuleMenuCategoryApiTest extends TestCase
{
    private ModuleMenuCategoryApiController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ModuleMenuCategoryApiController();
    }

    /** @return array<string,mixed> the `data` block of a JSON response */
    private function feed(array $params): array
    {
        $request = Request::create('/api/modules/menu-categories', 'GET', $params);
        $payload = json_decode($this->controller->index($request)->getContent(), true);

        $this->assertSame(1, $payload['status'] ?? null, 'The feed did not report success.');

        return $payload['data'];
    }

    /** A caller the feed will answer for: any user with an institute. */
    private function caller(): array
    {
        $user = DB::table('tbluser')->select('id', 'sub_institute_id')->whereNotNull('sub_institute_id')->first();
        if ($user === null) {
            $this->markTestSkipped('No users in this database to ask the feed as.');
        }

        return [
            'sub_institute_id' => $user->sub_institute_id,
            'user_id' => $user->id,
            'user_profile_name' => 'ADMIN',
        ];
    }

    public function test_session_context_is_required(): void
    {
        $request = Request::create('/api/modules/menu-categories', 'GET', ['module_name' => 'student']);
        $response = $this->controller->index($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_the_directory_names_every_configured_module_and_the_menu_row_it_is(): void
    {
        $data = $this->feed($this->caller());

        $this->assertNotEmpty($data['modules'], 'No modules are configured — the category bar cannot resolve.');
        $this->assertNull($data['module'], 'No module was asked for, so none should have been resolved.');
        $this->assertSame([], $data['categories']);

        foreach ($data['modules'] as $entry) {
            $this->assertNotSame('', $entry['module_name'], 'A directory entry has no slug.');
            $this->assertGreaterThan(0, $entry['level2_menu_id'], $entry['module_name'].' is not tied to a menu row.');
            // The label is what the sidebar shows, so it comes from
            // tblmenumaster and not from the configuration row.
            $this->assertArrayHasKey('label', $entry);
            $this->assertArrayHasKey('link', $entry);
        }

        $slugs = array_column($data['modules'], 'module_name');
        $this->assertSame(array_unique($slugs), $slugs, 'Two modules share a slug, so one would shadow the other.');
    }

    public function test_a_module_resolves_by_slug_and_by_the_menu_row_the_user_clicked(): void
    {
        $caller = $this->caller();
        $first = $this->feed($caller)['modules'][0];

        $bySlug = $this->feed($caller + ['module_name' => $first['module_name']]);
        $this->assertSame($first['module_name'], $bySlug['module']['module_name']);

        // The shell knows only which tblmenumaster row is selected. Both paths
        // must land on the same module, or the bar and the URL disagree.
        $byMenuId = $this->feed($caller + ['level2_menu_id' => $first['level2_menu_id']]);
        $this->assertSame($first['module_name'], $byMenuId['module']['module_name']);
        $this->assertSame(
            array_column($bySlug['categories'], 'key'),
            array_column($byMenuId['categories'], 'key'),
        );
    }

    /**
     * The modules that carry no category bar, and therefore no Intelligence
     * entry, by measurement rather than by taste.
     *
     * `circular` was added to `fees_menu_categories` after the bar was seeded and
     * carries exactly one row (`ai-stack`) where every other module carries
     * twelve. It is listed here rather than seeded because completing its bar is
     * a menu change, not an Intelligence one — and because the module has no
     * tenant data to analyse either way: all 33 rows of `circular` sit at demo
     * institute 1 in `syear` 2022. See
     * docs/module-intelligence-coverage.md.
     *
     * Naming them keeps this test a guard rather than a blanket exemption: a
     * module that loses its Intelligence entry still fails, and a NEW module
     * arriving without a bar also fails, which forces the classification instead
     * of letting it pass unnoticed.
     */
    private const MODULES_WITHOUT_A_CATEGORY_BAR = ['circular'];

    public function test_every_configured_module_offers_an_intelligence_category_on_a_route_of_its_own(): void
    {
        $caller = $this->caller();
        $directory = $this->feed($caller)['modules'];

        $seenRoutes = [];
        $withoutBar = [];

        foreach ($directory as $entry) {
            $categories = $this->feed($caller + ['module_name' => $entry['module_name']])['categories'];

            $keys = array_column($categories, 'key');

            if (! in_array('intelligence', $keys, true)) {
                $withoutBar[] = $entry['module_name'];
                continue;
            }

            $this->assertNotEmpty($categories, $entry['module_name'].' resolved but has no categories.');

            foreach ($categories as $category) {
                if ($category['key'] !== 'intelligence') {
                    continue;
                }

                $this->assertNotSame('', $category['route'], $entry['module_name'].' Intelligence has no route.');
                // Two modules pointing at one Intelligence route would mean a
                // menu item opening another module's screen.
                $this->assertArrayNotHasKey(
                    $category['route'],
                    $seenRoutes,
                    $entry['module_name'].' shares its Intelligence route with '.($seenRoutes[$category['route']] ?? ''),
                );
                $seenRoutes[$category['route']] = $entry['module_name'];
            }
        }

        sort($withoutBar);
        $expected = self::MODULES_WITHOUT_A_CATEGORY_BAR;
        sort($expected);

        $this->assertSame(
            $expected,
            $withoutBar,
            'The set of modules with no Intelligence category changed. A module that LOST one is a '
            .'coverage regression; a NEW module that never had one needs classifying in '
            .'docs/module-intelligence-coverage.md before being listed here.',
        );
    }

    /**
     * The three keys the feed must always carry, on every answering path.
     *
     * This is a regression test for a real outage rather than a shape check.
     * `index()` once returned `categories` alone, and the two callers that read
     * the other two keys both fail silently without them: the menu tree maps a
     * level-2 menu id to a module slug through `modules`, and the Intelligence
     * screen reads `module.label` and `module.link` to decide WHICH Intelligence
     * a module is. With those absent, `/modules/<slug>/intelligence` answered
     * "No such module" for every configured module while the endpoint still
     * returned 200 and a correct-looking category list — so nothing looked
     * broken anywhere.
     *
     * The early-return paths are covered too, because that is where it bit: the
     * menu tree asks with NO module named, which is precisely the path that
     * returned a bare empty list.
     */
    public function test_the_feed_always_carries_the_directory_and_the_resolved_module(): void
    {
        $caller = $this->caller();
        $slug = $this->feed($caller)['modules'][0]['module_name'];

        $paths = [
            'no module named' => $caller,
            'a real module' => $caller + ['module_name' => $slug],
            'an unknown module' => $caller + ['module_name' => 'no-such-module-'.uniqid()],
        ];

        foreach ($paths as $why => $params) {
            $data = $this->feed($params);

            foreach (['modules', 'module', 'categories'] as $key) {
                $this->assertArrayHasKey($key, $data, "The feed dropped '$key' when asked with $why.");
            }

            $this->assertNotEmpty($data['modules'], "The directory is empty when asked with $why.");
        }

        // The resolved module is the one asked for, and carries the two fields
        // the Intelligence matcher reads. A null label or link would send every
        // module to the "no Intelligence" notice.
        $resolved = $this->feed($caller + ['module_name' => $slug])['module'];
        $this->assertSame($slug, $resolved['module_name']);
        $this->assertArrayHasKey('label', $resolved);
        $this->assertArrayHasKey('link', $resolved);
    }

    public function test_an_unknown_module_is_answered_rather_than_failed(): void
    {
        // A module with no configuration is a module that keeps its existing
        // flat menu, which is not an error the shell should have to handle.
        $data = $this->feed($this->caller() + ['module_name' => 'no-such-module-'.uniqid()]);

        $this->assertNull($data['module']);
        $this->assertSame([], $data['categories']);
        $this->assertNotEmpty($data['modules']);
    }

    public function test_the_fees_feed_still_answers_for_its_own_module(): void
    {
        // FeesMenuCategoryApiController inherits the rights queries this change
        // widened. Its own response must be unchanged.
        $caller = $this->caller();
        $request = Request::create('/api/fees/menu-categories', 'GET', $caller);
        $payload = json_decode((new FeesMenuCategoryApiController())->index($request)->getContent(), true);

        $this->assertSame(1, $payload['status']);
        $this->assertArrayHasKey('categories', $payload['data']);
        $this->assertNotEmpty($payload['data']['categories'], 'Fees lost its own category bar.');
    }
}
