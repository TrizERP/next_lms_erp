<?php

namespace App\Console\Commands\PAL;

use App\Services\PAL\Coherence\ChapterAligner;
use Illuminate\Console\Command;

/**
 * Pair the chapter ids the content estate uses with the chapter ids the concept
 * layer uses. Run this BEFORE pal:coherence-tag - without an alignment the
 * tagger has nothing to match against and reports every row as no_concepts.
 *
 * Output is a proposal. Review the pairs, reject the wrong ones
 * (UPDATE pal_chapter_alignment SET status='rejected' WHERE id=...), and a
 * re-run will never resurrect them.
 */
class CoherenceAlignCommand extends Command
{
    protected $signature = 'pal:coherence-align
        {--tenant= : sub_institute_id (required)}
        {--standard= : standard_id (required)}
        {--subject= : subject_id (required)}
        {--dry-run : show the proposed pairs, write nothing}';

    protected $description = 'PAL V4: align the content estate chapter ids with the concept layer chapter ids by name';

    public function handle(ChapterAligner $aligner): int
    {
        $tenant = (int) $this->option('tenant');
        $standard = (int) $this->option('standard');
        $subject = (int) $this->option('subject');

        if ($tenant <= 0 || $standard <= 0 || $subject <= 0) {
            $this->error('--tenant, --standard and --subject are all required.');

            return self::FAILURE;
        }

        $result = $aligner->align($tenant, $standard, $subject, (bool) $this->option('dry-run'));

        $this->line("Scope: tenant {$tenant} / standard {$standard} / subject {$subject}");
        $this->line(sprintf(
            'Concept-bearing chapters: %d   Estate chapters needing alignment: %d',
            $result['concept_chapters'],
            $result['estate_chapters']
        ));

        if ($result['concept_chapters'] === 0) {
            $this->error('No chapter in this scope has any concepts. Nothing can be aligned until '
                . 'semantic_intelligence has been extracted and materialised into lms_concept.');

            return self::FAILURE;
        }

        if ($result['pairs'] !== []) {
            $this->newLine();
            $this->info('Proposed pairs');
            $this->table(
                ['estate id', 'estate chapter (content/questions)', 'concept id', 'concept chapter (extracted)', 'conf'],
                array_map(fn ($p) => [
                    $p['estate_chapter_id'],
                    $this->truncate($p['estate_chapter_name']),
                    $p['concept_chapter_id'],
                    $this->truncate($p['concept_chapter_name']),
                    number_format($p['confidence'], 2),
                ], $result['pairs'])
            );
        }

        if ($result['unmatched_names'] !== []) {
            $this->newLine();
            // Not a failure: a chapter renamed beyond recognition genuinely has
            // no automatic match, and a wrong pairing would route answers into
            // the wrong concept's mastery. These need a human pair, not a guess.
            $this->warn('Unmatched estate chapters - content on these stays outside the map until paired by hand:');

            foreach ($result['unmatched_names'] as $id => $name) {
                $this->line(sprintf('     %-8d %s', $id, $this->truncate($name, 70)));
            }
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->line('Dry run - nothing written.');
        } else {
            $this->info("Wrote {$result['written']} alignment row(s) with status='proposed'.");
            $this->line('Next: pal:coherence-tag --tenant=' . $tenant . ' --standard=' . $standard
                . ' --subject=' . $subject . ' --dry-run');
        }

        return self::SUCCESS;
    }

    private function truncate(?string $value, int $length = 42): string
    {
        $value = trim((string) $value);

        return strlen($value) > $length ? substr($value, 0, $length - 1) . '.' : $value;
    }
}
