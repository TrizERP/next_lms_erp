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
        Schema::table('syllabus', function (Blueprint $table) {
            //
            $table->integer('syear')->change(); // change dtat type from varchar to integer
            $table->integer('standard_id')->change(); // change dtat type from varchar to integer
            // make column nullable 
            $table->date('date_')->nullable()->change();
            $table->mediumText('title')->nullable()->change();
            $table->mediumText('message')->nullable()->change();
            $table->string('file_name',255)->nullable()->change();
            // add new columns
            $table->integer('no_of_days')->nullable()->after('subject_id');
            $table->integer('no_of_periods')->nullable()->after('no_of_days');
            $table->string('types',50)->nullable()->after('no_of_periods');
            $table->string('months',50)->nullable()->after('types');
            $table->mediumText('assesment_tool')->nullable()->after('message');
            $table->integer('created_by')->nullable()->after('sub_institute_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('syllabus', function (Blueprint $table) {
            $table->integer('syear')->change(); // change dtat type from varchar to integer
            $table->integer('standard_id')->change(); // change dtat type from varchar to integer
            // make column nullable 
            $table->date('date_')->nullable(false)->change();
            $table->mediumText('title')->nullable(false)->change();
            $table->mediumText('message')->nullable(false)->change();
            $table->mediumText('file_name')->nullable(false)->change();
            // new added columns
            $table->dropColumn('no_of_days');
            $table->dropColumn('no_of_periods');
            $table->dropColumn('types');
            $table->dropColumn('months');
            $table->dropColumn('assesment_tool');
            $table->dropColumn('created_by');
        });
    }
};
