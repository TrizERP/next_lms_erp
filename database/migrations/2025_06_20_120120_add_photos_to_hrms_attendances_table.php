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
         Schema::table('hrms_attendances', function (Blueprint $table) {
            $table->string('photo_in')->nullable()->after('punchin_time');
            $table->string('photo_out')->nullable()->after('punchout_time');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hrms_attendances', function (Blueprint $table) {
            $table->dropColumn(['photo_in', 'photo_out']);
        });
    }
};
