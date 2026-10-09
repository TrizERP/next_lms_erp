<?php

namespace App\Console\Commands\LMS;

use App\Services\ContentGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Take a stored study deck back out: the content_master row, its presentation and its deck file.
 *
 * The undo for `lms:store-study-deck`. It only ever touches a row whose file name says it is a study deck
 * (`study_deck_*`), so it cannot be pointed at anyone else's content. The pictures are shared by hash with other
 * decks, so they are left alone unless `--images` is given, and then only the ones no other study deck uses.
 */
class UnstoreStudyDeckCommand extends Command
{
    protected $signature = 'lms:unstore-study-deck {content_id : content_master.id of a study deck} {--images : also remove pictures no other study deck uses} {--dry-run}';

    protected $description = 'Remove a stored study deck (row, presentation, deck file) again';

    public function handle(): int
    {
        $row = DB::table('content_master')->where('id', (int) $this->argument('content_id'))->first(['id', 'filename', 'chapter_id', 'title']);
        if (!$row || !str_starts_with((string) $row->filename, 'study_deck_')) {
            $this->error('That is not a stored study deck.');

            return self::FAILURE;
        }

        $disk = Storage::disk('digitalocean');
        $sidecar = ContentGenerationService::studyDeckSidecarPath($row->filename);
        $deck = $disk->exists($sidecar) ? json_decode((string) $disk->get($sidecar), true) : null;
        $mine = array_keys((array) ($deck['assets'] ?? []));
        $paths = ['public/lms_content_file/' . $row->filename, $sidecar, ContentGenerationService::studyDeckPdfPath($row->filename)];

        $images = [];
        if ($this->option('images') && $mine) {
            $others = DB::table('content_master')->where('filename', 'like', 'study\\_deck\\_%')->where('id', '<>', $row->id)->pluck('filename');
            $used = [];
            foreach ($others as $name) {
                $other = $disk->exists(ContentGenerationService::studyDeckSidecarPath($name)) ? json_decode((string) $disk->get(ContentGenerationService::studyDeckSidecarPath($name)), true) : null;
                foreach ((array) ($other['assets'] ?? []) as $key => $_) {
                    $used[$key] = true;
                }
            }
            foreach ((array) ($deck['assets'] ?? []) as $key => $asset) {
                if (!isset($used[$key])) {
                    $images[] = $asset['path'];
                }
            }
        }

        $this->line("Study deck {$row->id} \"{$row->title}\" (chapter {$row->chapter_id})");
        foreach (array_merge($paths, $images) as $path) {
            $this->line('  ' . ($this->option('dry-run') ? 'would remove ' : 'removing ') . $path);
        }
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(fn () => DB::table('content_master')->where('id', $row->id)->delete());
        foreach (array_merge($paths, $images) as $path) {
            $disk->delete($path);
        }
        $this->info('Removed.');

        return self::SUCCESS;
    }
}
