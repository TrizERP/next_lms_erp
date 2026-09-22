<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renames the admission letter attachment to "Admission Confirmation.pdf".
 *
 * Only touches rows still carrying the old generated default, so a name an
 * admin has since set by hand is left alone.
 */
return new class extends Migration
{
    private const OLD_NAME = 'Admission_Letter_<< enquiry_no >>.pdf';
    private const NEW_NAME = 'Admission Confirmation.pdf';

    public function up(): void
    {
        if (!Schema::hasTable('email_templates') || !Schema::hasColumn('email_templates', 'pdf_filename')) {
            return;
        }

        DB::table('email_templates')
            ->where('pdf_filename', self::OLD_NAME)
            ->update(['pdf_filename' => self::NEW_NAME]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('email_templates') || !Schema::hasColumn('email_templates', 'pdf_filename')) {
            return;
        }

        DB::table('email_templates')
            ->where('pdf_filename', self::NEW_NAME)
            ->update(['pdf_filename' => self::OLD_NAME]);
    }
};
