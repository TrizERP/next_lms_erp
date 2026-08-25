<?php

namespace App\Domain\AI\Conversation;

use App\Domain\AI\Agents\AgentRunner;
use App\Domain\AI\Cases\CaseBuilder;
use App\Domain\AI\Decisions\DecisionGate;
use App\Domain\AI\Evidence\EvidenceStore;
use App\Domain\AI\Explanations\ExplanationBuilder;
use App\Domain\AI\Outcomes\OutcomeTracker;
use App\Domain\AI\Recommendations\RecommendationDrafter;
use App\Domain\AI\Signals\SignalStore;
use App\Domain\K12\AcademicRisk\AcademicRiskAgent;
use App\Domain\KnowledgeGraph\GraphQueryService;
use App\Domain\Ontology\EntityResolver;
use App\Domain\Templates\TemplateRegistry;
use App\Domain\Workflow\WorkflowEngine;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * One question in, one answer and one full-stack trace out.
 *
 * This is the layer the architecture was missing. Every service below it already
 * worked — the agent detected risk, the case was opened, the recommendation was
 * drafted — but nothing joined them to a sentence a teacher typed, and nothing
 * reported which of them had run. So the system was doing the work invisibly, which
 * from the outside is indistinguishable from not doing it at all.
 *
 * Two rules hold everywhere in this class:
 *
 *   1. It orchestrates; it does not re-implement. Risk detection happens in
 *      AcademicRiskAgent through AgentRunner, approval happens in DecisionGate,
 *      the intervention is created by the workflow. Nothing here writes a case or an
 *      approval by hand, so every rule those layers enforce still applies to a
 *      question asked in English.
 *
 *   2. Every stage reports, including the ones that did nothing. A trace where the
 *      Workflow Engine says "not reached — no recommendation has been approved yet"
 *      teaches the reader how the pipeline is wired. A trace that omits it does not.
 */
class AskService
{
    public const MODULE = 'student_profiles';

    public const AGENT_KEY = 'k12_academic_risk';

    public function __construct(
        private readonly IntentClassifier $classifier,
        private readonly ConversationStore $conversations,
        private readonly AnswerComposer $compose,
        private readonly AgentRunner $agents,
        private readonly CaseBuilder $cases,
        private readonly EvidenceStore $evidence,
        private readonly ExplanationBuilder $explanations,
        private readonly RecommendationDrafter $recommendations,
        private readonly DecisionGate $decisions,
        private readonly WorkflowEngine $workflows,
        private readonly OutcomeTracker $outcomes,
        private readonly EntityResolver $entities,
        private readonly GraphQueryService $graph,
        private readonly TemplateRegistry $templates,
        private readonly SignalStore $signals,
    ) {
    }

