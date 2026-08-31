<?php

namespace App\Domain\AI\Lifecycle\Stages;

use App\Domain\AI\Cases\CaseBuilder;
use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 7 — what was actually read, from which tables.
 *
 * Two routes produce real data and this stage reports both. An agent route reports what
 * the detectors swept and what they were able to judge; a tool route reports the rows
 * the MCP calls returned. Either way the claim being made is the same one: these numbers
 * came from the school's own records, and here is where to look them up.
 *
 * The hardest case is the agent that ran and opened nothing, because it has two
 * genuinely different causes and conflating them is what makes a platform feel dead:
 * either no signal fired at all, or signals fired and none cleared the bar for opening a
 * case. The second is real work with a real result, and it is reported as such — with
 * the signals, the floor each fell short of, and by how much.
 *
 * `DetectorCoverage` adds the third thing a reader needs: whether a detector could form
 * a view at all. A detector that skipped every student for want of five attendance rows
 * contributed nothing, and a trace that says "three detectors queried live records"
 * without saying that is flattering itself.
 */
class RealDataStage implements LifecycleStage
{
    public function __construct(private readonly CaseBuilder $cases)
    {
    }

    public function key(): StageKey
    {
        return StageKey::RealData;
    }

    public function run(StageContext $context): StageOutcome
    {
        if ($context->agentRun !== null) {
            return $context->cases !== []
                ? $this->fromCases($context)
                : $this->fromEmptySweep($context);
        }

        if ($context->toolCalls() !== []) {
            return $this->fromTools($context);
        }

        if ($context->focusCase !== null) {
            return StageOutcome::ran(
                'Read back from the stored case rather than re-queried — the numbers shown are the '
                . 'numbers the decision was made on.',
                ['case_opened_at' => $context->focusCase['opened_at'] ?? null]
            );
        }

        return StageOutcome::skipped(
            'This turn answered without reading source records.',
            ['route' => $context->plan?->route]
        )->withNote('Nothing was queried, so there is no data provenance to report.');
    }

    // ---------------------------------------------------------------- routes

    private function fromCases(StageContext $context): StageOutcome
    {
        $counters = $context->agentRun['counters'] ?? [];
        $coverage = $context->get('detector_coverage', []);
        $cohort = $context->get('cohort', []);

        return StageOutcome::ran(
            sprintf(
                'Detectors swept %s and raised %d signal%s across %d student%s.',
                $this->cohortPhrase($cohort),
                $counters['signals_detected'] ?? 0,
                ($counters['signals_detected'] ?? 0) === 1 ? '' : 's',
                count($context->cases),
                count($context->cases) === 1 ? '' : 's'
            ),
            [
                'cohort' => $cohort,
                'detector_coverage' => $coverage,
                'blind_detectors' => $this->blindDetectors($coverage),
                'tune_it' => 'The per-signal trigger and severity bands live in '
                    . 'ai_signal_definitions.thresholds, so a school can retune what counts as risk '
                    . 'without a deploy.',
            ],
            ['table' => 'ai_signals', 'ids' => []],
            ['api' => '/' . trim((string) config('ai.route_prefix', 'api/ai'), '/') . '/signals']
        )->withNote($this->blindPhrase($coverage));
    }

