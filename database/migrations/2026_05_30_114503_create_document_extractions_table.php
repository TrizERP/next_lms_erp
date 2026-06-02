<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_extractions', function (Blueprint $table) {
            $table->increments('id');

            $table->string('document_type')->nullable();
            $table->string('document_tittle')->nullable();

            $table->integer('chapter_number')->nullable();
            $table->integer('standard')->nullable();

            $table->string('subject_name')->nullable();
            $table->string('board')->nullable();
            $table->string('syear')->nullable();

            $table->text('pdf_url')->nullable();
            $table->longText('md_content')->nullable();

            $table->json('json_content')->nullable();

            $table->integer('page_count')->nullable();
            $table->integer('image_extracted')->nullable();

            $table->json('extraction_metadata')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_extractions');
    }
};