    /**
     * Ask a question of the Student Profiles module.
     *
     * @return array{
     *   conversation:array, question:string, intent:array, answer:array,
     *   trace:array, ladder:array, links:array, duration_ms:int
     * }
     */
    public function ask(
        string $question,
        McpRequestContext $scope,
        ?int $conversationId = null,
        array $options = []
    ): array {
        $trace = new FlowTrace();
        $startedAt = microtime(true);

        // ---- Stage 1: Conversational AI ------------------------------------
        $thread = $this->conversations->open($conversationId, $scope, self::MODULE);

        $trace->ran(
            'conversation',
            sprintf('Question accepted on thread %s (turn %d).', $thread['reference'] ?? 'in-memory', $thread['turn_count'] + 1),
            [
                'utterance' => $question,
                'conversation_id' => $thread['id'],
                'conversation_reference' => $thread['reference'],
                'turn' => $thread['turn_count'] + 1,
                'memory_before' => $thread['memory'],
                'module' => self::MODULE,
                'asked_by' => ['user_id' => $scope->userId, 'role' => $scope->role],
            ],
            ['table' => 'ai_conversations', 'ids' => array_filter([$thread['id']])],
            [
                'api' => 'GET ' . $this->prefix() . '/conversations/' . ($thread['id'] ?? '{id}'),
                'sql' => 'select * from ai_conversation_turns where conversation_id = ' . ($thread['id'] ?? 0) . ' order by sequence',
            ]
        );

        // ---- Stage 2: Gen AI — understanding -------------------------------
        $classifyStart = microtime(true);
        $intent = $this->classifier->classify($question, $thread['memory']);

        // A button in the console sends the same sentence a user could type, plus the id
        // it was rendered against. The sentence still drives the intent; the payload only
        // removes ambiguity about which record it applies to, so clicking and typing
        // follow one code path and produce one trace shape.
        $payload = array_intersect_key(
            $options['payload'] ?? [],
            array_flip(['case_id', 'student_id', 'recommendation_id', 'workflow_approval_id'])
        );

        if ($payload !== []) {
            $intent = $intent->with(array_map('intval', $payload));
        }

        [$intent, $inherited] = $this->conversations->resolveReferents($intent, $thread['memory']);

        $trace->ran(
            'gen_ai',
            $intent->isUnknown()
                ? 'The question did not match any Student Profiles intent with enough confidence to act on.'
                : sprintf('Understood as "%s" (confidence %.0f%%).', $intent->label, $intent->confidence * 100),
            $intent->toArray() + [
                'inherited_from_earlier_turns' => $inherited,
                'classifier' => 'deterministic lexicon + phrase patterns',
                'confidence_floor' => 0.34,
            ],
            [],
            ['api' => 'POST ' . $this->prefix() . '/ask/interpret  {"question": "..."} — classification only, nothing is written'],
            (int) round((microtime(true) - $classifyStart) * 1000)
        );

        // ---- Stages 3-15: whichever the intent actually needs ---------------
        $links = [];
        $error = null;

        try {
            [$answer, $links] = $this->dispatch($intent, $scope, $trace, $thread, $options);
        } catch (Throwable $exception) {
            report($exception);
            $error = $exception->getMessage();
            $answer = $this->compose->make(
                'Something went wrong while answering that.',
                [$this->compose->text('What happened', $exception->getMessage())],
                [],
                ['Which students are at academic risk?']
            );
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $turnId = $this->conversations->recordTurn(
            $thread['id'],
            $scope,
            $question,
            $intent,
            $answer,
            $trace,
            $links,
            $durationMs,
            $error
        );

        return [
            'conversation' => [
                'id' => $thread['id'],
                'reference' => $thread['reference'],
                'turn_id' => $turnId,
                'turn' => $thread['turn_count'] + 1,
            ],
            'question' => $question,
            'intent' => $intent->toArray(),
            'answer' => $answer,
            'trace' => $trace->toArray(),
            'ladder' => $trace->toLadder(),
            'stage_counts' => $trace->summaryCounts(),
            'links' => $links,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * Classification without side effects — useful for checking that a rephrasing lands
     * on the intent you expected before running it for real.
     */
    public function interpret(string $question, array $memory = []): array
    {
        return $this->classifier->classify($question, $memory)->toArray();
    }

    public function catalogue(): array
    {
        return $this->classifier->catalogue();
    }

    // ------------------------------------------------------------------ routing

    private function dispatch(
        Intent $intent,
        McpRequestContext $scope,
        FlowTrace $trace,
        array $thread,
        array $options
    ): array {
        return match ($intent->key) {
            'student_risk_scan' => $this->riskScan($intent, $scope, $trace, $options),
            'student_risk_explain' => $this->explainStudent($intent, $scope, $trace),
            'evidence_inspect' => $this->inspectEvidence($intent, $scope, $trace),
            'recommendation_advice' => $this->adviseAction($intent, $scope, $trace),
            'approve_recommendation' => $this->decide($intent, $scope, $trace, 'approved'),
            'reject_recommendation' => $this->decide($intent, $scope, $trace, 'rejected'),
            'workflow_status' => $this->workflowStatus($intent, $scope, $trace),
            'outcome_status' => $this->outcomeStatus($intent, $scope, $trace),
            'learning_effectiveness' => $this->learning($intent, $scope, $trace),
            default => $this->notUnderstood($intent, $trace),
        };
    }

    // ------------------------------------------------------- 1. the risk scan

    /**
     * "Which students are at academic risk?"
     *
     * Runs the real agent. Everything the agent does on the way — read the ontology,
     * query attendance and assessment tables, store evidence, open cases, compose a
     * governed explanation, draft a recommendation — is reported back as its own stage.
     */
    private function riskScan(Intent $intent, McpRequestContext $scope, FlowTrace $trace, array $options): array
    {
        // ---- Stage 3: Agent ------------------------------------------------
        $input = array_filter([
            'subject_entity_key' => 'student',
            'subject_id' => $intent->slot('student_id'),
            'limit' => $options['limit'] ?? 50,
        ], fn ($value) => $value !== null);

        $agentStart = microtime(true);
        $run = $this->agents->run(self::AGENT_KEY, $scope, $input, 'conversation', 'ask:student_risk_scan');
        $agentMs = (int) round((microtime(true) - $agentStart) * 1000);

        if ($run['status'] === 'rejected') {
            $trace->blocked('agent', $run['summary'], ['agent_key' => self::AGENT_KEY, 'status' => $run['status']]);

            foreach (['ontology', 'data', 'evidence', 'case', 'explanation', 'template', 'recommendation', 'approval', 'workflow', 'action', 'outcome', 'learning'] as $stage) {
                $trace->notReached($stage, 'The agent was not permitted to run, so nothing downstream happened.');
            }

            return [
                $this->compose->make(
                    'I could not run the risk analysis.',
                    [$this->compose->text('Why', $run['summary'])],
                    [],
                    ['What has the system learned?']
                ),
                [],
            ];
        }

        $counters = $run['counters'] ?? [];
        $cases = $run['result']['cases'] ?? [];

        $trace->ran(
            'agent',
            sprintf(
                'Academic Risk Agent ran (%s) — %d signal%s, %d case%s, %d recommendation%s.',
                $run['status'],
                $counters['signals_detected'] ?? 0,
                ($counters['signals_detected'] ?? 0) === 1 ? '' : 's',
                $counters['cases_opened'] ?? 0,
                ($counters['cases_opened'] ?? 0) === 1 ? '' : 's',
                $counters['recommendations_drafted'] ?? 0,
                ($counters['recommendations_drafted'] ?? 0) === 1 ? '' : 's'
            ),
            [
                'agent_key' => self::AGENT_KEY,
                'why_this_agent' => 'It is the only registered agent that owns the academic_risk case type, '
                    . 'and its manifest licenses the signals this question needs.',
                'run_reference' => $run['run_id'],
                'status' => $run['status'],
                'counters' => $counters,
                'verb_ceiling' => 'recommend — the agent may draft an intervention but cannot create one',
                'summary' => $run['summary'],
            ],
            ['table' => 'ai_agent_runs', 'ids' => array_filter([$run['run_id']])],
            [
                'api' => 'GET ' . $this->prefix() . '/agent-runs?agent_key=' . self::AGENT_KEY,
                'sql' => 'select * from ai_agent_runs where id = ' . ($run['run_id'] ?? 0),
            ],
            $agentMs
        );

        if ($cases === []) {
            return $this->noCasesOpened($scope, $trace, $run, $counters);
        }

        $top = $cases[0];
        $topStudentId = (int) ($top['student_id'] ?? 0);

        // ---- Stage 4: Ontology / Knowledge Graph ---------------------------
        $this->ontologyStage($scope, $trace, $topStudentId, $top['student_name'] ?? null);

        // ---- Stage 5: Real data --------------------------------------------
        $this->dataStage($scope, $trace, $cases, $counters);

        // ---- Stage 6: Evidence ---------------------------------------------
        $topEvidence = $this->evidence->forCase((int) $top['case_id'], $scope);

        $trace->ran(
            'evidence',
            sprintf(
                '%d evidence row%s stored across %d case%s; %d cited on the highest-priority case.',
                $counters['evidence_collected'] ?? 0,
                ($counters['evidence_collected'] ?? 0) === 1 ? '' : 's',
                count($cases),
                count($cases) === 1 ? '' : 's',
                count($topEvidence)
            ),
            [
                'total_collected' => $counters['evidence_collected'] ?? 0,
                'rule' => 'Evidence read from a table is born verified; anything generated is stored '
                    . 'unverified and may not be cited as fact.',
                'sample' => array_map(fn ($row) => [
                    'kind' => $row['kind'],
                    'summary' => $row['summary'],
                    'source_table' => $row['source']['table'] ?? null,
                    'verified' => $row['verified'],
                ], array_slice($topEvidence, 0, 6)),
            ],
            ['table' => 'ai_evidence', 'ids' => array_column($topEvidence, 'id')],
            [
                'api' => 'GET ' . $this->prefix() . '/cases/' . $top['case_id'] . '/evidence',
                'sql' => 'select * from ai_case_evidence where case_id = ' . $top['case_id'],
            ]
        );

        // ---- Stage 7: Case --------------------------------------------------
        $caseIds = array_values(array_filter(array_column($cases, 'case_id')));

        $trace->ran(
            'case',
            sprintf('%d academic risk case%s opened, one per student.', count($caseIds), count($caseIds) === 1 ? '' : 's'),
            [
                'case_type' => AcademicRiskAgent::CASE_TYPE,
                'rule' => 'A case is about a person, not a metric — three signals for one student '
                    . 'make one case, not three.',
                'cases' => array_map(fn ($case) => [
                    'case_id' => $case['case_id'],
                    'student' => $case['student_name'],
                    'severity' => $case['severity'],
                    'priority_score' => $case['priority_score'],
                    'signals' => count($case['signals'] ?? []),
                ], $cases),
            ],
            ['table' => 'ai_cases', 'ids' => $caseIds],
            [
                'api' => 'GET ' . $this->prefix() . '/cases?case_type=' . AcademicRiskAgent::CASE_TYPE,
                'sql' => 'select id, case_reference, severity, status from ai_cases where id in (' . implode(',', $caseIds ?: [0]) . ')',
            ]
        );

        // ---- Stage 8: Explain -----------------------------------------------
        $governed = array_filter($cases, fn ($case) => ($case['explanation']['governance_passed'] ?? false) === true);
        $refused = array_filter($cases, fn ($case) => ($case['explanation']['governance_passed'] ?? false) === false);

        if ($governed !== []) {
            $trace->ran(
                'explanation',
                sprintf(
                    '%d explanation%s composed from cited evidence and passed governance%s.',
                    count($governed),
                    count($governed) === 1 ? '' : 's',
                    $refused === [] ? '' : sprintf('; %d refused', count($refused))
                ),
                [
                    'rule' => 'Each sentence cites the evidence ids that support it. A claim with nothing '
                        . 'to cite is dropped rather than softened.',
                    'top_narrative' => $top['explanation']['narrative'] ?? null,
                    'refusals' => array_map(fn ($case) => [
                        'case_id' => $case['case_id'],
                        'reason' => $case['explanation']['reason_refused'] ?? null,
                    ], array_values($refused)),
                ],
                ['table' => 'ai_explanations', 'ids' => []],
                ['api' => 'GET ' . $this->prefix() . '/cases/' . $top['case_id'] . '/explanation']
            );
        } else {
            $trace->blocked('explanation', 'Governance refused every explanation — no claim had citable evidence behind it.');
        }

        // ---- Stage 9: Template Engine ---------------------------------------
        $trace->notReached(
            'template',
            'Not used yet. The intervention text is generated inside the workflow, at the '
            . '"generate_activity" step, after a human approves. Nothing is generated before then.'
        );

        // ---- Stage 10: Recommendation ---------------------------------------
        $drafted = array_values(array_filter($cases, fn ($case) => ! empty($case['recommendation']['id'])));
        $recommendationIds = array_map(fn ($case) => $case['recommendation']['id'], $drafted);

        if ($drafted !== []) {
            $trace->ran(
                'recommendation',
                sprintf(
                    '%d intervention%s drafted and left waiting for a human decision.',
                    count($drafted),
                    count($drafted) === 1 ? '' : 's'
                ),
                [
                    'action_type' => 'create_academic_intervention',
                    'status' => 'pending_approval',
                    'rule' => 'The agent may draft the intervention. It may not create it — that needs an '
                        . 'approval and then the workflow.',
                    'bound_workflow' => AcademicRiskAgent::WORKFLOW_KEY,
                    'items' => array_map(fn ($case) => [
                        'recommendation_id' => $case['recommendation']['id'],
                        'title' => $case['recommendation']['title'],
                        'case_id' => $case['case_id'],
                        'requires_approval' => true,
                    ], $drafted),
                ],
                ['table' => 'ai_recommendations', 'ids' => $recommendationIds],
                [
                    'api' => 'GET ' . $this->prefix() . '/recommendations/pending',
                    'sql' => 'select id, title, status from ai_recommendations where id in (' . implode(',', $recommendationIds ?: [0]) . ')',
                ]
            );

            $trace->pending(
                'approval',
                sprintf(
                    '%d recommendation%s waiting on a teacher. Nothing downstream runs until one is approved.',
                    count($drafted),
                    count($drafted) === 1 ? '' : 's'
                ),
                ['gate' => 'App\\Domain\\AI\\Decisions\\DecisionGate', 'blocking' => true],
                ['table' => 'ai_recommendations', 'ids' => $recommendationIds],
                ['api' => 'POST ' . $this->prefix() . '/recommendations/' . ($recommendationIds[0] ?? '{id}') . '/approve']
            );
        } else {
            $trace->skipped('recommendation', 'No explanation passed governance, so nothing could be recommended.');
            $trace->notReached('approval', 'There is no recommendation to approve.');
        }

        foreach (['workflow', 'action', 'outcome'] as $stage) {
            $trace->notReached($stage, 'Waiting on the human decision above. This is the gate, not a gap.');
        }

        // ---- Stage 15: Learning ---------------------------------------------
        $this->learningStage($scope, $trace);

        // ---- The answer ------------------------------------------------------
        $items = array_map(function (array $case) {
            $signalSummaries = array_map(
                fn ($signal) => $signal['summary'] ?? ($signal['signal_key'] ?? 'signal'),
                $case['signals'] ?? []
            );

            return [
                'id' => $case['case_id'],
                'title' => $case['student_name'],
                'badge' => $this->compose->severityLabel($case['severity']),
                'badge_tone' => in_array($case['severity'], ['critical', 'high'], true) ? 'danger' : 'warning',
                'lines' => $signalSummaries,
                'meta' => array_filter([
                    'Case' => '#' . $case['case_id'],
                    'Class' => $case['placement']['standard_name'] ?? null,
                    'Priority' => number_format((float) ($case['priority_score'] ?? 0), 2),
                ]),
                'case_id' => $case['case_id'],
                'student_id' => $case['student_id'],
                'recommendation_id' => $case['recommendation']['id'] ?? null,
            ];
        }, $cases);

        $bySeverity = [];
        foreach ($cases as $case) {
            $bySeverity[$this->compose->severityLabel($case['severity'])][] = $case['student_name'];
        }

        $answer = $this->compose->make(
            sprintf(
                '%d student%s currently showing academic risk signals.',
                count($cases),
                count($cases) === 1 ? ' is' : 's are'
            ),
            [
                $this->compose->keyValues('Breakdown', array_map(
                    fn ($names) => implode(', ', $names),
                    $bySeverity
                )),
                $this->compose->records('Students', $items),
                $this->compose->evidence(
                    'Evidence behind the highest-priority case (' . ($top['student_name'] ?? '') . ')',
                    array_slice($topEvidence, 0, 5)
                ),
                $this->compose->text('Why ' . ($top['student_name'] ?? 'this student') . ' is flagged', $top['explanation']['narrative'] ?? ''),
                $drafted === [] ? null : $this->compose->text(
                    'Recommended action',
                    $drafted[0]['recommendation']['title'] . ' — waiting for your approval.'
                ),
            ],
            $drafted === [] ? [] : [
                $this->compose->action(
                    'approve',
                    'Approve: ' . $drafted[0]['recommendation']['title'],
                    'approve_recommendation',
                    ['recommendation_id' => $drafted[0]['recommendation']['id'], 'utterance' => 'Approve the recommendation.'],
                    'primary'
                ),
                $this->compose->action(
                    'reject',
                    'Reject',
                    'reject_recommendation',
                    ['recommendation_id' => $drafted[0]['recommendation']['id'], 'utterance' => 'Reject the recommendation.'],
                    'danger'
                ),
            ],
            [
                'Why is ' . ($top['student_name'] ?? 'this student') . ' at risk?',
                'What evidence supports this?',
                'What should the teacher do?',
            ]
        );

        return [
            $answer,
            [
                'subject_entity_key' => 'student',
                'student_id' => $topStudentId,
                'student_name' => $top['student_name'] ?? null,
                'case_id' => $top['case_id'] ?? null,
                'recommendation_id' => $top['recommendation']['id'] ?? null,
                'agent_run_id' => $run['run_id'],
                'last_case_list' => array_map(fn ($case) => [
                    'case_id' => $case['case_id'],
                    'student_id' => $case['student_id'],
                    'student_name' => $case['student_name'],
                ], $cases),
            ],
        ];
    }

    /**
     * The agent ran but opened no case — the single most confusing outcome in the whole
     * pipeline, and the one most likely to look like a broken system.
     *
     * It has two genuinely different causes, and conflating them is what makes the
     * platform feel dead: either no signal fired at all, or signals fired but none of
     * them cleared the bar for opening a case. The second is real work with a real
     * result, so it is reported as such — with the detected signals, the rule that held
     * them back, and how far short they fell.
     */
    private function noCasesOpened(McpRequestContext $scope, FlowTrace $trace, array $run, array $counters): array
    {
        $detected = Schema::hasTable('ai_signals') && $run['run_id']
            ? DB::table('ai_signals')
                ->where('sub_institute_id', $scope->selectedInstituteId)
                ->where('detected_by_run_id', $run['run_id'])
                ->orderByDesc('score')
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'signal_key' => $row->signal_key,
                    'student_id' => (int) $row->subject_id,
                    'student' => $row->subject_label,
                    'severity' => $row->severity,
                    'score' => (float) $row->score,
                ])
                ->all()
            : [];

        $bySubject = [];

        foreach ($detected as $signal) {
            $bySubject[$signal['student_id']][] = $signal;
        }

        $rule = 'A case opens when one signal reaches "high" (score 0.5 or more), or when the same '
            . 'student has at least two signals at "moderate" or above. Corroboration matters: one '
            . 'middling number is not yet a case.';

        $trace->skipped('ontology', 'No case was opened, so no student needed a relationship walk.');

        $trace->ran(
            'data',
            $detected === []
                ? 'The detectors read attendance, assessment and assignment records and nothing crossed its trigger.'
                : sprintf(
                    '%d signal%s raised across %d student%s — but none reached the bar for opening a case.',
                    count($detected),
                    count($detected) === 1 ? '' : 's',
                    count($bySubject),
                    count($bySubject) === 1 ? '' : 's'
                ),
            [
                'signals_detected' => $detected,
                'case_rule' => $rule,
                'tune_it' => 'The per-signal trigger and severity bands live in ai_signal_definitions.thresholds, '
                    . 'so a school can retune what counts as risk without a deploy.',
            ],
            ['table' => 'ai_signals', 'ids' => array_column($detected, 'id')],
            [
                'api' => 'GET ' . $this->prefix() . '/signals',
                'sql' => 'select signal_key, subject_id, severity, score from ai_signals where detected_by_run_id = '
                    . ($run['run_id'] ?? 0),
            ]
        );

        $detected === []
            ? $trace->skipped('evidence', 'No signal fired, so nothing was worth storing as evidence.')
            : $trace->ran(
                'evidence',
                sprintf('%d evidence row%s stored for the signals above, ready to cite if they escalate.',
                    $counters['evidence_collected'] ?? 0,
                    ($counters['evidence_collected'] ?? 0) === 1 ? '' : 's'),
                ['note' => 'Evidence is stored whether or not a case opens, so a trend is visible before it is a case.'],
                ['table' => 'ai_evidence', 'ids' => []],
                ['api' => 'GET ' . $this->prefix() . '/signals']
            );

        $trace->skipped(
            'case',
            $detected === []
                ? 'No signal to build a case from.'
                : 'Signals were recorded but none cleared the case threshold. ' . $rule
        );

        foreach (['explanation', 'template', 'recommendation'] as $stage) {
            $trace->skipped($stage, 'No case was opened, so there was nothing to explain or recommend.');
        }

        foreach (['approval', 'workflow', 'action', 'outcome'] as $stage) {
            $trace->notReached($stage, 'No case was opened, so this stage had nothing to act on.');
        }

        $this->learningStage($scope, $trace);

        if ($detected === []) {
            return [
                $this->compose->make(
                    'No students are currently showing academic risk signals.',
                    [$this->compose->text(
                        'What was checked',
                        'Attendance rate and absence streaks, assessment averages and their trend, and assignment '
                        . 'completion — for every student in the current scope. Nothing crossed its trigger.'
                    )],
                    [],
                    ['What has the system learned?']
                ),
                ['agent_run_id' => $run['run_id']],
            ];
        }

        return [
            $this->compose->make(
                sprintf(
                    'No case was opened, but %d signal%s did fire — none of them strong enough on its own.',
                    count($detected),
                    count($detected) === 1 ? '' : 's'
                ),
                [
                    $this->compose->records('Signals below the case threshold', array_map(fn ($signal) => [
                        'title' => $signal['student'] ?: ('Student #' . $signal['student_id']),
                        'badge' => ucfirst($signal['severity']),
                        'badge_tone' => 'warning',
                        'lines' => [str_replace('_', ' ', $signal['signal_key'])],
                        'meta' => [
                            'Score' => number_format($signal['score'], 3),
                            'Needs' => '0.500 to open a case alone',
                            'Short by' => number_format(max(0, 0.5 - $signal['score']), 3),
                        ],
                    ], $detected)),
                    $this->compose->text('Why no case was opened', $rule),
                    $this->compose->text(
                        'This is not a failure',
                        'The evidence behind these signals is stored either way. If a second signal appears for the '
                        . 'same student, or one of these worsens past 0.5, the next run opens a case automatically '
                        . 'and the rest of the journey follows.'
                    ),
                ],
                [],
                ['What has the system learned?']
            ),
            ['agent_run_id' => $run['run_id']],
        ];
    }

    // -------------------------------------------------- 2. why is X at risk

    private function explainStudent(Intent $intent, McpRequestContext $scope, FlowTrace $trace): array
    {
        $resolved = $this->resolveCase($intent, $scope, $trace);

        if ($resolved === null) {
            return $this->needSubject($trace, 'I need to know which student you mean.');
        }

        [$case, $studentId, $studentName] = $resolved;

        $trace->skipped('agent', 'No agent run needed — the case already exists. Re-running would re-analyse, not explain.');

        $this->ontologyStage($scope, $trace, $studentId, $studentName);

        $evidence = $this->evidence->forCase((int) $case['id'], $scope);
        $explanation = $this->explanations->latestForCase((int) $case['id'], $scope, 'teacher');

        $trace->ran(
            'data',
            'Read back from the stored case rather than re-queried — the numbers a teacher sees are the '
            . 'numbers the decision was made on.',
            ['case_opened_at' => $case['opened_at'] ?? null]
        );

        $trace->ran(
            'evidence',
            sprintf('%d evidence row%s cited by this case.', count($evidence), count($evidence) === 1 ? '' : 's'),
            ['kinds' => array_values(array_unique(array_column($evidence, 'kind')))],
            ['table' => 'ai_evidence', 'ids' => array_column($evidence, 'id')],
            ['api' => 'GET ' . $this->prefix() . '/cases/' . $case['id'] . '/evidence']
        );

        $trace->ran(
            'case',
            sprintf('Case #%d (%s), severity %s.', $case['id'], $case['reference'] ?? '', $case['severity'] ?? ''),
            ['case' => array_intersect_key($case, array_flip(['id', 'reference', 'case_type', 'severity', 'status', 'title', 'priority_score']))],
            ['table' => 'ai_cases', 'ids' => [$case['id']]],
            ['api' => 'GET ' . $this->prefix() . '/cases/' . $case['id']]
        );

        if ($explanation) {
            $trace->ran(
                'explanation',
                'Stored explanation returned, with the evidence each claim cites.',
                [
                    'explanation_id' => $explanation['id'] ?? null,
                    'audience' => 'teacher',
                    'claims' => $explanation['claims'] ?? [],
                    'governance_passed' => $explanation['governance_passed'] ?? true,
                ],
                ['table' => 'ai_explanations', 'ids' => array_filter([$explanation['id'] ?? null])],
                ['api' => 'GET ' . $this->prefix() . '/cases/' . $case['id'] . '/explanation']
            );
        } else {
            $trace->blocked('explanation', 'This case has no governed explanation stored.');
        }

        $recommendations = $this->recommendations->forCase((int) $case['id'], $scope);
        $pending = $this->firstPending($recommendations);

        $this->recommendationStages($trace, $recommendations, $pending);
        $this->downstreamStages($scope, $trace, $case, $pending);
        $this->learningStage($scope, $trace);

        $claims = $explanation['claims'] ?? [];

        $answer = $this->compose->make(
            sprintf('%s is flagged as %s.', $studentName, strtolower($this->compose->severityLabel($case['severity'] ?? null))),
            [
                $this->compose->text('Explanation', $explanation['narrative'] ?? ($case['summary'] ?? 'No stored explanation.')),
                $this->compose->records('Each claim, and what it rests on', array_map(fn ($claim) => [
                    'title' => is_array($claim) ? ($claim['claim'] ?? '') : (string) $claim,
                    'lines' => is_array($claim) && ! empty($claim['evidence_ids'])
                        ? ['Cites evidence #' . implode(', #', $claim['evidence_ids'])]
                        : [],
                    'meta' => is_array($claim) && isset($claim['confidence'])
                        ? ['Confidence' => number_format((float) $claim['confidence'], 2)]
                        : [],
                ], $claims)),
                $this->compose->evidence('Supporting evidence', array_slice($evidence, 0, 8)),
            ],
            $pending === null ? [] : [
                $this->compose->action('approve', 'Approve: ' . $pending['title'], 'approve_recommendation', [
                    'recommendation_id' => $pending['id'],
                    'utterance' => 'Approve the recommendation.',
                ], 'primary'),
            ],
            ['What evidence supports this?', 'What should the teacher do?']
        );

        return [
            $answer,
            [
                'subject_entity_key' => 'student',
                'student_id' => $studentId,
                'student_name' => $studentName,
                'case_id' => $case['id'],
                'recommendation_id' => $pending['id'] ?? null,
            ],
        ];
    }

    // ----------------------------------------------- 3. what evidence is there

    private function inspectEvidence(Intent $intent, McpRequestContext $scope, FlowTrace $trace): array
    {
        $resolved = $this->resolveCase($intent, $scope, $trace);

        if ($resolved === null) {
            return $this->needSubject($trace, 'I need a student or a case before I can show its evidence.');
        }

        [$case, $studentId, $studentName] = $resolved;

        $trace->skipped('agent', 'A read of stored evidence — no analysis to run.');
        $trace->skipped('ontology', 'The case already names its subject; no relationship walk was needed.');

        $evidence = $this->evidence->forCase((int) $case['id'], $scope);
        $signals = $this->signals->historyFor('student', $studentId, $scope);

        $sourceTables = array_values(array_unique(array_filter(array_map(
            fn ($row) => $row['source']['table'] ?? null,
            $evidence
        ))));

        $trace->ran(
            'data',
            $sourceTables === []
                ? 'All evidence for this case was computed rather than read from a single row.'
                : 'Traced back to source tables: ' . implode(', ', $sourceTables) . '.',
            ['source_tables' => $sourceTables]
        );

        $trace->ran(
            'evidence',
            sprintf(
                '%d row%s — %d verified, %d generated.',
                count($evidence),
                count($evidence) === 1 ? '' : 's',
                count(array_filter($evidence, fn ($row) => $row['verified'])),
                count(array_filter($evidence, fn ($row) => $row['is_generated']))
            ),
            [
                'by_kind' => array_count_values(array_column($evidence, 'kind')),
                'rule' => 'Only verified evidence may be cited in a claim.',
            ],
            ['table' => 'ai_evidence', 'ids' => array_column($evidence, 'id')],
            [
                'api' => 'GET ' . $this->prefix() . '/cases/' . $case['id'] . '/evidence',
                'sql' => 'select e.* from ai_evidence e join ai_case_evidence ce on ce.evidence_id = e.id where ce.case_id = ' . $case['id'],
            ]
        );

        $trace->ran(
            'case',
            sprintf('Evidence read against case #%d.', $case['id']),
            ['signals_on_record' => count($signals)],
            ['table' => 'ai_cases', 'ids' => [$case['id']]],
            ['api' => 'GET ' . $this->prefix() . '/cases/' . $case['id']]
        );

        $explanation = $this->explanations->latestForCase((int) $case['id'], $scope, 'teacher');
        $explanation
            ? $trace->ran('explanation', 'The stored explanation cites exactly these rows.', ['explanation_id' => $explanation['id'] ?? null])
            : $trace->skipped('explanation', 'No explanation stored for this case.');

        $recommendations = $this->recommendations->forCase((int) $case['id'], $scope);
        $pending = $this->firstPending($recommendations);

        $this->recommendationStages($trace, $recommendations, $pending);
        $this->downstreamStages($scope, $trace, $case, $pending);
        $this->learningStage($scope, $trace);

        return [
            $this->compose->make(
                sprintf('%d pieces of evidence support the case for %s.', count($evidence), $studentName),
                [
                    $this->compose->evidence('Evidence', $evidence),
                    $this->compose->records('Signals detected', array_map(fn ($signal) => [
                        'title' => $signal['summary'] ?? ($signal['signal_key'] ?? ''),
                        'badge' => ucfirst((string) ($signal['severity'] ?? '')),
                        'meta' => array_filter([
                            'Score' => isset($signal['score']) ? number_format((float) $signal['score'], 2) : null,
                            'Detected' => $signal['detected_at'] ?? null,
                        ]),
                    ], array_slice($signals, 0, 10))),
                    $this->compose->text(
                        'How to check this yourself',
                        'Every row above names the table it came from. Open that table at the id shown and you '
                        . 'will find the same number the case was built on.'
                    ),
                ],
                $pending === null ? [] : [
                    $this->compose->action('approve', 'Approve: ' . $pending['title'], 'approve_recommendation', [
                        'recommendation_id' => $pending['id'],
                        'utterance' => 'Approve the recommendation.',
                    ], 'primary'),
                ],
                ['What should the teacher do?', 'Why is ' . $studentName . ' at risk?']
            ),
            [
                'subject_entity_key' => 'student',
                'student_id' => $studentId,
                'student_name' => $studentName,
                'case_id' => $case['id'],
                'recommendation_id' => $pending['id'] ?? null,
            ],
        ];
    }

    // --------------------------------------------- 4. what should be done

    private function adviseAction(Intent $intent, McpRequestContext $scope, FlowTrace $trace): array
    {
        $resolved = $this->resolveCase($intent, $scope, $trace);

        if ($resolved === null) {
            return $this->needSubject($trace, 'I need a student or a case before I can recommend anything.');
        }

        [$case, $studentId, $studentName] = $resolved;

        $trace->skipped('agent', 'The recommendation was drafted on the earlier agent run; this reads it back.');
        $trace->skipped('ontology', 'No relationship walk needed to read a drafted recommendation.');
        $trace->skipped('data', 'No new query — the draft is bound to the evidence already stored.');

        $evidence = $this->evidence->forCase((int) $case['id'], $scope);
        $trace->ran(
            'evidence',
            sprintf('%d rows are bound to the draft as its justification.', count($evidence)),
            [],
            ['table' => 'ai_evidence', 'ids' => array_column($evidence, 'id')]
        );

        $trace->ran('case', sprintf('Case #%d.', $case['id']), [], ['table' => 'ai_cases', 'ids' => [$case['id']]]);

        $explanation = $this->explanations->latestForCase((int) $case['id'], $scope, 'teacher');
        $explanation
            ? $trace->ran('explanation', 'The recommendation carries this explanation as its body.', ['explanation_id' => $explanation['id'] ?? null])
            : $trace->skipped('explanation', 'No stored explanation.');

        $recommendations = $this->recommendations->forCase((int) $case['id'], $scope);
        $pending = $this->firstPending($recommendations);

        // ---- Template Engine: what *would* be generated, and where -----------
        $this->templateStage($trace, $scope, $pending !== null);

        $this->recommendationStages($trace, $recommendations, $pending);
        $this->downstreamStages($scope, $trace, $case, $pending);
        $this->learningStage($scope, $trace);

        if ($recommendations === []) {
            return [
                $this->compose->make(
                    'There is no recommendation on this case.',
                    [$this->compose->text(
                        'Why',
                        'A recommendation is only drafted when the explanation passes governance. Ask "why is '
                        . $studentName . ' at risk?" to see whether it did.'
                    )],
                    [],
                    ['Why is ' . $studentName . ' at risk?']
                ),
                ['case_id' => $case['id'], 'student_id' => $studentId],
            ];
        }

        $target = $pending ?? $recommendations[0];
        $eso = $target['eso_binding'] ?? [];

        $answer = $this->compose->make(
            $target['title'],
            [
                $this->compose->text('What this does', $target['body'] ?? ''),
                $this->compose->keyValues('The commitment behind it', array_filter([
                    'Objective' => $eso['objective'] ?? null,
                    'Strategy' => $eso['strategy'] ?? null,
                    'Measured by' => $eso['outcome']['metric_label'] ?? null,
                    'Direction' => $eso['outcome']['direction'] ?? null,
                    'Checked after' => isset($eso['outcome']['horizon_days'])
                        ? $eso['outcome']['horizon_days'] . ' days'
                        : null,
                ])),
                $this->compose->keyValues('Governance', array_filter([
                    'Status' => $target['status'] ?? null,
                    'Risk level' => $target['risk_level'] ?? null,
                    'Confidence' => isset($target['confidence']) ? number_format((float) $target['confidence'], 2) : null,
                    'Needs approval' => 'Yes — this is a consequential action',
                    'Runs on approval' => $target['workflow_key'] ?? null,
                ])),
                $this->compose->text(
                    'What happens if you approve',
                    'The k12_academic_intervention workflow starts: it drafts the practice activities from a '
                    . 'template, asks you to confirm them, creates the intervention on the student record, '
                    . 'notifies the student, and records the current assessment average as the baseline to '
                    . 'measure against.'
                ),
            ],
            $pending === null ? [] : [
                $this->compose->action('approve', 'Approve', 'approve_recommendation', [
                    'recommendation_id' => $pending['id'],
                    'utterance' => 'Approve the recommendation.',
                ], 'primary'),
                $this->compose->action('reject', 'Reject', 'reject_recommendation', [
                    'recommendation_id' => $pending['id'],
                    'utterance' => 'Reject the recommendation.',
                ], 'danger'),
            ],
            ['Approve the recommendation.', 'What evidence supports this?']
        );

        return [
            $answer,
            [
                'subject_entity_key' => 'student',
                'student_id' => $studentId,
                'student_name' => $studentName,
                'case_id' => $case['id'],
                'recommendation_id' => $target['id'] ?? null,
            ],
        ];
    }

    // ------------------------------------------------ 5. approve / reject

    /**
     * The gate. Approving is the only thing in this whole file that causes the platform
     * to change a student's record, and it happens through DecisionGate and the workflow
     * engine — never here.
     */
    private function decide(Intent $intent, McpRequestContext $scope, FlowTrace $trace, string $decision): array
    {
        // A workflow that has already started can also be waiting on a person — the
        // "teacher_approval" step inside k12_academic_intervention. That is a different
        // gate from the recommendation gate, so it is resolved through the engine rather
        // than through DecisionGate, and the trace says which one this was.
        if ($intent->slot('workflow_approval_id') !== null) {
            return $this->resolveWorkflowStep((int) $intent->slot('workflow_approval_id'), $scope, $trace, $decision);
        }

        $recommendationId = $intent->slot('recommendation_id');

        if ($recommendationId === null) {
            $pending = $this->recommendations->pendingApproval($scope, 5);

            if (count($pending) === 1) {
                $recommendationId = (int) $pending[0]['id'];
                $trace->ran('recommendation', 'One recommendation was pending, so that is the one this decision applies to.', [
                    'recommendation_id' => $recommendationId,
                ]);
            } elseif ($pending === []) {
                // Nothing at the recommendation gate — but a workflow already running can
                // still be parked on a person. "Approve" should mean the obvious thing
                // when only one gate is actually open.
                $steps = $this->pendingWorkflowApprovals($scope);

                if (count($steps) === 1) {
                    $trace->skipped('recommendation', 'Nothing at the recommendation gate; a running workflow is what needs you.');

                    return $this->resolveWorkflowStep((int) $steps[0]['id'], $scope, $trace, $decision);
                }

                $trace->skipped('recommendation', 'Nothing is waiting for approval.');
                $trace->notReached('approval', 'There is no pending recommendation to decide on.');

                return [
                    $this->compose->make(
                        count($steps) > 1
                            ? sprintf('%d workflow steps are waiting — say which one, or approve it from the case.', count($steps))
                            : 'There is nothing waiting for your approval.',
                        [$this->compose->text('Next', 'Run a risk scan first — that is what drafts a recommendation.')],
                        [],
                        ['Which students are at academic risk?']
                    ),
                    [],
                ];
            } else {
                $trace->pending('approval', sprintf('%d recommendations are pending — say which one.', count($pending)));

                return [
                    $this->compose->make(
                        'Which one would you like to ' . ($decision === 'approved' ? 'approve' : 'reject') . '?',
                        [$this->compose->records('Pending', array_map(fn ($row) => [
                            'id' => $row['id'],
                            'title' => $row['title'],
                            'meta' => ['Recommendation' => '#' . $row['id']],
                        ], $pending))],
                        array_map(fn ($row) => $this->compose->action(
                            'approve_' . $row['id'],
                            ($decision === 'approved' ? 'Approve' : 'Reject') . ': ' . $row['title'],
                            $decision === 'approved' ? 'approve_recommendation' : 'reject_recommendation',
                            [
                                'recommendation_id' => $row['id'],
                                'utterance' => ($decision === 'approved' ? 'Approve' : 'Reject') . ' recommendation ' . $row['id'],
                            ]
                        ), $pending),
                        []
                    ),
                    [],
                ];
            }
        }

        $record = $this->recommendations->find((int) $recommendationId, $scope);

        if (! $record) {
            $trace->blocked('approval', 'That recommendation is not in your scope.');

            return [
                $this->compose->make('I could not find that recommendation.', [], [], ['Which students are at academic risk?']),
                [],
            ];
        }

        $caseId = $record['case_id'] ?? null;
        $case = $caseId ? $this->cases->find((int) $caseId, $scope) : null;

        $trace->skipped('agent', 'A decision is a human act; no agent runs to record one.');
        $trace->skipped('ontology', 'Not needed to record a decision.');
        $trace->skipped('data', 'Not needed to record a decision.');
        $trace->ran('evidence', 'The evidence bound to this recommendation is what the decision is recorded against.', [
            'evidence_ids' => $record['evidence_ids'] ?? [],
        ], ['table' => 'ai_evidence', 'ids' => $record['evidence_ids'] ?? []]);
        $trace->ran('case', $caseId ? sprintf('Decision recorded against case #%d.', $caseId) : 'No case linked.', [], [
            'table' => 'ai_cases',
            'ids' => array_filter([$caseId]),
        ]);
        $trace->ran('explanation', 'The explanation the teacher read is stored with the decision.', [
            'explanation_id' => $record['explanation_id'] ?? null,
        ]);
        $trace->ran('recommendation', sprintf('Recommendation #%d — "%s".', $record['id'], $record['title']), [
            'status_before' => $record['status'],
            'action_type' => $record['action_type'] ?? null,
        ], ['table' => 'ai_recommendations', 'ids' => [$record['id']]]);

        // ---- Stage 11: Human Approval ---------------------------------------
        try {
            $result = $decision === 'approved'
                ? $this->decisions->approve((int) $recommendationId, $scope, 'Approved from the AI Journey console.')
                : $this->decisions->reject((int) $recommendationId, $scope, 'Rejected from the AI Journey console.');
        } catch (Throwable $exception) {
            $trace->blocked('approval', $exception->getMessage());

            foreach (['workflow', 'action', 'outcome'] as $stage) {
                $trace->notReached($stage, 'The decision was refused, so nothing downstream ran.');
            }

            return [
                $this->compose->make(
                    'That decision could not be recorded.',
                    [$this->compose->text('Why', $exception->getMessage())],
                    [],
                    []
                ),
                ['case_id' => $caseId, 'recommendation_id' => $record['id']],
            ];
        }

        $trace->ran(
            'approval',
            sprintf(
                'Recorded as %s by user #%d (%s). Decision #%d.',
                $decision,
                $scope->userId,
                $scope->role,
                $result['decision_id']
            ),
            [
                'decision' => $decision,
                'decision_id' => $result['decision_id'],
                'outcome_id' => $result['outcome_id'] ?? null,
                'rule' => 'A consequential recommendation cannot execute without this row. The workflow '
                    . 're-checks it before every consequential step.',
            ],
            ['table' => 'ai_decisions', 'ids' => [$result['decision_id']]],
            [
                'api' => 'GET ' . $this->prefix() . '/recommendations/' . $record['id'],
                'sql' => 'select * from ai_decisions where recommendation_id = ' . $record['id'],
            ]
        );

        if ($decision === 'rejected') {
            foreach (['template', 'workflow', 'action', 'outcome'] as $stage) {
                $trace->notReached($stage, 'The recommendation was rejected. Nothing downstream runs — that is the point of the gate.');
            }
            $this->learningStage($scope, $trace);

            return [
                $this->compose->make(
                    'Rejected. Nothing was created.',
                    [
                        $this->compose->text('What was recorded', sprintf(
                            'Decision #%d against recommendation #%d. The case stays open, and the rejection is '
                            . 'part of the record for the next time this pattern appears.',
                            $result['decision_id'],
                            $record['id']
                        )),
                    ],
                    [],
                    ['Which students are at academic risk?', 'What has the system learned?']
                ),
                [
                    'case_id' => $caseId,
                    'recommendation_id' => $record['id'],
                    'decision_id' => $result['decision_id'],
                ],
            ];
        }

        // ---- Stage 12: Workflow Engine ---------------------------------------
        $workflow = $this->startWorkflow($record, $result, $scope);
        $runId = $workflow['run_id'] ?? null;
        $status = $runId ? $this->workflows->status((int) $runId, $scope) : null;

        $trace->ran(
            'workflow',
            $runId
                ? sprintf(
                    'Workflow "%s" started as run #%d — status %s%s.',
                    $record['workflow_key'],
                    $runId,
                    $workflow['status'] ?? 'unknown',
                    isset($workflow['current_step']) && $workflow['current_step'] ? ', parked at "' . $workflow['current_step'] . '"' : ''
                )
                : ('The workflow did not start: ' . ($workflow['message'] ?? 'unknown reason')),
            [
                'workflow_key' => $record['workflow_key'] ?? null,
                'trigger' => 'recommendation_approved',
                'why_not_manual' => 'This workflow can only be reached by approving its recommendation, so the '
                    . 'decision record can never be skipped.',
                'run_id' => $runId,
                'status' => $workflow['status'] ?? null,
                'current_step' => $workflow['current_step'] ?? null,
                'steps' => $status['steps'] ?? [],
            ],
            ['table' => 'workflow_runs', 'ids' => array_filter([$runId])],
            [
                'api' => 'GET ' . $this->prefix() . '/workflow-runs/' . ($runId ?? '{id}'),
                'sql' => 'select step_key, status, finished_at from workflow_steps where run_id = ' . ($runId ?? 0) . ' order by sequence',
            ]
        );

        $this->templateStage($trace, $scope, true, $status);
        $this->actionStage($trace, $scope, $status, $record, $runId);
        $this->outcomeStage($trace, $scope, $record, $caseId);
        $this->learningStage($scope, $trace);

        $pendingApproval = $this->pendingWorkflowApproval($runId, $scope);
        $steps = $this->plannedSteps($status);

        $answer = $this->compose->make(
            $pendingApproval
                ? 'Approved. The intervention workflow is running and needs one more confirmation from you.'
                : 'Approved. The intervention has been created.',
            [
                $this->compose->keyValues('What was recorded', array_filter([
                    'Decision' => '#' . $result['decision_id'] . ' — approved by you',
                    'Workflow run' => $runId ? '#' . $runId : 'not started',
                    'Status' => $workflow['status'] ?? null,
                    'Outcome tracking' => ($result['outcome_id'] ?? null) ? 'baseline registered (#' . $result['outcome_id'] . ')' : null,
                ])),
                $this->compose->steps('Process steps', $steps),
                $pendingApproval
                    ? $this->compose->text(
                        'Waiting on you',
                        'The workflow drafted the practice activities and parked at "' . $pendingApproval['step_key']
                        . '". Confirm them and it will create the intervention.'
                    )
                    : null,
            ],
            $pendingApproval ? [
                $this->compose->action(
                    'confirm_step',
                    'Confirm the drafted activities',
                    'approve_recommendation',
                    [
                        'workflow_approval_id' => $pendingApproval['id'],
                        'utterance' => 'Approve the workflow step.',
                    ],
                    'primary'
                ),
            ] : [],
            ['What happened after approval?', 'Did the intervention work?']
        );

        return [
            $answer,
            [
                'subject_entity_key' => 'student',
                'student_id' => $record['subject_id'] ?? null,
                'case_id' => $caseId,
                'recommendation_id' => $record['id'],
                'decision_id' => $result['decision_id'],
                'workflow_run_id' => $runId,
                'outcome_id' => $result['outcome_id'] ?? null,
            ],
        ];
    }

    /**
     * The second human gate: confirming what the workflow drafted, mid-run.
     *
     * Resolving it resumes the run, which is what finally creates the intervention — so
     * this is the turn where the Action stage stops being "not reached".
     */
    private function resolveWorkflowStep(int $approvalId, McpRequestContext $scope, FlowTrace $trace, string $decision): array
    {
        foreach (['agent', 'ontology', 'data', 'evidence', 'explanation'] as $stage) {
            $trace->skipped($stage, 'Confirming a workflow step re-reads nothing; the analysis is already recorded.');
        }

        try {
            $result = $this->workflows->resolveApproval(
                $approvalId,
                $decision === 'approved' ? 'approved' : 'rejected',
                $scope,
                'Resolved from the AI Journey console.'
            );
        } catch (Throwable $exception) {
            $trace->blocked('approval', $exception->getMessage());
            foreach (['workflow', 'action', 'outcome'] as $stage) {
                $trace->notReached($stage, 'The step was not resolved, so the run did not resume.');
            }

            return [
                $this->compose->make(
                    'That step could not be resolved.',
                    [$this->compose->text('Why', $exception->getMessage())],
                    [],
                    ['What happened after approval?']
                ),
                [],
            ];
        }

        $runId = $result['run_id'] ?? null;
        $status = $runId ? $this->workflows->status((int) $runId, $scope) : null;

        $trace->ran(
            'approval',
            sprintf('Workflow step approval #%d recorded as %s by user #%d.', $approvalId, $decision, $scope->userId),
            [
                'gate' => 'workflow step (in-run)',
                'approval_id' => $approvalId,
                'distinct_from' => 'the recommendation-level approval recorded in ai_decisions',
            ],
            ['table' => 'workflow_approvals', 'ids' => [$approvalId]],
            ['sql' => 'select * from workflow_approvals where id = ' . $approvalId]
        );

        $trace->ran(
            'case',
            $status && $status['case_id'] ? sprintf('Case #%d.', $status['case_id']) : 'Linked case unchanged.',
            [],
            ['table' => 'ai_cases', 'ids' => array_filter([$status['case_id'] ?? null])]
        );

        $trace->ran(
            'recommendation',
            $status && $status['recommendation_id']
                ? sprintf('Carrying recommendation #%d, already approved.', $status['recommendation_id'])
                : 'No recommendation linked to this run.',
            [],
            ['table' => 'ai_recommendations', 'ids' => array_filter([$status['recommendation_id'] ?? null])]
        );

        $trace->ran(
            'workflow',
            sprintf('Run #%s resumed — now %s.', (string) $runId, $result['status'] ?? 'unknown'),
            ['steps' => $status['steps'] ?? []],
            ['table' => 'workflow_runs', 'ids' => array_filter([$runId])],
            ['api' => 'GET ' . $this->prefix() . '/workflow-runs/' . ($runId ?? '{id}')]
        );

        $this->templateStage($trace, $scope, true, $status);
        $this->actionStage($trace, $scope, $status, [], $runId ? (int) $runId : null);
        $this->outcomeStage($trace, $scope, [], $status['case_id'] ?? null);
        $this->learningStage($scope, $trace);

        $steps = $this->plannedSteps($status);

        return [
            $this->compose->make(
                $decision === 'approved'
                    ? sprintf('Confirmed. The workflow is now %s.', $result['status'] ?? 'running')
                    : 'Rejected. The workflow was stopped and nothing was created.',
                [
                    $this->compose->steps('Process steps', $steps),
                    $this->compose->text(
                        'What changed',
                        $decision === 'approved'
                            ? 'The run resumed past the confirmation step. Anything after it — creating the '
                            . 'intervention, notifying the student, capturing the baseline — has now executed.'
                            : 'The run was closed as rejected. No intervention exists, and the case stays open.'
                    ),
                ],
                [],
                ['Did the intervention work?', 'What happened after approval?']
            ),
            [
                'case_id' => $status['case_id'] ?? null,
                'student_id' => $status['subject_id'] ?? null,
                'recommendation_id' => $status['recommendation_id'] ?? null,
                'workflow_run_id' => $runId,
            ],
        ];
    }

    // ------------------------------------------------ 6. workflow status

    private function workflowStatus(Intent $intent, McpRequestContext $scope, FlowTrace $trace): array
    {
        $resolved = $this->resolveCase($intent, $scope, $trace);

        if ($resolved === null) {
            return $this->needSubject($trace, 'I need a student or case before I can show what happened.');
        }

        [$case, $studentId, $studentName] = $resolved;

        foreach (['agent', 'ontology', 'data'] as $stage) {
            $trace->skipped($stage, 'A read of what already ran.');
        }

        $trace->ran('case', sprintf('Case #%d.', $case['id']), [], ['table' => 'ai_cases', 'ids' => [$case['id']]]);
        $trace->skipped('evidence', 'Not re-read for a status question.');
        $trace->skipped('explanation', 'Not re-read for a status question.');

        $recommendations = $this->recommendations->forCase((int) $case['id'], $scope);
        $approved = array_values(array_filter($recommendations, fn ($row) => ($row['status'] ?? '') === 'approved'));

        $this->recommendationStages($trace, $recommendations, $this->firstPending($recommendations));

        if ($approved === []) {
            $trace->notReached('approval', 'Nothing on this case has been approved yet.');
            foreach (['template', 'workflow', 'action', 'outcome'] as $stage) {
                $trace->notReached($stage, 'No approval, so the workflow never started.');
            }
            $this->learningStage($scope, $trace);

            return [
                $this->compose->make(
                    'Nothing has run yet — this case is still waiting for an approval.',
                    [$this->compose->text('Why', 'The intervention workflow only starts when its recommendation is approved.')],
                    [],
                    ['What should the teacher do?', 'Approve the recommendation.']
                ),
                ['case_id' => $case['id'], 'student_id' => $studentId],
            ];
        }

        $trace->ran('approval', sprintf('%d approved recommendation(s) on this case.', count($approved)), [
            'recommendation_ids' => array_column($approved, 'id'),
        ], ['table' => 'ai_decisions', 'ids' => []]);

        $runs = Schema::hasTable('workflow_runs')
            ? DB::table('workflow_runs')
                ->where('sub_institute_id', $scope->selectedInstituteId)
                ->where('case_id', $case['id'])
                ->orderByDesc('id')
                ->limit(5)
                ->get()
            : collect();

        if ($runs->isEmpty()) {
            $trace->blocked('workflow', 'The recommendation was approved but no workflow run exists for this case.');
            foreach (['template', 'action', 'outcome'] as $stage) {
                $trace->notReached($stage, 'No workflow run to carry them.');
            }
            $this->learningStage($scope, $trace);

            return [
                $this->compose->make(
                    'The recommendation was approved, but the workflow did not start.',
                    [$this->compose->text('What to do', 'Re-approving is safe; the decision is already recorded and will not be duplicated.')],
                    [],
                    []
                ),
                ['case_id' => $case['id']],
            ];
        }

        $runId = (int) $runs->first()->id;
        $status = $this->workflows->status($runId, $scope);
        $steps = $this->plannedSteps($status);

        $trace->ran(
            'workflow',
            sprintf('Run #%d — %s.', $runId, $status['status'] ?? 'unknown'),
            ['run' => $status, 'steps' => $steps],
            ['table' => 'workflow_runs', 'ids' => [$runId]],
            ['api' => 'GET ' . $this->prefix() . '/workflow-runs/' . $runId]
        );

        $this->templateStage($trace, $scope, true, $status);
        $this->actionStage($trace, $scope, $status, $approved[0] ?? [], $runId);
        $this->outcomeStage($trace, $scope, $approved[0] ?? [], $case['id']);
        $this->learningStage($scope, $trace);

        $pendingApproval = $this->pendingWorkflowApproval($runId, $scope);

        return [
            $this->compose->make(
                sprintf(
                    'Workflow run #%d for %s is %s.',
                    $runId,
                    $studentName,
                    $status['status'] ?? 'unknown'
                ),
                [
                    $this->compose->steps('Every step, in order', $steps),
                    $pendingApproval
                        ? $this->compose->text('Waiting on you', 'Step "' . $pendingApproval['step_key'] . '" needs your confirmation.')
                        : null,
                ],
                $pendingApproval ? [
                    $this->compose->action('confirm_step', 'Confirm', 'approve_recommendation', [
                        'workflow_approval_id' => $pendingApproval['id'],
                        'utterance' => 'Approve the workflow step.',
                    ], 'primary'),
                ] : [],
                ['Did the intervention work?']
            ),
            [
                'case_id' => $case['id'],
                'student_id' => $studentId,
                'workflow_run_id' => $runId,
            ],
        ];
    }

    // ---------------------------------------------------- 7. did it work

    private function outcomeStatus(Intent $intent, McpRequestContext $scope, FlowTrace $trace): array
    {
        $resolved = $this->resolveCase($intent, $scope, $trace);

        if ($resolved === null) {
            return $this->needSubject($trace, 'I need a student before I can check an outcome.');
        }

        [$case, $studentId, $studentName] = $resolved;

        foreach (['agent', 'ontology', 'evidence', 'explanation'] as $stage) {
            $trace->skipped($stage, 'An outcome question reads measurements, not analysis.');
        }

        $trace->ran('case', sprintf('Case #%d.', $case['id']), [], ['table' => 'ai_cases', 'ids' => [$case['id']]]);

        $outcomes = $this->outcomes->forSubject('student', $studentId, $scope, 10);
        $forCase = array_values(array_filter($outcomes, fn ($row) => (int) ($row['case_id'] ?? 0) === (int) $case['id']));
        $outcomes = $forCase !== [] ? $forCase : $outcomes;

        $trace->ran(
            'data',
            $outcomes === []
                ? 'No outcome row exists for this student yet.'
                : 'Metric re-read from the same resolver that recorded the baseline, so before and after are comparable.',
            ['metric_resolvers' => ['assessment_average', 'attendance_rate']]
        );

        $recommendations = $this->recommendations->forCase((int) $case['id'], $scope);
        $this->recommendationStages($trace, $recommendations, $this->firstPending($recommendations));

        $approved = array_values(array_filter($recommendations, fn ($row) => ($row['status'] ?? '') === 'approved'));
        $approved === []
            ? $trace->notReached('approval', 'Nothing approved on this case.')
            : $trace->ran('approval', 'Approved earlier — that decision is what registered the outcome to track.', [
                'recommendation_ids' => array_column($approved, 'id'),
            ]);

        $trace->skipped('template', 'Not involved in measurement.');

        $runId = Schema::hasTable('workflow_runs')
            ? DB::table('workflow_runs')
                ->where('sub_institute_id', $scope->selectedInstituteId)
                ->where('case_id', $case['id'])
                ->orderByDesc('id')
                ->value('id')
            : null;

        $runId
            ? $trace->ran('workflow', sprintf('Run #%d carried the intervention that this outcome measures.', $runId), [], [
                'table' => 'workflow_runs',
                'ids' => [(int) $runId],
            ])
            : $trace->notReached('workflow', 'No workflow run for this case.');

        $this->actionStage($trace, $scope, $runId ? $this->workflows->status((int) $runId, $scope) : null, $approved[0] ?? [], $runId ? (int) $runId : null);

        if ($outcomes === []) {
            $trace->notReached('outcome', 'No outcome is being tracked for this student yet — approving a recommendation is what registers one.');
            $this->learningStage($scope, $trace);

            return [
                $this->compose->make(
                    'Nothing is being measured for ' . $studentName . ' yet.',
                    [$this->compose->text(
                        'Why',
                        'An outcome is registered when a recommendation is approved: the recommendation names the '
                        . 'metric, the direction, and how long to wait before measuring.'
                    )],
                    [],
                    ['What should the teacher do?']
                ),
                ['case_id' => $case['id'], 'student_id' => $studentId],
            ];
        }

        $trace->ran(
            'outcome',
            sprintf(
                '%d outcome%s tracked; %s.',
                count($outcomes),
                count($outcomes) === 1 ? '' : 's',
                implode(', ', array_map(fn ($row) => $row['metric_label'] . ' is ' . $row['status'], $outcomes))
            ),
            ['outcomes' => $outcomes],
            ['table' => 'ai_outcomes', 'ids' => array_column($outcomes, 'id')],
            [
                'api' => 'GET ' . $this->prefix() . '/outcomes?subject_entity_key=student&subject_id=' . $studentId,
                'sql' => 'select metric_label, baseline_value, observed_value, delta, status from ai_outcomes where subject_id = ' . $studentId,
            ]
        );

        $this->learningStage($scope, $trace);

        $rows = array_map(fn ($row) => [
            'label' => $row['metric_label'],
            'before' => $row['baseline_value'],
            'after' => $row['observed_value'],
            'delta' => $row['delta'],
            'target' => $row['target_value'],
            'status' => $row['status'],
            'measured_at' => $row['observed_at'],
            'measure_after' => $row['measure_after'],
        ], $outcomes);

        $measured = array_values(array_filter($rows, fn ($row) => $row['after'] !== null));

        return [
            $this->compose->make(
                $measured === []
                    ? sprintf('Too early to tell — the first measurement for %s is due %s.', $studentName, $rows[0]['measure_after'] ?? 'soon')
                    : sprintf(
                        '%s: %s went from %s to %s (%s).',
                        $studentName,
                        $measured[0]['label'],
                        $this->number($measured[0]['before']),
                        $this->number($measured[0]['after']),
                        $measured[0]['status']
                    ),
                [
                    $this->compose->comparison('Before and after', $rows),
                    $this->compose->text(
                        'How this is measured',
                        'The baseline was captured at approval by the same resolver that reads the value now, '
                        . 'so the comparison is like-for-like rather than two different calculations.'
                    ),
                ],
                [],
                ['What has the system learned?']
            ),
            [
                'case_id' => $case['id'],
                'student_id' => $studentId,
                'outcome_id' => $outcomes[0]['id'] ?? null,
            ],
        ];
    }

    // ------------------------------------------------------ 8. learning

    private function learning(Intent $intent, McpRequestContext $scope, FlowTrace $trace): array
    {
        foreach (['agent', 'ontology', 'data', 'evidence', 'case', 'explanation', 'template', 'recommendation', 'approval', 'workflow', 'action'] as $stage) {
            $trace->skipped($stage, 'A learning question aggregates outcomes; it does not re-run the pipeline.');
        }

        $effectiveness = $this->outcomes->effectivenessByActionType($scope, AcademicRiskAgent::CASE_TYPE);

        $trace->ran(
            'outcome',
            'Read every measured outcome for academic risk cases.',
            ['case_type' => AcademicRiskAgent::CASE_TYPE],
            ['table' => 'ai_outcomes', 'ids' => []],
            ['api' => 'GET ' . $this->prefix() . '/outcomes/effectiveness']
        );

        $this->learningStage($scope, $trace, $effectiveness);

        if ($effectiveness === []) {
            return [
                $this->compose->make(
                    'Nothing has been measured yet, so there is nothing learned yet.',
                    [$this->compose->text(
                        'How the loop closes',
                        'Each approved intervention registers an outcome with a metric and a horizon. When that '
                        . 'horizon passes and the outcome is measured, it counts towards the effectiveness of its '
                        . 'action type. Once enough have been measured, this is what tells you which interventions '
                        . 'actually move the number.'
                    )],
                    [],
                    ['Which students are at academic risk?']
                ),
                [],
            ];
        }

        return [
            $this->compose->make(
                'Effectiveness of academic interventions so far.',
                [
                    $this->compose->records('By action type', array_map(fn ($actionType, $row) => [
                        'title' => $actionType,
                        'lines' => [
                            sprintf(
                                'improved %d, unchanged %d, worsened %d',
                                $row['counts']['improved'] ?? 0,
                                $row['counts']['unchanged'] ?? 0,
                                $row['counts']['worsened'] ?? 0
                            ),
                        ],
                        'meta' => array_filter([
                            'Measured' => $row['total'] ?? null,
                            'Improvement rate' => $row['improvement_rate'] === null
                                ? null
                                : round(((float) $row['improvement_rate']) * 100) . '%',
                        ]),
                    ], array_keys($effectiveness), $effectiveness)),
                    $this->compose->text(
                        'What the system does with this',
                        'This is the feedback signal: an action type that keeps failing to move its metric is '
                        . 'evidence against recommending it again, and the same measurement is what justifies '
                        . 'recommending one that works.'
                    ),
                ],
                [],
                ['Which students are at academic risk?']
            ),
            [],
        ];
    }

    // ------------------------------------------------------------ shared stages

    /**
     * Ontology and Knowledge Graph, reported honestly: which entity was resolved, and
     * which relations were actually walkable from it.
     */
    private function ontologyStage(McpRequestContext $scope, FlowTrace $trace, int $studentId, ?string $studentName): void
    {
        if ($studentId <= 0) {
            $trace->skipped('ontology', 'No single student to resolve.');

            return;
        }

        $entity = $this->entities->resolveOne('student', $studentId, $scope);
        $relations = $this->graph->availableRelations('student', $scope->selectedInstituteId);
        $walked = [];

        foreach (['enrolled_in', 'has_attendance', 'has_assessment', 'assigned_work'] as $relation) {
            if (! in_array($relation, array_column($relations, 'relation'), true)) {
                continue;
            }

            $neighbours = $this->graph->neighbours('student', $studentId, $relation, $scope, 5);

            if ($neighbours !== []) {
                $walked[$relation] = count($neighbours);
            }
        }

        $trace->ran(
            'ontology',
            $entity
                ? sprintf(
                    'Resolved "%s" to the student entity (#%d) and walked %d relation%s from it.',
                    $studentName ?? $entity['label'],
                    $studentId,
                    count($walked),
                    count($walked) === 1 ? '' : 's'
                )
                : sprintf('Student #%d could not be resolved in the ontology.', $studentId),
            [
                'entity' => 'student',
                'resolved_label' => $entity['label'] ?? null,
                'declared_relations' => array_column($relations, 'relation'),
                'relations_walked' => $walked,
                'why' => 'The relations are declared in ontology_relationships, not hard-coded, which is why '
                    . 'the same walk works for a teacher or a class without new code.',
            ],
            ['table' => 'ontology_relationships', 'ids' => []],
            [
                'api' => 'GET ' . $this->prefix() . '/knowledge-graph/relations/student',
                'sql' => 'select relation, target_entity_key from ontology_relationships where source_entity_key = "student"',
            ]
        );
    }

    /**
     * The real-data stage: which detectors ran, and against which tables.
     */
    private function dataStage(McpRequestContext $scope, FlowTrace $trace, array $cases, array $counters): void
    {
        $signalKinds = [];

        foreach ($cases as $case) {
            foreach ($case['signals'] ?? [] as $signal) {
                $key = $signal['signal_key'] ?? 'unknown';
                $signalKinds[$key] = ($signalKinds[$key] ?? 0) + 1;
            }
        }

        $trace->ran(
            'data',
            sprintf(
                'Three detectors queried live records and raised %d signal%s across %d student%s.',
                $counters['signals_detected'] ?? 0,
                ($counters['signals_detected'] ?? 0) === 1 ? '' : 's',
                count($cases),
                count($cases) === 1 ? '' : 's'
            ),
            [
                'detectors' => [
                    'attendance_risk' => 'reads the attendance records in scope and computes absence rate and streak',
                    'assessment_decline' => 'reads assessment attempts and compares the recent window against the previous one',
                    'missed_assignments' => 'reads assigned activities and their completion state',
                ],
                'signals_by_type' => $signalKinds,
                'scope_pinned_to' => [
                    'sub_institute_id' => $scope->selectedInstituteId,
                    'academic_year' => $scope->academicYear,
                    'term_id' => $scope->termId,
                ],
                'note' => 'Thresholds come from ai_signal_definitions, so a school can retune them without a deploy.',
            ],
            ['table' => 'ai_signals', 'ids' => []],
            [
                'api' => 'GET ' . $this->prefix() . '/signals',
                'sql' => 'select signal_key, severity, score, detected_at from ai_signals order by id desc limit 20',
            ]
        );
    }

    /**
     * The Template Engine stage. It is the one most often misunderstood, so it reports
     * where generation is allowed to happen and where it is not.
     */
    private function templateStage(FlowTrace $trace, McpRequestContext $scope, bool $reachable, ?array $status = null): void
    {
        $template = $this->templateFor('k12.intervention_activity', $scope);

        $generateStep = null;

        foreach ($status['steps'] ?? [] as $step) {
            if (($step['step_key'] ?? null) === 'generate_activity') {
                $generateStep = $step;
            }
        }

        if ($generateStep !== null) {
            $trace->ran(
                'template',
                sprintf(
                    'Template "k12.intervention_activity" rendered at the workflow\'s generate_activity step (%s).',
                    $generateStep['status'] ?? 'unknown'
                ),
                [
                    'template_key' => 'k12.intervention_activity',
                    'version' => $template['version'] ?? null,
                    'variables' => $template['variables'] ?? [],
                    'guardrails' => $template['guardrails'] ?? [],
                    'output' => $generateStep['output'] ?? null,
                    'rule' => 'Generated text is stored as unverified. It can be shown to a human for '
                        . 'confirmation, but it is never cited as evidence.',
                ],
                ['table' => 'ai_generation_outputs', 'ids' => []],
                [
                    'api' => 'GET ' . $this->prefix() . '/templates',
                    'sql' => 'select * from ai_generation_requests order by id desc limit 5',
                ]
            );

            return;
        }

        $trace->notReached(
            'template',
            $reachable
                ? 'Declared but not yet rendered. Generation happens inside the workflow at the '
                . '"generate_activity" step — after approval, never before.'
                : 'Not used on this path. No text is generated when reading stored records.'
        );
    }

    /**
     * The Action stage — did anything actually change on the student record.
     */
    private function actionStage(FlowTrace $trace, McpRequestContext $scope, ?array $status, array $recommendation, ?int $runId): void
    {
        $createStep = null;

        foreach ($status['steps'] ?? [] as $step) {
            if (($step['step_key'] ?? null) === 'create_intervention') {
                $createStep = $step;
            }
        }

        if ($createStep === null) {
            $trace->notReached(
                'action',
                $runId
                    ? 'The workflow has not reached "create_intervention" yet.'
                    : 'No workflow run, so no action was performed.'
            );

            return;
        }

        if (($createStep['status'] ?? null) !== 'completed') {
            $trace->pending(
                'action',
                sprintf('Step "create_intervention" is %s.', $createStep['status'] ?? 'pending'),
                ['step' => $createStep]
            );

            return;
        }

        $interventionId = $createStep['output']['intervention_id'] ?? null;

        $intervention = $interventionId && Schema::hasTable('academic_interventions')
            ? DB::table('academic_interventions')
                ->where('id', $interventionId)
                ->where('sub_institute_id', $scope->selectedInstituteId)
                ->first()
            : null;

        $trace->ran(
            'action',
            $intervention
                ? sprintf('Academic intervention #%d created on the student record.', $interventionId)
                : 'The create_intervention step completed.',
            [
                'action_class' => 'App\\Domain\\K12\\AcademicRisk\\CreateAcademicInterventionAction',
                'intervention_id' => $interventionId,
                'step_output' => $createStep['output'] ?? null,
                'rule' => 'The action re-checks the approval before it writes. An action reached any other way '
                    . 'refuses.',
                'record' => $intervention ? (array) $intervention : null,
            ],
            ['table' => 'academic_interventions', 'ids' => array_filter([$interventionId])],
            [
                'sql' => 'select * from academic_interventions where id = ' . ((int) $interventionId),
            ]
        );
    }

    /**
     * The Outcome stage — what will be measured, when, and against what baseline.
     */
    private function outcomeStage(FlowTrace $trace, McpRequestContext $scope, array $recommendation, ?int $caseId): void
    {
        if (! Schema::hasTable('ai_outcomes') || ! $caseId) {
            $trace->notReached('outcome', 'No case to attach an outcome to.');

            return;
        }

        $rows = DB::table('ai_outcomes')
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->where('case_id', $caseId)
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            $trace->notReached('outcome', 'No outcome registered for this case yet.');

            return;
        }

        $first = $rows->first();

        $trace->ran(
            'outcome',
            $first->observed_value === null
                ? sprintf(
                    'Baseline captured: %s = %s. Next measurement due %s.',
                    $first->metric_label,
                    $this->number($first->baseline_value),
                    $first->measure_after
                )
                : sprintf(
                    '%s moved from %s to %s (%s).',
                    $first->metric_label,
                    $this->number($first->baseline_value),
                    $this->number($first->observed_value),
                    $first->status
                ),
            [
                'metric_key' => $first->metric_key,
                'baseline' => $first->baseline_value === null ? null : (float) $first->baseline_value,
                'target' => $first->target_value === null ? null : (float) $first->target_value,
                'observed' => $first->observed_value === null ? null : (float) $first->observed_value,
                'measure_after' => $first->measure_after,
                'status' => $first->status,
                'rule' => 'The metric and horizon come from the recommendation\'s ESO binding, so what counts as '
                    . 'success was fixed before the action ran rather than chosen afterwards.',
            ],
            ['table' => 'ai_outcomes', 'ids' => $rows->pluck('id')->map(fn ($id) => (int) $id)->all()],
            [
                'api' => 'POST ' . $this->prefix() . '/outcomes/measure-due',
                'sql' => 'select * from ai_outcomes where case_id = ' . $caseId,
            ]
        );
    }

