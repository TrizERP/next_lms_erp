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
        //
        Schema::table('contents', function (Blueprint $table) {
            //
            $table->bigInteger('parent_id')->after('sub_institute_id')->nullable()->comment('for copied content');
        });
        Schema::table('content_master', function (Blueprint $table) {
            //
            $table->bigInteger('content_library_id')->after('created_by')->nullable()->comment('for copied content');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::table('contents', function (Blueprint $table) {
            //
            $table->dropColumn('parent_id');
        });
        Schema::table('content_master', function (Blueprint $table) {
            //
            $table->dropColumn('content_library_id');
        });
    }
};
