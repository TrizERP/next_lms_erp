<?php

namespace App\Console\Commands\PAL;

use App\Services\Eso\Video\ConceptVideoRelevanceScorer;
use App\Services\Eso\Video\InstituteVideoRepository;
use App\Services\PAL\ContentModel\ConceptVideoLibraryService;
use App\Services\PAL\Integration\ExternalVideoSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Find candidate videos for concepts and file them for review.
 *
 * This is the ONLY thing that ranks a library or calls a search API. The
 * student's request path reads approved rows and nothing else — see
 * EsoConceptVideoResolver for why that split exists.
 *
 * Everything written here is a `draft`. Nothing this command does can change
 * what a student sees until a human approves it.
 *
 *   php artisan pal:harvest-concept-videos --chapter=1014 --dry-run
 *   php artisan pal:harvest-concept-videos --chapter=1014 --tenant=1
 *   php artisan pal:harvest-concept-videos --concept=2451 --source=external
 */
class HarvestConceptVideosCommand extends Command
{
    protected $signature = 'pal:harvest-concept-videos
        {--chapter= : Only concepts in this chapter}
        {--concept= : Only this concept}
        {--tenant=1 : sub_institute_id to harvest for}
        {--source=all : institute|external|all}
        {--limit=0 : Stop after this many concepts (0 = no limit)}
        {--dry-run : Report what would be filed without writing anything}';

    protected $description = 'Find institute and external videos for PAL concepts and file them as drafts for review';

    public function handle(
        InstituteVideoRepository $institute,
        ExternalVideoSearchService $external,
        ConceptVideoLibraryService $library,
        ConceptVideoRelevanceScorer $scorer,
    ): int {
        $tenant = (int) $this->option('tenant');
        $source = (string) $this->option('source');
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        if (! in_array($source, ['institute', 'external', 'all'], true)) {
            $this->error('--source must be institute, external or all.');

            return self::FAILURE;
        }

        $wantInstitute = $source !== 'external';
        $wantExternal = $source !== 'institute';

        if ($wantExternal && ! $external->available()) {
            $this->warn('External search is not configured (pal_content.video.external) — institute tier only.');
            $wantExternal = false;
        }

        $concepts = $this->concepts($limit);

        if ($concepts === []) {
            $this->warn('No concepts matched.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d concept(s) for tenant %d%s',
            $dryRun ? 'Inspecting' : 'Harvesting',
            count($concepts),
            $tenant,
            $dryRun ? ' (dry run — nothing will be written)' : ''
        ));

        $totals = ['institute' => 0, 'external' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'none' => 0];

        foreach ($concepts as $concept) {
            $candidates = [];

            if ($wantInstitute) {
                $candidates = $this->fromInstitute($institute, $concept);
                $totals['institute'] += count($candidates);
            }

            // The external tier is a fallback, not a supplement: the school's
            // own material is already curriculum-aligned and already cleared
            // for its students, so it is never worth spending quota to
            // second-guess a confident local match.
            if ($candidates === [] && $wantExternal) {
                $candidates = $this->fromExternal($external, $scorer, $concept);
                $totals['external'] += count($candidates);
            }

            if ($candidates === []) {
                $totals['none']++;
                $this->line(sprintf('  <fg=gray>—</> %s', $concept->name));
                continue;
            }

            $this->line(sprintf(
                '  <info>✓</info> %s <fg=gray>(%d candidate(s), best %.2f via %s)</>',
                $concept->name,
                count($candidates),
                (float) ($candidates[0]['match_score'] ?? 0),
                $candidates[0]['source'] ?? 'institute'
            ));

            if ($dryRun) {
                foreach ($candidates as $candidate) {
                    $this->line(sprintf('      <fg=gray>%s</>', mb_substr((string) ($candidate['title'] ?? ''), 0, 90)));
                }
                continue;
            }

            $result = $library->ingest(
                (int) $concept->id,
                $concept->chapter_id === null ? null : (int) $concept->chapter_id,
                $tenant,
                $candidates
            );

            foreach (['created', 'updated', 'skipped'] as $key) {
                $totals[$key] += $result[$key];
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Candidates: %d institute, %d external. Rows: %d created, %d updated, %d skipped. %d concept(s) had nothing.',
            $totals['institute'],
            $totals['external'],
            $totals['created'],
            $totals['updated'],
            $totals['skipped'],
            $totals['none']
        ));

        if (! $dryRun && ($totals['created'] > 0 || $totals['updated'] > 0)) {
            $this->comment('All rows are drafts. They reach students only once approved in the review queue.');
        }

        if ($wantExternal) {
            $this->line(sprintf(
                '<fg=gray>YouTube searches used today: %d of %d.</>',
                $external->searchesUsedToday(),
                (int) config('pal_content.video.external.daily_search_cap', 90)
            ));
        }

        return self::SUCCESS;
    }

