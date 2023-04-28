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
        Schema::create('certificate_history', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('certificate_type', 255);
            $table->integer('syear');
            $table->bigInteger('student_id');
            $table->integer('sub_institute_id');
            $table->integer('certificate_number')->nullable();
            $table->mediumText('certificate_html')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('certificate_history');
    }
};
