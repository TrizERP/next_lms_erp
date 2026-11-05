<?php

namespace App\Domain\AI\Lifecycle\Stages;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Evidence\EvidenceStore;
use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;
use App\Domain\AI\Lifecycle\Support\CaseResolver;
use App\Domain\AI\Lifecycle\Support\RiskScanLimit;

/**
 * Stage 8 — the rows a claim is allowed to rest on.
 *
 * The rule this stage exists to enforce is short and load-bearing: evidence read from a
 * table is born verified; anything generated is stored unverified and may not be cited
 * as fact. Every row carries the table and id it came from, so a reader can open that
 * table at that id and find the same number the case was built on. That is the whole
 * claim of the platform, and it is checkable — which is the point.
 *
 * Evidence is stored whether or not a case opens, so a trend is visible before it is a
 * case. That is why this stage still reports when the sweep opened nothing.
 */
class EvidenceStage implements LifecycleStage
{
    public function __construct(
        private readonly EvidenceStore $evidence,
        private readonly CaseResolver $caseResolver,
        private readonly AnswerComposer $compose,
    ) {
    }

    public function key(): StageKey
    {
        return StageKey::Evidence;
    }

    public function run(StageContext $context): StageOutcome
    {
        if ($context->intent?->key === 'student_risk_scan' && count($context->cases) > 1) {
            return $this->forRankedRiskScan($context);
        }

        $resolved = $this->caseResolver->resolve($context);

        if ($resolved === null) {
            return $this->withoutCase($context);
        }

        $caseId = (int) ($resolved['case']['case_id'] ?? $resolved['case']['id'] ?? 0);
        $rows = $caseId > 0 ? $this->evidence->forCase($caseId, $context->scope) : [];
        $context->evidence = $rows;

        if ($rows === []) {
            return StageOutcome::skipped(
                sprintf('Case #%d has no evidence rows attached.', $caseId),
                ['case_id' => $caseId]
            )->withNote(
                'A case with no evidence cannot support a cited claim, so the explanation stage '
                . 'will have nothing to work from.'
            );
        }

        $verified = count(array_filter($rows, static fn (array $row) => ! empty($row['verified'])));
        $generated = count(array_filter($rows, static fn (array $row) => ! empty($row['is_generated'])));

        // Show each distinct observation once.
        //
        // A case accumulates evidence every time the agent runs, so a case scanned
        // thirty times carries thirty copies of the same sentence. Reading "assessment
        // average moved from 10% to 28.3%" four times in one answer does not make it
        // four findings, and a teacher counting bullet points would badly misjudge the
        // weight of the case. The trace still reports the true row count and every id,
        // so nothing is hidden — this is a display decision, not a filter on the record.
        $distinct = $this->distinctBySummary($rows);

        if (! $this->isWorkflowRead($context)) {
            $context->addSection($this->compose->evidence(
                'Evidence behind this',
                array_slice($distinct, 0, 8)
            ));
        }

        $repeats = count($rows) - count($distinct);

        return StageOutcome::ran(
            sprintf(
                '%d evidence row%s cited by case #%d — %d verified, %d generated%s.',
                count($rows),
                count($rows) === 1 ? '' : 's',
                $caseId,
                $verified,
                $generated,
                $repeats > 0
                    ? sprintf(', %d repeated from earlier runs', $repeats)
                    : ''
            ),
            [
                'distinct_observations' => count($distinct),
                'repeated_rows' => $repeats,
                'by_kind' => array_count_values(array_filter(array_column($rows, 'kind'), 'is_string')),
                'source_tables' => $this->sourceTables($rows),
                'rule' => 'Evidence read from a table is born verified; anything generated is stored '
                    . 'unverified and may not be cited as fact.',
                'sample' => array_map(static fn (array $row) => [
                    'kind' => $row['kind'] ?? null,
                    'summary' => $row['summary'] ?? null,
                    'source_table' => $row['source']['table'] ?? null,
                    'verified' => $row['verified'] ?? null,
                ], array_slice($rows, 0, 6)),
            ],
            ['table' => 'ai_evidence', 'ids' => array_column($rows, 'id')],
            [
                'api' => $this->prefix() . '/cases/' . $caseId . '/evidence',
                'sql' => 'select e.* from ai_evidence e join ai_case_evidence ce on ce.evidence_id = e.id '
                    . 'where ce.case_id = ' . $caseId,
            ]
        );
    }

    private function forRankedRiskScan(StageContext $context): StageOutcome
    {
        $count = RiskScanLimit::fromQuestion($context->question, count($context->cases));
        $cases = array_slice($context->cases, 0, $count);
        $allRows = [];
        $byCase = [];

        foreach ($cases as $case) {
            $caseId = (int) ($case['case_id'] ?? $case['id'] ?? 0);
            $rows = $caseId > 0 ? $this->evidence->forCase($caseId, $context->scope) : [];
            $allRows = [...$allRows, ...$rows];

            if ($caseId > 0) {
                $byCase[$caseId] = $rows;
            }
        }

        $context->evidence = $allRows;

        // Kept per case as well as flattened. `ai_evidence` rows carry no case id of
        // their own — the link lives in `ai_case_evidence` — so once these are merged
        // there is no way back to "the evidence behind this one case", which is exactly
        // what the answer shows for the highest-priority student. Grouping here costs
        // nothing: this loop already reads them one case at a time.
        $context->set('evidence_by_case', $byCase);

        return StageOutcome::ran(
            sprintf('Read %d evidence row%s for the top %d ranked cases.', count($allRows), count($allRows) === 1 ? '' : 's', count($cases)),
            [
                'ranked_case_ids' => array_column($cases, 'case_id'),
                'evidence_ids' => array_column($allRows, 'id'),
                'rule' => 'Each displayed observation is linked to the case selected from this run\'s ranked results.',
            ],
            ['table' => 'ai_evidence', 'ids' => array_column($allRows, 'id')]
        );
    }

