<?php

namespace Tests\Feature;

use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Workspace\ModuleSuggestions;
use App\Services\Mcp\McpRequestContext;
use Tests\TestCase;

/**
 * What the assistant offers to be asked next, and why it must follow the module.
 *
 * Every turn that could not suggest its own follow-up used to fall back to two
 * sentences about academic risk. On the Student Profiles screen that reads as help; on
 * the Fees screen it invited somebody looking at unpaid invoices to go and read about
 * struggling students, which is not a follow-up to their question and misrepresents
 * what the assistant does there.
 *
 * Two rules, both asserted below:
 *
 *   - **Suggestions come from the module**, read from `ai_suggestions` — the same rows
 *     the workspace panel offers as opening prompts, so a module's vocabulary is
 *     curated once and appears in both places.
 *   - **The risk journey is only offered where the agent exists.** "Start with the risk
 *     scan" is true advice in a module that binds the agent and false everywhere else.
 */
class ModuleFollowUpsTest extends TestCase
{
    public function test_a_module_suggests_its_own_questions(): void
    {
        $fees = app(ModuleSuggestions::class)->forModule('fees', $this->scope());

        $this->assertNotEmpty($fees, 'The fees module has curated prompts; they should be offered.');

        foreach ($fees as $prompt) {
            $this->assertStringNotContainsStringIgnoringCase(
                'academic risk',
                $prompt,
                'A fees turn must not send the reader to the risk journey.'
            );
        }
    }

    public function test_two_modules_do_not_suggest_the_same_thing(): void
    {
        $suggestions = app(ModuleSuggestions::class);

        $this->assertNotEquals(
            $suggestions->forModule('fees', $this->scope()),
            $suggestions->forModule('admissions', $this->scope()),
            'Suggestions that do not vary by module are not module-aware.'
        );
    }

    public function test_a_module_nobody_curated_offers_nothing_rather_than_something_wrong(): void
    {
        $this->assertSame([], app(ModuleSuggestions::class)->forModule('no_such_module', $this->scope()));
        $this->assertSame([], app(ModuleSuggestions::class)->forModule(null, $this->scope()));
    }

    public function test_a_prompt_already_offered_this_turn_is_not_repeated(): void
    {
        $scope = $this->scope();
        $all = app(ModuleSuggestions::class)->forModule('fees', $scope);

        $this->assertNotEmpty($all);

        $remaining = app(ModuleSuggestions::class)->forModule('fees', $scope, [$all[0]]);

        $this->assertNotContains($all[0], $remaining);
    }

    public function test_the_risk_journey_is_offered_only_where_the_agent_exists(): void
    {
        $withAgent = $this->context($this->module('student', agent: true));
        $withAgent->suggestRiskJourney('Which students are at academic risk?');

        $this->assertSame(['Which students are at academic risk?'], $withAgent->followUps());

        $withoutAgent = $this->context($this->module('fees', agent: false));
        $withoutAgent->suggestRiskJourney('Which students are at academic risk?');

        $this->assertSame(
            [],
            $withoutAgent->followUps(),
            'A module with no agent cannot honestly recommend starting the risk scan.'
        );
    }

    // --------------------------------------------------------------- fixtures

    private function context(ModuleCapability $module): StageContext
    {
        return new StageContext(question: 'anything', scope: $this->scope(), module: $module);
    }

    private function module(string $key, bool $agent): ModuleCapability
    {
        return new ModuleCapability(
            key: $key,
            label: ucfirst($key),
            capabilities: ['conversational' => true, 'agent' => $agent],
            mcpTools: [],
            agentKey: $agent ? 'k12_academic_risk' : null,
        );
    }

    private function scope(): McpRequestContext
    {
        return new McpRequestContext(
            userId: 1,
            role: 'admin',
            selectedInstituteId: 1,
            allowedInstituteIds: [1],
            userProfileId: null,
            clientId: null,
            academicYear: 2026,
            termId: null,
            isAdmin: true,
            isStudent: false,
        );
    }
}