    /**
     * The Learning stage — always reported, because "nothing measured yet" is itself the
     * honest state of a loop that has not closed.
     */
    private function learningStage(McpRequestContext $scope, FlowTrace $trace, ?array $effectiveness = null): void
    {
        $effectiveness ??= $this->outcomes->effectivenessByActionType($scope, AcademicRiskAgent::CASE_TYPE);

        if ($effectiveness === []) {
            $trace->pending(
                'learning',
                'No academic intervention has been measured yet, so there is no effectiveness signal to feed back.',
                ['closes_when' => 'an approved intervention passes its measurement horizon and is measured'],
                [],
                ['api' => 'GET ' . $this->prefix() . '/outcomes/effectiveness']
            );

            return;
        }

        $trace->ran(
            'learning',
            sprintf('Effectiveness known for %d action type(s).', count($effectiveness)),
            [
                'effectiveness' => $effectiveness,
                'how_it_feeds_back' => 'Measured outcomes are grouped by action type. That distribution is the '
                    . 'evidence for or against recommending the same action next time.',
            ],
            ['table' => 'ai_outcomes', 'ids' => []],
            ['api' => 'GET ' . $this->prefix() . '/outcomes/effectiveness']
        );
    }

    /**
     * Recommendation stage for read-only paths.
     */
    private function recommendationStages(FlowTrace $trace, array $recommendations, ?array $pending): void
    {
        if ($recommendations === []) {
            $trace->skipped('recommendation', 'No recommendation exists on this case.');
            $trace->notReached('approval', 'Nothing to approve.');

            return;
        }

        $trace->ran(
            'recommendation',
            sprintf(
                '%d recommendation%s on this case (%s).',
                count($recommendations),
                count($recommendations) === 1 ? '' : 's',
                implode(', ', array_unique(array_column($recommendations, 'status')))
            ),
            [
                'items' => array_map(fn ($row) => [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'status' => $row['status'],
                    'action_type' => $row['action_type'] ?? null,
                    'workflow_key' => $row['workflow_key'] ?? null,
                ], $recommendations),
            ],
            ['table' => 'ai_recommendations', 'ids' => array_column($recommendations, 'id')],
            ['api' => 'GET ' . $this->prefix() . '/cases/{case}/recommendations']
        );

        if ($pending !== null) {
            $trace->pending(
                'approval',
                sprintf('Recommendation #%d is waiting for a human decision.', $pending['id']),
                ['blocking' => true],
                ['table' => 'ai_recommendations', 'ids' => [$pending['id']]],
                ['api' => 'POST ' . $this->prefix() . '/recommendations/' . $pending['id'] . '/approve']
            );
        }
    }

