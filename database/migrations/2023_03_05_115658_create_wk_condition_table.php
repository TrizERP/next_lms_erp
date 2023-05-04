<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wk_condition', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('main_id')->nullable();
            $table->integer('module_id')->nullable();
            $table->string('condition', 250)->nullable();
            $table->string('compare_value', 250)->nullable();
            $table->string('condition_type', 250)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wk_condition');
    }
};
