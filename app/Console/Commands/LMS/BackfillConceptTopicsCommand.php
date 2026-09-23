<?php

namespace App\Console\Commands\LMS;

use App\Services\PAL\Coherence\Concerns\TokenisesTitles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Attach concepts to the topic they belong to, where the names agree.
 *
 * WHY THIS EXISTS
 * `lms_concept.topic_id` is the authoritative concept -> topic_master link and the
 * only thing standing between the Coherence Map and the full four-level hierarchy
 * it is specified to draw. Estate-wide it is populated on 1,410 of 2,571 concepts,
 * but on subject 3976 / standard 42 - the course the map was built for - it is
 * empty on all 109, while that course's chapters carry 52 perfectly good topics
 * (measured 2026-09-16). The map degrades gracefully by parenting those concepts to
 * their chapter, so this command improves the picture rather than unblocking it.
 *
 * HOW IT MATCHES, AND WHY IT IS DELIBERATELY TIMID
 * Token overlap between the concept's name and the topic's name + description,
 * using the same tokeniser ChapterAligner and ConceptTagger share - so this agrees
 * with them about what "Co-ordinate Geometry" and "The Use of Coordinates" have in
 * common instead of inventing a third opinion.
 *
 * Containment, not Jaccard: a concept name is short ("Origin") and a topic name is
 * long ("The Cartesian coordinate system and its origin"), so the honest question
 * is "how much of the CONCEPT is present in the topic", not how similar the two
 * strings are overall. Jaccard would score that pair near zero on length alone.
 *
 * Only the best-scoring topic wins, only above --min-score, and only when it beats
 * the runner-up by --margin. A concept that fits two topics equally well is left
 * alone: a wrong parent is worse than a missing one, because the map would then
 * assert a curriculum relationship nobody authored.
 *
 * WHAT IT WILL NEVER DO
 * It does not overwrite an existing topic_id - a value already there was authored
 * by extraction or by a person, and neither is this command's to second-guess. It
 * writes exactly one column. It creates no topics: a concept with no plausible
 * topic stays parented to its chapter, which is a true statement about the data.
 */
class BackfillConceptTopicsCommand extends Command
{
    use TokenisesTitles;

    protected $signature = 'lms:backfill-concept-topics
        {--tenant= : restrict to one sub_institute_id}
        {--subject= : restrict to one subject_id}
        {--standard= : restrict to one standard_id}
        {--chapter= : restrict to one chapter_id}
        {--min-score=0.5 : reject a match below this containment score (0-1)}
        {--margin=0.15 : the winner must beat the runner-up by this much}
        {--limit=0 : stop after N concepts (0 = all)}
        {--dry-run : report what would change, write nothing}';

    protected $description = 'Populate lms_concept.topic_id by matching concept names to their chapter\'s topics';

