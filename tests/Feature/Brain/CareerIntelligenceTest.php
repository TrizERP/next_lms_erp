<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\CareerIntelligence;
use App\Brain\Intelligence\CareerSignalRules;
use App\Brain\Intelligence\ModuleIntegrationService;
use App\Brain\Intelligence\ModuleSignalBridge;
use App\Brain\Intelligence\RuleCatalogue;
use App\Brain\Intelligence\RuleModules;
use App\Http\Controllers\Brain\BrainCareerIntelligenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Career Intelligence (Brain module).
 *
 * Reads only counts, and only through a SYNTHETIC tenant id (-1) that no
 * institute can own, so nothing here selects, asserts or prints a real
 * student's row. The invariants are the point: the module is registered on
 * the signal bridge under a collision-free rule namespace, a tenant with no
 * Grade 9-10 cohort gets an honest "unavailable" rather than a zero-coverage
 * finding, and the career tables are never written to.
 */
class CareerIntelligenceTest extends TestCase
{
    private function requireDatabase(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('vivek_erp is not reachable: '.$e->getMessage());
        }
    }

    public function test_career_is_registered_on_the_signal_bridge(): void
    {
        $this->assertArrayHasKey('career', ModuleSignalBridge::MODULES);
        [$analytics, $rules] = ModuleSignalBridge::MODULES['career'];
        $this->assertSame(CareerIntelligence::class, $analytics);
        $this->assertSame(CareerSignalRules::class, $rules);
        $this->assertSame('mod_career_stale_evidence', ModuleSignalBridge::ruleKey('career', 'stale_evidence'));
        $this->assertSame('students', RuleModules::moduleFor('mod_career_stale_evidence'));
    }

    public function test_only_the_coverage_rule_carries_an_approved_cause(): void
    {
        // The human-approval gate: a rule with no approved cause raises a signal
        // with evidence and no explanation. Three of the four are meant to.
        $this->assertArrayHasKey('mod_career_evidence_coverage_gap', RuleCatalogue::CAUSES);
        foreach (['aspiration_without_evidence', 'stale_evidence', 'revised_evidence'] as $rule) {
            $this->assertArrayNotHasKey(ModuleSignalBridge::ruleKey('career', $rule), RuleCatalogue::CAUSES);
        }
    }

    public function test_a_tenant_with_no_cohort_is_unavailable_not_a_zero_coverage_finding(): void
    {
        $this->requireDatabase();

        $analytics = new CareerIntelligence('-1', '2025');
        $coverage = $analytics->coverage();

        $this->assertFalse($coverage['available']);
        $this->assertNotEmpty($coverage['reason']);
        $this->assertNull($analytics->position());
        $this->assertSame([], $analytics->bySource());
        $this->assertSame([], (new CareerSignalRules($analytics, '2025'))->run()['findings']);
    }

    public function test_the_bridge_raises_nothing_for_a_synthetic_tenant(): void
    {
        $this->requireDatabase();

        $analytics = new CareerIntelligence('-1', '2025');
        $this->assertSame([], (new CareerSignalRules($analytics, '2025'))->run()['ruleStatus']);
    }

    public function test_payload_keeps_its_contract_when_there_is_no_data(): void
    {
        $this->requireDatabase();

        $request = Request::create('/api/brain/-1/career/intelligence', 'GET', ['syear' => '2025']);
        $request->attributes->set('tenantId', '-1');
        $request->attributes->set('auth.tenantId', '-1');
        $request->attributes->set('auth.userId', '0');
        $request->attributes->set('brain.payload', ['is_student' => false]);

        $payload = json_decode((new BrainCareerIntelligenceController())->index($request)->getContent(), true);

        foreach (['coverage', 'summary', 'position', 'breakdowns', 'findings', 'recommendations', 'dataQuality', 'ruleStatus'] as $key) {
            $this->assertArrayHasKey($key, $payload);
        }
        $this->assertFalse($payload['coverage']['available']);
        $this->assertFalse($payload['summary']['available']);
        $this->assertSame([], $payload['findings']);
    }

    public function test_integration_tab_counts_career_links(): void
    {
        $this->requireDatabase();

        $result = (new ModuleIntegrationService())->getModuleIntegrations('career', -1, '2025');

        $this->assertSame('career', $result['module']);
        $this->assertSame('Career Intelligence', $result['module_label']);
        $this->assertSame(3, $result['summary']['total_integrations']);
        $this->assertSame(0, $result['summary']['active_connections']);
    }

    public function test_career_sources_are_never_written_to(): void
    {
        // The module is read-only over the Career ledger: no insert/update/delete
        // against the career tables appears in any file this module owns.
        $files = [
            app_path('Brain/Intelligence/CareerIntelligence.php'),
            app_path('Brain/Intelligence/CareerSignalRules.php'),
            app_path('Http/Controllers/Brain/BrainCareerIntelligenceController.php'),
        ];
        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('/->(insert|update|delete|upsert|truncate)\s*\(/i', file_get_contents($file), $file);
        }
    }
}
