<?php

namespace App\Console\Commands\LMS;

use App\Services\ContentGenerationService;
use App\Services\StudyDeck\Documents\DocumentKind;
use App\Services\StudyDeck\StudyDeckImages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Take a stored study document back out: the content_master row, its PDF and its structured source.
 *
 * The undo for `lms:store-study-document`. It only ever touches a row whose file name AND category say it is a study
 * document (`study_<kind>_...pdf` filed under that kind's category), so it cannot be pointed at anyone else's content
 * or at a study deck (use lms:unstore-study-deck for that).
 *
 * Pictures. A document's diagrams are rows in study_deck_images, shared by checksum with other decks and documents.
 * Removing the document always removes its links to them; the pictures themselves are left alone unless `--images` is
 * given, and then only the ones nothing else uses.
 *
 * An earlier document of the same kind and scope that storing this one hid stays hidden: take this one out and the
 * chapter has none of that kind until the earlier one is stored again (its bundle, if kept, with
 * lms:store-study-document) or its show_hide is set back. The command lists those documents so that is a decision, not
 * a surprise.
 */
class UnstoreStudyDocumentCommand extends Command
{
    protected $signature = 'lms:unstore-study-document {content_id : content_master.id of a study document} {--images : also remove pictures nothing else uses} {--dry-run}';

    protected $description = 'Remove a stored study document (row, PDF, structured source) again';

    public function handle(StudyDeckImages $images): int
    {
        $row = DB::table('content_master')->where('id', (int) $this->argument('content_id'))
            ->first(['id', 'filename', 'chapter_id', 'sub_institute_id', 'title', 'content_category']);
        $kind = $row ? DocumentKind::fromFilename($row->filename) : null;
        if (!$row || $kind === null || DocumentKind::fromCategory($row->content_category) !== $kind) {
            $this->error('That is not a stored study document.');

            return self::FAILURE;
        }

        $paths = [
            ContentGenerationService::studyDocumentPdfPath($row->filename),
            ContentGenerationService::studyDocumentSidecarPath($row->filename),
            ContentGenerationService::studyDocumentPracticePdfPath($row->filename),
        ];

        $mine = $images->linkedImageIds((int) $row->id);
        $removable = $this->option('images') ? array_values(array_diff($mine, $images->usedElsewhere($mine, (int) $row->id))) : [];

        $this->line("Study document {$row->id} \"{$row->title}\" ({$kind->label()}, chapter {$row->chapter_id})");
        $verb = $this->option('dry-run') ? 'would remove ' : 'removing ';
        foreach ($paths as $path) {
            $this->line('  ' . $verb . $path);
        }
        $this->line('  ' . $verb . 'its record of using ' . count($mine) . ' picture(s)');
        foreach ($removable as $id) {
            $this->line('  ' . $verb . 'picture ' . StudyDeckImages::ref($id));
        }

        $hidden = DB::table('content_master')
            ->where('chapter_id', $row->chapter_id)
            ->where('sub_institute_id', $row->sub_institute_id)
            ->where('content_category', $kind->category())
            ->where('file_type', 'pdf')
            ->where('filename', 'like', $kind->filePrefix() . '%')
            ->where('id', '<>', $row->id)
            ->where('show_hide', 0)
            ->pluck('id');
        if ($hidden->isNotEmpty()) {
            $this->line('  note: ' . $hidden->count() . ' earlier ' . strtolower($kind->label()) . ' row(s) of this chapter stay hidden: ' . $hidden->implode(', '));
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
        $disk = Storage::disk('digitalocean');
        foreach ($paths as $path) {
            $disk->delete($path);
        }
        $this->info('Removed.');

        return self::SUCCESS;
    }
}
