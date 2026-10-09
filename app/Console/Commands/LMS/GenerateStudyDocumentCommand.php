<?php

namespace App\Console\Commands\LMS;

use App\Services\ContentGenerationService;
use App\Services\StudyDeck\ClaudeCliCompleter;
use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\Documents\DocumentKind;
use App\Services\StudyDeck\Documents\StudyDocumentService;
use App\Services\StudyDeck\StudyImageStores;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Write revision notes, a remedial class or classroom activities for one chapter, as a REVIEW BUNDLE.
 *
 * The sibling of lms:generate-study-deck, for the three documents the study-deck pipeline writes besides the lesson
 * deck. Like it, a run READS the chapter and writes files under storage/app/study-deck/chapter-<id>/<kind>/, plus the
 * diagrams it draws into the study_deck_images table (pictures are kept in the database, never as files; the ones of
 * a run that is never stored are removed by `study-deck:images-prune`). Nothing reaches content_master or the shared
 * object store unless --store is passed, and --store refuses a document that failed validation. --store hands the
 * bundle it just wrote to `lms:store-study-document`, so what is stored is exactly what was written here (and can be
 * reviewed first: run without --store, open the two PDFs, then store).
 *
 *   php artisan lms:generate-study-document 8592 revision_notes
 *   php artisan lms:generate-study-document 8592 remedial --concepts=101,102,103
 *   php artisan lms:generate-study-document 8592 activities --store
 */
class GenerateStudyDocumentCommand extends Command
{
    protected $signature = 'lms:generate-study-document
        {chapter : chapter_master.id}
        {kind : revision_notes | remedial | activities}
        {--tenant=1 : sub_institute_id}
        {--executor= : api | cli (cli is dev-only; default from config claude.executor)}
        {--out= : bundle directory (default storage/app/study-deck/chapter-<id>/<kind>)}
        {--concepts= : comma-separated concept ids to write about (default: every concept of the chapter)}
        {--per-concept=4 : most bank questions offered per concept}
        {--store : on a passing validation, store THIS bundle (see lms:store-study-document: writes to the DB and shared storage)}';

    protected $description = 'Write revision notes, a remedial class or classroom activities for a chapter from its LMS data';

    public function handle(ContentGenerationService $content): int
    {
        $kind = DocumentKind::fromValue((string) $this->argument('kind'));
        if ($kind === null) {
            $this->error('Unknown kind "' . $this->argument('kind') . '". Use revision_notes, remedial or activities.');

            return self::FAILURE;
        }

        $chapterId = (int) $this->argument('chapter');
        $tenant = (int) $this->option('tenant');
        $executor = (string) ($this->option('executor') ?: config('claude.executor', 'api'));
        $dir = rtrim((string) ($this->option('out') ?: storage_path('app/study-deck/chapter-' . $chapterId . '/' . $kind->value)), '/\\');
        $store = (bool) $this->option('store');
        $concepts = array_values(array_unique(array_filter(array_map('intval', array_map('trim', explode(',', (string) $this->option('concepts')))))));

        $this->line('Executor: <options=bold>' . $executor . '</>' . ($executor === 'cli' ? ' (dev-only, tools disabled)' : ''));
        $this->line($kind->label() . ' for chapter ' . $chapterId . ($concepts ? ' (' . count($concepts) . ' concept(s) chosen)' : ' (the whole chapter)'));

        try {
            $service = $this->service($this->completer($executor), $tenant, $chapterId);

            $result = $service->generate($kind, $chapterId, $tenant, [
                'concept_ids' => $concepts,
                'per_concept' => (int) $this->option('per-concept'),
            ], fn ($stage, $msg) => $this->line(sprintf('  <fg=cyan>%-8s</> %s', $stage, $msg)));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->writeBundle($dir, $kind, $result);
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

        // Both copies of the PDF, drawn from the structured source exactly as the stored one will be.
        try {
            foreach ($this->pdfs($content, $kind, $result['document'], $tenant, $dir) as $line) {
                $this->line('  ' . $line);
            }
        } catch (\Throwable $e) {
            $this->error('The PDF could not be drawn: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Validation passed.');

        if (!$store) {
            $this->line('Review the PDFs in ' . $dir . '/out; then run: php artisan lms:store-study-document ' . $chapterId . ' ' . $kind->value . ' --dry-run   (and without --dry-run to store it)');

            return self::SUCCESS;
        }

        return $this->call('lms:store-study-document', ['chapter' => $chapterId, 'kind' => $kind->value, '--bundle' => $dir, '--tenant' => $tenant]);
    }

    /** The pipeline for one school and chapter. Pictures go straight into the database and the bundle carries only their references. */
    protected function service(Completer $completer, int $tenant, int $chapterId): StudyDocumentService
    {
        return StudyDocumentService::make($completer, StudyImageStores::database($tenant, $chapterId));
    }

    /** The model to ask. The service's own key resolution and streaming call, reached through a subclass so this command carries no Anthropic code of its own. */
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

    /**
     * Draw the stored copy and the other copy into the bundle's out folder.
     *
     * @param array<string,mixed> $document
     * @return array<int,string> one line per file written
     */
    private function pdfs(ContentGenerationService $content, DocumentKind $kind, array $document, int $tenant, string $dir): array
    {
        $lines = [];
        foreach ($kind->variantLabels() as $variant => $label) {
            $bytes = $content->studyDocumentPdfBytes($document, ['variant' => $variant, 'tenant' => $tenant]);
            if (!str_starts_with($bytes, '%PDF-')) {
                throw new \RuntimeException('the "' . $label . '" copy came out empty');
            }
            $file = $dir . '/out/' . $kind->value . '-' . Str::slug($label) . '.pdf';
            file_put_contents($file, $bytes);
            $lines[] = sprintf('%s (%s, %s)', $file, $label, number_format(strlen($bytes) / 1024, 0) . ' KiB');
        }

        return $lines;
    }

    /** @param array<string,mixed> $r */
    private function writeBundle(string $dir, DocumentKind $kind, array $r): void
    {
        foreach ([$dir, $dir . '/out'] as $d) {
            if (!is_dir($d)) {
                mkdir($d, 0775, true);
            }
        }
        $json = fn ($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        file_put_contents($dir . '/concepts.json', $json(array_values(array_map(fn ($c) => $c['name'], $r['context']['concepts']))));
        file_put_contents($dir . '/learning-map.json', $json($r['map']));
        file_put_contents($dir . '/draft.json', $json($r['draft']));
        file_put_contents($dir . '/document.json', $json($r['document']));
        file_put_contents($dir . '/questions-excluded.json', $json($r['selection']['excluded']));
        file_put_contents($dir . '/report.json', $json($r['report']));
        file_put_contents($dir . '/out/' . $kind->value . '.html', $r['html']);
    }
}
