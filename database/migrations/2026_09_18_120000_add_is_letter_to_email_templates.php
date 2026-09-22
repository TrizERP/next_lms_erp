<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separates the two roles a template can play.
 *
 * A template saved only to be attached as the PDF letter must not compete to be
 * the mail body. Without this flag a letter saved against specific standards
 * outranked the covering note (standard match scores above status match), so
 * the whole letter went out inline and no PDF was attached.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }

        if (!Schema::hasColumn('email_templates', 'is_letter')) {
            Schema::table('email_templates', function (Blueprint $table) {
                $table->tinyInteger('is_letter')->default(0)->after('attach_as_pdf');
            });
        }

        // Anything already referenced as another template's PDF letter is one.
        $referenced = DB::table('email_templates')
            ->whereNotNull('pdf_template_id')
            ->pluck('pdf_template_id')
            ->filter()
            ->unique()
            ->all();

        if (!empty($referenced)) {
            DB::table('email_templates')->whereIn('id', $referenced)->update(['is_letter' => 1]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates') && Schema::hasColumn('email_templates', 'is_letter')) {
            Schema::table('email_templates', function (Blueprint $table) {
                $table->dropColumn('is_letter');
            });
        }
    }
};
