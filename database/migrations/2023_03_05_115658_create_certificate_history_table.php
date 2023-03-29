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
            $table->increments('id');
            $table->string('certificate_type', 255);
            $table->unsignedInteger('syear');
            $table->string('student_id', 255);
            $table->string('sub_institute_id', 255);
            $table->string('certificate_number', 255)->nullable();
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
