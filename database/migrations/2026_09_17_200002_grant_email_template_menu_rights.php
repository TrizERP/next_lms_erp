<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grant rights on the new Settings > Email Templates menu.
 *
 * checkPermission resolves a menu id from the route name and then requires a
 * can_view row; without one every profile gets a 403. The rights are copied
 * from the existing Template Master menu so exactly the profiles that already
 * manage templates can manage email layouts too.
 */
return new class extends Migration
{
    private const NEW_LINK = 'email_template.index';
    private const REF_LINK = 'templatemaster.index';

    public function up(): void
    {
        if (!Schema::hasTable('tblmenumaster')) {
            return;
        }

        $newMenuId = DB::table('tblmenumaster')->where('link', self::NEW_LINK)->value('id');
        $refMenuId = DB::table('tblmenumaster')->where('link', self::REF_LINK)->value('id');

        if (!$newMenuId || !$refMenuId) {
            return;
        }

        foreach (['tblgroupwise_rights', 'tblindividual_rights'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            if (DB::table($table)->where('menu_id', $newMenuId)->exists()) {
                continue;
            }

            $rows = DB::table($table)->where('menu_id', $refMenuId)->get();

            foreach ($rows->chunk(200) as $chunk) {
                $insert = [];

                foreach ($chunk as $row) {
                    $values = (array) $row;
                    unset($values['id']);
                    $values['menu_id'] = $newMenuId;
                    $values['created_at'] = now();
                    $insert[] = $values;
                }

                if (!empty($insert)) {
                    DB::table($table)->insert($insert);
                }
            }
        }
    }

    public function down(): void
    {
        $newMenuId = DB::table('tblmenumaster')->where('link', self::NEW_LINK)->value('id');

        if (!$newMenuId) {
            return;
        }

        foreach (['tblgroupwise_rights', 'tblindividual_rights'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('menu_id', $newMenuId)->delete();
            }
        }
    }
};
