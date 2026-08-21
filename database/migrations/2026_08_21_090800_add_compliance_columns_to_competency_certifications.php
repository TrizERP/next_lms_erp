<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's
 * `2026_07_29_110100_add_compliance_columns_to_competency_certifications.php`.
 *
 * Additive columns on s_competency_certifications for the Certification &
 * Compliance Center:
 *  - certification_type   -> the "Certification Type" filter + field.
 *  - verification_status  -> the "Pending Verification" KPI + Verify/Reject.
 *  - verified_by/_at      -> who signed the credential off and when.
 *  - notes                -> the Overview panel's Notes block.
 *  - requirement_id       -> links a held credential to the requirement it
 *                            satisfies (s_competency_certification_requirements).
 *
 * Purely additive and nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('s_competency_certifications', function (Blueprint $table) {
            if (!Schema::hasColumn('s_competency_certifications', 'certification_type')) {
                $table->string('certification_type', 100)->nullable()->index()->after('issuing_body');
            }
            if (!Schema::hasColumn('s_competency_certifications', 'verification_status')) {
                // pending | verified | rejected
                $table->string('verification_status', 30)->nullable()->index()->after('status');
            }
            if (!Schema::hasColumn('s_competency_certifications', 'verified_by')) {
                $table->unsignedBigInteger('verified_by')->nullable()->after('verification_status');
            }
            if (!Schema::hasColumn('s_competency_certifications', 'verified_at')) {
                $table->dateTime('verified_at')->nullable()->after('verified_by');
            }
            if (!Schema::hasColumn('s_competency_certifications', 'notes')) {
                $table->text('notes')->nullable()->after('expiry_date');
            }
            if (!Schema::hasColumn('s_competency_certifications', 'requirement_id')) {
                $table->unsignedBigInteger('requirement_id')->nullable()->index()->after('competency_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('s_competency_certifications', function (Blueprint $table) {
            foreach (['certification_type', 'verification_status', 'verified_by', 'verified_at', 'notes', 'requirement_id'] as $column) {
                if (Schema::hasColumn('s_competency_certifications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
