<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compliance Management foundation, phase 1c: multi-document evidence,
 * replacing the single `org_compliance_library.attachment` string column
 * (kept in place, unmodified - see the sibling migration's docblock) with a
 * proper one-to-many table, each row independently trackable and
 * verifiable per the product brief (uploaded by/date, expiry, verification
 * status/reason).
 *
 * FK target `org_compliance_library.id` is a `bigIncrements` (bigint
 * unsigned) - same type-matched-FK reasoning already used successfully for
 * `org_disciplinary_library.department_id` -> `hrms_departments.id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('compliance_evidence')) {
            Schema::create('compliance_evidence', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('compliance_id')->index();
                $table->unsignedBigInteger('sub_institute_id')->index();

                $table->string('file_path', 255);
                $table->string('file_name', 255)->nullable();
                $table->string('document_type', 100)->nullable();
                $table->text('description')->nullable();
                $table->date('expiry_date')->nullable();

                $table->string('verification_status', 30)->default('Pending Verification')->index();
                $table->unsignedBigInteger('verified_by')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->text('rejection_reason')->nullable();

                $table->unsignedBigInteger('uploaded_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->foreign('compliance_id', 'compliance_evidence_compliance_id_foreign')
                    ->references('id')->on('org_compliance_library')
                    ->onDelete('CASCADE')->onUpdate('NO ACTION');
            });
        }

        $this->backfillFromLegacyAttachment();
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_evidence');
    }

    /**
     * Every existing row's single `attachment` becomes its first evidence
     * document. Marked `Verified` rather than `Pending Verification` -
     * these files were already in active use with no verification gate
     * before this table existed, so flagging all of them as newly
     * needing review would be a false, noisy signal on day one.
     *
     * Idempotent via a `NOT EXISTS` guard (matches this table having no
     * natural unique key to insertOrIgnore against).
     */
    private function backfillFromLegacyAttachment(): void
    {
        $now = now();

        DB::statement(
            <<<'SQL'
            INSERT INTO compliance_evidence
                (compliance_id, sub_institute_id, file_path, file_name, verification_status, uploaded_by, created_at, updated_at)
            SELECT
                c.id,
                c.sub_institute_id,
                CONCAT('compliance_library/', c.attachment),
                c.attachment,
                'Verified',
                c.created_by,
                ?,
                ?
            FROM org_compliance_library c
            WHERE c.attachment IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM compliance_evidence e
                  WHERE e.compliance_id = c.id AND e.file_name = c.attachment
              )
            SQL,
            [$now, $now]
        );
    }
};
