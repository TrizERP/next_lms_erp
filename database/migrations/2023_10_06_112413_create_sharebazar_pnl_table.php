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
        Schema::create('sharebazar_pnl', function (Blueprint $table) {
            $table->id();
            $table->string('code', 15)->nullable();
            $table->string('name', 50)->nullable();
            $table->text('gross', 15)->nullable();
            $table->text('exp', 15)->nullable();
            $table->text('other_exp', 15)->nullable();
            $table->text('gross_total', 15)->nullable();
            $table->text('intrest', 15)->nullable();
            $table->text('net_total', 15)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sharebazar_pnl');
    }
};
