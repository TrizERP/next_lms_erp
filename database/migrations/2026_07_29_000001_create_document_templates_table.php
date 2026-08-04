<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document Templates — the tenant-scoped store behind the drag-and-drop
 * template designer (/document-templates in the Next frontend).
 *
 * `content` holds the Craft.js serialized document (JSON string), not HTML, so
 * a saved template can be re-opened in the designer losslessly. Rendering to
 * PDF/PNG happens client-side from the same JSON.
 *
 * Deliberately a NEW table rather than an extension of the legacy
 * `template_master` (raw `html_content`, no versions, no category): that table
 * is still read by the Blade-era screens and must keep working untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('name', 255);
            $table->string('category', 60)->default('general');
            $table->string('description', 500)->nullable();
            $table->longText('content');
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->string('syear', 20)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['sub_institute_id', 'category'], 'doc_tpl_tenant_category_idx');
            $table->index(['sub_institute_id', 'status'], 'doc_tpl_tenant_status_idx');
            $table->index('name', 'doc_tpl_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
