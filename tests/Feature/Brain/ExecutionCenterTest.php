<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\ExecutionCenter;
use App\Http\Controllers\Brain\BrainExecutionCenterController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Execution Center against the real hpbrain_* schema, on synthetic institutes.
 *
 * Every row is written under a made-up tenant id inside a transaction that is rolled back after each
 * test (DatabaseTransactions), so no real institute's data is read, changed or left behind.
 */
class ExecutionCenterTest extends TestCase
{
    use DatabaseTransactions;

    private string $a = 'xc-test-a';
    private string $b = 'xc-test-b';

    private function id(): string
    {
        return (string) Str::uuid();
    }

    /** A recommendation -> decision -> execution chain, with finding evidence on the way. */
    private function chain(string $tenant, array $o = []): array
    {
        $signal = $this->id();
        $step = $this->id();
        $rec = $this->id();
        $dec = $o['decisionId'] ?? $this->id();
        $exec = $o['executionId'] ?? $this->id();
        $now = $o['createdAt'] ?? now()->subDays($o['ageDays'] ?? 1)->format('Y-m-d H:i:s');

        DB::table('hpbrain_signals')->insert(['id' => $signal, 'tenant_id' => $tenant, 'source' => 'test', 'created_by' => 't']);
        DB::table('hpbrain_reasoning_steps')->insert(['id' => $step, 'tenant_id' => $tenant, 'signal_id' => $signal, 'step_order' => 1, 'description' => 'step', 'created_by' => 't']);
        DB::table('hpbrain_recommendations')->insert(['id' => $rec, 'tenant_id' => $tenant, 'reasoning_step_id' => $step, 'title' => $o['title'] ?? 'Assign a head to every department',
            'description' => "Why: Departments have no head.\n\nBasis: 10 of 12.", 'created_by' => 't']);
        DB::table('hpbrain_decisions')->insert(['id' => $dec, 'tenant_id' => $tenant, 'recommendation_id' => $rec, 'decided_by' => $o['decidedBy'] ?? '7', 'rationale' => $o['rationale'] ?? 'Approved', 'status' => 'approved']);
        if (empty($o['noExecution'])) {
            DB::table('hpbrain_eso_executions')->insert(['id' => $exec, 'tenant_id' => $tenant, 'eso_id' => $this->id(), 'decision_id' => $dec,
            'status' => $o['status'] ?? 'completed', 'executed_by' => $o['executedBy'] ?? '7', 'error' => $o['error'] ?? null, 'created_date' => $now]);
        }

        $evidence = [];
        for ($i = 0; $i < ($o['findingEvidence'] ?? 2); $i++) {
            $evidence[] = $e = $this->id();
            DB::table('hpbrain_evidence')->insert(['id' => $e, 'tenant_id' => $tenant, 'signal_id' => $signal, 'source' => 'lms', 'hash' => md5($e), 'content' => json_encode(['label' => "Class average $i", 'value' => '62.6%']), 'created_by' => 't']);
        }

        return ['exec' => $exec, 'decision' => $dec, 'rec' => $rec, 'evidence' => $evidence, 'signal' => $signal];
    }

    private function outcome(string $tenant, string $decision, string $exec, array $o = []): string
    {
        $id = $o['id'] ?? $this->id();
        DB::table('hpbrain_outcomes')->insert([
            'id' => $id, 'tenant_id' => $tenant, 'decision_id' => $decision, 'result' => $o['result'] ?? 'success',
            'metrics' => json_encode(['executionId' => $exec]), 'kpis' => json_encode($o['kpis'] ?? new \stdClass()),
            'evidence_ids' => json_encode($o['evidenceIds'] ?? []), 'feedback' => $o['feedback'] ?? 'Done', 'confidence' => 0.9,
            'created_by' => $o['createdBy'] ?? '7',
        ]);

        return $id;
    }

    private function build(string $tenant, array $filters = []): array
    {
        return (new ExecutionCenter($tenant))->build($filters);
    }

