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
            $table->date('late_date')->change();
            $table->renameColumn('term_id', 'month_id');
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
            $table->string('late_date',10)->change();
            $table->renameColumn('month_id', 'term_id');
        });
    }
};
