<?php

namespace App\Console\Commands\PAL;

use App\Models\PAL\ConceptMastery;
use App\Services\Graph\CoherenceGraphProjection;
use App\Services\PAL\Coherence\CoherenceMapRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Projects the Set Coherence Map into Neo4j and reports the gate.
 *
 * Idempotent by construction - every write is a MERGE - so it is safe to run on
 * a schedule, after an extraction, or by hand while debugging. Run it with
 * --dry-run first on a scope you have not touched before: the counts tell you
 * whether the endpoints exist before you write anything.
 */
class CoherenceSyncCommand extends Command
{
    protected $signature = 'pal:coherence-sync
        {--tenant= : sub_institute_id (required)}
        {--standard= : standard_id (required)}
        {--subject= : subject_id (required)}
        {--mastery : also sweep pal_concept_mastery rows owed to the graph}
        {--mastery-only : skip the map, sweep mastery only}
        {--health : run the structural gate afterwards and fail on a cycle}
        {--dry-run : count what would be projected, write nothing}';

    protected $description = 'PAL V4: project the Set Coherence Map (concepts, REQUIRES, TEACHES, ASSESSES, HAS_MASTERY) into Neo4j';

    public function handle(CoherenceGraphProjection $projection, CoherenceMapRepository $repository): int
    {
        $tenant = (int) $this->option('tenant');
        $standard = (int) $this->option('standard');
        $subject = (int) $this->option('subject');

        if ($tenant <= 0 || $standard <= 0 || $subject <= 0) {
            $this->error('--tenant, --standard and --subject are all required.');

            return self::FAILURE;
        }

        $this->line("Scope: tenant {$tenant} / standard {$standard} / subject {$subject}");

        if ($this->option('dry-run')) {
            return $this->reportScope($tenant, $standard, $subject);
        }

        if (! $this->option('mastery-only')) {
            $this->projectMap($projection, $tenant, $standard, $subject);
        }

        if ($this->option('mastery') || $this->option('mastery-only')) {
            $this->sweepMastery($projection, $tenant);
        }

        return $this->option('health')
            ? $this->gate($repository, $standard, $subject)
            : self::SUCCESS;
    }

    // ==================================================================

    private function projectMap(CoherenceGraphProjection $projection, int $tenant, int $standard, int $subject): void
    {
        $this->newLine();
        $this->info('1/4  Concepts + HAS_CONCEPT');
        $concepts = $projection->projectConcepts($tenant, $standard, $subject);
        $this->report($concepts);

        if (($concepts['chapters_missing'] ?? 0) > 0) {
            // The concept node is written either way; only the chapter link is
            // lost. Worth calling out because a concept with no chapter never
            // appears in the map (every read starts at :Chapter).
            $this->warn(sprintf(
                '     %d concept(s) had no :Chapter with uid Chapter:%d:0:{chapter_id} - they are in the graph '
                . 'but will NOT appear in the map until the chapter node exists.',
                $concepts['chapters_missing'],
                $tenant
            ));
        }

        $this->info('2/4  REQUIRES + CROSS_LINKS');
        $relations = $projection->projectRelations($tenant, $standard, $subject);
        $this->report($relations);

        if (($relations['unresolved'] ?? 0) > 0) {
            $this->warn(sprintf(
                '     %d edge(s) reference a concept that is not in the graph. Those prerequisites are '
                . 'invisible to the recommender - a learner will be offered work they are not ready for.',
                $relations['unresolved']
            ));
        }

        $this->info('3/4  TEACHES (Content -> Concept)');
        $this->report($projection->projectTeaches($tenant, $standard, $subject));

        $this->info('4/4  ASSESSES (Question -> Concept)');
        $this->report($projection->projectAssesses($tenant, $standard, $subject));
    }

    private function sweepMastery(CoherenceGraphProjection $projection, int $tenant): void
    {
        $this->newLine();
        $this->info('Mastery sweep (rows owed to the graph)');

        $owed = ConceptMastery::query()
            ->where('sub_institute_id', $tenant)
            ->where(fn ($q) => $q->whereNull('graph_synced_at')->orWhereColumn('graph_synced_at', '<', 'updated_at'))
            ->get();

        if ($owed->isEmpty()) {
            $this->line('     backlog 0 - nothing owed.');

            return;
        }

        $result = $projection->projectMastery($owed);
        $this->report(['mastery' => $result['mastery'], 'learners_missing' => $result['learners_missing']]);

        // Only stamp the rows the graph confirmed. Stamping all of them would
        // mark an unwritten edge as delivered and the sweeper would never
        // retry it - the exact failure mode that stranded the April 2026 rows.
        if ($result['written'] !== []) {
            $written = $this->stampDelivered($result['written']);
            $this->line("     stamped graph_synced_at on {$written} row(s).");
        }

        if (($result['learners_missing'] ?? 0) > 0) {
            $this->warn(sprintf(
                '     %d row(s) had no :StuDetail or :Concept endpoint. Run neo4j:backfill-students for '
                . 'this tenant, then re-run with --mastery-only.',
                $result['learners_missing']
            ));
        }
    }