    private function request(string $tenant, array $body = []): Request
    {
        $r = Request::create('/x', 'POST', $body);
        $r->attributes->set('tenantId', $tenant);
        $r->attributes->set('auth.tenantId', $tenant);
        $r->attributes->set('auth.userId', '7');

        return $r;
    }

    public function test_counts_follow_the_stored_statuses_and_sample_rows_are_kept_apart(): void
    {
        $this->chain($this->a, ['status' => 'queued']);
        $this->chain($this->a, ['status' => 'queued']);
        $this->chain($this->a, ['status' => 'completed']);
        $this->chain($this->a, ['status' => 'failed', 'error' => 'API timeout']);
        $this->chain($this->a, ['status' => 'completed', 'executedBy' => 'demo-seed']);

        $p = $this->build($this->a);

        $this->assertSame(4, $p['health']['real']['total']);
        $this->assertSame(2, $p['health']['real']['queued']);
        $this->assertSame(1, $p['health']['real']['completed']);
        $this->assertSame(1, $p['health']['real']['failed']);
        $this->assertSame(0, $p['health']['real']['blocked']);
        $this->assertSame(1, $p['health']['sample']['completed'], 'sample rows are counted separately');
        $this->assertSame(5, $p['history']['total']);
    }

    public function test_records_carry_meaningful_titles_reasons_and_people_not_just_uuids(): void
    {
        $c = $this->chain($this->a, ['title' => 'Assign a head to every department']);

        $r = $this->build($this->a)['history']['data'][0];

        $this->assertSame('Assign a head to every department', $r['title']);
        $this->assertSame('Departments have no head.', $r['reason']);
        $this->assertSame('Approved', $r['decision']['rationale']);
        $this->assertSame($c['exec'], $r['technical']['executionId'], 'the identifier stays available for troubleshooting');
        $this->assertNotSame($r['id'], $r['title']);
    }

    public function test_outcomes_join_to_executions_through_the_decision(): void
    {
        $linked = $this->chain($this->a);
        $this->outcome($this->a, $linked['decision'], $linked['exec']);
        $noOutcome = $this->chain($this->a);
        // An outcome whose decision has no execution in this institute.
        $orphan = $this->chain($this->a, ['noExecution' => true]);
        $this->outcome($this->a, $orphan['decision'], $orphan['exec']);

        $p = $this->build($this->a);

        $this->assertSame(2, $p['verification']['outcomes']['total']);
        $this->assertSame(1, $p['verification']['outcomes']['linkedToAnExecution']);
        $this->assertSame(1, $p['verification']['outcomes']['notLinked']);
        $byId = array_column($p['history']['data'], null, 'id');
        $this->assertNotNull($byId[$linked['exec']]['outcome']);
        $this->assertNull($byId[$noOutcome['exec']]['outcome']);
        $this->assertSame('no_outcome', $byId[$noOutcome['exec']]['outcomeState']);
    }

    public function test_completed_is_not_verified_until_measured_and_evidenced(): void
    {
        $reported = $this->chain($this->a);
        $this->outcome($this->a, $reported['decision'], $reported['exec']);

        $measured = $this->chain($this->a);
        $this->outcome($this->a, $measured['decision'], $measured['exec'], ['kpis' => ['measuredBefore' => 32, 'measuredAfter' => 22, 'measuredChange' => 10, 'unit' => 'vehicles']]);

        $verified = $this->chain($this->a);
        $this->outcome($this->a, $verified['decision'], $verified['exec'], ['kpis' => ['measuredBefore' => 32, 'measuredAfter' => 22]]);
        DB::table('hpbrain_eso_execution_evidence')->insert(['tenant_id' => $this->a, 'execution_id' => $verified['exec'], 'evidence_id' => $verified['evidence'][0]]);

        $p = $this->build($this->a);
        $byId = array_column($p['history']['data'], null, 'id');

        $this->assertSame('reported', $byId[$reported['exec']]['outcomeState']);
        $this->assertSame('measured', $byId[$measured['exec']]['outcomeState']);
        $this->assertSame('verified', $byId[$verified['exec']]['outcomeState']);
        $this->assertSame(3, $p['health']['real']['completed'], 'all three are completed');
        $this->assertSame(1, $p['verification']['real']['verified'], 'but only one is verified');
        $this->assertSame(10.0, $byId[$measured['exec']]['outcome']['measurement']['change']);
        $this->assertSame('vehicles', $byId[$measured['exec']]['outcome']['measurement']['unit']);
    }

