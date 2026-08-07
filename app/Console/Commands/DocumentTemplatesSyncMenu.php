<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Adds the Document Templates module to the sidebar (tblmenumaster) and grants
 * view-rights (tblgroupwise_rights) so it appears for the target institute.
 *
 * The module sits as an L2 group under "Institute ERP" (menu id 1), alongside
 * Certificate (262), I-card (261/266) and Circular (102) — the screens that
 * consume the templates it produces. It is deliberately NOT under LMS + PAL
 * (230): the documents are cross-module (TCs, fee receipts, admission letters,
 * report-card covers), not learning content.
 *
 * The legacy "Template Management" row (id 291, /general/template_management,
 * raw-HTML template_master) is left completely alone — it still serves the
 * Blade screens for other institutes.
 *
 * Idempotent: re-running only inserts what is missing (matched by parent + name
 * for the group, parent + link for items, and menu_id+profile_id+institute for
 * rights). Nothing existing is moved, renamed, re-linked or deleted.
 *
 *   php artisan document-templates:sync-menu --institute=1 --dry-run
 *   php artisan document-templates:sync-menu --institute=1
 *
 * Profiles are "mirrored" from whoever already has rights on Institute ERP (1)
 * for the institute, so rights go to exactly the roles that already see it.
 */
class DocumentTemplatesSyncMenu extends Command
{
    protected $signature = 'document-templates:sync-menu {--institute=1 : sub_institute_id to target} {--dry-run : preview only, no writes}';

    protected $description = 'Add the Document Templates module to the sidebar (tblmenumaster) + grant view rights, idempotently.';

    /** The L1 parent: "Institute ERP". */
    private const PARENT_L1 = 1;

    private const GROUP_NAME = 'Document Templates';

