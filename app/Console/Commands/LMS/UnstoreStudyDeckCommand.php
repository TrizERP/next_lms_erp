<?php

namespace App\Console\Commands\LMS;

use App\Services\ContentGenerationService;
use App\Services\StudyDeck\StudyDeckImages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Take a stored study deck back out: the content_master row, its presentation and its deck file.
 *
 * The undo for `lms:store-study-deck`. It only ever touches a row whose file name says it is a study deck
 * (`study_deck_*`), so it cannot be pointed at anyone else's content.
 *
 * Pictures. A deck's pictures are rows in study_deck_images, shared by checksum with other decks. Removing the deck
 * always removes its links to them; the pictures themselves are left alone unless `--images` is given, and then only
 * the ones no other study deck uses. (A deck stored before pictures moved to the database lists object-store files in
 * its `assets`; `--images` removes the ones no other study deck lists, as it always did.)
 */
class UnstoreStudyDeckCommand extends Command
{
    protected $signature = 'lms:unstore-study-deck {content_id : content_master.id of a study deck} {--images : also remove pictures no other study deck uses} {--dry-run}';

    protected $description = 'Remove a stored study deck (row, presentation, deck file) again';

    public function handle(StudyDeckImages $images): int
    {
        $row = DB::table('content_master')->where('id', (int) $this->argument('content_id'))->first(['id', 'filename', 'chapter_id', 'title']);
        if (!$row || !str_starts_with((string) $row->filename, 'study_deck_')) {
            $this->error('That is not a stored study deck.');

            return self::FAILURE;
        }

        $disk = Storage::disk('digitalocean');
        $sidecar = ContentGenerationService::studyDeckSidecarPath($row->filename);
        $deck = $disk->exists($sidecar) ? json_decode((string) $disk->get($sidecar), true) : null;
        $paths = ['public/lms_content_file/' . $row->filename, $sidecar, ContentGenerationService::studyDeckPdfPath($row->filename)];

        // Pictures in the database: the ones the deck's links and its own asset list name.
        $assets = (array) ($deck['assets'] ?? []);
        $mine = array_values(array_unique(array_merge(
            $images->linkedImageIds((int) $row->id),
            array_values(array_filter(array_map(fn ($a) => (int) ($a['image_id'] ?? 0), $assets)))
        )));
        $removable = $this->option('images') ? array_values(array_diff($mine, $images->usedElsewhere($mine, (int) $row->id))) : [];

        // Pictures in the object store (a deck stored before pictures moved to the database).
        $legacy = [];
        $legacyMine = array_filter($assets, fn ($a) => isset($a['path']) && !isset($a['image_id']));
        if ($this->option('images') && $legacyMine) {
            $used = [];
            foreach (DB::table('content_master')->where('filename', 'like', 'study\\_deck\\_%')->where('id', '<>', $row->id)->pluck('filename') as $name) {
                $other = $disk->exists(ContentGenerationService::studyDeckSidecarPath($name)) ? json_decode((string) $disk->get(ContentGenerationService::studyDeckSidecarPath($name)), true) : null;
                foreach ((array) ($other['assets'] ?? []) as $key => $_) {
                    $used[$key] = true;
                }
            }
            foreach ($legacyMine as $key => $asset) {
                if (!isset($used[$key])) {
                    $legacy[] = $asset['path'];
                }
            }
        }

        $this->line("Study deck {$row->id} \"{$row->title}\" (chapter {$row->chapter_id})");
        $verb = $this->option('dry-run') ? 'would remove ' : 'removing ';
        foreach (array_merge($paths, $legacy) as $path) {
            $this->line('  ' . $verb . $path);
        }
        $this->line('  ' . $verb . 'its record of using ' . count($mine) . ' picture(s)');
        foreach ($removable as $id) {
            $this->line('  ' . $verb . 'picture ' . StudyDeckImages::ref($id));
        }
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        // The row and its links go together; a picture is only deleted when nothing links to it any more.
        DB::transaction(function () use ($row, $images, $removable) {
            DB::table('content_master')->where('id', $row->id)->delete();
            $images->unlink((int) $row->id);
            if ($removable) {
                $images->deleteUnlinked($removable);
            }
        });
        foreach (array_merge($paths, $legacy) as $path) {
            $disk->delete($path);
        }
        $this->info('Removed.');

        return self::SUCCESS;
    }
}
