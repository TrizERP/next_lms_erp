<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\AutomationCatalogue;
use App\Brain\Intelligence\FeesIntelligence;
use App\Brain\Intelligence\FeesSignalRules;
use App\Brain\Intelligence\FeesSummary;
use App\Brain\Intelligence\Narrative;
use App\Brain\Intelligence\Reasoner;
use App\Brain\Intelligence\RuleCatalogue;
use App\Brain\Intelligence\SignalWriter;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Brain\Support\SchemaCache;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Fees Intelligence — one institute, one academic year, the whole loop.
 *
 * WHY A SEPARATE CONTROLLER. BrainIntelligenceController answers "how is the
 * school doing" across every module. This answers one question — "where does
 * this school stand financially, and what should be done about it" — and it
 * answers it for the year the LMS header has selected. Folding it into the
 * general controller would have meant every Fees request paying for attendance,
 * marks and capability aggregates it never reads.
 *
 * TENANT AND YEAR ARE NOT PARAMETERS THE CALLER CONTROLS. The route group
 * applies `brain.auth` and `brain.tenant`, so the tenant comes from the signed
 * token and a request for another institute's fees is rejected before this
 * class runs. The year is validated by AcademicYear::resolve against that
 * institute's OWN `academic_year` rows, so the worst a caller can do with
 * `?syear=` is look at a different year of their own school. Every figure below
 * is then derived under both.
 *
 * NOTHING HERE INVENTS A NUMBER. Every amount comes from FeesIntelligence,
 * which reads vivek_erp; every sentence comes from a signal a rule raised over
 * that data, rendered through Narrative. Where evidence is missing the payload
 * says so in words the screen can show, rather than returning a zero the screen
 * would have to guess the meaning of.
 */
class BrainFeesIntelligenceController extends Controller
{
    /** Set by scope(); the institute every read is confined to. */
    private string $tenantId = '';

    /** Set by scope(); the academic year, already validated against this institute. */
    private ?string $syear = null;

    private string $actorId = '';

    private bool $actorIsStudent = false;

    /* ================================================================ read */

    /**
     * The whole Fees Intelligence payload.
     *
     * Ordered the way the screen reads it: what is true, what it means, what is
     * worth doing, what was decided, and what happened next.
     */
    public function index(Request $request): JsonResponse
    {
        $this->scope($request);
        $fees = new FeesIntelligence($this->tenantId, $this->syear);
        $coverage = $fees->coverage();

        // With no usable fee position there is nothing to interpret, and the
        // honest payload is the reason rather than a page of zeros.
        if (! $coverage['available']) {
            return response()->json($this->envelope($coverage) + [
                'summary' => (new FeesSummary($fees))->compose(),
                'position' => null,
                'adjustments' => $fees->adjustments(),
                'dataQuality' => $this->dataQuality($fees),
                'trends' => ['cycles' => [], 'heads' => [], 'classes' => [], 'paymentModes' => []],
                'findings' => [],
                'priorities' => [],
                'recommendations' => [],
                'decisionTrail' => [],
                'learning' => $this->learning(),
                'ruleStatus' => [],
            ]);
        }

        $findings = $this->findings();

        return response()->json($this->envelope($coverage) + [
            // "What is happening", composed from the figures below — see
            // FeesSummary: deterministic, never model-generated.
            'summary' => (new FeesSummary($fees))->compose(),
            'position' => $fees->position(),
            'adjustments' => $fees->adjustments(),
            'dataQuality' => $this->dataQuality($fees),
            'trends' => [
                'cycles' => $fees->cycles(),
                'heads' => $fees->heads(),
                'classes' => $fees->classes(),
                'paymentModes' => $fees->paymentModes(),
            ],
            'paymentFailures' => $fees->paymentFailures(),
            'paymentMethods' => $fees->paymentMethods(),
            'reconciliation' => $fees->reconciliation(),
            'bankMandates' => $fees->bankMandates(),
            'lateRules' => $fees->lateRules(),
            'reminders' => $fees->reminders(),
            'velocity' => $fees->velocity(),
            'otherCollections' => $fees->otherCollections(),
            'feeRevisions' => $fees->feeRevisions(),
            'findings' => $findings,
            'priorities' => $this->priorities($findings),
            'recommendations' => $this->recommendations(),
            'decisionTrail' => $this->decisionTrail(),
            'learning' => $this->learning(),
            'ruleStatus' => $this->ruleStatus($fees),
        ]);
    }