    public function handle(): int
    {
        $institute = (int) $this->option('institute');
        $dry = (bool) $this->option('dry-run');
        $this->info('Document Templates menu sync — institute ' . $institute . ' — ' . ($dry ? 'DRY-RUN (no writes)' : 'LIVE WRITE'));

        // --- mirror the profiles that already see Institute ERP here ----------
        $profiles = DB::table('tblgroupwise_rights')
            ->where('menu_id', self::PARENT_L1)->where('sub_institute_id', $institute)
            ->distinct()->pluck('profile_id')->all();
        if (empty($profiles)) {
            $this->error("No profiles have Institute ERP rights at institute {$institute} to mirror. Aborting so we don't over-grant.");

            return self::FAILURE;
        }
        $this->line('Mirror profiles (get can_view): ' . implode(', ', $profiles));

        $parent = DB::table('tblmenumaster')->where('id', self::PARENT_L1)->first();
        if (! $parent) {
            $this->error('Institute ERP menu (id ' . self::PARENT_L1 . ') not found. Aborting.');

            return self::FAILURE;
        }

        $menuInserts = 0;
        $rightInserts = 0;

        // --- ensure the L2 group ---------------------------------------------
        $group = DB::table('tblmenumaster')
            ->where('parent_menu_id', self::PARENT_L1)
            ->where('name', self::GROUP_NAME)
            ->where('level', 2)
            ->first();
        $groupId = $group->id ?? null;

        if (! $groupId) {
            $this->line('  + L2 group "' . self::GROUP_NAME . '" under Institute ERP (sort 22)' . ($dry ? '  [would insert]' : ''));
            if (! $dry) {
                $groupId = DB::table('tblmenumaster')->insertGetId([
                    'name' => self::GROUP_NAME,
                    'menu_title' => 'Institute ERP',
                    'description' => 'Design and manage printable school documents',
                    'parent_menu_id' => self::PARENT_L1,
                    'level' => 2,
                    'status' => 1,
                    'sort_order' => 22,
                    'link' => 'javascript:void(0);',
                    'icon' => 'mdi mdi-file-document-edit-outline',
                    'sub_institute_id' => (string) $institute,
                    'client_id' => $parent->client_id,
                    'menu_type' => 'ENTRY',
                    'created_at' => now(),
                ]);
            }
            $menuInserts++;
        } else {
            $this->line("  = L2 group \"" . self::GROUP_NAME . "\" exists (id {$groupId})");
            if (! $dry && ! $this->csvContains($group->sub_institute_id, $institute)) {
                DB::table('tblmenumaster')->where('id', $groupId)
                    ->update(['sub_institute_id' => rtrim((string) $group->sub_institute_id, ',') . ',' . $institute]);
            }
        }

        // --- the L3 screens ---------------------------------------------------
        // The editor itself (/document-templates/editor/{id}) is intentionally
        // NOT a menu item: it is reached by opening a template from the gallery.
        $G = $groupId ?? 'DocumentTemplates(new)';
        $items = [
            [$G, 'All templates', '/document-templates', 1, 'mdi mdi-file-document-multiple-outline', 'ENTRY'],
            [$G, 'Certificates', '/document-templates?category=certificate', 2, 'mdi mdi-certificate-outline', 'ENTRY'],
            [$G, 'ID cards', '/document-templates?category=id_card', 3, 'mdi mdi-card-account-details-outline', 'ENTRY'],
            [$G, 'Fee documents', '/document-templates?category=fees', 4, 'mdi mdi-receipt-text-outline', 'ENTRY'],
            [$G, 'Admission documents', '/document-templates?category=admission', 5, 'mdi mdi-account-plus-outline', 'ENTRY'],
        ];

        $grantMenuIds = [self::PARENT_L1];
        if ($groupId) {
            $grantMenuIds[] = $groupId;
        }

        foreach ($items as [$parentId, $label, $link, $sort, $icon, $type]) {
            $parentIsNew = ! is_int($parentId);
            $existing = null;
            if (! $parentIsNew) {
                $existing = DB::table('tblmenumaster')
                    ->where('parent_menu_id', $parentId)->where('link', $link)->first();
            }

            if ($existing) {
                $this->line("  = \"{$label}\" exists (id {$existing->id})");
                $grantMenuIds[] = $existing->id;
                if (! $dry && ! $this->csvContains($existing->sub_institute_id, $institute)) {
                    DB::table('tblmenumaster')->where('id', $existing->id)
                        ->update(['sub_institute_id' => rtrim((string) $existing->sub_institute_id, ',') . ',' . $institute]);
                }
                continue;
            }

            $this->line("  + L3 \"{$label}\" → {$link}" . ($dry ? '  [would insert]' : ''));
            if (! $dry) {
                $grantMenuIds[] = DB::table('tblmenumaster')->insertGetId([
                    'name' => $label,
                    'menu_title' => 'Institute ERP',
                    'description' => $label,
                    'parent_menu_id' => $parentId,
                    'level' => 3,
                    'status' => 1,
                    'sort_order' => $sort,
                    'link' => $link,
                    'icon' => $icon,
                    'sub_institute_id' => (string) $institute,
                    'client_id' => $parent->client_id,
                    'menu_type' => $type,
                    'created_at' => now(),
                ]);
            }
            $menuInserts++;
        }

        // --- grant view-rights (idempotent) -----------------------------------
        foreach (array_values(array_unique($grantMenuIds)) as $menuId) {
            foreach ($profiles as $profileId) {
                $has = DB::table('tblgroupwise_rights')
                    ->where('menu_id', $menuId)->where('profile_id', $profileId)
                    ->where('sub_institute_id', $institute)->exists();
                if ($has) {
                    continue;
                }

                $this->line("  + right: menu {$menuId} → profile {$profileId} @ inst {$institute}" . ($dry ? '  [would insert]' : ''));
                if (! $dry) {
                    DB::table('tblgroupwise_rights')->insert([
                        'menu_id' => $menuId, 'profile_id' => $profileId,
                        'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1,
                        'sub_institute_id' => $institute, 'created_at' => now(),
                    ]);
                }
                $rightInserts++;
            }
        }

        $this->info(($dry ? 'WOULD insert' : 'Inserted') . ": {$menuInserts} menu row(s), {$rightInserts} rights row(s).");
        if ($dry) {
            $this->comment('Dry-run only — nothing was written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    private function csvContains(?string $csv, int $id): bool
    {
        if (! $csv) {
            return false;
        }

        return in_array((string) $id, array_map('trim', explode(',', $csv)), true);
    }
}
