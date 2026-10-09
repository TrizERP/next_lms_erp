<?php

namespace App\Console\Commands\LMS;

use App\Services\StudyDeck\StudyDeckImageMigrator;
use Illuminate\Console\Command;

/**
 * Move study-deck picture files into the database, then (only when asked) delete the files.
 *
 *   php artisan study-deck:images-migrate --dry-run                 what would be stored; changes nothing
 *   php artisan study-deck:images-migrate                           store them and rewrite the deck files to references
 *   php artisan study-deck:images-migrate --dry-run --cleanup       what would be deleted afterwards
 *   php artisan study-deck:images-migrate --cleanup                 delete the verified files and the folders that empty
 *
 * Safe to run again: pictures already stored are found by their bytes, nothing is duplicated, and a deck file that
 * already holds references is not changed. A file is deleted only after its database copy has been read back and
 * matched; a picture no deck names is reported and kept unless --include-unreferenced stores (and so verifies) it.
 * See StudyDeckImageMigrator for the exact rules.
 */
class MigrateStudyDeckImagesCommand extends Command
{
    protected $signature = 'study-deck:images-migrate
        {--path=* : a folder holding chapter-<id>/ folders (repeatable). Default: this app\'s storage/app/study-deck, and the student app\'s public/study-deck (config claude.study_deck_player_dir, else the lms_k12 folder beside this app)}
        {--chapter= : only this chapter id}
        {--tenant= : the school (sub_institute_id) the pictures belong to; default: the chapter\'s own school}
        {--include-unreferenced : also store picture files that no deck names (otherwise they are only reported)}
        {--cleanup : delete the picture files whose database copy has been verified, and the folders that become empty}
        {--dry-run : report what would happen and change nothing}';

    protected $description = 'Move study-deck picture files into the database (idempotent), then optionally delete the verified files';

    public function handle(StudyDeckImageMigrator $migrator): int
    {
        $roots = array_values(array_filter((array) $this->option('path')));
        if (!$roots) {
            $roots = array_values(array_filter([
                storage_path('app/study-deck'),
                (string) config('claude.study_deck_player_dir') ?: dirname(base_path()) . '/lms_k12/public/study-deck',
            ], 'is_dir'));
        }
        if (!$roots) {
            $this->warn('No study-deck folders found. Pass --path=<folder holding chapter-<id>/ folders>.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $this->line(($dry ? '<options=bold>DRY RUN</> - nothing will be changed. ' : '') . 'Looking in:');
        foreach ($roots as $root) {
            $this->line('  ' . $root);
        }

        try {
            $stats = $migrator->run($roots, [
                'chapter' => $this->option('chapter') !== null ? (int) $this->option('chapter') : null,
                'tenant' => $this->option('tenant') !== null ? (int) $this->option('tenant') : null,
                'include_unreferenced' => (bool) $this->option('include-unreferenced'),
                'cleanup' => (bool) $this->option('cleanup'),
                'dry_run' => $dry,
            ], function (string $level, string $message): void {
                match ($level) {
                    'ok' => $this->line('<fg=green>' . $message . '</>'),
                    'warn' => $this->line('<fg=yellow>' . $message . '</>'),
                    'fail' => $this->line('<fg=red>' . $message . '</>'),
                    default => $this->line($message),
                };
            });
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $rows = [
            ['folders looked at', $stats['folders']],
            [$dry ? 'would be stored' : 'stored', $dry ? $stats['would_migrate'] : $stats['migrated']],
            ['already stored (skipped)', $stats['already_stored']],
            ['not named by any deck (kept)', $stats['unreferenced']],
            ['deck files rewritten', $stats['rewritten']],
            [$dry ? 'would be deleted' : 'files deleted', $dry ? $stats['would_delete'] : $stats['deleted']],
            ['empty folders removed', $stats['removed_dirs']],
            ['failed', $stats['failed']],
        ];
        $this->table(['', 'count'], $rows);

        if ($stats['unreferenced_files']) {
            $this->warn(count($stats['unreferenced_files']) . ' picture file(s) are named by no deck, so they have no database copy and were kept. '
                . 'Delete them yourself if they are leftovers, or run with --include-unreferenced to store (and then clean up) them too.');
        }
        foreach ($stats['failures'] as $failure) {
            $this->error($failure);
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
