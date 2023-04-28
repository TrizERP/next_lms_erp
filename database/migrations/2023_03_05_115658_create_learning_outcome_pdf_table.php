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
        Schema::create('learning_outcome_pdf', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->string('MEDIUM', 50)->nullable();
            $table->string('STANDARD', 200)->nullable();
            $table->string('SUBJECTS', 200)->nullable();
            $table->string('DISPLAY_SUBJECT', 200)->nullable();
            $table->string('PDF_FILE_NAME', 200)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('learning_outcome_pdf');
    }
};
