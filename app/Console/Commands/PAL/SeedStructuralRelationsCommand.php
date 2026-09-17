<?php

namespace App\Console\Commands\PAL;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The prerequisite edges the curriculum already implies by its own ordering.
 *
 * WHY THIS EXISTS
 * `pal_concept_relations` holds 1,601 AI-proposed concept edges, but nothing links
 * one topic to another anywhere in the estate - `pal_learning_relations` starts
 * empty. Yet the curriculum is not unordered: chapters carry `sort_order`, topics
 * carry `topic_sort_order`, and a syllabus that numbers "1.3 The 2-D Cartesian
 * coordinate system" before "1.4 Distance between two points" is stating a teaching
 * order someone already decided.
 *
 * This command writes that down as edges so the Coherence Map has a topic-level
 * spine on day one, instead of a level with nodes and no relationships.
 *
 * ---------------------------------------------------------------------------
 * AN ORDER IS NOT A PREREQUISITE, AND THESE EDGES SAY SO
 * ---------------------------------------------------------------------------
 * "Taught after" and "requires" are different claims. A syllabus often sequences
 * two topics that have no dependency at all, and this command cannot tell the
 * difference - it can only see the numbering.
 *
 * So every edge it writes is `quality_status = 'draft'` and `tagged_by =
 * 'structural'`, which the map renders as a dashed suggestion, never as confirmed
 * curriculum. A curator approving one is making the pedagogical claim; this command
 * is only proposing where to look. `tagged_by` is 'structural' rather than 'ai'
 * precisely so the two kinds of guess stay tellable apart in review.
 *
 * ---------------------------------------------------------------------------
 * WHY IT DOES NOT SEED CONCEPT EDGES
 * ---------------------------------------------------------------------------
 * Two reasons. `lms_concept` has no ordering column at all - the only sequence
 * available is insertion id, which reflects when an extraction ran and not what a
 * teacher intends. And that level is the one place already covered: an AI pass has
 * proposed 1,601 concept edges. Adding id-order edges underneath them would bury
 * real suggestions in noise.
 *
 * ---------------------------------------------------------------------------
 * IDEMPOTENCY, AND WHY IT DEFERS TO PEOPLE
 * ---------------------------------------------------------------------------
 * Re-running writes nothing new: an edge is inserted only when that exact pair has
 * no row. If a curator approved one, it stays approved; if they rejected one, it
 * stays rejected and is NOT re-proposed. A command that resurrected a rejected
 * suggestion every night would make review pointless.
 */
class SeedStructuralRelationsCommand extends Command
{
    protected $signature = 'pal:seed-structural-relations
        {--tenant= : restrict to one sub_institute_id (required in practice)}
        {--subject= : restrict to one subject_id}
        {--standard= : restrict to one standard_id}
        {--levels=topic,chapter : which levels to seed}
        {--limit=0 : stop after N edges (0 = all)}
        {--dry-run : report what would be written, write nothing}';

    protected $description = 'Propose prerequisite edges between topics and chapters from the curriculum\'s own ordering';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tenant = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $limit = (int) $this->option('limit');
        $levels = array_filter(array_map('trim', explode(',', (string) $this->option('levels'))));

        if ($tenant === null) {
            $this->error('--tenant is required: these edges are tenant-scoped and a blank tenant would write them as shared (0).');

            return self::FAILURE;
        }

        $chapters = $this->chapters($tenant);

