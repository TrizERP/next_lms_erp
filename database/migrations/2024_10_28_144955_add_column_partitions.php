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
        Schema::table('result_co_scholastic_parent', function (Blueprint $table) {
            //
            $table->integer('part_no')->after('sub_institute_id')->nullable();
            $table->string('part_name',50)->after('part_no')->nullable();
            $table->integer('status')->after('part_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('result_co_scholastic_parent', function (Blueprint $table) {
            //
            $table->dropColumn('part_no');
            $table->dropColumn('part_name');
            $table->dropColumn('status');
        });
    }
};
