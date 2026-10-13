<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase F: bulk import of alumni from CSV / XLSX, in four steps so nothing is written to the alumni table until a person
 * has seen the result: upload (the file is stored privately) -> map columns -> preview (every row validated and checked for
 * duplicates, still nothing written) -> confirm.
 *
 * The uploaded file holds personal data, so it is deleted as soon as the batch is confirmed or cancelled, and abandoned
 * uploads are cleaned up by `alumni:run import-purge`. Row data is kept only for rows that still need attention (errors,
 * duplicates, failures) so they can be corrected and re-uploaded; the scheduled purge clears it after 14 days.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alumni_import_batches')) {
            Schema::create('alumni_import_batches', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->string('original_name', 255);
                $t->string('stored_path', 255)->nullable();                // null once the file has been deleted
                $t->string('file_hash', 64);                               // sha256: the same file cannot be confirmed twice
                $t->string('file_type', 5);                                // csv|xlsx
                $t->string('status', 15)->default('uploaded');             // uploaded|previewed|importing|completed|cancelled
                $t->unsignedInteger('total_rows')->default(0);
                $t->text('headers')->nullable();                           // JSON
                $t->text('mapping')->nullable();                           // JSON: field => column index
                $t->unsignedInteger('valid_count')->default(0);
                $t->unsignedInteger('error_count')->default(0);
                $t->unsignedInteger('duplicate_count')->default(0);
                $t->unsignedInteger('imported_count')->default(0);
                $t->unsignedInteger('skipped_count')->default(0);
                $t->unsignedInteger('failed_count')->default(0);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('confirmed_by')->nullable();
                $t->dateTime('confirmed_at')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'status'], 'alumni_imp_status_ix');
                $t->index(['sub_institute_id', 'file_hash'], 'alumni_imp_hash_ix');
            });
        }

        if (! Schema::hasTable('alumni_import_rows')) {
            Schema::create('alumni_import_rows', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('batch_id');
                $t->unsignedInteger('row_no');                             // the row number in the user's file (header = row 1)
                $t->string('status', 12)->default('pending');              // pending|valid|error|duplicate|imported|skipped|failed
                $t->text('data')->nullable();                              // JSON of the normalised values; cleared by the purge
                $t->text('errors')->nullable();                            // JSON: field => [messages]
                $t->unsignedBigInteger('duplicate_of')->nullable();        // the existing alumnus it matched
                $t->string('note', 255)->nullable();                       // "Repeats row 4", "Matched on email", ...
                $t->unsignedBigInteger('alumni_id')->nullable();           // the record created
                $t->timestamps();
                $t->index(['batch_id', 'status'], 'alumni_improw_batch_ix');
                $t->unique(['batch_id', 'row_no'], 'alumni_improw_uq');
            });
        }
    }

    public function down(): void
    {
        // Forward-only: import history is part of the audit trail and is never dropped by a rollback.
    }
};