    /**
     * Workflow / action / outcome for read-only paths, driven by what actually exists.
     */
    private function downstreamStages(McpRequestContext $scope, FlowTrace $trace, array $case, ?array $pending): void
    {
        $runId = Schema::hasTable('workflow_runs')
            ? DB::table('workflow_runs')
                ->where('sub_institute_id', $scope->selectedInstituteId)
                ->where('case_id', $case['id'])
                ->orderByDesc('id')
                ->value('id')
            : null;

        if (! $runId) {
            $trace->notReached(
                'workflow',
                $pending !== null
                    ? 'Waiting on the approval above — the workflow only starts once a human approves.'
                    : 'No workflow run for this case.'
            );
            $trace->notReached('action', 'No workflow run, so nothing has been created.');
            $trace->notReached('outcome', 'Nothing is being measured until an intervention exists.');

            return;
        }

        $status = $this->workflows->status((int) $runId, $scope);

        $trace->ran(
            'workflow',
            sprintf('Run #%d — %s.', $runId, $status['status'] ?? 'unknown'),
            ['steps' => $status['steps'] ?? []],
            ['table' => 'workflow_runs', 'ids' => [(int) $runId]],
            ['api' => 'GET ' . $this->prefix() . '/workflow-runs/' . $runId]
        );

        $this->actionStage($trace, $scope, $status, [], (int) $runId);
        $this->outcomeStage($trace, $scope, [], (int) $case['id']);
    }

