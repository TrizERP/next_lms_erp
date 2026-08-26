<?php

namespace App\Console\Commands\LMS;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Narrows the student side of the LMS Assignment module to view + submit.
 *
 * The module is three menu rows under LMS > Test:
 *
 *   312  Assignment             teacher   create / assign to students
 *   313  Assignment Submission  student   view own assignments + upload a file
 *   314  Annotate Assignment    teacher   review, remark, grade
 *
 * Only 313 belongs to a student. 314 already carries no student rights row
 * anywhere, but 312 does at every institute, which is why the teacher create
 * screen shows up in the student sidebar today.
 *
 * DELETING the rights row is what hides a menu, not zeroing can_view.
 * App\Http\Controllers\api\MenuRightsController builds the sidebar by joining
 * tblmenumaster against tblgroupwise_rights / tblindividual_rights on row
 * EXISTENCE ("i.menu_id = m.id OR g.menu_id = m.id") and never looks at
 * can_view; checkPermission is what reads can_view, and it only runs once the
 * user is already on the page. So a can_view=0 row would leave the entry
 * visible and turn clicking it into an authorization error, which is not the
 * ask.
 *
 * On 313 the row is kept and normalised instead: can_view + can_add (the
 * upload IS an add), with can_edit and can_delete cleared so a student cannot
 * revise or withdraw a submission once it is in.
 *
 * Every row this touches is written to a JSON backup first, and --restore puts
 * them back exactly as they were.
 *
 *   php artisan lms:restrict-student-assignments --dry-run
 *   php artisan lms:restrict-student-assignments --institute=1
 *   php artisan lms:restrict-student-assignments
 *   php artisan lms:restrict-student-assignments --restore=storage/app/....json
 */
class RestrictStudentAssignmentsCommand extends Command
{
    protected $signature = 'lms:restrict-student-assignments
        {--institute= : limit to one sub_institute_id (default: every institute)}
        {--dry-run : report what would change, write nothing}
        {--backup= : path for the restore file (default: storage/app/lms-assignment-rights-<scope>-<timestamp>.json)}
        {--restore= : path of a backup file to roll back}';

    protected $description = 'Restrict students to viewing and submitting assignments; keep every other assignment screen teacher-side';

    /** Teacher-only screens: a student must hold no rights row at all. */
    private const TEACHER_MENU_IDS = [312, 314];

    /** The one student screen: kept, but trimmed to view + submit. */
    private const SUBMISSION_MENU_ID = 313;

    private const RIGHTS_TABLES = ['tblgroupwise_rights', 'tblindividual_rights'];

