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
        Schema::create('master_fields', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('module',255)->nullable();
            $table->string('field_name',255)->nullable();
            $table->string('field_label',255)->nullable();
            $table->string('field_type',255)->nullable();
            $table->text('field_value')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->text('validation_rules')->nullable();
            $table->integer('sort_order')->nullable();
            $table->string('section',255)->nullable();
            $table->string('main_menu',255)->nullable();
            $table->string('table_name',255)->nullable();
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
        Schema::dropIfExists('master_fields');
    }
};