    public function test_missing_execution_evidence_is_reported_not_invented(): void
    {
        $c = $this->chain($this->a, ['findingEvidence' => 3]);
        $this->outcome($this->a, $c['decision'], $c['exec']);

        $r = $this->build($this->a)['history']['data'][0];

        $this->assertSame(0, $r['evidence']['executionCount'], 'nothing is auto-linked');
        $this->assertSame(3, $r['evidence']['findingTotal'], 'the finding\'s own evidence is shown separately');
        $this->assertSame('evidence_missing', $r['attention']['kind']);
    }

    public function test_sample_outcomes_are_labelled_and_excluded_from_real_tallies(): void
    {
        $c = $this->chain($this->a, ['executedBy' => 'demo-seed']);
        $this->outcome($this->a, $c['decision'], $c['exec'], ['feedback' => '[Sample] Owner assigned', 'createdBy' => 'demo-seed']);

        $p = $this->build($this->a);

        $this->assertTrue($p['impact'][0]['isSample']);
        $this->assertSame(0, $p['verification']['real']['finished']);
        $this->assertSame(1, $p['verification']['sample']['finished']);
        $this->assertStringContainsString('sample record', $p['impact'][0]['confidenceMeaning']);
    }

    public function test_institutes_cannot_see_each_other(): void
    {
        $mine = $this->chain($this->a);
        $this->outcome($this->a, $mine['decision'], $mine['exec']);
        $this->chain($this->b);

        $a = $this->build($this->a);
        $b = $this->build($this->b);

        $this->assertSame(1, $a['history']['total']);
        $this->assertSame(1, $b['history']['total']);
        $this->assertNotContains($mine['exec'], array_column($b['history']['data'], 'id'));
        $this->assertSame(0, $b['verification']['outcomes']['total']);
        $this->assertNull((new ExecutionCenter($this->b))->find($mine['exec']), 'another institute\'s execution is not found');

        $res = (new BrainExecutionCenterController())->show(Request::create('/x'), $this->b, $mine['exec']);
        $this->assertSame(404, $res->getStatusCode());
    }

    public function test_evidence_can_be_linked_only_from_the_executions_own_finding(): void
    {
        $c = $this->chain($this->a);
        $other = $this->chain($this->a);
        $foreign = $this->chain($this->b);
        $ctl = new BrainExecutionCenterController();

        // Allowed: evidence of the same finding.
        $ok = $ctl->linkEvidence($this->request($this->a, ['evidence_ids' => [$c['evidence'][0]]]), $this->a, $c['exec']);
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertSame(1, json_decode($ok->getContent(), true)['linked']);
        $this->assertSame(1, $this->build($this->a)['history']['data'][array_search($c['exec'], array_column($this->build($this->a)['history']['data'], 'id'))]['evidence']['executionCount']);

        // Idempotent.
        $again = json_decode($ctl->linkEvidence($this->request($this->a, ['evidence_ids' => [$c['evidence'][0]]]), $this->a, $c['exec'])->getContent(), true);
        $this->assertSame(0, $again['linked']);
        $this->assertSame(1, $again['alreadyLinked']);

        // Refused: evidence of a different finding, and evidence of another institute.
        $this->assertSame(422, $ctl->linkEvidence($this->request($this->a, ['evidence_ids' => [$other['evidence'][0]]]), $this->a, $c['exec'])->getStatusCode());
        $this->assertSame(422, $ctl->linkEvidence($this->request($this->a, ['evidence_ids' => [$foreign['evidence'][0]]]), $this->a, $c['exec'])->getStatusCode());

        // Another institute cannot link to this execution at all.
        $this->assertSame(404, $ctl->linkEvidence($this->request($this->b, ['evidence_ids' => [$c['evidence'][0]]]), $this->b, $c['exec'])->getStatusCode());

        // Validation.
        $this->assertSame(422, $ctl->linkEvidence($this->request($this->a, []), $this->a, $c['exec'])->getStatusCode());
        $this->assertSame(0, DB::table('hpbrain_eso_execution_evidence')->where('tenant_id', $this->b)->count());
    }

