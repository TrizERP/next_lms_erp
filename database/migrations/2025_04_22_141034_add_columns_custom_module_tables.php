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
        Schema::table('custom_module_tables', function (Blueprint $table) {
            //
            $table->string('level_2')->nullable();
            $table->string('helper_function')->nullable();
            $table->char('syear_wise')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('custom_module_tables', function (Blueprint $table) {
            //
            $table->dropColumn(['level_2', 'helper_function', 'syear_wise']);
        });
    }
};
