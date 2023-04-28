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
        Schema::create('learning_outcome_indicator', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->string('MEDIUM', 50)->nullable()->index('MEDIUM');
            $table->string('STANDARD', 50)->nullable()->index('STANDARD');
            $table->string('SUBJECT', 50)->nullable()->index('SUBJECT');
            $table->mediumText('INDICATOR')->nullable();
            $table->timestamp('CREATED_AT')->nullable()->useCurrent();
            $table->dateTime('UPDATED_AT')->nullable();
            $table->string('CREATED_BY', 50)->nullable();
            $table->string('UPDATED_BY', 50)->nullable();

            $table->index(['ID'], 'ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('learning_outcome_indicator');
    }
};
