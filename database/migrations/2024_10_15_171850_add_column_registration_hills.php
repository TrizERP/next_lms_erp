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
        Schema::table('admission_registration_v1', function (Blueprint $table) {
            //
            $table->string('p_int_remark',50)->after('p_int_time')->nullable();
            $table->mediumText('p_int_attandance')->after('p_int_remark')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('admission_registration_v1', function (Blueprint $table) {
            //
            $table->dropColumn('p_int_remark');
            $table->dropColumn('p_int_attandance');
        });
    }
};
