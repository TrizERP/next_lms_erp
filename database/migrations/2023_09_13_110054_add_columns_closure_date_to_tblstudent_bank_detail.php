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
        Schema::table('tblstudent_bank_detail', function (Blueprint $table) {
            $table->date('closure_date')->nullable()->after('UMRN');
            $table->String('status', 255)->nullable()->after('closure_date');
            $table->String('reason', 255)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tblstudent_bank_detail', function (Blueprint $table) {
            $table->dropIfExists('closure_date');
            $table->dropIfExists('status');
            $table->dropIfExists('reason');
        });
    }
};