    /**
     * The agent ran and opened nothing — the single most confusing outcome in the
     * pipeline, and the one most likely to look like a broken system.
     */
    private function fromEmptySweep(StageContext $context): StageOutcome
    {
        $runId = $context->agentRun['run_id'] ?? null;
        $coverage = $context->get('detector_coverage', []);
        $cohort = $context->get('cohort', []);
        $detected = $this->signalsFromRun($context, $runId);

        $floors = [];

        foreach ($detected as $signal) {
            $key = (string) ($signal['signal_key'] ?? '');
            $floors[$key] = $this->cases->caseFloor($context->scope, $key ?: null);
        }

        $context->set('signals_below_threshold', $detected);
        $context->set('case_floors', $floors);

        $summary = $detected === []
            ? sprintf(
                'The detectors read the source records for %s and nothing crossed its trigger.',
                $this->cohortPhrase($cohort)
            )
            : sprintf(
                '%d signal%s raised across %d student%s — none reached the bar for opening a case.',
                count($detected),
                count($detected) === 1 ? '' : 's',
                count(array_unique(array_column($detected, 'student_id'))),
                count(array_unique(array_column($detected, 'student_id'))) === 1 ? '' : 's'
            );

        return StageOutcome::ran(
            $summary,
            [
                'cohort' => $cohort,
                'detector_coverage' => $coverage,
                'blind_detectors' => $this->blindDetectors($coverage),
                'signals_detected' => $detected,
                'case_floors_in_force' => $floors,
            ],
            ['table' => 'ai_signals', 'ids' => array_column($detected, 'id')],
            [
                'api' => '/' . trim((string) config('ai.route_prefix', 'api/ai'), '/') . '/signals',
                'sql' => 'select signal_key, subject_id, severity, score from ai_signals '
                    . 'where detected_by_run_id = ' . ((int) $runId),
            ]
        )->withNote($this->blindPhrase($coverage));
    }

    private function fromTools(StageContext $context): StageOutcome
    {
        $completed = array_values(array_filter(
            $context->toolCalls(),
            static fn (array $call) => ($call['status'] ?? null) === 'completed'
        ));

        if ($completed === []) {
            return StageOutcome::blocked(
                'No source records were read — every tool call was refused or unavailable.',
                ['calls' => $context->toolCalls()]
            );
        }

        $rows = array_sum(array_map(
            static fn (array $call) => (int) ($call['count'] ?? 0),
            $completed
        ));

        return StageOutcome::ran(
            sprintf(
                'Read %d row%s from live records through %d tool call%s.',
                $rows,
                $rows === 1 ? '' : 's',
                count($completed),
                count($completed) === 1 ? '' : 's'
            ),
            [
                'sources' => array_map(static fn (array $call) => [
                    'tool' => $call['tool'] ?? null,
                    'rows' => $call['count'] ?? 0,
                    'why' => $call['note'] ?? null,
                ], $completed),
                'results' => $context->get('mcp_step_results', []),
            ],
            [],
            ['api' => 'POST /api/mcp/tools/call']
        );
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return array<int, array<string, mixed>>
     */
    private function signalsFromRun(StageContext $context, ?int $runId): array
    {
        if (! $runId || ! Schema::hasTable('ai_signals')) {
            return [];
        }

        return DB::table('ai_signals')
            ->where('sub_institute_id', $context->scope->selectedInstituteId)
            ->where('detected_by_run_id', $runId)
            ->orderByDesc('score')
            ->get()
            ->map(static fn ($row) => [
                'id' => (int) $row->id,
                'signal_key' => $row->signal_key,
                'student_id' => (int) $row->subject_id,
                'student' => $row->subject_label,
                'severity' => $row->severity,
                'score' => (float) $row->score,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $cohort
     */
    private function cohortPhrase(array $cohort): string
    {
        $count = (int) ($cohort['examined'] ?? $cohort['count'] ?? 0);

        if ($count === 0) {
            return 'the students in scope';
        }

        return sprintf('%d student%s in scope', $count, $count === 1 ? '' : 's');
    }

    /**
     * @param  array<int, array<string, mixed>>  $coverage
     * @return array<int, array<string, mixed>>
     */
    private function blindDetectors(array $coverage): array
    {
        return array_values(array_filter(
            $coverage,
            static fn ($entry) => is_array($entry) && ($entry['blind'] ?? false) === true
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $coverage
     */
    private function blindPhrase(array $coverage): ?string
    {
        $blind = $this->blindDetectors($coverage);

        if ($blind === []) {
            return null;
        }

        $names = array_map(
            static fn (array $entry) => str_replace('_', ' ', (string) ($entry['signal_key'] ?? 'a detector')),
            $blind
        );

        return sprintf(
            '%s could not judge a single student for want of records, so this sweep says nothing '
            . 'about what %s would have found.',
            ucfirst(implode(' and ', $names)),
            count($names) === 1 ? 'it' : 'they'
        );
    }
}
