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
        Schema::table('custom_module_table_columns', function (Blueprint $table) {
            $table->string('field_type');
            $table->json('field_value');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('custom_module_table_columns', function (Blueprint $table) {
            $table->dropColumn('field_type');
            $table->dropColumn('field_value');
        });
    }
};
