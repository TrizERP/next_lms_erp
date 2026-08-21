<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's
 * `2026_07_29_110200_add_certification_link_to_competency_evidence.php`.
 *
 * Lets the Evidence Repository (s_competency_evidence) also serve as the
 * document store for the Certification & Compliance Center, instead of a
 * second near-identical table. Additive and nullable, so the Employee
 * Profile's Evidence tab (the existing consumer) is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('s_competency_evidence', function (Blueprint $table) {
            if (!Schema::hasColumn('s_competency_evidence', 'certification_id')) {
                $table->unsignedBigInteger('certification_id')->nullable()->index()->after('competency_id');
            }
            if (!Schema::hasColumn('s_competency_evidence', 'file_name')) {
                $table->string('file_name', 191)->nullable()->after('link');
            }
            if (!Schema::hasColumn('s_competency_evidence', 'file_path')) {
                $table->string('file_path', 500)->nullable()->after('file_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('s_competency_evidence', function (Blueprint $table) {
            foreach (['certification_id', 'file_name', 'file_path'] as $column) {
                if (Schema::hasColumn('s_competency_evidence', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
