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
        Schema::create('sharebazar_margin', function (Blueprint $table) {
            $table->id();
            $table->string('Code', 15)->nullable();
            $table->string('exchange', 20)->nullable();
            $table->string('script', 50)->nullable();
            $table->text('qty', 15)->nullable();
            $table->text('span', 15)->nullable();
            $table->text('exposure', 15)->nullable();
            $table->text('delivery_margin', 15)->nullable();
            $table->text('additional_margin', 15)->nullable();
            $table->text('ex_%', 15)->nullable();
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
        Schema::dropIfExists('sharebazar_margin');
    }
};