    /** @return array<int, object> */
    protected function concepts(int $limit): array
    {
        $query = DB::table('lms_concept')
            ->select(['id', 'name', 'chapter_id', 'topic_id', 'subject_id', 'standard_id', 'sub_institute_id', 'syear'])
            ->orderBy('chapter_id')
            ->orderBy('id');

        if ($chapter = $this->option('chapter')) {
            $query->where('chapter_id', (int) $chapter);
        }

        if ($concept = $this->option('concept')) {
            $query->where('id', (int) $concept);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function fromInstitute(InstituteVideoRepository $institute, object $concept): array
    {
        $tenant = (int) $this->option('tenant');

        return array_map(static fn (array $row) => [
            'media_url' => $row['media_url'],
            'title' => $row['title'] ?? null,
            'description' => $row['description'] ?? null,
            'content_master_id' => (int) ($row['id'] ?? 0) ?: null,
            'match_score' => $row['match_score'] ?? null,
            'match_reason' => $row['match_reason'] ?? null,
            'source' => 'institute',
            'provider' => 'upload',
            'attribution' => null,
        ], $institute->candidatesForConcept($concept, $tenant));
    }

    /**
     * External candidates, scored by the SAME scorer as the institute tier.
     *
     * Results below the threshold are dropped here rather than filed: asking a
     * teacher to review material the machine already knows is off-topic is how
     * a review queue becomes something nobody opens.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fromExternal(
        ExternalVideoSearchService $external,
        ConceptVideoRelevanceScorer $scorer,
        object $concept,
    ): array {
        $context = $this->curriculumContext($concept);

        // Two attempts, and the second exists because the chapter name cuts
        // both ways. It anchors a generically-named concept to the right level
        // ("Divisibility by 3" + "Integers" + "class 7"), but it hijacks a
        // concept that is not about the chapter's headline topic: searching
        // "Cube numbers and index notation Integers class 7" returns videos
        // about integer arithmetic, and every result is then correctly
        // rejected, leaving the concept with nothing.
        //
        // So: ask with the chapter, and if nothing clears the relevance gate,
        // ask again without it. The retry only costs quota for the minority of
        // concepts that would otherwise have had no video at all.
        $attempts = [
            $external->queryFor(
                (string) $concept->name,
                $context['chapter_name'],
                $context['subject_name'],
                $context['grade_name']
            ),
            $external->queryFor(
                (string) $concept->name,
                null,
                $context['subject_name'],
                $context['grade_name']
            ),
        ];

        foreach (array_unique($attempts) as $query) {
            $results = $external->search($query);
            if ($results === []) {
                continue;
            }

            $ranked = $scorer->rank((string) $concept->name, $results, [
                'min_score' => (float) config('pal_content.video.min_match_score', 0.55),

                // Search results are not a library to discriminate within — see
                // ConceptVideoRelevanceScorer::rank(). Scoring them as one drives
                // every shared token's IDF to zero and rejects the whole set.
                'pool_mode' => 'search',
            ]);

            $usable = array_values(array_filter($ranked, static fn (array $row) => ! empty($row['media_url'])));

            if ($usable !== []) {
                return $usable;
            }
        }

        return [];
    }

    /** @return array{chapter_name:?string, subject_name:?string, grade_name:?string} */
    protected function curriculumContext(object $concept): array
    {
        $chapter = DB::table('chapter_master')
            ->where('id', (int) ($concept->chapter_id ?? 0))
            ->first(['chapter_name', 'subject_id', 'standard_id']);

        $subjectId = (int) ($concept->subject_id ?? ($chapter->subject_id ?? 0));
        $standardId = (int) ($concept->standard_id ?? ($chapter->standard_id ?? 0));

        $subject = $subjectId > 0
            ? DB::table('subject')->where('id', $subjectId)->value('subject_name')
            : null;

        // standard.name is the class number ("10"), which is what a learner
        // searching for help would actually type.
        $grade = $standardId > 0
            ? DB::table('standard')->where('id', $standardId)->value('name')
            : null;

        return [
            'chapter_name' => $chapter->chapter_name ?? null,
            'subject_name' => $subject === null ? null : (string) $subject,
            'grade_name' => $grade === null ? null : (string) $grade,
        ];
    }
}
