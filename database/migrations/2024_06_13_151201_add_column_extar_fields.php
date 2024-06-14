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
        Schema::table('general_data', function (Blueprint $table) {
            //
            $table->string('extra_field1')->nullable()->after('fieldvalue');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('general_data', function (Blueprint $table) {
            //
            $table->dropColumn('extra_field1');
        });
    }
};
