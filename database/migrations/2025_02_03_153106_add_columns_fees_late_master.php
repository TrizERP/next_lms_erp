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
        Schema::table('fees_late_master', function (Blueprint $table) {
            //
            $table->string('fine_type',255)->after('term_id')->nullable();
            $table->char('status')->after('fine_type')->nullable();
            $table->timestamp('updated_on')->after('created_on')->nullable(); 
            $table->string('late_date',10)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fees_late_master', function (Blueprint $table) {
            //
            $table->dropColumn('fine_type');
            $table->dropColumn('status');
            $table->dropColumn('updated_on');
            $table->date('late_date')->change();
        });
    }
};
