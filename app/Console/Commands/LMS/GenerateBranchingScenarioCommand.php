<?php

namespace App\Console\Commands\LMS;

use App\Services\ContentGenerationService;
use App\Services\lms\H5P\BranchingGraph;
use App\Services\lms\H5P\BranchingPayload;
use App\Services\StudyDeck\Branching\BranchingScenarioService;
use App\Services\StudyDeck\ClaudeCliCompleter;
use App\Services\StudyDeck\Contracts\Completer;
use Illuminate\Console\Command;

/**
 * Write a branching scenario for one chapter, as a REVIEW BUNDLE of files.
 *
 * The sibling of lms:generate-study-deck and lms:generate-study-document. A run
 * READS the chapter (SELECTs only) and writes files under
 * storage/app/branching-scenario/chapter-<id>/<stamp>/. It has no --store: a
 * scenario reaches the database as an H5P DRAFT through the app's own save,
 * after a teacher has looked at it in the player, and is published by a second
 * act. This command exists to let the output be inspected without either.
 *
 *   php artisan lms:generate-branching-scenario 8592 --sources
 *   php artisan lms:generate-branching-scenario 8592 --concepts=101,102,103
 *   php artisan lms:generate-branching-scenario 8592 --length=short --executor=cli
 *
 * The bundle:
 *   scenario.json   the save payload, exactly as the editor would send it
 *   report.json     what was checked and found
 *   blueprint.json  the skeleton the model planned (stage 1)
 *   paths.md        every path through the scenario, as a learner would read it
 *   replies.json    the raw model replies, for debugging a failed run
 */
