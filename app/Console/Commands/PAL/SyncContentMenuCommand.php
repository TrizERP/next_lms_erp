<?php

namespace App\Console\Commands\PAL;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Places the PAL V4 Content Intelligence screens in the LMS sidebar.
 *
 * `tblmenumaster` is three levels deep and nothing goes deeper, so there is no
 * fourth level to nest a sub-module into. Two placements are therefore possible,
 * and `--placement` chooses between them.
 *
 * --placement=test (DEFAULT) — inside the existing Test module, as siblings of
 * Exam and Assignment. At this depth a "module" simply IS a run of adjacent L3
 * rows, which is exactly how Assignment / Assignment Submission / Annotate
 * Assignment already read:
 *
 *     LMS (230, L1)
 *       └── Test (276, L2)
 *             ├── Exam                      (242)
 *             ├── Assignment                (312)
 *             ├── PAL                       (426)
 *             ├── PAL Content Intelligence  <- these three
 *             ├── PAL Review Queue          <-
 *             └── PAL Misconception Library <-
 *
 * --placement=group — a separate PAL group directly below Homework:
 *
 *     LMS (230, L1)
 *       ├── Homework (505, L2, sort 8)
 *       └── PAL      (L2, sort 9)
 *             ├── PAL Content Intelligence
 *             ├── PAL Review Queue
 *             └── PAL Misconception Library
 *
 * `link` must equal the ROUTE NAME, not a URL: Laravel's checkPermission looks
 * the menu row up by `Route::currentRouteName()`, and the Next.js frontend's
 * routeMapper keys off the same value. Get it wrong and the screen loads with
 * no permission record behind it.
 *
 * Idempotent, and safe to run repeatedly to switch placement: rows are matched
 * on `link` alone, so an existing screen is RELOCATED rather than duplicated.
 * It only ever moves rows it created; the legacy PAL entry (426) is left alone
 * unless --move-legacy-pal is passed.
 *
 *   php artisan pal:sync-content-menu --institute=1 --dry-run
 *   php artisan pal:sync-content-menu --institute=1
 *   php artisan pal:sync-content-menu --institute=1 --placement=group
 */
class SyncContentMenuCommand extends Command
{
    protected $signature = 'pal:sync-content-menu
        {--institute=1 : sub_institute_id to target}
        {--placement=test : test = inside the existing Test module (default); group = a separate PAL group below Homework}
        {--group-name=PAL : name of the L2 group used by placement=group}
        {--create-group= : create an EMPTY L2 module with this name below Homework, then stop}
        {--move-legacy-pal : with placement=group, also move the existing PAL entry (426) into it}
        {--prune-empty-group : delete the PAL group if placement=test leaves it with no children}
        {--dry-run : preview only, no writes}';

    protected $description = 'PAL V4: place the Content Intelligence screens in the LMS sidebar and grant rights';

    /** LMS root — the L1 the frontend renders as "LMS + PAL". */
    private const LMS_MENU_ID = 230;

    /** Homework L2 group; the PAL group is placed immediately after it. */
    private const HOMEWORK_MENU_ID = 505;

    /** LMS > Test group — where an earlier run of this command put the screens. */
    private const TEST_MENU_ID = 276;

    /** The existing PAL entry, used to mirror rights. */
    private const PAL_MENU_ID = 426;

