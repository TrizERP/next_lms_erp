<?php

namespace App\Console\Commands\LMS;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Prepares an institute for the LMS Engagement modules (Leader Board and
 * Social & Collaborative) migrated to the K12 frontend.
 *
 * Two entirely additive, idempotent steps:
 *
 *  1. Leader-board configuration (`lb_master`). The board can only attribute
 *     points to a module that has a master row for the learner's standard, so
 *     an institute with no configuration shows an empty board. This inserts the
 *     four modules the module supports - login / exampass / examfail / homework
 *     - for every standard of the institute that is missing them, using the
 *     same values the reference institute has carried since 2021.
 *
 *  2. Optionally (--grant-menu-rights) grants view rights on the two existing
 *     menu rows ("Leader Board" and "Social & Collabrotive") to exactly the
 *     profiles that already hold rights on the institute's LMS root, so no role
 *     gains access it did not already have to the LMS.
 *
 * Nothing is updated or deleted: existing rows are detected and skipped, so
 * running this repeatedly can never create a duplicate.
 *
 *   php artisan lms:engagement-init --institute=1 --dry-run
 *   php artisan lms:engagement-init --institute=1
 *   php artisan lms:engagement-init --institute=1 --grant-menu-rights
 */
class LmsEngagementInitCommand extends Command
{
    protected $signature = 'lms:engagement-init
        {--institute= : sub_institute_id to prepare (required)}
        {--standard=* : limit to these standard ids (default: every standard of the institute)}
        {--grant-menu-rights : also grant sidebar rights for the two engagement menus}
        {--dry-run : preview only, no writes}';

    protected $description = 'Idempotently create the missing leader-board point configuration (and optionally menu rights) for an institute.';

    /** Menu rows that already exist in tblmenumaster for these two modules. */
    private const MENU_IDS = [290, 463];

    /** LMS root menu ids whose rights we mirror when granting. */
    private const LMS_ROOT_MENU_IDS = [230, 277];

    /**
     * Default points configuration, matching the reference institute's rows.
     * `per_value` is the pass/fail percentage threshold and only applies to the
     * exam modules, exactly as the legacy master form enforces.
     */
    private const DEFAULTS = [
        ['module_name' => 'login',    'points' => 10,  'per_value' => null,  'icon' => 'xf005', 'description' => 'Login Points'],
        ['module_name' => 'exampass', 'points' => 20,  'per_value' => 50.00, 'icon' => 'xf091', 'description' => 'Exam Passed Points'],
        ['module_name' => 'examfail', 'points' => -10, 'per_value' => 50.00, 'icon' => 'xf165', 'description' => 'Exam Failed Points'],
        ['module_name' => 'homework', 'points' => 10,  'per_value' => null,  'icon' => 'xf164', 'description' => 'Homework Points'],
    ];

    public function handle(): int
    {
        $institute = (int) $this->option('institute');
        if ($institute <= 0) {
            $this->error('--institute is required (the sub_institute_id to prepare).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info(sprintf(
            'LMS Engagement init - institute %d - %s',
            $institute,
            $dryRun ? 'DRY-RUN (no writes)' : 'LIVE WRITE'
        ));

        $standards = $this->standards($institute);
        if ($standards->isEmpty()) {
            $this->error("Institute {$institute} has no standards. Nothing to configure.");

            return self::FAILURE;
        }

        $created = $this->seedLeaderboardMaster($institute, $standards, $dryRun);
        $this->line(sprintf(
            '%s %d leader-board master row(s) across %d standard(s); the rest already existed.',
            $dryRun ? 'Would create' : 'Created',
            $created,
            $standards->count()
        ));

        if ($this->option('grant-menu-rights')) {
            $granted = $this->grantMenuRights($institute, $dryRun);
            if ($granted === null) {
                return self::FAILURE;
            }
            $this->line(sprintf(
                '%s %d menu-right row(s) for menus %s.',
                $dryRun ? 'Would create' : 'Created',
                $granted,
                implode(', ', self::MENU_IDS)
            ));
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function standards(int $institute)
    {
        $only = array_filter(array_map('intval', (array) $this->option('standard')));

        return DB::table('standard')
            ->where('sub_institute_id', $institute)
            ->when($only, fn ($query) => $query->whereIn('id', $only))
            ->orderBy('sort_order')
            ->get(['id', 'grade_id', 'name']);
    }

    private function seedLeaderboardMaster(int $institute, $standards, bool $dryRun): int
    {
        $created = 0;

        foreach ($standards as $standard) {
            foreach (self::DEFAULTS as $default) {
                $exists = DB::table('lb_master')
                    ->where('sub_institute_id', $institute)
                    ->where('standard_id', $standard->id)
                    ->where('module_name', $default['module_name'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $created++;

                if ($dryRun) {
                    $this->line(sprintf(
                        '  + class %s / %s (%d pts)',
                        $standard->name,
                        $default['module_name'],
                        $default['points']
                    ));

                    continue;
                }

                DB::table('lb_master')->insert(array_merge($default, [
                    'grade_id'         => $standard->grade_id,
                    'standard_id'      => $standard->id,
                    'status'           => 1,
                    'sub_institute_id' => $institute,
                    'created_on'       => now(),
                ]));
            }
        }

        return $created;
    }

    /** @return int|null null when there is no safe set of profiles to mirror */
    private function grantMenuRights(int $institute, bool $dryRun): ?int
    {
        $profiles = DB::table('tblgroupwise_rights')
            ->whereIn('menu_id', self::LMS_ROOT_MENU_IDS)
            ->where('sub_institute_id', $institute)
            ->distinct()
            ->pluck('profile_id')
            ->all();

        if (! $profiles) {
            $this->error("No profile holds LMS rights at institute {$institute}, so there is nothing to mirror. Skipping rather than over-granting.");

            return null;
        }

        $this->line('Mirroring profiles: ' . implode(', ', $profiles));

        $created = 0;
        foreach (self::MENU_IDS as $menuId) {
            foreach ($profiles as $profileId) {
                $exists = DB::table('tblgroupwise_rights')
                    ->where('menu_id', $menuId)
                    ->where('profile_id', $profileId)
                    ->where('sub_institute_id', $institute)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $created++;

                if ($dryRun) {
                    $this->line("  + menu {$menuId} -> profile {$profileId}");

                    continue;
                }

                DB::table('tblgroupwise_rights')->insert([
                    'menu_id'          => $menuId,
                    'profile_id'       => $profileId,
                    'sub_institute_id' => $institute,
                    'can_view'         => 1,
                    'created_at'       => now(),
                ]);
            }
        }

        return $created;
    }
}