    // ------------------------------------------------------------- helpers

    /**
     * Work out which case the user means, from the sentence or from memory, and report
     * the resolution in the trace so an inherited subject is never invisible.
     *
     * @return array{0:array, 1:int, 2:string}|null
     */
    private function resolveCase(Intent $intent, McpRequestContext $scope, FlowTrace $trace): ?array
    {
        $caseId = $intent->slot('case_id');
        $studentId = $intent->slot('student_id');
        $case = null;

        if ($caseId !== null) {
            $case = $this->cases->find((int) $caseId, $scope);
        }

        // A name given in this sentence beats anything remembered.
        if ($case === null && $intent->slot('student_name') !== null && $studentId === null) {
            $matches = $this->entities->resolve('student', $scope, $intent->slot('student_name'), 5);

            if (count($matches) === 1) {
                $studentId = (int) $matches[0]['id'];
            } elseif (count($matches) > 1) {
                $trace->blocked('gen_ai', sprintf(
                    '"%s" matches %d students — the question needs to be more specific.',
                    $intent->slot('student_name'),
                    count($matches)
                ));
            }
        }

        // "Student A" / "Student B" refer to positions in the last answer's list.
        if ($case === null && $studentId === null && $intent->slot('student_label') !== null) {
            $studentId = null; // resolved by the caller's memory list, below
        }

        if ($case === null && $studentId !== null) {
            $open = $this->cases->list($scope, AcademicRiskAgent::CASE_TYPE, 'open', null, 100);

            foreach ($open as $candidate) {
                if ((int) ($candidate['subject_id'] ?? 0) === (int) $studentId) {
                    $case = $candidate;
                    break;
                }
            }
        }

        if ($case === null) {
            return null;
        }

        $studentId = (int) ($case['subject_id'] ?? $studentId ?? 0);
        $entity = $studentId > 0 ? $this->entities->resolveOne('student', $studentId, $scope) : null;
        $studentName = $entity['label'] ?? ($intent->slot('student_name') ?? ('Student #' . $studentId));

        return [$case, $studentId, $studentName];
    }