class GenerateBranchingScenarioCommand extends Command
{
    protected $signature = 'lms:generate-branching-scenario
        {chapter : chapter_master.id}
        {--tenant=1 : sub_institute_id}
        {--executor= : api | cli (cli is dev-only; default from config claude.executor)}
        {--out= : bundle directory (default storage/app/branching-scenario/chapter-<id>/<stamp>)}
        {--topics= : comma-separated topic ids to build the scenario from}
        {--concepts= : comma-separated concept ids to build the scenario from (at most 8)}
        {--length=standard : short | standard | extended}
        {--focus= : a line from the teacher on what the scenario should be about}
        {--no-questions : do not reuse bank questions as knowledge checks}
        {--sources : list the chapter\'s topics and concepts and stop (no model call)}';

    protected $description = 'Write a branching scenario for a chapter from its LMS data, as a review bundle (stores nothing)';

    public function handle(): int
    {
        $chapterId = (int) $this->argument('chapter');
        $tenant = (int) $this->option('tenant');
        $length = (string) $this->option('length');
        if (! in_array($length, ['short', 'standard', 'extended'], true)) {
            $this->error('--length must be short, standard or extended.');

            return self::FAILURE;
        }

        if ($this->option('sources')) {
            return $this->listSources($chapterId, $tenant);
        }

        $executor = (string) ($this->option('executor') ?: config('claude.executor', 'api'));
        $this->line('Executor: <options=bold>' . $executor . '</>' . ($executor === 'cli' ? ' (dev-only, tools disabled)' : ''));

        $options = [
            'topic_ids' => $this->ids('topics'),
            'concept_ids' => $this->ids('concepts'),
            'length' => $length,
            'focus' => $this->option('focus') ?: null,
            'use_questions' => ! $this->option('no-questions'),
            'model' => $executor === 'cli' ? 'claude-cli (dev)' : (string) config('claude.model'),
        ];

        try {
            $service = BranchingScenarioService::make($this->completer($executor));
            $result = $service->generate($chapterId, $tenant, $options, fn ($stage, $msg) => $this->line(sprintf('  <fg=cyan>%-8s</> %s', $stage, $msg)));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $dir = rtrim((string) ($this->option('out') ?: storage_path('app/branching-scenario/chapter-' . $chapterId . '/' . date('Ymd-His'))), '/\\');
        $this->writeBundle($dir, $result);
        $this->newLine();
        $this->line('Bundle: ' . $dir);

        foreach ($result['report']['warnings'] as $w) {
            $this->warn('  warning: ' . $w);
        }
        foreach ($result['report']['errors'] as $e) {
            $this->line('  <fg=red>FAIL</> ' . $e);
        }
        $this->line('  ' . json_encode($result['report']['stats']));

        if (! $result['report']['ok']) {
            $this->error('Validation failed.');

            return self::FAILURE;
        }

        $this->info('Validation passed. Nothing was stored; review ' . $dir . '/paths.md.');

        return self::SUCCESS;
    }

    private function listSources(int $chapterId, int $tenant): int
    {
        try {
            $sources = BranchingScenarioService::make(null)->sources($chapterId, $tenant);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $chapter = $sources['chapter'];
        $this->line(sprintf('%s / %s / %s%s', $chapter['standard'], $chapter['subject'], $chapter['name'], $chapter['has_text'] ? '' : '   (NO CHAPTER TEXT: generation would be refused)'));
        $this->table(
            ['Concept', 'Name', 'Topic', 'Questions', 'Suits (0-10)', 'Material'],
            array_map(fn ($c) => [$c['id'], mb_substr($c['name'], 0, 60), $c['topic_id'] ?? '-', $c['questions'], $c['suitability'], $c['has_material'] ? 'yes' : 'no'], $sources['concepts'])
        );

        return self::SUCCESS;
    }

    /** @return array<int,int> */
    private function ids(string $option): array
    {
        return array_values(array_unique(array_filter(array_map('intval', array_map('trim', explode(',', (string) $this->option($option)))))));
    }

    /** The model to ask. Reached through a subclass so this command carries no Anthropic code of its own. */
    protected function completer(string $executor): Completer
    {
        if ($executor === 'cli') {
            return new ClaudeCliCompleter();
        }

        config(['claude.executor' => 'api']);
        $bridge = new class extends ContentGenerationService {
            public function completer(): ?Completer
            {
                return $this->studyCompleter();
            }
        };

        return $bridge->completer()
            ?? throw new \RuntimeException('No Anthropic API key (ai_api_keys / ANTHROPIC_API_KEY). Use --executor=cli for a dev run.');
    }

    /** @param array<string,mixed> $result */
    private function writeBundle(string $dir, array $result): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        file_put_contents($dir . '/scenario.json', json_encode($result['scenario'], $flags));
        file_put_contents($dir . '/report.json', json_encode($result['report'], $flags));
        file_put_contents($dir . '/blueprint.json', json_encode($result['blueprint'], $flags));
        file_put_contents($dir . '/replies.json', json_encode($result['replies'], $flags));
        file_put_contents($dir . '/paths.md', $this->pathsMarkdown($result['scenario']));
    }

    /**
     * Every path, written the way a learner meets it: the screen, the choice
     * they make, what they are told, the next screen.
     *
     * @param array<string,mixed> $scenario
     */
    private function pathsMarkdown(array $scenario): string
    {
        $nodes = array_values($scenario['nodes']);
        $byRef = [];
        foreach ($nodes as $n) {
            $byRef[(string) $n['ref']] = $n;
        }

        $paths = BranchingGraph::enumeratePaths(BranchingPayload::graphNodes($nodes), 400);
        $plain = fn (string $html) => BranchingPayload::plain($html);

        $out = ['# ' . $scenario['title'], '', $scenario['description'], '', '**Learning objectives**', ''];
        foreach ($scenario['learning_objectives'] as $o) {
            $out[] = '- ' . $o;
        }
        $out[] = '';
        $out[] = count($paths) . ' path(s) through ' . count($nodes) . ' steps.';

        foreach ($paths as $i => $path) {
            $out[] = '';
            $out[] = '## Path ' . ($i + 1);
            foreach ($path as $k => $ref) {
                $n = $byRef[$ref];
                $out[] = '';
                $out[] = '### ' . $n['title'] . ' _(' . $n['node_type'] . ($n['purpose'] ? ', ' . $n['purpose'] : '') . ')_';
                $out[] = $plain((string) $n['body']);
                if ($n['node_type'] === 'ending') {
                    $out[] = '_Score: ' . $n['score'] . '_';
                }
                if ($n['node_type'] === 'branching_question') {
                    $following = $path[$k + 1] ?? null;
                    foreach ($n['options'] as $o) {
                        $taken = $following !== null && (string) $o['next_ref'] === $following;
                        $out[] = ($taken ? '- **chosen** ' : '- ') . '[' . ($o['quality'] ?? '?') . '] ' . $o['text'];
                        if ($taken) {
                            $out[] = '  - ' . $plain((string) $o['feedback_title']) . ': ' . $plain((string) $o['feedback_text']);
                        }
                    }
                }
            }
        }

        return implode("\n", $out) . "\n";
    }
}
