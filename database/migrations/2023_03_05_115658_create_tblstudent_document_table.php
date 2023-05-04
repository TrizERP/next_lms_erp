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
        Schema::create('tblstudent_document', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('student_id');
            $table->integer('document_type_id');
            $table->string('document_title', 250);
            $table->string('file_name', 250);
            $table->integer('sub_institute_id');
            $table->timestamp('created_on')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tblstudent_document');
    }
};
