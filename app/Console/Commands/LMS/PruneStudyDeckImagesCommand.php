<?php

namespace App\Console\Commands\LMS;

use App\Services\StudyDeck\StudyDeckImages;
use Illuminate\Console\Command;

/**
 * Remove study-deck pictures that no stored deck uses and that are older than a cut-off.
 *
 * Generating a deck stores its pictures in the database straight away, before anyone has decided to publish it. A run
 * that is never stored leaves its pictures behind; this removes them. A picture a stored deck uses (it has a row in
 * study_deck_image_links) is never touched, however old.
 *
 *   php artisan study-deck:images-prune --dry-run
 *   php artisan study-deck:images-prune --days=14
 */
class PruneStudyDeckImagesCommand extends Command
{
    protected $signature = 'study-deck:images-prune {--days=14 : only pictures older than this many days} {--dry-run : report only}';

    protected $description = 'Delete study-deck pictures that no stored deck uses (leftovers of generation runs that were never published)';

    public function handle(StudyDeckImages $images): int
    {
        $days = max(0, (int) $this->option('days'));
        $orphans = $images->orphans($days);

        $this->line(sprintf('%d picture(s), %s, used by no stored deck and older than %d day(s).', $orphans['count'], $this->size($orphans['bytes']), $days));
        if ($orphans['count'] === 0 || $this->option('dry-run')) {
            return self::SUCCESS;
        }

        // deleteUnlinked re-checks the links as it deletes, so a deck stored a moment ago keeps its pictures.
        $deleted = $images->deleteUnlinked($orphans['ids']);
        $this->info("Deleted $deleted picture(s).");

        return self::SUCCESS;
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MiB' : number_format($bytes / 1024, 0) . ' KiB';
    }
}
