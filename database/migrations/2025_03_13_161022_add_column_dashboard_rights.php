<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tblgroupwise_rights', function (Blueprint $table) {
            // new dashboard rights
            $table->integer('dashboard_right')->after('can_delete')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tblgroupwise_rights', function (Blueprint $table) {
            // new dashboard rights
            $table->dropColumn('dashboard_right');
        });
    }
};
