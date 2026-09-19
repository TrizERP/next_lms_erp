<?php

namespace Tests\Feature\AI\Attendance;

use App\Agents\Attendance\AttendanceAgent;
use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentRegistry;
use App\Domain\AI\Lifecycle\Modules\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The wiring that makes the Attendance Agent reachable at all.
 *
 * WHY A TEST ABOUT ROWS RATHER THAN BEHAVIOUR
 *
 * The Fees agent existed in code for some time before anybody could run it: the class was
 * written, the module was bound to it in config, and the one thing missing was the
 * manifest row — so `AgentRegistry` returned null and every fee question answered
 * `agent · skipped`. Nothing in the code was wrong and no test failed. This file is the
 * test that would have failed.
 *
 * Each assertion below names a link in the chain between "somebody asks about attendance"
 * and "a person is asked to approve a follow-up". Break any one and the module silently
 * degrades to a read.
 *
 * READ-ONLY. It asserts against whatever this estate holds and writes nothing.
 */
class AttendanceAgentRegistrationTest extends TestCase
{
    private const AGENT_KEY = 'k12_attendance';

    private const WORKFLOW_KEY = 'attendance_followup';

    private const SIGNAL_KEY = 'attendance_low_rate';

    public function test_the_agent_class_implements_the_agent_interface(): void
    {
        $this->assertInstanceOf(Agent::class, app(AttendanceAgent::class));
    }

    public function test_an_active_manifest_exists_and_names_a_runnable_class(): void
    {
        $manifest = app(AgentRegistry::class)->find(self::AGENT_KEY);

        $this->assertNotNull($manifest, 'No active manifest for ' . self::AGENT_KEY . '.');
        $this->assertSame(AttendanceAgent::class, $manifest->runnerClass);
    }

    /**
     * The agent may analyse and recommend. It may not act.
     *
     * This is the guardrail that keeps a family from being contacted by a sweep that ran
     * overnight, and it is a column rather than a code path — so it is asserted here.
     */
    public function test_the_manifest_cannot_execute_actions(): void
    {
        $manifest = app(AgentRegistry::class)->find(self::AGENT_KEY);

        $this->assertNotNull($manifest);
        $this->assertSame('recommend', $manifest->maxVerb->value ?? (string) $manifest->maxVerb);
        $this->assertFalse((bool) $manifest->mayExecuteActions);
    }

    /**
     * The workflow key must be readable as a key, not as JSON.
     *
     * `AgentManifest` splits this column on commas. The fees migration originally wrote a
     * JSON array here, which arrived as one key literally named `["fees_collection"]` and
     * had every recommendation refused as unauthorised. The comment in the attendance
     * migration says so; this asserts it.
     */
    public function test_the_manifest_is_authorised_for_the_attendance_workflow(): void
    {
        $manifest = app(AgentRegistry::class)->find(self::AGENT_KEY);

        $this->assertNotNull($manifest);
        $this->assertContains(self::WORKFLOW_KEY, $manifest->authorizedWorkflowKeys);
    }

    public function test_the_workflow_is_published_and_pauses_for_a_person(): void
    {
        $definition = DB::table('workflow_definitions')
            ->where('workflow_key', self::WORKFLOW_KEY)
            ->where('status', 1)
            ->first();

        $this->assertNotNull($definition, 'The attendance follow-up workflow is not registered.');
        $this->assertSame(1, (int) $definition->requires_approval, 'The workflow does not require approval.');
        $this->assertNotNull($definition->active_version_id, 'The workflow has no published version.');

        $version = DB::table('workflow_versions')->where('id', $definition->active_version_id)->first();

        $this->assertNotNull($version);
        $this->assertSame('published', $version->status);

        $steps = json_decode((string) $version->steps, true);

        $this->assertIsArray($steps);
        $this->assertNotEmpty($steps);
        // An approval step is the whole mechanism. Without one the workflow would run to
        // completion on its own and "human approval" would be a label on nothing.
        $this->assertSame('approval', $steps[0]['type'] ?? null);
    }

    public function test_the_signal_the_agent_raises_is_defined_with_a_detector(): void
    {
        $definition = DB::table('ai_signal_definitions')
            ->where('signal_key', self::SIGNAL_KEY)
            ->where('status', 1)
            ->first();

        $this->assertNotNull($definition, 'The low-attendance signal is not defined.');
        $this->assertSame(
            \App\Domain\Attendance\Risk\LowAttendanceDetector::class,
            $definition->detector_class
        );
        $this->assertTrue(class_exists((string) $definition->detector_class));
    }

    /**
     * The module registry verifies its bindings against the tables before claiming depth.
     *
     * So a module reporting the agent key is a module whose manifest and workflow both
     * actually resolved — which is a stronger statement than reading the config file.
     */
    public function test_the_attendance_module_claims_the_agent_and_the_workflow(): void
    {
        $module = app(ModuleRegistry::class)->find('attendance');

        $this->assertNotNull($module, 'Attendance is not a registered lifecycle module.');
        $this->assertSame(self::AGENT_KEY, $module->agentKey);
        $this->assertSame(self::WORKFLOW_KEY, $module->workflowKey);
        $this->assertContains('attendance.overview', $module->mcpTools);
    }

    /**
     * The permission key is registered AND has a menu row to hold a grant.
     *
     * Registering the key alone is what the Fees screen shipped with: `PermissionService`
     * could not resolve it to a row, `allow_when_unresolved => false` denied everybody
     * including a full administrator, and the screen told people to ask for a right that
     * did not exist. Both halves are asserted, because either one alone is the bug.
     */
    public function test_the_agents_attendance_right_is_registered_and_grantable(): void
    {
        $registry = config('rbac_modules.modules');

        $this->assertArrayHasKey('agents.attendance', $registry, 'agents.attendance is not registered.');

        $links = $registry['agents.attendance']['links'] ?? [];
        $this->assertNotEmpty($links);

        $row = DB::table('tblmenumaster')
            ->whereIn('link', $links)
            ->where('status', 1)
            ->first();

        $this->assertNotNull(
            $row,
            'agents.attendance resolves to no active menu row, so nobody can be granted it.'
        );
    }

    /** Fees keeps its own key and row. Registering a second one must not disturb it. */
    public function test_the_fees_agent_right_is_untouched(): void
    {
        $registry = config('rbac_modules.modules');

        $this->assertArrayHasKey('agents.fees', $registry);
        $this->assertSame(['ai_agents.fees', 'ai_agents'], $registry['agents.fees']['links']);
    }
}
