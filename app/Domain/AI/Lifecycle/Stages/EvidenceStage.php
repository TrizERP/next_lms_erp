<?php

namespace App\Domain\AI\Lifecycle\Stages;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Evidence\EvidenceStore;
use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;
use App\Domain\AI\Lifecycle\Support\CaseResolver;

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

        $context->addSection($this->compose->evidence(
            'Evidence behind this',
            array_slice($distinct, 0, 8)
        ));

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
            $key = ($row['summary'] ?? '') . '|' . ($row['source']['table'] ?? $row['source']['service'] ?? '');

            // Later rows win, so the id shown is the freshest copy of the observation.
            $seen[$key] = $row;
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
}
