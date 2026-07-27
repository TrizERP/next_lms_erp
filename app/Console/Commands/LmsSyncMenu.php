<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Adds the migrated LMS screens to the sidebar (tblmenumaster) and grants
 * view-rights (tblgroupwise_rights) so they appear for the target institute.
 *
 * Idempotent: re-running only inserts what is missing (matched by parent + link
 * for items, parent + name for the group, and menu_id+profile_id+institute for
 * rights). Nothing existing is moved, renamed, re-linked or deleted.
 *
 *   php artisan lms:sync-menu --institute=1 --dry-run   (read-only preview)
 *   php artisan lms:sync-menu --institute=1             (live write)
 *
 * Profiles are "mirrored" from whoever already has rights on the LMS root (230)
 * for the institute, so we grant to exactly the roles that already see LMS.
 */
class LmsSyncMenu extends Command
{
    protected $signature = 'lms:sync-menu {--institute=1 : sub_institute_id to target} {--dry-run : preview only, no writes}';

    protected $description = 'Add migrated LMS screens to the sidebar (tblmenumaster) + grant view rights, idempotently.';

    public function handle(): int
    {
        $institute = (int) $this->option('institute');
        $dry = (bool) $this->option('dry-run');
        $mode = $dry ? 'DRY-RUN (no writes)' : 'LIVE WRITE';
        $this->info("LMS menu sync — institute {$institute} — {$mode}");

        // --- mirror the profiles that already see LMS at this institute --------
        $profiles = DB::table('tblgroupwise_rights')
            ->where('menu_id', 230)->where('sub_institute_id', $institute)
            ->distinct()->pluck('profile_id')->all();
        if (empty($profiles)) {
            $profiles = DB::table('tblgroupwise_rights')
                ->where('menu_id', 277)->where('sub_institute_id', $institute)
                ->distinct()->pluck('profile_id')->all();
        }
        if (empty($profiles)) {
            $this->error("No profiles have LMS rights at institute {$institute} to mirror. Aborting so we don't over-grant.");
            return self::FAILURE;
        }
        $this->line('Mirror profiles (get can_view): ' . implode(', ', $profiles));

        // LMS L1 row (parent of the Homework group we may create).
        $lms = DB::table('tblmenumaster')->where('id', 230)->first();
        if (!$lms) {
            $this->error('LMS root menu (id 230) not found. Aborting.');
            return self::FAILURE;
        }

        $menuInserts = 0;
        $rightInserts = 0;

        // --- ensure the new "Homework" L2 group under LMS ---------------------
        $homework = DB::table('tblmenumaster')
            ->where('parent_menu_id', 230)->where('name', 'Homework')->where('level', 2)->first();
        $homeworkId = $homework->id ?? null;
        if (!$homeworkId) {
            $this->line('  + L2 group "Homework" under LMS (sort 8)' . ($dry ? '  [would insert]' : ''));
            if (!$dry) {
                $homeworkId = DB::table('tblmenumaster')->insertGetId([
                    'name' => 'Homework', 'menu_title' => 'LMS', 'description' => 'Homework',
                    'parent_menu_id' => 230, 'level' => 2, 'status' => 1, 'sort_order' => 8,
                    'link' => 'javascript:void(0);', 'icon' => 'mdi mdi-notebook-edit-outline',
                    'sub_institute_id' => (string) $institute, 'client_id' => $lms->client_id,
                    'menu_type' => null, 'created_at' => now(),
                ]);
            }
            $menuInserts++;
        } else {
            $this->line("  = L2 group \"Homework\" exists (id {$homeworkId})");
        }

        // --- the migrated screens missing from the LMS sidebar ----------------
        // [parent group id, label, link (direct Next path), sort_order, icon, menu_type]
        $HW = $homeworkId ?? 'Homework(new)';
        // NOTE: AI Question Paper is intentionally NOT a menu item — it lives as a
        // button next to "Create exam" on the exam hub (/student_homework).
        $items = [
            [276, 'Online Exam',                  '/exam/online',                  11, 'mdi mdi-monitor-dashboard',          'ENTRY'],
            [276, 'Exam-wise Progress Report',    '/exam/progress-report',         12, 'mdi mdi-chart-line',                 'REPORT'],
            [276, 'Question Wise Report',         '/lms/question-wise-report',     13, 'mdi mdi-format-list-checks',         'REPORT'],
            [$HW, 'Exam',             '/lms/homework',                  1, 'mdi mdi-notebook-outline',           'ENTRY'],
            [$HW, 'Homework Submission',          '/lms/homework/submission',       2, 'mdi mdi-file-upload-outline',        'ENTRY'],
            [$HW, 'Student Homework Report',      '/lms/homework/report',           3, 'mdi mdi-chart-box-outline',          'REPORT'],
            [$HW, 'Homework Submission Report',   '/lms/homework/submission-report',4, 'mdi mdi-clipboard-text-outline',     'REPORT'],
            [327, 'Teacher Diary',                '/lms/teacher-diary',             5, 'mdi mdi-book-open-variant',          'REPORT'],
            [327, 'Book List',                    '/lms/book-list',                 6, 'mdi mdi-bookshelf',                  'ENTRY'],
            [327, 'Syllabus Plan',                '/lms/syllabus-plan',             7, 'mdi mdi-notebook-edit-outline',      'ENTRY'],
            [302, 'Student Analysis Report',      '/lms/student-analysis',          2, 'mdi mdi-account-search',             'REPORT'],
            [302, 'LMS Dashboard',                '/lms/dashboard',                 3, 'mdi mdi-view-dashboard-outline',     'REPORT'],
        ];

        // menu_id set to grant rights on: parent groups + Homework group + each item.
        // 242 = the existing "Exam" item (now opens the /student_homework hub).
        $grantMenuIds = [230, 276, 302, 327, 242];
        if ($homeworkId) $grantMenuIds[] = $homeworkId;

        foreach ($items as [$parentId, $label, $link, $sort, $icon, $type]) {
            $parentIsNew = !is_int($parentId);           // Homework group not yet created (dry-run)
            $existing = null;
            if (!$parentIsNew) {
                $existing = DB::table('tblmenumaster')
                    ->where('parent_menu_id', $parentId)->where('link', $link)->first();
            }
            if ($existing) {
                $this->line("  = \"{$label}\" exists (id {$existing->id}) under {$parentId}");
                $grantMenuIds[] = $existing->id;
                // ensure this institute is in its CSV so it shows here too
                if (!$dry && !$this->csvContains($existing->sub_institute_id, $institute)) {
                    DB::table('tblmenumaster')->where('id', $existing->id)
                        ->update(['sub_institute_id' => rtrim($existing->sub_institute_id, ',') . ',' . $institute]);
                }
                continue;
            }
            $this->line("  + L3 \"{$label}\" → {$link} under " . ($parentIsNew ? $parentId : "group {$parentId}") . ($dry ? '  [would insert]' : ''));
            if (!$dry) {
                $newId = DB::table('tblmenumaster')->insertGetId([
                    'name' => $label, 'menu_title' => 'LMS', 'description' => $label,
                    'parent_menu_id' => $parentId, 'level' => 3, 'status' => 1, 'sort_order' => $sort,
                    'link' => $link, 'icon' => $icon,
                    'sub_institute_id' => (string) $institute, 'client_id' => $lms->client_id,
                    'menu_type' => $type, 'created_at' => now(),
                ]);
                $grantMenuIds[] = $newId;
            }
            $menuInserts++;
        }

        // --- grant view-rights (idempotent) -----------------------------------
        $grantMenuIds = array_values(array_unique($grantMenuIds));
        foreach ($grantMenuIds as $mid) {
            foreach ($profiles as $pid) {
                $has = DB::table('tblgroupwise_rights')
                    ->where('menu_id', $mid)->where('profile_id', $pid)
                    ->where('sub_institute_id', $institute)->exists();
                if ($has) continue;
                $this->line("  + right: menu {$mid} → profile {$pid} @ inst {$institute}" . ($dry ? '  [would insert]' : ''));
                if (!$dry) {
                    DB::table('tblgroupwise_rights')->insert([
                        'menu_id' => $mid, 'profile_id' => $pid,
                        'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1,
                        'sub_institute_id' => $institute, 'created_at' => now(),
                    ]);
                }
                $rightInserts++;
            }
        }

        $this->info(($dry ? 'WOULD insert' : 'Inserted') . ": {$menuInserts} menu row(s), {$rightInserts} rights row(s).");
        if ($dry) $this->comment('Dry-run only — nothing was written. Re-run without --dry-run to apply.');
        return self::SUCCESS;
    }

    private function csvContains(?string $csv, int $id): bool
    {
        if (!$csv) return false;
        return in_array((string) $id, array_map('trim', explode(',', $csv)), true);
    }
}
