<?php

namespace App\Console\Commands\PAL;

use App\Services\Eso\EsoPolicyService;
use App\Services\PAL\Flow\EsoFlowRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * What would this learner be served, under each flow?
 *
 * ---------------------------------------------------------------------------
 * WHY THIS EXISTS
 * ---------------------------------------------------------------------------
 * There is no admin screen for flows yet, so the only way to see what a
 * profile actually DOES was to assign a school to it and watch real students.
 * That is a bad way to discover that `no_cfu` was not what someone meant.
 *
 * This answers the question without keeping anything: it resolves the same
 * learner, on the same concept, through every shipped profile, and prints what
 * each would serve next. The differences between the rows ARE the difference
 * between the flows.
 *
 * ---------------------------------------------------------------------------
 * IT REALLY ASSIGNS, AND THEN REALLY ROLLS BACK
 * ---------------------------------------------------------------------------
 * Each profile is previewed by performing a genuine
 * EsoFlowRegistry::assign() and a genuine resolve — the same code path a live
 * school takes — inside a transaction that is always rolled back in a finally.
 *
 * That is deliberate over faking the plan. A preview that took a shortcut past
 * assignment would be testing the shortcut, and the question being asked here
 * is precisely "what happens when we assign this". The rollback is what makes
 * it safe; the realism is what makes it worth running.
 *
 * The rollback also covers a subtler write: stateFor() creates a
 * learner_node_state row for any node lacking one, and under the pipeline it
 * STAMPS that row with a flow version — pinning a learner to a flow they were
 * never taught under. `silent: true` alone would not have prevented that.
 */
class FlowPreviewCommand extends Command
{
    protected $signature = 'pal:flow-preview
        {learner? : Learner (student) id}
        {concept? : Concept id (lms_concept.id)}
        {--limit=15 : How many candidates to list when no learner is given}';

    protected $description = 'Show what one learner would be served next under each flow profile. Saves nothing.';

    public function handle(): int
    {
        if (! Schema::hasTable('learner_node_state')) {
            $this->error('learner_node_state does not exist on this connection.');

            return self::FAILURE;
        }

        if (! app(EsoFlowRegistry::class)->available()) {
            $this->error('The PAL flow tables are not present. Run the 2026_09_21_1000* migrations with --path.');

            return self::FAILURE;
        }

        $learner = $this->argument('learner');
        $concept = $this->argument('concept');

        if ($learner === null || $concept === null) {
            return $this->listCandidates();
        }

        return $this->compare((int) $learner, (int) $concept);
    }

    /**
     * Learners who actually have ESO state, so the preview has something to
     * show.
     *
     * Worth its own mode: most concepts in this estate have no authored K/A/S
     * nodes at all, so a randomly chosen learner and concept resolves
     * `no_nodes_defined` under every profile and looks identical — which reads
     * as "the profiles do nothing" rather than "you picked an empty concept".
     */
    private function listCandidates(): int
    {
        $rows = DB::table('learner_node_state as s')
            ->join('pal_concept_nodes as n', 'n.id', '=', 's.node_id')
            ->join('lms_concept as c', 'c.id', '=', 'n.concept_id')
            ->groupBy('s.student_id', 'n.concept_id', 'c.name', 's.sub_institute_id')
            ->selectRaw('s.student_id, n.concept_id, c.name, s.sub_institute_id, COUNT(*) as nodes')
            ->orderByDesc('nodes')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No learner has any ESO node state on this connection, so there is nothing to preview.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->line('  Learners with ESO state. Pick one and run:');
        $this->line('    <fg=cyan>php artisan pal:flow-preview {learner} {concept}</>');
        $this->line('');

        $this->table(
            ['Learner', 'Concept', 'Name', 'Institute', 'Nodes'],
            $rows->map(static fn ($r): array => [
                (string) $r->student_id,
                (string) $r->concept_id,
                mb_substr((string) $r->name, 0, 40),
                (string) $r->sub_institute_id,
                (string) $r->nodes,
            ])->all()
        );

        return self::SUCCESS;
    }

    private function compare(int $learnerId, int $conceptId): int
    {
        $subInstituteId = (int) (DB::table('tblstudent')->where('id', $learnerId)->value('sub_institute_id') ?? 0);
        $concept = DB::table('lms_concept')->where('id', $conceptId)->first();

        $this->line('');
        $this->line(sprintf(
            '  Learner %d, concept %d%s, institute %d',
            $learnerId,
            $conceptId,
            $concept === null ? '' : ' "' . $concept->name . '"',
            $subInstituteId
        ));
        $this->line('');

        $engineWas = config('pal_flow.guards.engine');
        config(['pal_flow.guards.engine' => 'pipeline']);

        $rows = [];

        DB::beginTransaction();

        try {
            foreach (array_keys((array) config('pal_flow.profiles', [])) as $profileKey) {
                $rows[] = $this->previewOne((string) $profileKey, $learnerId, $conceptId, $subInstituteId);
            }
        } finally {
            // Always. Whatever happened above, none of it survives.
            DB::rollBack();
            config(['pal_flow.guards.engine' => $engineWas]);
        }

        $this->table(['Flow  [phase order]', 'Would serve', 'Rule', 'Node'], $rows);

        $this->line('');
        $this->line('  <fg=gray>Nothing was saved. Every assignment and resolve above ran inside a</>');
        $this->line('  <fg=gray>transaction that was rolled back.</>');
        $this->line('');

        if (collect($rows)->pluck(1)->unique()->count() === 1) {
            $this->warn('  Every flow serves the same thing for this learner right now.');
            $this->line('  <fg=gray>That is normal. The flows only diverge where they actually differ: a learner</>');
            $this->line('  <fg=gray>waiting on a check looks different under no_cfu, one not yet diagnosed looks</>');
            $this->line('  <fg=gray>different under diagnostic_free. Try another learner from the list.</>');
            $this->line('');
        }

        return self::SUCCESS;
    }

    /**
     * Assign one profile for real, resolve for real, and report.
     *
     * A fresh EsoPolicyService per profile because the service memoises reads
     * for its own lifetime; reusing one would serve the first profile's cached
     * learner state to the second.
     *
     * @return array<int, string>
     */
    private function previewOne(string $profileKey, int $learnerId, int $conceptId, int $subInstituteId): array
    {
        try {
            $registry = app(EsoFlowRegistry::class);
            $registry->assign($subInstituteId, $profileKey);

            $policy = app(EsoPolicyService::class);
            $action = $policy->nextAction($learnerId, $conceptId, $subInstituteId, silent: true);

            $plan = app(\App\Services\PAL\Flow\EsoFlowResolver::class)->resolve($subInstituteId);

            return [
                $profileKey . '  [' . implode(' > ', $plan->phaseOrder()) . ']',
                (string) ($action['action'] ?? '?'),
                (string) ($action['rule_fired'] ?? '-'),
                (string) ($action['node_id'] ?? '-'),
            ];
        } catch (Throwable $e) {
            return [$profileKey, 'error', mb_substr($e->getMessage(), 0, 70), '-'];
        }
    }
}