    private function needSubject(FlowTrace $trace, string $message): array
    {
        foreach (['agent', 'ontology', 'data', 'evidence', 'case', 'explanation', 'template', 'recommendation', 'approval', 'workflow', 'action', 'outcome', 'learning'] as $stage) {
            $trace->notReached($stage, 'The question did not identify a student or a case.');
        }

        return [
            $this->compose->make(
                $message,
                [$this->compose->text(
                    'Try',
                    'Ask "which students are at academic risk?" first — then follow-up questions know who you mean.'
                )],
                [],
                ['Which students are at academic risk?']
            ),
            [],
        ];
    }

    private function notUnderstood(Intent $intent, FlowTrace $trace): array
    {
        foreach (['agent', 'ontology', 'data', 'evidence', 'case', 'explanation', 'template', 'recommendation', 'approval', 'workflow', 'action', 'outcome', 'learning'] as $stage) {
            $trace->notReached($stage, 'Nothing ran: the question was not understood, and guessing would be worse.');
        }

        return [
            $this->compose->make(
                'I did not understand that well enough to act on it.',
                [$this->compose->text(
                    'Why nothing ran',
                    'Routing a half-understood question would mean running an analysis, or recording a decision, '
                    . 'against the wrong record. The system stops instead.'
                )],
                [],
                $intent->suggestions
            ),
            [],
        ];
    }