    /**
     * Words that carry no subject meaning in a curriculum title.
     *
     * Narration verbs ("understanding", "introduction to") and structural nouns
     * ("chapter", "topic", "concept") appear in half the titles in this corpus and
     * would match everything to everything.
     */
    protected function stopwords(): array
    {
        return [
            'the', 'and', 'for', 'with', 'from', 'that', 'this', 'its', 'are', 'was',
            'introduction', 'intro', 'understand', 'understand', 'basic', 'basics',
            'chapter', 'topic', 'topics', 'concept', 'concepts', 'unit', 'lesson',
            'study', 'learn', 'learning', 'overview', 'about', 'using', 'use',
            'part', 'section', 'general', 'simple', 'other', 'more', 'their',
        ];
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $minScore = (float) $this->option('min-score');
        $margin = (float) $this->option('margin');
        $limit = (int) $this->option('limit');

        $chapters = $this->targetChapters();

        if ($chapters === []) {
            $this->warn('No chapters matched those filters.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? 'DRY RUN - ' : '').'Scanning '.count($chapters).' chapter(s).');

        $bar = $this->output->createProgressBar(count($chapters));
        $bar->start();

        $considered = 0;
        $matched = 0;
        $ambiguous = 0;
        $weak = 0;
        $noTopics = 0;
        $samples = [];

        foreach ($chapters as $chapterId) {
            $topics = DB::table('topic_master')
                ->select('id', 'name', 'description')
                ->where('chapter_id', $chapterId)
                ->get();

            $concepts = DB::table('lms_concept')
                ->select('id', 'name', 'description')
                ->where('chapter_id', $chapterId)
                // Only ever fills a gap. `0` is how some rows spell "unset".
                ->where(function ($q) {
                    $q->whereNull('topic_id')->orWhere('topic_id', 0);
                })
                ->get();

            if ($concepts->isEmpty()) {
                $bar->advance();

                continue;
            }

            if ($topics->isEmpty()) {
                $noTopics += $concepts->count();
                $bar->advance();

                continue;
            }

            // Tokenise each topic once per chapter, not once per concept.
            $topicTokens = [];

            foreach ($topics as $t) {
                $topicTokens[$t->id] = array_flip($this->tokenise(
                    $t->name.' '.(string) $t->description
                ));
            }

            foreach ($concepts as $c) {
                if ($limit > 0 && $considered >= $limit) {
                    break 2;
                }

                $considered++;

                $conceptTokens = $this->tokenise((string) $c->name);

                if ($conceptTokens === []) {
                    $weak++;

                    continue;
                }

                $scores = [];

                foreach ($topicTokens as $topicId => $bag) {
                    $hits = 0;

                    foreach ($conceptTokens as $token) {
                        if (isset($bag[$token])) {
                            $hits++;
                        }
                    }

                    $scores[$topicId] = $hits / count($conceptTokens);
                }

                arsort($scores);
                $bestId = array_key_first($scores);
                $best = $scores[$bestId];
                $runnerUp = count($scores) > 1 ? array_values($scores)[1] : 0.0;

                if ($best < $minScore) {
                    $weak++;

                    continue;
                }

                if ($best - $runnerUp < $margin) {
                    // Two topics fit equally well. Leave it to a person.
                    $ambiguous++;

                    continue;
                }

                $matched++;

                if (count($samples) < 10) {
                    $topicName = $topics->firstWhere('id', $bestId)->name ?? '';
                    $samples[] = [$c->name, $topicName, number_format($best, 2)];
                }

                if (! $dryRun) {
                    DB::table('lms_concept')->where('id', $c->id)->update(['topic_id' => $bestId]);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($samples !== []) {
            $this->table(['Concept', 'Matched topic', 'Score'], $samples);
        }

        $rate = $considered > 0 ? round($matched / $considered * 100, 1) : 0.0;

        $this->table(['Outcome', 'Concepts'], [
            ['Considered (topic_id empty)', $considered],
            ['Matched'.($dryRun ? ' (not written)' : ' and written'), $matched],
            ['Left alone - two topics fit equally', $ambiguous],
            ['Left alone - no topic scored high enough', $weak],
            ['Left alone - chapter has no topics', $noTopics],
            ['Match rate', $rate.'%'],
        ]);

        if ($dryRun) {
            $this->comment('Nothing was written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * Chapter ids in scope.
     *
     * Driven off chapter_master rather than lms_concept so a --chapter filter can be
     * validated even when that chapter has no unassigned concepts left.
     *
     * @return array<int, int>
     */
    private function targetChapters(): array
    {
        $q = DB::table('chapter_master')->select('id');

        foreach (['tenant' => 'sub_institute_id', 'subject' => 'subject_id', 'standard' => 'standard_id'] as $opt => $column) {
            if ($this->option($opt) !== null && $this->option($opt) !== '') {
                $q->where($column, (int) $this->option($opt));
            }
        }

        if ($this->option('chapter')) {
            $q->where('id', (int) $this->option('chapter'));
        }

        return $q->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }
}
