<?php

namespace App\Console\Commands\PAL;

use App\Services\PAL\Coherence\ConceptTagger;
use Illuminate\Console\Command;

/**
 * Propose the content -> concept and question -> concept links the coherence
 * map needs in order to have anything to deliver.
 *
 * Writes drafts only (Content Law C5). Nothing this command produces is visible
 * to a learner until a reviewer approves it - see the approve endpoint on
 * CoherenceMapController.
 */
class CoherenceTagCommand extends Command
{
    protected $signature = 'pal:coherence-tag
        {--tenant= : sub_institute_id (required)}
        {--standard= : standard_id (required)}
        {--subject= : subject_id (required)}
        {--estate=both : both | content | questions}
        {--chapter-fallback : attach unmatched rows to their chapter top concept at low confidence}
        {--dry-run : report what would be written, write nothing}';

    protected $description = 'PAL V4: propose concept links for content and questions so the coherence map has material (drafts only)';

    public function handle(ConceptTagger $tagger): int
    {
        $tenant = (int) $this->option('tenant');
        $standard = (int) $this->option('standard');
        $subject = (int) $this->option('subject');
        $estate = (string) $this->option('estate');
        $dryRun = (bool) $this->option('dry-run');
        $fallback = (bool) $this->option('chapter-fallback');

        if ($tenant <= 0 || $standard <= 0 || $subject <= 0) {
            $this->error('--tenant, --standard and --subject are all required.');

            return self::FAILURE;
        }

        if (! in_array($estate, ['both', 'content', 'questions'], true)) {
            $this->error("--estate must be both, content or questions (got '{$estate}').");

            return self::FAILURE;
        }

        $this->line("Scope: tenant {$tenant} / standard {$standard} / subject {$subject}");

        if ($dryRun) {
            $this->warn('Dry run - nothing will be written.');
        }

        if ($fallback) {
            $this->warn('--chapter-fallback is on: unmatched rows are attached to their chapter top concept '
                . 'at confidence 0.2. Use it to get a demo moving, not to ship a map.');
        }

        if ($estate === 'both' || $estate === 'content') {
            $this->newLine();
            $this->info('Content -> Concept');
            $this->summarise($tagger->tagContent($tenant, $standard, $subject, $dryRun, $fallback));
        }

        if ($estate === 'both' || $estate === 'questions') {
            $this->newLine();
            $this->info('Question -> Concept');
            $this->summarise($tagger->tagQuestions($tenant, $standard, $subject, $dryRun, $fallback));
        }

        $this->newLine();
        $this->line('Next: pal:coherence-sync --tenant=' . $tenant . ' --standard=' . $standard
            . ' --subject=' . $subject . ' --health');

        return self::SUCCESS;
    }

    /**
     * @param  array{scanned: int, tagged: int, ambiguous: int, no_concepts: int, samples: array}  $result
     */
    private function summarise(array $result): void
    {
        $this->table(
            ['outcome', 'rows', 'meaning'],
            [
                ['scanned', $result['scanned'], 'rows in scope'],
                ['tagged', $result['tagged'], 'concept link proposed (draft)'],
                ['ambiguous', $result['ambiguous'], 'chapter has concepts, none matched the text'],
                ['no_concepts', $result['no_concepts'], 'the row chapter has NO concepts at all'],
            ]
        );

        // This is the number that explains a thin map. It is not a tagging
        // failure - it means the chapter those rows point at was never
        // extracted, or does not exist as a row anywhere.
        if ($result['no_concepts'] > 0) {
            $this->warn(sprintf(
                '%d row(s) sit on a chapter with no concepts. They cannot enter the coherence map at all '
                . 'until that chapter is extracted into semantic_intelligence and materialised.',
                $result['no_concepts']
            ));
        }

        if ($result['samples'] !== []) {
            $this->line('Sample proposals:');

            foreach ($result['samples'] as $s) {
                $this->line(sprintf('     #%-8d -> %-45s  conf %.2f', $s['row'], $s['concept'], $s['confidence']));
            }
        }
    }
}