    private function startWorkflow(array $recommendation, array $decision, McpRequestContext $scope): array
    {
        if (empty($recommendation['workflow_key'])) {
            return ['run_id' => null, 'status' => 'skipped', 'message' => 'This recommendation binds no workflow.'];
        }

        try {
            $payload = $recommendation['workflow_payload'] ?? [];

            return $this->workflows->start(
                $recommendation['workflow_key'],
                $scope,
                is_array($payload) ? $payload : [],
                [
                    'trigger_type' => 'recommendation_approved',
                    'recommendation_id' => $recommendation['id'],
                    'decision_id' => $decision['decision_id'] ?? null,
                    'case_id' => $recommendation['case_id'] ?? null,
                    'subject_entity_key' => $recommendation['subject_entity_key'] ?? null,
                    'subject_id' => $recommendation['subject_id'] ?? null,
                ]
            );
        } catch (Throwable $exception) {
            report($exception);

            return [
                'run_id' => null,
                'status' => 'failed',
                'message' => 'The decision was recorded, but the workflow could not be started: ' . $exception->getMessage(),
            ];
        }
    }

    /**
     * Every workflow step waiting on this user, across runs.
     *
     * @return array<int, array{id:int, step_key:string, run_id:int}>
     */
    private function pendingWorkflowApprovals(McpRequestContext $scope): array
    {
        if (! Schema::hasTable('workflow_approvals')) {
            return [];
        }

        return DB::table('workflow_approvals')
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->where('status', 'pending')
            ->where(function ($query) use ($scope) {
                // Mine, my role's, or unassigned — the same rule the approvals inbox uses.
                $query->where('assigned_to', $scope->userId)
                    ->orWhereNull('assigned_to')
                    ->orWhere('approver_role', $scope->role);
            })
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'step_key' => $row->step_key,
                'run_id' => (int) $row->run_id,
            ])
            ->all();
    }

    private function pendingWorkflowApproval(?int $runId, McpRequestContext $scope): ?array
    {
        if (! $runId || ! Schema::hasTable('workflow_approvals')) {
            return null;
        }

        $row = DB::table('workflow_approvals')
            ->where('run_id', $runId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->where('status', 'pending')
            ->orderBy('id')
            ->first();

        return $row ? ['id' => (int) $row->id, 'step_key' => $row->step_key] : null;
    }

    /**
     * Steps as the console renders them: the version's plan, marked with what happened.
     */
    private function plannedSteps(?array $status): array
    {
        if (! $status) {
            return [];
        }

        return array_map(fn ($step) => [
            'key' => $step['step_key'] ?? null,
            'label' => $step['label'] ?? ucfirst(str_replace('_', ' ', (string) ($step['step_key'] ?? ''))),
            'type' => $step['step_type'] ?? null,
            'status' => $step['status'] ?? 'pending',
            'finished_at' => $step['finished_at'] ?? null,
            'is_current' => ($status['current_step_key'] ?? null) === ($step['step_key'] ?? null),
        ], $status['steps'] ?? []);
    }

    private function firstPending(array $recommendations): ?array
    {
        foreach ($recommendations as $row) {
            if (($row['status'] ?? '') === 'pending_approval') {
                return $row;
            }
        }

        return null;
    }

    private function templateFor(string $key, McpRequestContext $scope): array
    {
        try {
            $template = $this->templates->find($key, $scope->selectedInstituteId);
        } catch (Throwable) {
            return [];
        }

        if (! $template) {
            return [];
        }

        return [
            'name' => $template->name,
            'version' => $template->version,
            'variables' => $template->variables,
            // The rules the generation is held to. Shown in the trace because "a model
            // wrote this" is only acceptable if you can see what it was forbidden to say.
            'guardrails' => $template->safetyRules,
            'allowed_as_evidence' => $template->allowAsEvidence,
            'requires_review' => $template->requiresReview,
        ];
    }

    private function number(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private function prefix(): string
    {
        return '/' . trim((string) config('ai.route_prefix', 'api/ai'), '/');
    }
}
