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
        Schema::table('import_table_fields', function (Blueprint $table) {
            $table->boolean('is_required')->default(0);
            $table->boolean('is_customized_table')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('import_table_fields', function (Blueprint $table) {
            $table->dropColumn('is_required');
            $table->dropColumn('is_customized_table');
        });
    }
};