    public function handle(): int
    {
        $institute = (int) $this->option('institute');
        $dry = (bool) $this->option('dry-run');

        $this->info('PAL Content Intelligence — menu sync');
        $this->line("institute {$institute}  ·  " . ($dry ? 'DRY-RUN (no writes)' : 'LIVE WRITE'));
        $this->line(str_repeat('─', 78));

        $lms = DB::table('tblmenumaster')->where('id', self::LMS_MENU_ID)->first();
        if (! $lms) {
            $this->error('LMS root menu (id ' . self::LMS_MENU_ID . ') not found. Aborting.');

            return self::FAILURE;
        }

        // ── --create-group: make an empty L2 module and stop ────────────────
        // Separate from --placement because it deliberately moves nothing: it
        // just adds the section, to be populated later.
        $createGroup = $this->option('create-group');
        if ($createGroup !== null && $createGroup !== '') {
            return $this->createEmptyGroup((string) $createGroup, $institute, $lms, $dry);
        }

        $placement = (string) $this->option('placement');
        if (! in_array($placement, ['test', 'group'], true)) {
            $this->error("--placement must be 'test' or 'group'.");

            return self::FAILURE;
        }

        // ── decide the parent ───────────────────────────────────────────────
        if ($placement === 'test') {
            // The screens live inside the existing Test module, as siblings of
            // Exam and Assignment. A "module" at this depth IS a run of adjacent
            // L3 rows — tblmenumaster has no fourth level to nest into.
            $parent = DB::table('tblmenumaster')->where('id', self::TEST_MENU_ID)->first();
            if (! $parent) {
                $this->error('LMS > Test menu (id ' . self::TEST_MENU_ID . ') not found. Aborting.');

                return self::FAILURE;
            }
            $groupId = self::TEST_MENU_ID;
            $this->line("Placement: inside the existing \"{$parent->name}\" module (id {$groupId})");
        } else {
            // Separate PAL group, immediately after Homework. Homework's sort is
            // read rather than hardcoded so the ordering survives a re-sort.
            $homeworkSort = (int) (DB::table('tblmenumaster')
                ->where('id', self::HOMEWORK_MENU_ID)->value('sort_order') ?? 8);
            $palSort = $homeworkSort + 1;

            $groupName = trim((string) $this->option('group-name')) ?: 'PAL';
            $this->line("Placement: \"{$groupName}\" group under \"{$lms->name}\", sort {$palSort} (below Homework)");

            $group = DB::table('tblmenumaster')
                ->where('parent_menu_id', self::LMS_MENU_ID)
                ->where('level', 2)
                ->where('name', $groupName)
                ->first();

            $groupId = $group->id ?? null;

            if ($groupId) {
                $this->line("  = L2 group \"{$groupName}\" exists (id {$groupId})");
                if (! $dry && ! $this->csvContains($group->sub_institute_id, $institute)) {
                    DB::table('tblmenumaster')->where('id', $groupId)->update([
                        'sub_institute_id' => rtrim((string) $group->sub_institute_id, ',') . ',' . $institute,
                    ]);
                    $this->line("    + added institute {$institute} to its visibility list");
                }
            } else {
                $this->line("  + L2 group \"{$groupName}\" under LMS (sort {$palSort})" . ($dry ? '  [would insert]' : ''));
                if (! $dry) {
                    $groupId = DB::table('tblmenumaster')->insertGetId([
                        'name' => $groupName,
                        'menu_title' => 'LMS',
                        'description' => 'Personalized Adaptive Learning',
                        'parent_menu_id' => self::LMS_MENU_ID,
                        'level' => 2,
                        'status' => 1,
                        'sort_order' => $palSort,
                        // Group headers are not navigable; children carry the links.
                        'link' => 'javascript:void(0);',
                        'icon' => 'mdi mdi-brain',
                        'sub_institute_id' => (string) $institute,
                        'client_id' => $lms->client_id,
                        'menu_type' => null,
                        'created_at' => now(),
                    ]);
                }
            }

            if ($dry && ! $groupId) {
                $this->comment('Dry run — the group does not exist yet, so child placement cannot be previewed.');
                $this->comment('Re-run without --dry-run to create the group and its screens together.');

                return self::SUCCESS;
            }
        }

        // Mirror whoever already sees the existing PAL screen, so the new screens
        // land with exactly the roles that already have PAL — never broader.
        $profiles = DB::table('tblgroupwise_rights')
            ->where('menu_id', self::PAL_MENU_ID)
            ->where('sub_institute_id', $institute)
            ->distinct()->pluck('profile_id')->all();

        if ($profiles === []) {
            $profiles = DB::table('tblgroupwise_rights')
                ->where('menu_id', self::TEST_MENU_ID)
                ->where('sub_institute_id', $institute)
                ->distinct()->pluck('profile_id')->all();
            $mirrored = 'LMS > Test';
        } else {
            $mirrored = 'the existing PAL entry';
        }

        if ($profiles === []) {
            $this->error("No profile has PAL or Test rights at institute {$institute} to mirror. "
                . 'Aborting rather than granting to everyone.');

            return self::FAILURE;
        }
        $this->line("Mirroring rights from {$mirrored}: profile(s) " . implode(', ', $profiles));

        // Inside Test the rows must sort AFTER the existing entries so the PAL
        // block stays contiguous instead of splitting the exam items; inside a
        // dedicated group they are the only children, so they start at 1.
        $baseSort = $placement === 'test'
            ? ((int) DB::table('tblmenumaster')
                ->where('parent_menu_id', self::TEST_MENU_ID)
                ->where('link', 'not like', 'pal_content%')
                ->max('sort_order')) + 1
            : 0;

        // [label, route name, sort_order, icon, menu_type]
        $items = [
            ['PAL Content Intelligence',  'pal_content.index',          $baseSort + 1, 'mdi mdi-brain',                   'REPORT'],
            ['PAL Review Queue',          'pal_content.review',         $baseSort + 2, 'mdi mdi-clipboard-check-outline', 'ENTRY'],
            ['PAL Misconception Library', 'pal_content.misconceptions', $baseSort + 3, 'mdi mdi-alert-decagram-outline',  'ENTRY'],
        ];

        $grantMenuIds = [$groupId];
        $inserted = 0;
        $existingCount = 0;
        $moved = 0;

        foreach ($items as [$label, $routeName, $sort, $icon, $type]) {
            // Match on link alone, not (parent, link): an earlier run of this
            // command put these under Test, and those rows must be relocated
            // rather than duplicated.
            $existing = DB::table('tblmenumaster')->where('link', $routeName)->first();

            if ($existing) {
                $grantMenuIds[] = $existing->id;

                if ((int) $existing->parent_menu_id !== (int) $groupId) {
                    $this->line("  ~ \"{$label}\" moving from parent {$existing->parent_menu_id} → PAL group {$groupId}"
                        . ($dry ? '  [would move]' : ''));
                    if (! $dry) {
                        DB::table('tblmenumaster')->where('id', $existing->id)->update([
                            'parent_menu_id' => $groupId,
                            'sort_order' => $sort,
                        ]);
                    }
                    $moved++;
                } else {
                    $existingCount++;
                    $this->line("  = \"{$label}\" already in the PAL group (id {$existing->id})");
                }

                // Make sure THIS institute is in the CSV, otherwise the row exists
                // but the sidebar still hides it here.
                if (! $dry && ! $this->csvContains($existing->sub_institute_id, $institute)) {
                    DB::table('tblmenumaster')->where('id', $existing->id)->update([
                        'sub_institute_id' => rtrim((string) $existing->sub_institute_id, ',') . ',' . $institute,
                    ]);
                    $this->line("    + added institute {$institute} to its visibility list");
                }

                continue;
            }

            $this->line("  + L3 \"{$label}\" → {$routeName} (sort {$sort})" . ($dry ? '  [would insert]' : ''));

            if (! $dry) {
                $grantMenuIds[] = DB::table('tblmenumaster')->insertGetId([
                    'name' => $label,
                    'menu_title' => 'LMS',
                    'description' => $label,
                    'parent_menu_id' => $groupId,
                    'level' => 3,
                    'status' => 1,
                    'sort_order' => $sort,
                    'link' => $routeName,
                    'icon' => $icon,
                    'sub_institute_id' => (string) $institute,
                    'client_id' => $lms->client_id,
                    'menu_type' => $type,
                    'created_at' => now(),
                ]);
            }

            $inserted++;
        }

        // ── clean up a PAL group left empty by moving back into Test ────────
        if ($placement === 'test') {
            $emptyGroup = DB::table('tblmenumaster')
                ->where('parent_menu_id', self::LMS_MENU_ID)
                ->where('level', 2)
                ->where('name', 'PAL')
                ->first();

            if ($emptyGroup) {
                $remaining = DB::table('tblmenumaster')->where('parent_menu_id', $emptyGroup->id)->count();

                if ($remaining === 0) {
                    if ($this->option('prune-empty-group')) {
                        $this->line("  - removing the now-empty PAL group (id {$emptyGroup->id})"
                            . ($dry ? '  [would delete]' : ''));
                        if (! $dry) {
                            DB::table('tblgroupwise_rights')->where('menu_id', $emptyGroup->id)->delete();
                            DB::table('tblmenumaster')->where('id', $emptyGroup->id)->delete();
                        }
                    } else {
                        $this->warn("  ! PAL group (id {$emptyGroup->id}) is now empty and will render as an "
                            . 'empty section. Re-run with --prune-empty-group to remove it.');
                    }
                } else {
                    $this->line("  · PAL group (id {$emptyGroup->id}) still has {$remaining} child(ren) — left alone");
                }
            }
        }

        // ── optionally pull the legacy PAL screen in as well ────────────────
        // Off by default: row 426 is visible to ~300 institutes, and relocating
        // it changes navigation for every one of them, not just this tenant.
        if ($placement === 'group' && $this->option('move-legacy-pal')) {
            $legacy = DB::table('tblmenumaster')->where('id', self::PAL_MENU_ID)->first();
            if ($legacy && (int) $legacy->parent_menu_id !== (int) $groupId) {
                $this->warn("  ~ moving the legacy PAL entry (id {$legacy->id}) out of Test into the PAL group");
                $this->warn('    this affects every institute that can see it, not only ' . $institute);
                if (! $dry) {
                    DB::table('tblmenumaster')->where('id', $legacy->id)->update([
                        'parent_menu_id' => $groupId,
                        'sort_order' => 0,
                    ]);
                }
                $moved++;
            }
        } elseif ($placement === 'group') {
            $this->line('  · legacy PAL entry (426) left under Test — pass --move-legacy-pal to relocate it');
        }

        // ── rights ──────────────────────────────────────────────────────────
        $rightsInserted = 0;
        foreach (array_unique($grantMenuIds) as $menuId) {
            foreach ($profiles as $profileId) {
                $has = DB::table('tblgroupwise_rights')
                    ->where('menu_id', $menuId)
                    ->where('profile_id', $profileId)
                    ->where('sub_institute_id', $institute)
                    ->exists();

                if ($has) {
                    continue;
                }

                $this->line("  + right: menu {$menuId} → profile {$profileId}" . ($dry ? '  [would insert]' : ''));

                if (! $dry) {
                    DB::table('tblgroupwise_rights')->insert([
                        'menu_id' => $menuId,
                        'profile_id' => $profileId,
                        'can_view' => 1,
                        'can_add' => 1,
                        'can_edit' => 1,
                        // Content is deprecated, never deleted — the tags are foreign
                        // keys in learner history (plan §4).
                        'can_delete' => 0,
                        'sub_institute_id' => $institute,
                        'created_at' => now(),
                    ]);
                    $rightsInserted++;
                }
            }
        }

        $this->line(str_repeat('─', 78));
        $this->table(['Result', 'Count'], [
            [$dry ? 'Menu items to insert' : 'Menu items inserted', $inserted],
            [$dry ? 'Menu items to relocate' : 'Menu items relocated', $moved],
            ['Menu items already in place', $existingCount],
            [$dry ? 'Rights to grant' : 'Rights granted', $dry ? 'n/a' : $rightsInserted],
        ]);

        if ($dry) {
            $this->comment('Dry run — nothing written. Re-run without --dry-run to apply.');
        } else {
            $this->info($placement === 'test'
                ? 'Done. The three PAL screens sit inside the existing Test module.'
                : 'Done. PAL is its own module under LMS, directly below Homework.');
            $this->line('Log out and back in if the sidebar is cached in your session.');
        }

        return self::SUCCESS;
    }

