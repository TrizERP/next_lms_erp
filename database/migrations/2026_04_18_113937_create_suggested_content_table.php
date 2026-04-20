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
        Schema::create('suggested_content', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ENUM type
            $table->enum('type', ['exam_content', 'pal_content', 'question'])->nullable();

            $table->bigInteger('type_id')->nullable();

            $table->string('student_level')->nullable();

            $table->bigInteger('standard_id')->nullable();
            $table->bigInteger('subject_id')->nullable();
            $table->bigInteger('chapter_id')->nullable();

            $table->bigInteger('sub_institute_id')->nullable();
            $table->bigInteger('syear')->nullable();

            $table->bigInteger('student_id')->nullable();

            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->bigInteger('deleted_by')->nullable();

            $table->timestamps(); // created_at & updated_at
            $table->softDeletes(); // deleted_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void        
    {
        Schema::dropIfExists('suggested_content');
    }
};
