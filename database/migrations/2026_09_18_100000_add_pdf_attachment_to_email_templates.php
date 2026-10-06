<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a template send its letter as a PDF attachment instead of as the mail body.
 *
 * The admission-confirmed mail now carries a short covering note in the body
 * ("provisional admission granted in the Morning/Afternoon Session") and the
 * full letter travels as an attached PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }

        Schema::table('email_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('email_templates', 'attach_as_pdf')) {
                $table->tinyInteger('attach_as_pdf')->default(0)->after('html_content');
            }

            if (!Schema::hasColumn('email_templates', 'pdf_template_id')) {
                // Which layout becomes the PDF. NULL = the legacy blade letter
                // registered for this event + standard.
                $table->integer('pdf_template_id')->nullable()->after('attach_as_pdf');
            }

            if (!Schema::hasColumn('email_templates', 'pdf_filename')) {
                $table->string('pdf_filename', 191)->nullable()->after('pdf_template_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }

        Schema::table('email_templates', function (Blueprint $table) {
            foreach (['attach_as_pdf', 'pdf_template_id', 'pdf_filename'] as $column) {
                if (Schema::hasColumn('email_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