    /**
     * The accounts behind the outstanding figure, a page at a time.
     *
     * The browser never receives the roll — this is what makes "click
     * Outstanding to see who" possible without shipping thousands of students
     * to the client.
     */
    public function accounts(Request $request): JsonResponse
    {
        $this->scope($request);

        $limit = (int) $request->query('limit', 25);
        $offset = max(0, (int) $request->query('offset', 0));
        // The drill-down scope. It narrows a list the caller can already see in
        // full, so it is a filter rather than a permission — the tenant and year
        // that DO gate the data were fixed by scope() before this ran.
        $standardId = trim((string) $request->query('standard_id', ''));

        $fees = new FeesIntelligence($this->tenantId, $this->syear);
        $page = $fees->outstandingAccounts($limit, $offset, $standardId === '' ? null : $standardId);

        return response()->json([
            'tenantId' => $this->tenantId,
            'syear' => $this->syear,
            'total' => $page['total'],
            'offset' => $offset,
            'limit' => max(1, min($limit, 200)),
            'scope' => $page['scope'],
            'rows' => $page['rows'],
        ]);
    }

    /* =============================================================== write */

    /**
     * Re-evaluate the fee rules against live LMS data for THIS year.
     *
     * Idempotent, exactly like the general pipeline: a rule that still fires
     * refreshes its own year's signal instead of stacking another, and a rule
     * that no longer fires leaves its previous signal standing for a human to
     * resolve rather than deleting the history.
     */
    public function run(Request $request): JsonResponse
    {
        $this->scope($request);

        // Resolving per-student demand across a large roll on a remote database
        // legitimately outruns the default limit, as the general pipeline found.
        @set_time_limit(600);

        $started = microtime(true);
        $fees = new FeesIntelligence($this->tenantId, $this->syear);

        $writer = new SignalWriter($this->tenantId, $this->syear);
        $rules = new FeesSignalRules($this->tenantId, $writer, $this->syear, $fees);

        $outcomes = [];
        $created = 0;
        $refreshed = 0;

        foreach ($rules->applicable() as $ruleKey => $rule) {
            try {
                $result = $rule();
            } catch (\Throwable $e) {
                // One rule that cannot read its table must not abort the rest.
                // The failure is reported, never swallowed into "no findings".
                $outcomes[] = ['rule' => $ruleKey, 'created' => false, 'refreshed' => false, 'reason' => 'error', 'error' => $e->getMessage()];

                continue;
            }

            $created += $result['created'] ? 1 : 0;
            $refreshed += ! empty($result['refreshed']) ? 1 : 0;
            $outcomes[] = [
                'rule' => $ruleKey,
                'created' => (bool) $result['created'],
                'refreshed' => (bool) ($result['refreshed'] ?? false),
                'reason' => $result['reason'] ?? null,
            ];
        }

        // Reasoning turns the new signals into cases, hypotheses and
        // recommendations. It inherits each signal's own year, so this cannot
        // cross-label a finding raised for a different one.
        $reasoning = (new Reasoner($this->tenantId))->reasonOverOpenSignals();

        // Give each fee recommendation the procedure that would carry it out.
        // WITHOUT THIS THE LOOP STOPS AT THE DECISION: approving a
        // recommendation with no ESO records the authorisation and queues
        // nothing, so Execution has no row and Outcome has nothing to report
        // on. The catalogue is the general pipeline's own — every procedure
        // ships at trust level 'suggest', so a person still carries the work
        // out and nothing runs against the LMS unattended.
        $catalogue = new AutomationCatalogue($this->tenantId);
        $catalogue->sync();
        $linked = $catalogue->linkRecommendations('fee_');

        $this->audit($request, 'fees.intelligence.run', 'FeesIntelligence', $this->tenantId, [
            'syear' => $this->syear,
            'signalsCreated' => $created,
            'signalsRefreshed' => $refreshed,
            'reasoning' => $reasoning,
            'recommendationsLinked' => $linked,
        ]);

        return response()->json([
            'tenantId' => $this->tenantId,
            'syear' => $this->syear,
            'rules' => ['evaluated' => count($outcomes), 'signalsCreated' => $created, 'signalsRefreshed' => $refreshed, 'outcomes' => $outcomes],
            'reasoning' => $reasoning,
            'recommendationsLinked' => $linked,
            'coverage' => $fees->coverage(),
            'elapsedMs' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }

    /* ============================================================ sections */

    /** Context every section is read against. */
    private function envelope(array $coverage): array
    {
        return [
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => DB::connection()->getDatabaseName(),
            'academicYear' => ['syear' => $this->syear],
            'coverage' => $coverage,
            'freshness' => $this->freshness(),
            'execution' => $this->executionCapability(),
        ];
    }

    /**
     * When the findings were last computed — and NOT a fabricated timestamp.
     *
     * The figures on the Analytics section are read live on every request, so
     * they are current by construction. The FINDINGS are only as fresh as the
     * last rule run, which may be never. Saying "last analysed just now" when no
     * rule has ever run for this year would be the exact fabrication this whole
     * screen exists to avoid, so when there is no run the label says the
     * position is live and the findings have not been computed yet.
     */
    private function freshness(): array
    {
        $at = null;
        if (SchemaCache::hasTable('hpbrain_signals')) {
            $at = DB::table('hpbrain_signals')
                ->where('tenant_id', $this->tenantId)
                ->where('rule_key', 'like', 'fee_%')
                ->when($this->syear !== null, fn ($q) => $q->where('syear', $this->syear))
                ->max('updated_date');
        }

        return [
            'positionLabel' => 'Based on current LMS fee records',
            'findingsRefreshedAt' => $at ? (string) $at : null,
            'findingsLabel' => $at
                ? 'Findings last computed '.(string) $at
                : 'Findings have not been computed for this year yet',
        ];
    }

    /**
     * Whether approving a fee recommendation can actually DO anything.
     *
     * It cannot, and the screen says so rather than implying otherwise. A
     * decision records a named human authorisation and queues the agreed
     * procedure; no ESO in this installation is permitted to touch the LMS
     * unattended (AutomationCatalogue pins every one at trust level 'suggest'),
     * and there is no fee action — no reminder, no ledger write — wired to
     * execute automatically. Somebody carries the work out and records what
     * happened; that is what makes the outcome real.
     */
    private function executionCapability(): array
    {
        return [
            'automated' => false,
            'note' => 'Approving a recommendation records the decision and queues the agreed follow-up for a person to carry out. No fee action is executed automatically.',
        ];
    }

    /**
     * Section 2 — what the Brain sees, as sentences with their evidence.
     *
     * Year-scoped and fee-scoped: `rule_key LIKE 'fee_%'` keeps attendance and
     * staffing findings off a fees screen, and the year filter keeps last year's
     * findings off this year's.
     *
     * @return array<int, array<string, mixed>>
     */
    private function findings(): array
    {
        if (! SchemaCache::hasTable('hpbrain_signals')) {
            return [];
        }

        $rows = DB::table('hpbrain_signals')
            ->where('tenant_id', $this->tenantId)
            ->where('rule_key', 'like', 'fee_%')
            ->whereNotIn('status', ['dismissed'])
            ->when($this->syear !== null, fn ($q) => $q->where('syear', $this->syear))
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->orderByDesc('created_date')
            ->limit(50)
            ->get();

        return $rows->map(function ($row) {
            $row = (array) $row;
            $metadata = json_decode((string) ($row['metadata'] ?? '{}'), true);
            $row['metadata'] = is_array($metadata) ? $metadata : [];

            $finding = Narrative::forSignal($row);
            // The money a finding puts in play, kept separate from the finding's
            // own counts so the screen can rank by it without re-deriving it.
            $finding['impactAmount'] = isset($row['metadata']['impactAmount'])
                ? (float) $row['metadata']['impactAmount']
                : null;
            $finding['syear'] = $row['syear'] ?? null;
            $finding['status'] = (string) ($row['status'] ?? 'new');

            return $finding;
        })->all();
    }

    /**
     * Section 4 — Priority attention, DERIVED, never hardcoded.
     *
     * The order is severity first, then the amount actually in play, because two
     * findings of equal severity are not equally urgent when one carries ₹37L
     * and the other ₹4,000. Nothing is assigned a priority by hand.
     *
     * @param  array<int, array<string, mixed>>  $findings
     * @return array<int, array<string, mixed>>
     */
    private function priorities(array $findings): array
    {
        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

        $items = array_map(fn ($finding) => [
            'id' => $finding['id'],
            'severity' => $finding['severity'],
            'severityLabel' => $finding['severityLabel'],
            'title' => $finding['title'],
            'whatHappened' => $finding['whatHappened'],
            'whyItMatters' => $finding['whyItMatters'],
            'evidence' => $finding['evidence'],
            'impactAmount' => $finding['impactAmount'] ?? null,
            'nextStep' => $finding['recommendation'],
            'owner' => $finding['owner'],
            'confidence' => $finding['confidence'],
        ], $findings);

        usort($items, function ($a, $b) use ($rank) {
            $bySeverity = ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9);
            if ($bySeverity !== 0) {
                return $bySeverity;
            }

            return ((float) ($b['impactAmount'] ?? 0)) <=> ((float) ($a['impactAmount'] ?? 0));
        });

        return $items;
    }

    /**
     * Section 5 — recommendations, each carried back to the finding it came
     * from and forward to the decision taken on it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recommendations(): array
    {
        if (! SchemaCache::hasTable('hpbrain_recommendations')) {
            return [];
        }

        $rows = DB::table('hpbrain_recommendations as r')
            ->join('hpbrain_reasoning_steps as s', 's.id', '=', 'r.reasoning_step_id')
            ->join('hpbrain_signals as sg', 'sg.id', '=', 's.signal_id')
            ->where('r.tenant_id', $this->tenantId)
            ->where('sg.rule_key', 'like', 'fee_%')
            ->when($this->syear !== null, fn ($q) => $q->where('r.syear', $this->syear))
            ->orderByRaw("FIELD(r.priority, 'critical', 'high', 'medium', 'low')")
            ->orderByDesc('r.created_date')
            ->limit(50)
            ->get([
                'r.id', 'r.title', 'r.description', 'r.category', 'r.priority', 'r.urgency',
                'r.confidence', 'r.impact', 'r.status', 'r.eso_id', 'r.created_date', 'r.syear',
                'sg.id as signal_id', 'sg.rule_key', 'sg.severity', 'sg.metadata',
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        $decisions = SchemaCache::hasTable('hpbrain_decisions')
            ? DB::table('hpbrain_decisions')->where('tenant_id', $this->tenantId)
                ->whereIn('recommendation_id', $rows->pluck('id'))
                ->orderByDesc('created_date')->get()->keyBy('recommendation_id')
            : collect();

        return $rows->map(function ($row) use ($decisions) {
            $metadata = json_decode((string) $row->metadata, true);
            $metadata = is_array($metadata) ? $metadata : [];
            $cause = RuleCatalogue::for((string) $row->rule_key);
            $decision = $decisions[$row->id] ?? null;

            $impact = isset($metadata['impactAmount']) ? (float) $metadata['impactAmount'] : null;

            return [
                'id' => (string) $row->id,
                'title' => (string) $row->title,
                'description' => (string) $row->description,
                'category' => (string) $row->category,
                'priority' => (string) $row->priority,
                'urgency' => (string) $row->urgency,
                'confidence' => [
                    'band' => Narrative::confidenceBand((float) $row->confidence),
                    'value' => round((float) $row->confidence, 2),
                ],
                'status' => (string) $row->status,
                'syear' => $row->syear,
                // What this addresses, in money, and stated as EXPOSURE rather
                // than as recovery. The amount is already outstanding and already
                // counted; nothing here forecasts what follow-up collects.
                'expectedImpact' => $impact === null ? null : [
                    'amount' => $impact,
                    'basis' => 'Outstanding balance this recommendation covers',
                    'wording' => 'Could potentially address '.Narrative::money($impact).' of the current outstanding balance.',
                ],
                'finding' => [
                    'signalId' => (string) $row->signal_id,
                    'title' => (string) ($metadata['title'] ?? ''),
                    'severity' => (string) $row->severity,
                ],
                'why' => $cause['hypothesis'] ?? null,
                // Review-only when the recommendation has no procedure behind it.
                'actionable' => ! empty($row->eso_id),
                'decision' => $decision === null ? null : [
                    'id' => (string) $decision->id,
                    'status' => (string) $decision->status,
                    'rationale' => (string) $decision->rationale,
                    'decidedBy' => (string) $decision->decided_by,
                    'decidedAt' => (string) $decision->created_date,
                ],
            ];
        })->all();
    }

    /**
     * Sections 6 and 7 — decisions taken on fee recommendations, the work they
     * queued, and the outcome recorded against it.
     *
     * One trail rather than three lists, because a decision with no execution
     * and an execution with no outcome are states of the same thing, and showing
     * them apart invites the reader to assume the missing half happened.
     *
     * @return array<int, array<string, mixed>>
     */
    private function decisionTrail(): array
    {
        if (! SchemaCache::hasTable('hpbrain_decisions')) {
            return [];
        }

        $rows = DB::table('hpbrain_decisions as d')
            ->join('hpbrain_recommendations as r', 'r.id', '=', 'd.recommendation_id')
            ->join('hpbrain_reasoning_steps as s', 's.id', '=', 'r.reasoning_step_id')
            ->join('hpbrain_signals as sg', 'sg.id', '=', 's.signal_id')
            ->where('d.tenant_id', $this->tenantId)
            ->where('sg.rule_key', 'like', 'fee_%')
            ->when($this->syear !== null, fn ($q) => $q->where('d.syear', $this->syear))
            ->orderByDesc('d.created_date')
            ->limit(50)
            ->get([
                'd.id', 'd.status', 'd.rationale', 'd.decided_by', 'd.created_date', 'd.confidence', 'd.syear',
                'r.id as recommendation_id', 'r.title as recommendation_title', 'r.category',
                'sg.metadata', 'sg.rule_key',
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        $executions = SchemaCache::hasTable('hpbrain_eso_executions')
            ? DB::table('hpbrain_eso_executions')->where('tenant_id', $this->tenantId)
                ->whereIn('decision_id', $rows->pluck('id'))->get()->keyBy('decision_id')
            : collect();

        $outcomes = SchemaCache::hasTable('hpbrain_outcomes')
            ? DB::table('hpbrain_outcomes')->where('tenant_id', $this->tenantId)
                ->whereIn('decision_id', $rows->pluck('id'))->get()->keyBy('decision_id')
            : collect();

        $esoNames = $this->esoNames($executions);

        return $rows->map(function ($row) use ($executions, $outcomes) {
            $metadata = json_decode((string) $row->metadata, true);
            $metadata = is_array($metadata) ? $metadata : [];
            $execution = $executions[$row->id] ?? null;
            $outcome = $outcomes[$row->id] ?? null;

            return [
                'decisionId' => (string) $row->id,
                'status' => (string) $row->status,
                'rationale' => (string) $row->rationale,
                'decidedBy' => (string) $row->decided_by,
                'decidedAt' => (string) $row->created_date,
                'syear' => $row->syear,
                'recommendation' => [
                    'id' => (string) $row->recommendation_id,
                    'title' => (string) $row->recommendation_title,
                    'category' => (string) $row->category,
                ],
                'finding' => (string) ($metadata['title'] ?? ''),
                'expectedImpact' => isset($metadata['impactAmount']) ? (float) $metadata['impactAmount'] : null,
                // The work queue as a person needs to read it: WHAT was
                // authorised, WHO is carrying it out, and where it has got to.
                // `executor_type` is 'human' throughout — nothing in Fees runs
                // unattended, and the payload says so rather than implying it.
                'execution' => $execution === null ? null : [
                    'id' => (string) $execution->id,
                    'status' => (string) $execution->status,
                    'action' => (string) ($esoNames[(string) ($execution->eso_id ?? '')] ?? $row->recommendation_title),
                    'owner' => (string) ($execution->executed_by ?? ''),
                    'executorType' => (string) ($execution->executor_type ?? 'human'),
                    'queuedAt' => (string) $execution->created_date,
                    'startedAt' => $execution->started_date ? (string) $execution->started_date : null,
                    'completedAt' => $execution->completed_date ? (string) $execution->completed_date : null,
                ],
                // Absent, not "pending": an outcome nobody has recorded is
                // undetermined, and forcing a status before the follow-up point
                // would make the loop report a result it does not have.
                'outcome' => $outcome === null ? null : [
                    'id' => (string) $outcome->id,
                    'result' => (string) $outcome->result,
                    'feedback' => (string) $outcome->feedback,
                    'recordedAt' => (string) $outcome->created_date,
                    // Null where nobody measured it. A zero here would read as
                    // "the action moved nothing", which is a different claim.
                    'measured' => $this->measured($outcome),
                ],
                'outcomeState' => $this->outcomeState($execution, $outcome),
            ];
        })->all();
    }

    /**
     * The measured before/after a person recorded against an outcome.
     *
     * @return array<string, mixed>|null
     */
    private function measured(object $outcome): ?array
    {
        $kpis = json_decode((string) ($outcome->kpis ?? '{}'), true);
        if (! is_array($kpis) || ! isset($kpis['measuredBefore'], $kpis['measuredAfter'])) {
            return null;
        }
        if ($kpis['measuredBefore'] === null || $kpis['measuredAfter'] === null) {
            return null;
        }

        return [
            'before' => (float) $kpis['measuredBefore'],
            'after' => (float) $kpis['measuredAfter'],
            'change' => (float) ($kpis['measuredChange'] ?? 0),
            'accountsAffected' => $kpis['accountsAffected'] ?? null,
            'basis' => (string) ($kpis['basis'] ?? ''),
        ];
    }

    /**
     * Procedure names for the queued executions, so the work queue shows the
     * action rather than an ESO id.
     *
     * @param  \Illuminate\Support\Collection<string, object>  $executions
     * @return array<string, string>
     */
    private function esoNames($executions): array
    {
        $ids = array_values(array_filter($executions->pluck('eso_id')->all()));
        if ($ids === [] || ! SchemaCache::hasTable('hpbrain_eso_definitions')) {
            return [];
        }

        return DB::table('hpbrain_eso_definitions')
            ->where('tenant_id', $this->tenantId)
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->map(fn ($name) => (string) $name)
            ->all();
    }

    /** The loop's honest state for one decision. */
    private function outcomeState(?object $execution, ?object $outcome): string
    {
        if ($outcome !== null) {
            return match ((string) $outcome->result) {
                'success' => 'resolved',
                'partial' => 'partially_resolved',
                'failed' => 'not_reached',
                default => 'undetermined',
            };
        }

        if ($execution === null) {
            return 'no_action_queued';
        }

        return 'awaiting_outcome';
    }

    /**
     * Section 8 — what the organization has learnt from fee decisions it has
     * already seen through.
     *
     * READS ONLY WHAT WAS RECORDED. A learning here is a prior decision whose
     * outcome a human actually captured; there is no inference, no synthesis and
     * no placeholder. With nothing captured yet, the section reports that
     * plainly instead of showing an illustrative example.
     */
    private function learning(): array
    {
        if (! SchemaCache::hasTable('hpbrain_outcomes')) {
            return ['available' => false, 'reason' => 'Outcome capture is not provisioned in this database.', 'entries' => []];
        }

        $rows = DB::table('hpbrain_outcomes as o')
            ->join('hpbrain_decisions as d', 'd.id', '=', 'o.decision_id')
            ->join('hpbrain_recommendations as r', 'r.id', '=', 'd.recommendation_id')
            ->join('hpbrain_reasoning_steps as s', 's.id', '=', 'r.reasoning_step_id')
            ->join('hpbrain_signals as sg', 'sg.id', '=', 's.signal_id')
            ->where('o.tenant_id', $this->tenantId)
            ->where('sg.rule_key', 'like', 'fee_%')
            // DELIBERATELY NOT YEAR-FILTERED. What worked last year is exactly
            // what this year's decision should be informed by; a memory that
            // only remembers the year you are looking at is not a memory. The
            // year each entry belongs to is carried on the entry instead.
            ->orderByDesc('o.created_date')
            ->limit(25)
            ->get(['o.result', 'o.feedback', 'o.created_date', 'o.syear', 'o.kpis', 'd.rationale',
                'r.title as action', 'sg.metadata', 'sg.rule_key']);

        if ($rows->isEmpty()) {
            return [
                'available' => false,
                'reason' => 'No fee decision has been seen through to a recorded outcome yet, so there is nothing learnt to show.',
                'entries' => [],
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'entries' => $rows->map(function ($row) {
                $metadata = json_decode((string) $row->metadata, true);
                $metadata = is_array($metadata) ? $metadata : [];

                return [
                    'finding' => (string) ($metadata['title'] ?? ''),
                    'action' => (string) $row->action,
                    'rationale' => (string) $row->rationale,
                    'result' => (string) $row->result,
                    'feedback' => (string) $row->feedback,
                    'syear' => $row->syear,
                    'recordedAt' => (string) $row->created_date,
                    'measured' => $this->measured($row),
                    'appliesToThisYear' => $this->syear !== null && (string) $row->syear === (string) $this->syear,
                ];
            })->all(),
        ];
    }

    /**
     * Which rules ran, which fired, and which declined for want of evidence.
     *
     * THIS IS THE SECTION THAT MAKES SILENCE READABLE. Without it a screen with
     * two findings looks identical whether the other six rules found nothing or
     * could not run at all, and those mean very different things. Each rule
     * reports in words, and rule keys never reach the user.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ruleStatus(FeesIntelligence $fees): array
    {
        $raised = SchemaCache::hasTable('hpbrain_signals')
            ? DB::table('hpbrain_signals')
                ->where('tenant_id', $this->tenantId)
                ->where('rule_key', 'like', 'fee_%')
                ->when($this->syear !== null, fn ($q) => $q->where('syear', $this->syear))
                ->whereNotIn('status', ['resolved', 'dismissed'])
                ->pluck('rule_key')->map(fn ($k) => (string) $k)->all()
            : [];

        $labels = [
            'fee_collection_shortfall' => 'Collection against what was billed',
            'fee_receipt_coverage' => 'Whether collection is being recorded',
            'fee_outstanding_concentration' => 'Where the outstanding balance sits',
            'fee_overdue_backlog' => 'Fees overdue from past cycles',
            'fee_cycle_decline' => 'Change between billed cycles',
            'fee_class_collection_gap' => 'Collection by class',
            'fee_head_collection_gap' => 'Collection by fee head',
            'fee_payment_mode_concentration' => 'How fee money arrives',
            'fee_cancellation_pressure' => 'Cancelled receipts against collection',
            'fee_reconciliation_gap' => 'Cancelled receipts still counted as collected',
            'fee_payment_failures' => 'Payment failures & auto-debit bounces',
            'fee_gateway_reconciliation_gap' => 'Gateway settlement reconciliation',
            'fee_nach_mandate_coverage' => 'NACH bank mandate coverage',
            'fee_configured_late_backlog' => 'Overdue against institutional late dates',
            'fee_cancellation_reasons' => 'Receipt cancellation root causes',
            'fee_revision_impact' => 'Mid-session fee structure modifications',
        ];

        $applicable = array_keys((new FeesSignalRules(
            $this->tenantId,
            new SignalWriter($this->tenantId, $this->syear),
            $this->syear,
            $fees
        ))->applicable());

        $status = [];
        foreach ($labels as $key => $label) {
            $status[] = [
                'key' => $key,
                'label' => $label,
                'checked' => in_array($key, $applicable, true),
                'raised' => in_array($key, $raised, true),
            ];
        }

        return $status;
    }

    /**
     * Ledger quality, stated as measured facts rather than as a score.
     *
     * Two checks, both with an exact definition: how much was cancelled against
     * what was collected, and how many cancelled receipts are still flagged
     * live. A "data health score" would be a number nobody could act on; these
     * are row counts somebody can go and fix.
     *
     * @return array<string, mixed>
     */
    private function dataQuality(FeesIntelligence $fees): array
    {
        $adjustments = $fees->adjustments();
        $gaps = $fees->reconciliationGaps();
        $recon = $fees->reconciliation();
        $mandates = $fees->bankMandates();

        $checks = [];

        if ($adjustments['available']) {
            $checks[] = [
                'key' => 'cancellation_load',
                'label' => 'Cancelled receipts',
                'value' => $adjustments['cancelledReceipts'],
                'amount' => $adjustments['cancelledAmount'],
                'sharePercent' => $adjustments['cancelledShareOfCollection'],
                'state' => $adjustments['cancelledShareOfCollection'] !== null
                    && $adjustments['cancelledShareOfCollection'] >= 25.0 ? 'attention' : 'ok',
                'note' => $adjustments['cancelledReceipts'] === 0
                    ? 'No receipt was cancelled this year.'
                    : 'Cancelled receipts are money the school counted and then un-counted.',
            ];
            $checks[] = [
                'key' => 'refunds',
                'label' => 'Refunds issued',
                'value' => $adjustments['refunds'],
                'amount' => $adjustments['refundedAmount'],
                'sharePercent' => null,
                'state' => 'ok',
                'note' => $adjustments['refunds'] === 0
                    ? 'No refund was issued this year.'
                    : 'Refunds recorded against this academic year.',
            ];
        }

        if ($gaps['available']) {
            $checks[] = [
                'key' => 'reconciliation',
                'label' => 'Cancelled but still counted',
                'value' => $gaps['count'],
                'amount' => $gaps['amount'],
                'sharePercent' => null,
                'state' => $gaps['count'] > 0 ? 'attention' : 'ok',
                'note' => $gaps['count'] > 0
                    ? 'These receipts appear in the cancellation record but are still marked live, so reports count them as collected.'
                    : 'Every cancelled receipt is correctly excluded from collection.',
            ];
        }

        if ($recon['available']) {
            $checks[] = [
                'key' => 'gateway_reconciliation',
                'label' => 'Gateway reconciliation gap',
                'value' => $recon['unmatchedCount'],
                'amount' => $recon['reconciliationGapAmount'],
                'sharePercent' => null,
                'state' => $recon['reconciliationGapAmount'] > 0 ? 'attention' : 'ok',
                'note' => $recon['reconciliationGapAmount'] > 0
                    ? 'Discrepancy detected between online gateway settlements and ERP receipts.'
                    : 'All online gateway settlements match ERP receipts.',
            ];
        }

        if ($mandates['available'] && $mandates['rejectedMandates'] > 0) {
            $checks[] = [
                'key' => 'mandate_rejections',
                'label' => 'Rejected bank mandates',
                'value' => $mandates['rejectedMandates'],
                'amount' => null,
                'sharePercent' => null,
                'state' => 'attention',
                'note' => 'Student bank accounts rejected during e-mandate registration.',
            ];
        }

        return [
            'available' => $checks !== [],
            'reason' => $checks === [] ? 'This ERP does not record fee cancellations or refunds separately.' : null,
            'checks' => $checks,
        ];
    }

    /* ============================================================= helpers */

    /**
     * Bind this request to one institute and one academic year.
     *
     * The tenant is read from the request ATTRIBUTES set by BrainTenantScope,
     * never from the `{tenantId}` path segment, so a future route change cannot
     * widen the scope by accident.
     */
    private function scope(Request $request): void
    {
        $this->tenantId = (string) $request->attributes->get(
            'tenantId',
            $request->attributes->get('auth.tenantId')
        );

        $this->syear = AcademicYear::resolve($this->tenantId, $request->query('syear'));

        $this->actorId = (string) $request->attributes->get('auth.userId', '');
        $payload = (array) $request->attributes->get('brain.payload', []);
        $this->actorIsStudent = (bool) ($payload['is_student'] ?? false);
    }

    /** @param array<string, mixed> $changes */
    private function audit(Request $request, string $action, string $entityType, string $entityId, array $changes): void
    {
        try {
            if (! SchemaCache::hasTable('hpbrain_audit_logs')) {
                return;
            }

            DB::table('hpbrain_audit_logs')->insert(SchemaCache::only('hpbrain_audit_logs', [
                'id' => \App\Brain\Intelligence\Uuid::v4(),
                'tenant_id' => $this->tenantId,
                'org_id' => 'org-'.$this->tenantId,
                'entity_type' => $entityType,
                'entity_id' => substr($entityId, 0, 36),
                'action' => $action,
                'actor_id' => (string) $request->attributes->get('auth.userId'),
                'actor_name' => (string) $request->attributes->get('auth.role'),
                'changes' => json_encode($changes),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'source' => 'lms',
                'status' => 'success',
                'created_at' => now(),
            ]));
        } catch (\Throwable $e) {
            // Audit storage being unavailable must never turn the read into a 500.
        }
    }
}
