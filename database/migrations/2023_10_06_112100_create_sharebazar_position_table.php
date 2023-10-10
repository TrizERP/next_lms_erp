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
        Schema::dropIfExists('sharebazar_position');
        Schema::create('sharebazar_position', function (Blueprint $table) {
            $table->id();
            $table->string('date', 15)->nullable();
            $table->string('client_id', 20)->nullable();
            $table->string('exchange', 20)->nullable();
            $table->string('ScriptName', 50)->nullable();
            $table->text('b_f_qty', 15)->nullable();
            $table->text('b_f_rate', 15)->nullable();
            $table->text('b_f_value', 15)->nullable();
            $table->text('buy_qty', 15)->nullable();
            $table->text('buy_rate', 15)->nullable();
            $table->text('buy_amount', 15)->nullable();
            $table->text('sale_qty', 15)->nullable();
            $table->text('sale_rate', 15)->nullable();
            $table->text('sale_amount', 15)->nullable();
            $table->text('net_qty', 15)->nullable();
            $table->text('net_rate', 15)->nullable();
            $table->text('net_amount', 15)->nullable();
            $table->text('closing_price', 15)->nullable();
            $table->text('booked', 15)->nullable();
            $table->text('notional', 15)->nullable();
            $table->text('total', 15)->nullable();
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
        Schema::dropIfExists('sharebazar_position');
    }
};