    public function test_linking_evidence_moves_a_measured_outcome_to_verified(): void
    {
        $c = $this->chain($this->a);
        $this->outcome($this->a, $c['decision'], $c['exec'], ['kpis' => ['measuredBefore' => 10, 'measuredAfter' => 4]]);
        $find = fn () => (new ExecutionCenter($this->a))->find($c['exec']);

        $this->assertSame('measured', $find()['outcomeState']);
        (new BrainExecutionCenterController())->linkEvidence($this->request($this->a, ['evidence_ids' => [$c['evidence'][0]]]), $this->a, $c['exec']);
        $this->assertSame('verified', $find()['outcomeState']);
    }

    public function test_empty_institute_and_paging_and_filters(): void
    {
        $empty = $this->build('xc-test-none');
        $this->assertTrue($empty['available']);
        $this->assertSame(0, $empty['history']['total']);
        $this->assertSame([], $empty['attention']);
        $this->assertSame(1, $empty['history']['lastPage']);

        for ($i = 0; $i < 7; $i++) {
            $this->chain($this->a, ['status' => $i < 2 ? 'queued' : 'completed', 'title' => $i === 3 ? 'Findable needle title' : 'Other action '.$i]);
        }
        $this->assertSame(2, $this->build($this->a, ['status' => 'queued'])['history']['total']);
        $this->assertSame(1, $this->build($this->a, ['q' => 'needle'])['history']['total']);
        $page = $this->build($this->a, ['perPage' => 5, 'page' => 2])['history'];
        $this->assertSame(2, $page['lastPage']);
        $this->assertCount(2, $page['data']);
        $this->assertSame(0, $this->build($this->a, ['sample' => 'sample'])['history']['total']);
    }

    public function test_queued_work_is_flagged_and_old_queues_are_stale(): void
    {
        $this->chain($this->a, ['status' => 'queued', 'ageDays' => 2]);
        $this->chain($this->a, ['status' => 'queued', 'ageDays' => 12]);

        $kinds = array_column($this->build($this->a)['attention'], 'kind');

        $this->assertContains('queued', $kinds);
        $this->assertContains('stale_queue', $kinds);
    }

    public function test_existing_screens_and_routes_are_preserved(): void
    {
        // The generic registry screen the old page used is untouched, so its API still answers.
        $screen = \App\Brain\Screens\ScreenRegistry::get('executions');
        $this->assertSame('Execution Center', $screen['title']);
        $this->assertSame('intelligence-loop', $screen['section']);
        $this->assertSame(['executions', 'completed', 'failed', 'evidence'], array_column($screen['metrics'], 'key'));

        $routes = file_get_contents(base_path('routes/brain.php'));
        foreach (["executions/{id}/complete", "execution-center", "executions/{id}/evidence", "fees/intelligence", "attendance/intelligence", "cross-module/intelligence", "screens/{screen}"] as $needle) {
            $this->assertStringContainsString($needle, $routes, $needle);
        }
        $this->assertStringContainsString("brain.permission:update", substr($routes, strpos($routes, "executions/{id}/evidence"), 220), 'writing evidence needs update permission');
    }
}
