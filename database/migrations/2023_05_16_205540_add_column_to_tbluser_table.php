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
        Schema::table('tbluser', function (Blueprint $table) {
            $table->string('bank_name')->nullable();
            $table->unsignedBigInteger('account_no')->nullable();
            $table->string('ifsc_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tbluser', function (Blueprint $table) {
            $table->dropColumn('bank_name');
            $table->dropColumn('account_no');
            $table->dropColumn('ifsc_code');
        });
    }
};
