<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a native dynamic page tile be tappable: `drill_endpoint`, when set, is
 * a second Laravel API (relative to /api/, same convention as
 * mobile_dynamic_page.data_endpoint) that returns a self-describing flat
 * list -- {columns:[{key,label}], rows:[{...}]} -- for the tile to open on
 * tap, instead of the tile being a read-only stat.
 *
 * Self-describing (the endpoint states its own columns) rather than a second
 * MobileDynamicPageFieldRegistry-style closed field list, because unlike the
 * summary tiles' data source, a drill endpoint's rows do not share one
 * common shape across every possible drill target -- see
 * FeesDashboardApiController@defaulters for the first one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_dynamic_page_field') && ! Schema::hasColumn('mobile_dynamic_page_field', 'drill_endpoint')) {
            Schema::table('mobile_dynamic_page_field', function (Blueprint $table) {
                $table->string('drill_endpoint', 200)->nullable()->after('field_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mobile_dynamic_page_field') && Schema::hasColumn('mobile_dynamic_page_field', 'drill_endpoint')) {
            Schema::table('mobile_dynamic_page_field', function (Blueprint $table) {
                $table->dropColumn('drill_endpoint');
            });
        }
    }
};