        if ($chapters->isEmpty()) {
            $this->warn('No chapters matched those filters.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? 'DRY RUN - ' : '').'Scanning '.$chapters->count().' chapter(s) for levels: '.implode(', ', $levels).'.');

        $pairs = [];

        if (in_array('topic', $levels, true)) {
            $pairs = array_merge($pairs, $this->topicPairs($chapters->pluck('id')->all()));
        }

        if (in_array('chapter', $levels, true)) {
            $pairs = array_merge($pairs, $this->chapterPairs($chapters));
        }

        if ($limit > 0) {
            $pairs = array_slice($pairs, 0, $limit);
        }

        if ($pairs === []) {
            $this->warn('Nothing to propose - every chapter has fewer than two ordered children.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($pairs));
        $bar->start();

        $written = 0;
        $existing = 0;
        $samples = [];
        $now = Carbon::now();

        foreach ($pairs as $pair) {
            [$type, $prerequisiteId, $dependentId, $label] = $pair;

            $match = [
                'from_node_type' => $type,
                'from_node_id' => $dependentId,
                'to_node_type' => $type,
                'to_node_id' => $prerequisiteId,
                'relation_type' => 'requires',
                'sub_institute_id' => $tenant,
            ];

            // Any row at all - draft, approved or rejected - means this pair has
            // already been proposed or judged. Leave it exactly as it is.
            if (DB::table('pal_learning_relations')->where($match)->exists()) {
                $existing++;
                $bar->advance();

                continue;
            }

            if (count($samples) < 10) {
                $samples[] = [$type, $label];
            }

            if (! $dryRun) {
                DB::table('pal_learning_relations')->insert($match + [
                    'scope' => 'tenant',
                    'quality_status' => 'draft',
                    'tagged_by' => 'structural',
                    'note' => 'Proposed from curriculum ordering',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $written++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($samples !== []) {
            $this->table(['Level', 'Proposed edge (prerequisite -> dependent)'], $samples);
        }

        $this->table(['Outcome', 'Edges'], [
            ['Proposed'.($dryRun ? ' (not written)' : ' and written'), $written],
            ['Already present - left untouched', $existing],
            ['Total pairs examined', count($pairs)],
        ]);

        if ($dryRun) {
            $this->comment('Nothing was written. Re-run without --dry-run to apply.');
        } else {
            $this->comment('All new edges are drafts. They appear on the map as suggestions until someone approves them.');
        }

        return self::SUCCESS;
    }

    private function chapters(int $tenant)
    {
        $q = DB::table('chapter_master')
            ->select('id', 'chapter_name', 'unit_id', 'sort_order')
            ->where('sub_institute_id', $tenant);

        foreach (['subject' => 'subject_id', 'standard' => 'standard_id'] as $opt => $column) {
            if ($this->option($opt) !== null && $this->option($opt) !== '') {
                $q->where($column, (int) $this->option($opt));
            }
        }

        return $q->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Consecutive topics within one chapter.
     *
     * Chapter-bounded on purpose: the last topic of chapter 1 and the first of
     * chapter 2 are consecutive in the book, but that is a chapter-level
     * relationship and is emitted as one, not as a topic edge that would imply the
     * two chapters' internals are interleaved.
     *
     * @return array<int, array{0: string, 1: int, 2: int, 3: string}>
     */
    private function topicPairs(array $chapterIds): array
    {
        if ($chapterIds === []) {
            return [];
        }

        $topics = DB::table('topic_master')
            ->select('id', 'chapter_id', 'name', 'topic_sort_order')
            ->whereIn('chapter_id', $chapterIds)
            ->where(function ($q) {
                $q->whereNull('topic_show_hide')->orWhere('topic_show_hide', '!=', 'hide');
            })
            ->orderBy('chapter_id')
            ->orderBy('topic_sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('chapter_id');

        $pairs = [];

        foreach ($topics as $group) {
            $ordered = $group->values();

            for ($i = 1; $i < $ordered->count(); $i++) {
                $prev = $ordered[$i - 1];
                $curr = $ordered[$i];

                $pairs[] = ['topic', (int) $prev->id, (int) $curr->id, $prev->name.'  ->  '.$curr->name];
            }
        }

        return $pairs;
    }

    /**
     * Consecutive chapters within one unit.
     *
     * Unit-bounded, and chapters with no unit are skipped entirely rather than
     * chained to each other: `unit_id` is null for chapters created straight from
     * /course-master, and those carry no curricular ordering to infer from - their
     * sort_order is a display preference.
     *
     * @return array<int, array{0: string, 1: int, 2: int, 3: string}>
     */
    private function chapterPairs($chapters): array
    {
        $byUnit = $chapters->filter(static fn ($c): bool => ! empty($c->unit_id))->groupBy('unit_id');

        $pairs = [];

        foreach ($byUnit as $group) {
            $ordered = $group->values();

            for ($i = 1; $i < $ordered->count(); $i++) {
                $prev = $ordered[$i - 1];
                $curr = $ordered[$i];

                $pairs[] = ['chapter', (int) $prev->id, (int) $curr->id, $prev->chapter_name.'  ->  '.$curr->chapter_name];
            }
        }

        return $pairs;
    }
}
