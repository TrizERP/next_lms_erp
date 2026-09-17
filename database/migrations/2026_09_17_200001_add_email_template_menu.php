<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sidebar entry for Settings > Email Templates.
 *
 * Modelled on the existing `templatemaster.index` row so the new screen lands in
 * the same menu group and picks up the same rights handling.
 */
return new class extends Migration
{
    private const LINK = 'email_template.index';

    public function up(): void
    {
        if (!Schema::hasTable('tblmenumaster')) {
            return;
        }

        if (DB::table('tblmenumaster')->where('link', self::LINK)->exists()) {
            return;
        }

        $reference = DB::table('tblmenumaster')->where('link', 'templatemaster.index')->first();

        if (!$reference) {
            return;
        }

        DB::table('tblmenumaster')->insert([
            'name'             => 'Email Templates',
            'menu_title'       => 'Email Templates',
            'menu_sortorder'   => $reference->menu_sortorder,
            'description'      => 'Manage transactional email layouts',
            'parent_menu_id'   => $reference->parent_menu_id,
            'level'            => $reference->level,
            'status'           => 1,
            'sort_order'       => (int) $reference->sort_order + 1,
            'link'             => self::LINK,
            'icon'             => $reference->icon ?: 'fa fa-envelope',
            'sub_institute_id' => $reference->sub_institute_id,
            'client_id'        => $reference->client_id,
            'menu_type'        => $reference->menu_type,
            'created_at'       => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('tblmenumaster')) {
            DB::table('tblmenumaster')->where('link', self::LINK)->delete();
        }
    }
};