    /**
     * Create an empty L2 module directly below Homework and grant it rights.
     *
     * Nothing is moved into it — that is the point. It adds the section so
     * screens can be filed under it later.
     *
     * NOTE: an L2 group with no children renders as a section that expands to
     * nothing. Both the Blade sidebar and the Next.js menu build their groups
     * from the child rows, so until something is filed under it the section may
     * not be visible at all. The command says so rather than leaving the caller
     * to wonder why nothing appeared.
     */
    private function createEmptyGroup(string $name, int $institute, object $lms, bool $dry): int
    {
        $name = trim($name);

        if ($name === '') {
            $this->error('--create-group needs a name.');

            return self::FAILURE;
        }

        $homeworkSort = (int) (DB::table('tblmenumaster')
            ->where('id', self::HOMEWORK_MENU_ID)->value('sort_order') ?? 8);
        $sort = $homeworkSort + 1;

        $this->line("Creating an empty L2 module \"{$name}\" below Homework (sort {$sort})");

        $existing = DB::table('tblmenumaster')
            ->where('parent_menu_id', self::LMS_MENU_ID)
            ->where('level', 2)
            ->where('name', $name)
            ->first();

        if ($existing) {
            $this->line("  = \"{$name}\" already exists (id {$existing->id})");
            $groupId = $existing->id;

            if (! $dry && ! $this->csvContains($existing->sub_institute_id, $institute)) {
                DB::table('tblmenumaster')->where('id', $groupId)->update([
                    'sub_institute_id' => rtrim((string) $existing->sub_institute_id, ',') . ',' . $institute,
                ]);
                $this->line("    + added institute {$institute} to its visibility list");
            }
        } else {
            $this->line("  + L2 \"{$name}\" under \"{$lms->name}\"" . ($dry ? '  [would insert]' : ''));

            if ($dry) {
                $this->comment('Dry run — nothing written.');

                return self::SUCCESS;
            }

            $groupId = DB::table('tblmenumaster')->insertGetId([
                'name' => $name,
                'menu_title' => 'LMS',
                'description' => $name,
                'parent_menu_id' => self::LMS_MENU_ID,
                'level' => 2,
                'status' => 1,
                'sort_order' => $sort,
                // Group headers are not navigable; children carry the links.
                'link' => 'javascript:void(0);',
                'icon' => 'mdi mdi-brain',
                'sub_institute_id' => (string) $institute,
                'client_id' => $lms->client_id,
                'menu_type' => null,
                'created_at' => now(),
            ]);
        }

        // Mirror the roles that already see PAL, so the section appears for the
        // same people rather than for everyone.
        $profiles = DB::table('tblgroupwise_rights')
            ->where('menu_id', self::PAL_MENU_ID)
            ->where('sub_institute_id', $institute)
            ->distinct()->pluck('profile_id')->all();

        $granted = 0;
        foreach ($profiles as $profileId) {
            $has = DB::table('tblgroupwise_rights')
                ->where('menu_id', $groupId)->where('profile_id', $profileId)
                ->where('sub_institute_id', $institute)->exists();

            if ($has) {
                continue;
            }

            if (! $dry) {
                DB::table('tblgroupwise_rights')->insert([
                    'menu_id' => $groupId,
                    'profile_id' => $profileId,
                    'can_view' => 1,
                    'can_add' => 1,
                    'can_edit' => 1,
                    'can_delete' => 0,
                    'sub_institute_id' => $institute,
                    'created_at' => now(),
                ]);
            }
            $granted++;
        }

        $this->line("  + rights granted to {$granted} profile(s)");
        $this->line(str_repeat('─', 78));
        $this->info("\"{$name}\" created (id {$groupId}), directly below Homework.");
        $this->warn('It has no screens yet, so the sidebar may not render it until something is filed under it.');
        $this->line('To file the Content Intelligence screens here instead of Test:');
        $this->line("  php artisan pal:sync-content-menu --institute={$institute} --placement=group --group-name=\"{$name}\"");

        return self::SUCCESS;
    }

    /** Is $needle one of the comma-separated ids in $csv? */
    private function csvContains(?string $csv, int $needle): bool
    {
        if ($csv === null || $csv === '') {
            return false;
        }

        return in_array((string) $needle, array_map('trim', explode(',', $csv)), true);
    }
}
