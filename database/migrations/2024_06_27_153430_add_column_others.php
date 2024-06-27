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
        Schema::table('task', function (Blueprint $table) {
            //
            $table->string('KRA',50)->nullable()->after('TASK_DATE');
            $table->string('KPA',50)->nullable()->after('KRA');
            $table->string('task_type',50)->nullable()->after('KPA');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('task', function (Blueprint $table) {
            //
            $table->dropColumn('KRA');
            $table->dropColumn('KPA');
            $table->dropColumn('task_type');
        });
    }
};
