<?php

namespace App\Console\Commands\LMS;

use App\Services\lms\Intelligence\ConceptIntelligenceProjection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Rebuild the queryable Concept Intelligence index from `semantic_intelligence`.
 *
 * Follow-on to tracker row 6 / Decision #38. The verification memo
 * (docs/decisions/2026-09-07-concept-intelligence-evidence-store.md) established that
 * the intelligence lives in opaque JSON columns; this command makes it queryable.
 *
 * IDEMPOTENT, and safe to run repeatedly:
 *   - every write is a chunked INSERT ... ON DUPLICATE KEY UPDATE keyed on
 *     (semantic_id, row_hash);
 *   - each run stamps `projected_at`, then prunes rows for the chapters it touched
 *     whose stamp is older - so an item deleted upstream disappears here too, without
 *     a destructive truncate.
 *
 * `semantic_intelligence` is never written.
 */
class ProjectConceptIntelligenceCommand extends Command
{
    protected $signature = 'lms:project-concept-intelligence
        {--tenant= : restrict to one sub_institute_id}
        {--chapter= : restrict to one chapter_id}
        {--limit=0 : stop after N source rows (0 = all)}
        {--batch=1000 : projected rows per insert}
        {--dry-run : project and report, write nothing}';

    protected $description = 'Rebuild lms_concept_intelligence_index from the semantic_intelligence blobs';

    public function __construct(private ConceptIntelligenceProjection $projection)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $batch = max(1, (int) $this->option('batch'));

        if ($dryRun) {
            $this->warn('DRY RUN - projecting only, nothing will be written.');
        }

        // Stamped once for the whole run so the prune step has a single watermark.
        $runStamp = now()->toDateTimeString();

        $sources = $this->projection->sourceRows(
            $this->option('tenant') !== null ? (int) $this->option('tenant') : null,
            $this->option('chapter') !== null ? (int) $this->option('chapter') : null,
            (int) $this->option('limit')
        );

        if ($sources === []) {
            $this->warn('No semantic_intelligence rows matched.');

            return self::SUCCESS;
        }

        $this->line('  ' . count($sources) . ' source chapter row(s) in scope');

        $byDimension = [];
        $written = 0;
        $failed = 0;
        $touchedSemanticIds = [];
        $pending = [];

        $bar = $this->output->createProgressBar(count($sources));
        $bar->start();

        foreach ($sources as $row) {
            $projected = $this->projection->project($row, $runStamp);
            $touchedSemanticIds[] = (int) $row['id'];

            foreach ($projected as $p) {
                $byDimension[$p['dimension']] = ($byDimension[$p['dimension']] ?? 0) + 1;
            }

            if (! $dryRun) {
                $pending = array_merge($pending, $projected);

                while (count($pending) >= $batch) {
                    $chunk = array_splice($pending, 0, $batch);
                    $written += $this->flush($chunk, $failed);
                }
            } else {
                $written += count($projected);
            }

            $bar->advance();
        }

        if (! $dryRun && $pending !== []) {
            $written += $this->flush($pending, $failed);
        }

        $bar->finish();
        $this->newLine(2);

        $pruned = 0;
        if (! $dryRun) {
            // Anything for a chapter we just reprojected that did NOT get this run's
            // stamp no longer exists upstream.
            $pruned = DB::table('lms_concept_intelligence_index')
                ->whereIn('semantic_id', $touchedSemanticIds)
                ->where(function ($q) use ($runStamp) {
                    $q->whereNull('projected_at')->orWhere('projected_at', '<', $runStamp);
                })
                ->delete();
        }

        ksort($byDimension);
        $this->info('== projected by dimension ==');
        $this->table(
            ['dimension', 'rows'],
            array_map(static fn ($d, $n) => [$d, $n], array_keys($byDimension), array_values($byDimension))
        );

        $this->line(sprintf(
            '  total=%d  written=%d  pruned=%d  failed=%d',
            array_sum($byDimension),
            $written,
            $pruned,
            $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array<string,mixed>>  $chunk
     */
    private function flush(array $chunk, int &$failed): int
    {
        try {
            DB::table('lms_concept_intelligence_index')->upsert(
                $chunk,
                ['semantic_id', 'row_hash'],
                ['chapter_id', 'sub_institute_id', 'dimension', 'concept_name', 'item_key',
                    'item_label', 'ordinal', 'confidence', 'attributes', 'projected_at']
            );

            return count($chunk);
        } catch (Throwable $e) {
            $failed += count($chunk);
            $this->newLine();
            $this->warn('  chunk rejected: ' . $e->getMessage());

            return 0;
        }
    }
}
