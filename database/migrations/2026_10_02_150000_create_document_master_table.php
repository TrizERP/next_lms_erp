<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('document_master', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sub_institute_id')->default(1)->index();
            $table->string('title', 255);
            $table->string('original_file_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum_sha256', 64)->index();
            $table->string('storage_path', 500);
            $table->string('preview_path', 500)->nullable();
            $table->unsignedInteger('current_version')->default(1);

            // Classification & Metadata
            $table->string('document_type', 100)->nullable()->index();
            $table->string('category', 100)->nullable();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('subject', 255)->nullable();
            $table->date('document_date')->nullable();
            $table->string('academic_year', 20)->nullable()->index();
            $table->string('organization', 255)->nullable();
            $table->string('project', 255)->nullable();
            $table->enum('lifecycle_status', ['active', 'expired', 'archived', 'filed'])->default('active')->index();
            $table->text('summary')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();

            // Extracted entities and text
            $table->json('people')->nullable();
            $table->json('keywords')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->text('tags_text')->nullable();

            // Tags
            $table->json('tags')->nullable();
            $table->json('tag_names')->nullable();

            // Embedding for semantic search (LONGBLOB / JSON)
            $table->binary('embedding')->nullable();

            // Access Control & Principals
            $table->unsignedBigInteger('owner_id')->index();
            $table->enum('visibility', ['private', 'department', 'organization'])->default('organization');
            $table->json('view_principals')->nullable();
            $table->json('permissions')->nullable();

            // Processing Pipeline status
            $table->enum('processing_status', ['pending', 'processing', 'ready_for_review', 'done', 'failed'])->default('pending')->index();
            $table->text('processing_error')->nullable();
            $table->json('warnings')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Add FullText indexes for MariaDB / MySQL
        // Check if ngram parser is supported, fallback to regular FULLTEXT if not.
        try {
            DB::statement('ALTER TABLE document_master ADD FULLTEXT idx_doc_fulltext (title, original_file_name, tags_text, subject, organization) WITH PARSER ngram');
            DB::statement('ALTER TABLE document_master ADD FULLTEXT idx_doc_extracted_text (extracted_text) WITH PARSER ngram');
        } catch (\Throwable $e) {
            // Fallback to standard fulltext if ngram is not loaded in this engine
            DB::statement('ALTER TABLE document_master ADD FULLTEXT idx_doc_fulltext (title, original_file_name, tags_text, subject, organization)');
            DB::statement('ALTER TABLE document_master ADD FULLTEXT idx_doc_extracted_text (extracted_text)');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('document_master');
    }
};
