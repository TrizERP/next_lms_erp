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
    public function up(): void
    {
        Schema::create('document_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->enum('entry_type', ['version', 'audit']);
            $table->string('action', 50);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();

            // Version columns
            $table->unsignedInteger('version_number')->nullable();
            $table->string('storage_path', 500)->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('change_note', 500)->nullable();

            // Audit details context
            $table->json('details')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Foreign Key and Indexes
            $table->foreign('document_id')->references('id')->on('document_master')->onDelete('cascade');
            $table->index(['document_id', 'entry_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('document_history');
    }
};
