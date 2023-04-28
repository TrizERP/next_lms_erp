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
        Schema::create('wk_main', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('modulename', 250)->nullable();
            $table->string('description', 250)->nullable();
            $table->integer('execute_id')->nullable();
            $table->string('status', 50)->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->integer('created_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wk_main');
    }
};
