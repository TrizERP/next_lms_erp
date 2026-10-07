<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every save of a document template snapshots the PREVIOUS content here, so a
 * designer can roll a template back after a bad edit. Rows are removed with the
 * parent template (cascade) — a version has no meaning on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_template_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_template_id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('name', 255);
            $table->longText('content');
            $table->unsignedInteger('version');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('document_template_id', 'doc_tpl_version_template_fk')
                ->references('id')
                ->on('document_templates')
                ->onDelete('cascade');

            $table->index(['document_template_id', 'version'], 'doc_tpl_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_template_versions');
    }
};