    public function handle(): int
    {
        if ($restore = $this->option('restore')) {
            return $this->restore((string) $restore);
        }

        $institute = $this->option('institute');
        $dry = (bool) $this->option('dry-run');

        $this->info('LMS Assignment - student-side restriction');
        $this->line(($institute ? "institute {$institute}" : 'all institutes')
            . '  ·  ' . ($dry ? 'DRY-RUN (no writes)' : 'LIVE WRITE'));
        $this->line(str_repeat('-', 78));

        $studentProfileIds = DB::table('tbluserprofilemaster')
            ->whereRaw('LOWER(TRIM(name)) = ?', ['student'])
            ->pluck('id')->all();

        if ($studentProfileIds === []) {
            $this->error('No profile named "Student" found. Aborting rather than guessing.');

            return self::FAILURE;
        }
        $this->line(count($studentProfileIds) . ' student profile(s) in scope');

        // Collect everything first, so the backup is complete before any write.
        $toDelete = [];
        foreach (self::RIGHTS_TABLES as $table) {
            foreach ($this->rowsFor($table, self::TEACHER_MENU_IDS, $studentProfileIds, $institute) as $row) {
                $toDelete[] = ['table' => $table, 'row' => (array) $row];
            }
        }

        $toTrim = [];
        foreach (self::RIGHTS_TABLES as $table) {
            foreach ($this->rowsFor($table, [self::SUBMISSION_MENU_ID], $studentProfileIds, $institute) as $row) {
                $row = (array) $row;
                // Already view+submit only? Then there is nothing to write.
                if ((int) ($row['can_view'] ?? 0) === 1 && (int) ($row['can_add'] ?? 0) === 1
                    && (int) ($row['can_edit'] ?? 0) === 0 && (int) ($row['can_delete'] ?? 0) === 0) {
                    continue;
                }
                $toTrim[] = ['table' => $table, 'row' => $row];
            }
        }

        $this->line('  - ' . count($toDelete) . ' student rights row(s) on the teacher screens (312 Assignment, 314 Annotate) to remove');
        $this->line('  ~ ' . count($toTrim) . ' student rights row(s) on 313 Assignment Submission to trim to view + submit');

        if ($toDelete === [] && $toTrim === []) {
            $this->info('Nothing to do - the student side is already restricted.');

            return self::SUCCESS;
        }

        $this->table(
            ['action', 'table', 'menu', 'profile', 'institute', 'view/add/edit/delete'],
            array_merge(
                array_map(fn ($entry) => $this->summarise('remove', $entry), array_slice($toDelete, 0, 10)),
                array_map(fn ($entry) => $this->summarise('trim', $entry), array_slice($toTrim, 0, 10))
            )
        );
        if (count($toDelete) + count($toTrim) > 20) {
            $this->line('  (first 20 of ' . (count($toDelete) + count($toTrim)) . ' shown)');
        }

        if ($dry) {
            $this->comment('Dry run - nothing written. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        $backupPath = $this->writeBackup($toDelete, $toTrim, $institute);
        $this->line("Backup written: {$backupPath}");
        $this->line('Roll back with: php artisan lms:restrict-student-assignments --restore=' . $backupPath);

        DB::transaction(function () use ($toDelete, $toTrim) {
            foreach ($toDelete as $entry) {
                DB::table($entry['table'])->where('id', $entry['row']['id'])->delete();
            }
            foreach ($toTrim as $entry) {
                DB::table($entry['table'])->where('id', $entry['row']['id'])->update([
                    'can_view' => 1,
                    'can_add' => 1,
                    'can_edit' => 0,
                    'can_delete' => 0,
                ]);
            }
        });

        $this->info('Done. Students now see only "Assignment Submission", with view + submit rights.');

        return self::SUCCESS;
    }

    private function rowsFor(string $table, array $menuIds, array $profileIds, $institute): array
    {
        return DB::table($table)
            ->whereIn('menu_id', $menuIds)
            ->whereIn('profile_id', $profileIds)
            ->when($institute, fn ($query) => $query->where('sub_institute_id', $institute))
            ->orderBy('id')
            ->get()->all();
    }

    private function summarise(string $action, array $entry): array
    {
        $row = $entry['row'];

        return [
            $action,
            $entry['table'],
            $row['menu_id'] ?? '',
            $row['profile_id'] ?? '',
            $row['sub_institute_id'] ?? '',
            implode('/', [
                (int) ($row['can_view'] ?? 0),
                (int) ($row['can_add'] ?? 0),
                (int) ($row['can_edit'] ?? 0),
                (int) ($row['can_delete'] ?? 0),
            ]),
        ];
    }

    private function writeBackup(array $toDelete, array $toTrim, $institute): string
    {
        $path = (string) ($this->option('backup') ?: storage_path(
            'app/lms-assignment-rights-' . ($institute ?: 'all') . '-' . date('Ymd-His') . '.json'
        ));

        file_put_contents($path, json_encode([
            'taken_at' => date('c'),
            'institute' => $institute ?: 'all',
            'deleted' => $toDelete,
            'trimmed' => $toTrim,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function restore(string $path): int
    {
        if (! is_file($path)) {
            $this->error("Backup file not found: {$path}");

            return self::FAILURE;
        }

        $backup = json_decode((string) file_get_contents($path), true);
        if (! is_array($backup) || ! isset($backup['deleted'], $backup['trimmed'])) {
            $this->error('That file is not a backup written by this command.');

            return self::FAILURE;
        }

        $this->info('Restoring assignment rights from ' . $backup['taken_at']
            . ' (institute: ' . $backup['institute'] . ')');

        DB::transaction(function () use ($backup) {
            foreach ($backup['deleted'] as $entry) {
                // insertOrIgnore keeps a re-run idempotent if the row was
                // re-created by hand in the meantime.
                DB::table($entry['table'])->insertOrIgnore($entry['row']);
            }
            foreach ($backup['trimmed'] as $entry) {
                DB::table($entry['table'])->where('id', $entry['row']['id'])->update([
                    'can_view' => $entry['row']['can_view'],
                    'can_add' => $entry['row']['can_add'],
                    'can_edit' => $entry['row']['can_edit'],
                    'can_delete' => $entry['row']['can_delete'],
                ]);
            }
        });

        $this->info('Restored ' . count($backup['deleted']) . ' removed row(s) and '
            . count($backup['trimmed']) . ' trimmed row(s).');

        return self::SUCCESS;
    }
}