    /**
     * Stamp the pairs Neo4j confirmed it wrote, and only those.
     *
     * @param  array<int, array{learner: int, concept: int}>  $written
     */
    private function stampDelivered(array $written): int
    {
        $stamped = 0;

        foreach ($written as $pair) {
            $stamped += DB::table('pal_concept_mastery')
                ->where('learner_id', $pair['learner'])
                ->where('concept_ref_id', $pair['concept'])
                ->update(['graph_synced_at' => now()]);
        }

        return $stamped;
    }

    private function gate(CoherenceMapRepository $repository, int $standard, int $subject): int
    {
        $this->newLine();
        $this->info('Structural gate');

        $health = $repository->health($standard, $subject);

        $this->table(
            ['check', 'value'],
            [
                ['concepts in map', $health['concepts']],
                ['entry points (no prerequisite)', $health['roots']],
                ['concepts with NO content', $health['without_content']],
                ['concepts with NO questions', $health['without_questions']],
                ['REQUIRES acyclic', $health['acyclic'] ? 'yes' : 'NO'],
            ]
        );

        if ($health['concepts'] === 0) {
            $this->error('FAIL: no concepts are mapped for this scope.');

            return self::FAILURE;
        }

        // A cycle is the one defect that makes the map actively wrong rather
        // than merely incomplete: every concept on the ring is permanently
        // blocked, so the recommender can never offer any of them.
        if (! $health['acyclic']) {
            $this->error('FAIL: REQUIRES contains a cycle. Concepts on the ring can never become ready:');

            foreach ($health['cycles'] as $node) {
                $this->line("       #{$node['id']}  {$node['name']}");
            }

            return self::FAILURE;
        }

        if ($health['roots'] === 0) {
            $this->error('FAIL: no entry point. Every concept has a prerequisite, so nothing is ever ready.');

            return self::FAILURE;
        }

        if ($health['without_questions'] > 0) {
            $this->warn(sprintf(
                'WARN: %d concept(s) have no question. A learner reaching one cannot demonstrate mastery '
                . 'and will stall there permanently.',
                $health['without_questions']
            ));
        }

        $this->info('PASS');

        return self::SUCCESS;
    }

    /**
     * What is available in MariaDB before anything is written, so a first run
     * on a new scope is a measurement rather than a leap.
     */
    private function reportScope(int $tenant, int $standard, int $subject): int
    {
        $concepts = DB::table('lms_concept')
            ->where('sub_institute_id', $tenant)
            ->where('standard_id', $standard)
            ->where('subject_id', $subject)
            ->count();

        $relations = DB::table('pal_concept_relations as r')
            ->join('lms_concept as t', 't.id', '=', 'r.to_concept_id')
            ->whereIn('r.sub_institute_id', [$tenant, 0])
            ->where('t.standard_id', $standard)
            ->where('t.subject_id', $subject)
            ->where('t.sub_institute_id', $tenant)
            ->count();

        $teaches = DB::table('pal_content_metadata as m')
            ->join('lms_concept as k', 'k.id', '=', 'm.concept_ref_id')
            ->where('m.sub_institute_id', $tenant)
            ->where('k.standard_id', $standard)
            ->where('k.subject_id', $subject)
            ->count();

        $assesses = DB::table('pal_question_metadata as m')
            ->join('lms_concept as k', 'k.id', '=', 'm.concept_ref_id')
            ->where('m.sub_institute_id', $tenant)
            ->where('k.standard_id', $standard)
            ->where('k.subject_id', $subject)
            ->count();

        $this->table(
            ['source', 'rows ready to project'],
            [
                ['lms_concept', $concepts],
                ['pal_concept_relations', $relations],
                ['pal_content_metadata.concept_ref_id', $teaches],
                ['pal_question_metadata.concept_ref_id', $assesses],
            ]
        );

        if ($concepts === 0) {
            $this->warn('No concepts for this scope. The map cannot exist until semantic_intelligence has been '
                . 'extracted for these chapters and materialised into lms_concept.');
        }

        if ($teaches === 0 || $assesses === 0) {
            $this->warn('Content and/or questions are not linked to concepts. Run pal:coherence-tag first, '
                . 'or the map will have a spine with nothing to deliver.');
        }

        $this->line('Dry run - nothing written.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function report(array $counts): void
    {
        foreach ($counts as $key => $value) {
            $this->line(sprintf('     %-20s %d', $key, $value));
        }
    }
}
