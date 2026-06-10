<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('master_fields', function (Blueprint $table) {
            $table->string('use_table_data', 255)->nullable()->after('field_type');
        });
    }

    public function down()
    {
        Schema::table('master_fields', function (Blueprint $table) {
            $table->dropColumn('use_table_data');
        });
    }
};