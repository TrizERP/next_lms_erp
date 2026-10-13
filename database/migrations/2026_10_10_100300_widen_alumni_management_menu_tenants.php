<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrects the institute / client scope of the Alumni Management menu rows.
 *
 * The menu API only shows a row to an institute whose id is in the row's
 * sub_institute_id list (FIND_IN_SET), and to a client whose id is in client_id.
 * 2026_10_10_100100 built that list from school_detail alone, which omits institutes that
 * only exist in school_setup (for example institute 1), so those institutes never saw the
 * menu. This widens the rows to the union of what they have now, school_setup, and the
 * "Institute ERP" top-level module (the established list of every provisioned institute).
 *
 * Only ids are added, never removed. Idempotent. Rights rows are per profile and were
 * already granted by 2026_10_10_100200, so nothing else needs to change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $module = DB::table('tblmenumaster')->where('link', 'alumni_management.index')->first();
        if ($module === null) {
            return;
        }
        $ids = DB::table('tblmenumaster')->where('id', $module->id)->orWhere('parent_menu_id', $module->id)->pluck('id')->all();

        $reference = DB::table('tblmenumaster')->where('level', 1)->where('name', 'Institute ERP')->first();

        $tenants = $this->csv($module->sub_institute_id);
        $clients = $this->csv($module->client_id);
        if ($reference !== null) {
            $tenants = array_merge($tenants, $this->csv($reference->sub_institute_id));
            $clients = array_merge($clients, $this->csv($reference->client_id));
        }
        foreach (['school_setup' => 'Id', 'school_detail' => 'id'] as $table => $col) {
            if (Schema::hasTable($table)) {
                $tenants = array_merge($tenants, DB::table($table)->pluck($col)->map(fn ($v) => (int) $v)->all());
            }
        }

        DB::table('tblmenumaster')->whereIn('id', $ids)->update([
            'sub_institute_id' => implode(',', $this->unique($tenants)),
            'client_id' => implode(',', $this->unique($clients)),
        ]);
    }

    public function down(): void
    {
        // Forward-only: narrowing would hide the menu again.
    }

    /** @return array<int,int> */
    private function csv(?string $value): array
    {
        return array_map('intval', array_filter(array_map('trim', explode(',', (string) $value)), 'strlen'));
    }

    /** @return array<int,int> */
    private function unique(array $values): array
    {
        $values = array_values(array_unique(array_filter($values, fn ($v) => $v > 0)));
        sort($values);

        return $values;
    }
};
