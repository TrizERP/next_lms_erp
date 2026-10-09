<?php

namespace App\Console\Commands\LMS;

use App\Services\ContentGenerationService;
use App\Services\StudyDeck\ClaudeApiCompleter;
use App\Services\StudyDeck\ClaudeCliCompleter;
use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\StudyDeckService;
use App\Services\StudyDeck\StudyImageStores;
use Illuminate\Console\Command;

/**
 * Generate a classroom study deck for one chapter and write a REVIEW BUNDLE.
 *
 * By default this READS the chapter and writes the deck's text files under
 * storage/app/study-deck/chapter-<id>/, plus the deck's pictures into the
 * study_deck_images table (they are kept in the database, never as files).
 * Nothing reaches content_master or the shared object store unless --store is
 * passed, and --store refuses a deck that failed validation. Pictures of a run
 * that is never stored are removed by `study-deck:images-prune`. --store hands the bundle it just wrote to
 * `lms:store-study-deck`, so what is stored is exactly what was written here
 * (and can be reviewed first: run without --store, look, then store).
 *
 * The bundle is laid out so the existing validator runs on it unchanged:
 *   php artisan lms:validate-content storage/app/study-deck/chapter-8592 --file=presentation.html
 */
class GenerateStudyDeckCommand extends Command
{
    protected $signature = 'lms:generate-study-deck
        {chapter : chapter_master.id}
        {--tenant=1 : sub_institute_id}
        {--executor= : api | cli (cli is dev-only; default from config claude.executor)}
        {--out= : bundle directory (default storage/app/study-deck/chapter-<id>)}
        {--min-slides=30}
        {--max-slides=35}
        {--per-concept=4 : most bank questions offered per concept}
        {--export-player= : copy deck.json (picture references only, no picture files) to this directory so the local student player can load the review bundle}
        {--store : on a passing validation, store THIS bundle (see lms:store-study-deck: writes to the DB and shared storage)}';

    protected $description = 'Generate a 30-35 slide classroom study deck from a chapter\'s LMS data';

    public function handle(ContentGenerationService $content): int
    {
        $chapterId = (int) $this->argument('chapter');
        $tenant = (int) $this->option('tenant');
        $executor = (string) ($this->option('executor') ?: config('claude.executor', 'api'));
        $dir = rtrim((string) ($this->option('out') ?: storage_path('app/study-deck/chapter-' . $chapterId)), '/\\');
        $store = (bool) $this->option('store');

        $this->line('Executor: <options=bold>' . $executor . '</>' . ($executor === 'cli' ? ' (dev-only, tools disabled)' : ''));

        try {
            $completer = $this->completer($executor, $content);
            // Pictures go straight into the database (study_deck_images) and the bundle carries only their
            // references: a run writes no picture file anywhere. Publishing is lms:store-study-deck's job.
            $images = StudyImageStores::database($tenant, $chapterId);
            $service = StudyDeckService::make($completer, $images);

            $result = $service->generate($chapterId, $tenant, [
                'min_slides' => (int) $this->option('min-slides'),
                'max_slides' => (int) $this->option('max-slides'),
                'per_concept' => (int) $this->option('per-concept'),
            ], fn ($stage, $msg) => $this->line(sprintf('  <fg=cyan>%-8s</> %s', $stage, $msg)));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->writeBundle($dir, $result);
        if ($target = (string) $this->option("export-player")) {
            $this->exportPlayer($dir, $target);
        }
        $this->newLine();
        $this->line('Bundle: ' . $dir);

        foreach ($result['report']['warnings'] as $w) {
            $this->warn('  warning: ' . $w);
        }
        foreach ($result['report']['errors'] as $e) {
            $this->line('  <fg=red>FAIL</> ' . $e);
        }
        $this->line('  ' . json_encode($result['report']['stats']));

        if (!$result['report']['ok']) {
            $this->error('Validation failed - not stored.');

            return self::FAILURE;
        }

        $this->info('Validation passed.');

        if (!$store) {
            $this->line('Review the bundle; then run: php artisan lms:store-study-deck ' . $chapterId . ' --dry-run   (and without --dry-run to store it)');

            return self::SUCCESS;
        }

        return $this->call('lms:store-study-deck', ['chapter' => $chapterId, '--bundle' => $dir, '--tenant' => $tenant]);
    }
    private function completer(string $executor, ContentGenerationService $content): Completer
    {
        if ($executor === 'cli') {
            return new ClaudeCliCompleter();
        }

        // The service's own key resolution and streaming call, reached through a
        // closure so this command carries no Anthropic code of its own.
        $bridge = new class extends ContentGenerationService {
            public function completer(): ?ClaudeApiCompleter
            {
                $key = $this->resolveApiKey();
                if ($key === '') {
                    return null;
                }
                $model = (string) config('claude.model', 'claude-opus-5');

                return new ClaudeApiCompleter(fn (string $s, string $p, int $m): array => $this->callClaude($key, $model, $p, $s, $m));
            }
        };

        return $bridge->completer()
            ?? throw new \RuntimeException('No Anthropic API key (ai_api_keys / ANTHROPIC_API_KEY). Use --executor=cli for a dev run.');
    }

    /** @param array<string,mixed> $r */
    private function writeBundle(string $dir, array $r): void
    {
        foreach ([$dir, $dir . '/out'] as $d) {
            if (!is_dir($d)) {
                mkdir($d, 0775, true);
            }
        }
        $json = fn ($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        file_put_contents($dir . '/concepts.json', $json(array_values(array_map(fn ($c) => $c['name'], $r['context']['concepts']))));
        file_put_contents($dir . '/learning-map.json', $json($r['map']));
        file_put_contents($dir . '/plan.json', $json($r['plan']));
        file_put_contents($dir . '/slides.json', $json($r['content']));
        file_put_contents($dir . '/images.json', $json($r['images']));
        file_put_contents($dir . '/deck.json', $json($r['deck']));
        file_put_contents($dir . '/questions-excluded.json', $json($r['selection']['excluded']));
        file_put_contents($dir . '/questions-flagged.json', $json($r['selection']['flagged'] ?? []));
        file_put_contents($dir . '/activities.json', $json($r['activities'] ?? []));
        file_put_contents($dir . '/interactions.json', $json($r['interactions'] ?? []));
        file_put_contents($dir . '/report.json', $json($r['report']));
        file_put_contents($dir . '/out/presentation.html', $r['html']);
    }

    /**
     * Copy the review bundle's deck to a folder the local player can serve.
     *
     * Only deck.json: its pictures are `study-deck-image:<id>` references to rows in the database, which the player
     * turns into addresses through the API. No picture is copied, so nothing accumulates in that folder.
     */
    private function exportPlayer(string $dir, string $target): void
    {
        $target = rtrim($target, '/\\');
        if (!is_dir($target)) {
            mkdir($target, 0775, true);
        }
        copy($dir . '/deck.json', $target . '/deck.json');
        $this->line('Player fixture: ' . $target . '/deck.json');
    }
}
