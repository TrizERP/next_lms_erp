<?php

namespace Tests\Feature\AI;

use App\Domain\KnowledgeGraph\KnowledgeGraphSettingsResolver;
use App\Http\Controllers\api\ModuleMenuCategoryApiController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for 2026-11-21's follow-up: switching a module's
 * Knowledge Graph off must drop the "Knowledge Graph" button itself from the
 * module's level-3 category bar (`AbstractMenuCategoryApiController::index()`,
 * which `use-module-level3-nav.ts` reads to build it), not merely leave the
 * button in place pointing at an empty tab — the behaviour before this fix.
 *
 * Every module already carries a real `knowledge-graph` row in
 * `fees_menu_categories` (seeded by
 * 2026_11_02_320000_seed_knowledge_graph_category_for_all_modules.php), so this
 * exercises a real module from the live feed rather than fabricating rows.
 * Wrapped in a transaction (unlike the read-only `ModuleMenuCategoryApiTest`)
 * because this test writes a `kg_module_settings` override.
 */
class KnowledgeGraphMenuCategoryTest extends TestCase
{
    use DatabaseTransactions;

    private string $subInstituteId;

    private string $userId;

    private string $moduleName;

    protected function setUp(): void
    {
        parent::setUp();

        $user = DB::table('tbluser')->select('id', 'sub_institute_id')->whereNotNull('sub_institute_id')->first();
        if ($user === null) {
            $this->markTestSkipped('No users in this database to ask the feed as.');
        }

        $this->subInstituteId = (string) $user->sub_institute_id;
        $this->userId = (string) $user->id;

        $moduleName = DB::table('fees_menu_categories')
            ->where('category_key', 'knowledge-graph')
            ->where('status', 1)
            ->value('module_name');

        if (! is_string($moduleName) || $moduleName === '') {
            $this->markTestSkipped('No module carries a seeded Knowledge Graph category to test against.');
        }

        $this->moduleName = $moduleName;
    }

    /** @return array<int,array<string,mixed>> the `categories` block for $this->moduleName */
    private function categories(): array
    {
        $request = Request::create('/api/modules/menu-categories', 'GET', [
            'module_name' => $this->moduleName,
            'sub_institute_id' => $this->subInstituteId,
            'user_id' => $this->userId,
            'user_profile_name' => 'ADMIN',
        ]);

        $payload = json_decode(
            app(ModuleMenuCategoryApiController::class)->index($request)->getContent(),
            true
        );

        $this->assertSame(1, $payload['status'] ?? null, 'The feed did not report success.');

        return $payload['data']['categories'];
    }

    public function test_the_button_is_present_by_default(): void
    {
        $this->assertContains('knowledge-graph', array_column($this->categories(), 'key'));
    }

    public function test_disabling_the_module_drops_the_button_entirely(): void
    {
        $this->assertContains('knowledge-graph', array_column($this->categories(), 'key'), 'Precondition: the bar should start with the button.');

        app(KnowledgeGraphSettingsResolver::class)->save($this->moduleName, $this->subInstituteId, false);

        $keys = array_column($this->categories(), 'key');
        $this->assertNotContains('knowledge-graph', $keys, 'The button stayed in the bar after the module was switched off.');
        // Disabling the Knowledge Graph tab must not take any other tab with it.
        $this->assertNotEmpty($keys, 'Every other category disappeared along with Knowledge Graph.');
    }

    public function test_re_enabling_the_module_restores_the_button(): void
    {
        app(KnowledgeGraphSettingsResolver::class)->save($this->moduleName, $this->subInstituteId, false);
        $this->assertNotContains('knowledge-graph', array_column($this->categories(), 'key'));

        app(KnowledgeGraphSettingsResolver::class)->clear($this->moduleName, $this->subInstituteId);

        $this->assertContains('knowledge-graph', array_column($this->categories(), 'key'));
    }
}
