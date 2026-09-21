<?php

namespace App\Console\Commands;

use App\Services\Remap\ChapterCorpusBuilder;
use App\Services\Remap\LegacyProfileBuilder;
use App\Services\Remap\LexicalRanker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Emits the evidence dossier used to author the crosswalk.
 *
 * Read-only. This pipeline uses no LLM: the chapter decisions are made
 * by a human (or by Claude) reading this dossier, and recorded in
 * database/remap/crosswalk_std10.php. This command produces the
 * evidence those decisions rest on, and re-running it after a data
 * change shows exactly what moved.
 */
class RemapEvidence extends Command
{
    protected $signature = 'remap:evidence
                            {--subject= : restrict to one legacy subject id}
                            {--chapter= : restrict to one legacy chapter id}
                            {--candidates=6 : candidates to show per entity class}
                            {--items=6 : sample items to show per entity class}
                            {--json : also write a machine-readable dossier}
                            {--compact : one block per group, for bulk review}';

    protected $description = 'Dump per-group evidence for authoring the std-10 chapter crosswalk';

    public function handle(): int
    {
        $scope   = config('remap.scope');
        $corpus  = (new ChapterCorpusBuilder())->build();
        $ranker  = new LexicalRanker($corpus);
        $builder = new LegacyProfileBuilder();

        $groups = $builder->groups();

        if ($subject = $this->option('subject')) {
            $groups = array_filter($groups, fn ($g) => $g['subject_id'] == $subject);
        }
        if ($chapter = $this->option('chapter')) {
            $groups = array_filter($groups, fn ($g) => $g['chapter_id'] == $chapter);
        }

        $nCand  = (int) $this->option('candidates');
        $nItems = (int) $this->option('items');

        $this->line("corpus: " . count($corpus) . ' chapters, '
            . array_sum(array_column($corpus, 'concept_count')) . ' concepts, '
            . array_sum(array_column($corpus, 'topic_count')) . ' topics');
        $this->line('legacy groups: ' . count($groups));
        $this->newLine();

        $dossier = [];

        foreach ($groups as $group) {
            $dossier[] = $this->option('compact')
                ? $this->renderCompact($group, $builder, $ranker, $corpus)
                : $this->renderGroup($group, $builder, $ranker, $corpus, $nCand, $nItems);
        }

        if ($this->option('json')) {
            $dir  = storage_path('app/' . config('remap.storage_dir', 'remap'));
            File::ensureDirectoryExists($dir);
            $path = $dir . '/evidence.json';
            File::put($path, json_encode($dossier, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("dossier written: {$path}");
        }

        return self::SUCCESS;
    }

    /**
     * One legacy group, profiled separately per entity class because a
     * legacy chapter id can mean different topics in different tables.
     */
    private function renderGroup(
        array $group,
        LegacyProfileBuilder $builder,
        LexicalRanker $ranker,
        array $corpus,
        int $nCand,
        int $nItems
    ): array {
        $subjectId = $group['subject_id'];
        $chapterId = $group['chapter_id'];

        $this->line(str_repeat('=', 100));
        $this->line(sprintf(
            'LEGACY %d / %d      questions=%d  content=%d  teacher=%d',
            $subjectId, $chapterId, $group['questions'], $group['content'], $group['teacher']
        ));

        $out = [
            'legacy_subject_id' => $subjectId,
            'legacy_chapter_id' => $chapterId,
            'counts'            => [
                'questions' => $group['questions'],
                'content'   => $group['content'],
                'teacher'   => $group['teacher'],
            ],
            'classes' => [],
        ];

        foreach (['questions', 'content', 'teacher'] as $class) {
            if ($group[$class] < 1) {
                continue;
            }

            $profile = $builder->build($subjectId, $chapterId, [$class]);
            if ($profile['item_count'] === 0) {
                continue;
            }

            $ranked = $ranker->rank($profile);

            $this->newLine();
            $this->line(sprintf(
                '  [%s] items=%d chars=%d devanagari=%.2f%s',
                strtoupper($class),
                $profile['item_count'],
                $profile['char_count'],
                $profile['devanagari'],
                $profile['is_thin'] ? '  THIN' : ''
            ));

            if (!empty($profile['labels'])) {
                $this->line('    legacy labels: ' . mb_substr(implode(' | ', array_slice($profile['labels'], 0, 12)), 0, 160));
            }

            $terms = array_column($ranked['match_terms'], 'term');
            $this->line(sprintf(
                '    evidence: specificity=%.2f margin=%.2f distinctive_terms=%d [%s]',
                $ranked['specificity'], $ranked['lex_margin'], count($terms),
                mb_substr(implode(', ', array_slice($terms, 0, 10)), 0, 130)
            ));

            $this->line('    sample items:');
            $shown = 0;
            foreach ($profile['sampled'] as $item) {
                if ($shown >= $nItems) {
                    break;
                }
                if (empty($item['title'])) {
                    continue;
                }
                $this->line('      - ' . mb_substr(\App\Services\Remap\Support\TextNormalizer::flatten($item['title']), 0, 92));
                $shown++;
            }

            $this->line('    candidates:');
            $candidates = [];

            foreach (array_slice($ranked['ranked'], 0, $nCand) as $i => $cand) {
                $entry  = $corpus[$cand['chapter_id']];
                $topics = implode(', ', array_slice(array_column($entry['topic_rows'], 'name'), 0, 5));

                $this->line(sprintf(
                    '      %d) ch=%-6d %-9s %-34s score=%-9.1f %s',
                    $i + 1,
                    $cand['chapter_id'],
                    $cand['same_subject'] ? 'same' : 'CROSS',
                    mb_substr($cand['chapter_name'], 0, 34),
                    $cand['score'],
                    $cand['same_subject'] ? '' : ('<- ' . $cand['subject_name'])
                ));
                $this->line('         topics: ' . mb_substr($topics, 0, 104));

                $candidates[] = [
                    'chapter_id'   => $cand['chapter_id'],
                    'subject_id'   => $cand['subject_id'],
                    'subject_name' => $cand['subject_name'],
                    'chapter_name' => $cand['chapter_name'],
                    'same_subject' => $cand['same_subject'],
                    'score'        => round($cand['score'], 2),
                    'topics'       => array_column($entry['topic_rows'], 'name'),
                    'concepts'     => array_slice(array_column($entry['concept_rows'], 'name'), 0, 14),
                ];
            }

            $out['classes'][$class] = [
                'item_count'   => $profile['item_count'],
                'char_count'   => $profile['char_count'],
                'devanagari'   => round($profile['devanagari'], 3),
                'is_thin'      => $profile['is_thin'],
                'specificity'  => round($ranked['specificity'], 3),
                'lex_margin'   => round($ranked['lex_margin'], 3),
                'match_terms'  => $terms,
                'labels'       => array_slice($profile['labels'], 0, 20),
                'samples'      => array_values(array_filter(array_map(
                    fn ($i) => empty($i['title']) ? null : mb_substr(\App\Services\Remap\Support\TextNormalizer::flatten($i['title']), 0, 160),
                    array_slice($profile['sampled'], 0, 14)
                ))),
                'candidates'   => $candidates,
            ];
        }

        return $out;
    }

    /**
     * Dense one-block-per-group view for reviewing all 137 groups.
     */
    private function renderCompact(
        array $group,
        LegacyProfileBuilder $builder,
        LexicalRanker $ranker,
        array $corpus
    ): array {
        $subjectId = $group['subject_id'];
        $chapterId = $group['chapter_id'];

        $this->line(sprintf(
            'L%d/%d  q=%d c=%d t=%d',
            $subjectId, $chapterId, $group['questions'], $group['content'], $group['teacher']
        ));

        $out = ['legacy_subject_id' => $subjectId, 'legacy_chapter_id' => $chapterId, 'classes' => []];

        foreach (['questions', 'content', 'teacher'] as $class) {
            if ($group[$class] < 1) {
                continue;
            }

            $profile = $builder->build($subjectId, $chapterId, [$class]);
            if ($profile['item_count'] === 0) {
                continue;
            }

            $ranked = $ranker->rank($profile);
            $terms  = array_column($ranked['match_terms'], 'term');

            // Per-item vote is the primary signal: a pooled query lets a
            // rare-vocabulary minority outvote a common-vocabulary
            // majority (see Geography 6237).
            $vote = $ranker->voteOnItems($profile['items'], $subjectId, $profile['devanagari']);

            $tops = [];
            foreach (array_slice($vote['spread'], 0, 4) as $x) {
                $tops[] = sprintf('%d%s:%d', $x['chapter_id'], $x['same'] ? '' : '*', $x['votes']);
            }

            $this->line(sprintf(
                '   %-9s n=%-4d vote=%-6s share=%.2f voters=%-3d abst=%-4d pooled=%-6s terms[%s]',
                $class, $profile['item_count'],
                $vote['winner'] ?? 'none', $vote['winner_share'], $vote['voters'], $vote['abstained'],
                $ranked['ranked'] ? $ranked['ranked'][0]['chapter_id'] : 'none',
                mb_substr(implode(',', array_slice($terms, 0, 6)), 0, 80)
            ));
            if ($tops) {
                $this->line('       votes: ' . implode('  ', $tops));
            }

            // Titles are what a reviewer actually judges on, so show a
            // few even in compact mode.
            $titles = [];
            foreach ($profile['sampled'] as $item) {
                if (!empty($item['title']) && count($titles) < 3) {
                    $titles[] = mb_substr(\App\Services\Remap\Support\TextNormalizer::flatten($item['title']), 0, 62);
                }
            }
            if ($titles) {
                $this->line('       eg: ' . implode(' // ', $titles));
            }

            $out['classes'][$class] = [
                'n' => $profile['item_count'],
                'specificity' => round($ranked['specificity'], 3),
                'margin' => round($ranked['lex_margin'], 3),
                'terms' => $terms,
                'top' => array_map(fn ($c) => [
                    'chapter_id' => $c['chapter_id'],
                    'name' => $c['chapter_name'],
                    'same' => $c['same_subject'],
                    'score' => round($c['score'], 1),
                ], array_slice($ranked['ranked'], 0, 5)),
                'samples' => $titles,
                'vote' => $vote,
            ];
        }

        return $out;
    }
}