    /**
     * No case — but that does not always mean no evidence.
     */
    private function withoutCase(StageContext $context): StageOutcome
    {
        $belowThreshold = $context->get('signals_below_threshold', []);

        if ($belowThreshold !== []) {
            $collected = $context->agentRun['counters']['evidence_collected'] ?? 0;

            return StageOutcome::ran(
                sprintf(
                    '%d evidence row%s stored for signals that did not open a case.',
                    $collected,
                    $collected === 1 ? '' : 's'
                ),
                [
                    'note' => 'Evidence is stored whether or not a case opens, so a trend is visible '
                        . 'before it is a case. If one of these signals worsens past its floor, the next '
                        . 'run opens a case and the rest of the journey follows.',
                ],
                ['table' => 'ai_evidence', 'ids' => []]
            );
        }

        // Check if live tool calls produced real database rows to cite as factual evidence
        $stepResults = (array) $context->get('mcp_step_results', []);
        $feesStudent = $context->get('fees_student_flow');
        if (is_array($feesStudent) && ! empty($feesStudent['accounts'])) {
            $stepResults['fees_student_flow'] = ['accounts' => $feesStudent['accounts']];
        }
        $completedCalls = array_filter(
            $context->toolCalls(),
            static fn (array $c) => ($c['status'] ?? null) === 'completed'
        );

        if ($stepResults !== [] || $completedCalls !== []) {
            $evidenceItems = [];
            foreach ($stepResults as $stepId => $res) {
                if (! is_array($res)) {
                    continue;
                }
                $candidates = [];
                if (isset($res['data']) && is_array($res['data'])) {
                    $candidates[] = $res['data'];
                }
                $candidates[] = $res;

                foreach ($candidates as $container) {
                    foreach ($container as $key => $val) {
                        if (is_array($val) && isset($val[0]) && (is_array($val[0]) || is_object($val[0]))) {
                            foreach (array_slice($val, 0, 10) as $row) {
                                $row = (array) $row;
                                $subjectId = $row['student_id'] ?? $row['user_id'] ?? $row['id'] ?? 0;
                                $summary = '';
                                if (isset($row['student_name'])) {
                                    $summary .= $row['student_name'] . ': ';
                                } elseif (isset($row['name'])) {
                                    $summary .= $row['name'] . ': ';
                                } elseif (isset($row['title'])) {
                                    $summary .= $row['title'] . ': ';
                                }
                                $facts = [];
                                foreach ($row as $k => $v) {
                                    if (is_scalar($v) && ! str_contains($k, 'id') && strlen((string) $v) < 60) {
                                        $facts[] = "{$k}: {$v}";
                                    }
                                }
                                $summary .= implode(', ', array_slice($facts, 0, 4));
                                $evidenceItems[] = [
                                    'id' => (int) $subjectId,
                                    'kind' => 'tool_record',
                                    'summary' => $summary ?: "Record from step {$stepId}",
                                    'source' => ['table' => (string) $stepId, 'id' => $subjectId],
                                    'verified' => true,
                                    'is_generated' => false,
                                ];
                            }
                        }
                    }
                }
            }

            if ($evidenceItems !== []) {
                $context->evidence = $evidenceItems;
                $context->addSection($this->compose->evidence(
                    'Evidence from live queries',
                    array_slice($evidenceItems, 0, 6)
                ));

                return StageOutcome::ran(
                    sprintf(
                        '%d verified evidence row%s extracted from live tool queries across %d tool call%s.',
                        count($evidenceItems),
                        count($evidenceItems) === 1 ? '' : 's',
                        count($completedCalls),
                        count($completedCalls) === 1 ? '' : 's'
                    ),
                    ['source_tools' => array_column($completedCalls, 'tool'), 'sample_ids' => array_column(array_slice($evidenceItems, 0, 5), 'id')],
                    ['table' => 'ai_evidence', 'ids' => array_column(array_slice($evidenceItems, 0, 10), 'id')],
                    ['api' => '/' . trim((string) config('ai.route_prefix', 'api/ai'), '/') . '/evidence']
                );
            }
        }

        return StageOutcome::skipped(
            'No case or signal was in play, so there was nothing to store or cite as evidence.',
            []
        )->withNote('Evidence is always attached to something. With no subject, there is nothing to attach it to.');
    }

    /**
     * One row per distinct observation, keeping the most recently stored copy.
     *
     * Keyed on the summary plus the source, so two genuinely different measurements that
     * happen to read alike are still both kept — it is repetition of the *same* recorded
     * observation that is being collapsed, not similarity of wording.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function distinctBySummary(array $rows): array
    {
        $seen = [];

        foreach ($rows as $row) {
            $source = $row['source']['table'] ?? $row['source']['service'] ?? '';
            $sourceId = $row['source']['id'] ?? '';
            $key = ($row['kind'] ?? '') . '|' . $source . '|' . $sourceId;

            // Rows arrive newest first. Keep the first current measurement rather than
            // allowing an older scan to replace it later in this loop.
            if (! isset($seen[$key])) {
                $seen[$key] = $row;
            }
        }

        return array_values($seen);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private function sourceTables(array $rows): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (array $row) => $row['source']['table'] ?? null,
            $rows
        ))));
    }

    private function prefix(): string
    {
        return '/' . trim((string) config('ai.route_prefix', 'api/ai'), '/');
    }

    private function isWorkflowRead(StageContext $context): bool
    {
        return in_array($context->intent?->key, ['workflow_status', 'outcome_status'], true);
    }
}
