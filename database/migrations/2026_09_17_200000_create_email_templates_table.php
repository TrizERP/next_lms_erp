<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frontend-manageable email templates.
 *
 * Until now the admission mails for Hills High (sub_institute_id 254) lived in
 * hardcoded blade files under resources/views/admission/registrationHills. This
 * table stores the same layouts as editable HTML so an admin can change the
 * wording from the UI without a code deploy. Placeholders follow the existing
 * `template_master` convention: << placeholder >>.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_templates')) {
            return;
        }

        Schema::create('email_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sub_institute_id');
            $table->string('module', 50)->default('admission');
            // Business event this template is used for, e.g. admission_confirmed.
            $table->string('event_key', 100);
            $table->string('name', 191);
            $table->string('subject', 255);
            $table->longText('html_content');
            // CSV of standard ids this template applies to. NULL/empty = all standards.
            $table->string('standard_ids', 500)->nullable();
            // Sub-state of the event, e.g. C, C/A, I, NO, W/L. NULL = any.
            $table->string('status_code', 20)->nullable();
            $table->text('remarks')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();

            $table->index(['sub_institute_id', 'event_key', 'status'], 'email_templates_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
