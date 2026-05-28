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
       Schema::table('lms_curriculum', function (Blueprint $table) {

    $table->string('board',100)->nullable()->after('syear');

    $table->string('framework',100)->nullable()->after('board');

    $table->unsignedSmallInteger('total_marks')->nullable()->after('framework');

    $table->unsignedSmallInteger('internal_marks')->nullable()->after('total_marks');

    $table->unique(
        ['subject_id','standard_id','syear'],
        'uq_curriculum'
    );
});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lms_curriculum', function (Blueprint $table) {
            //
        });
    }
};
