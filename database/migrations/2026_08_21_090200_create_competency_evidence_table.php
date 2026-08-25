<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's `2026_07_28_150000_create_competency_evidence_table.php`.
 *
 * Evidence Repository for the Employee Competency Profile - per-employee
 * proof items (certificates, projects, documents, endorsements) optionally
 * tied to a competency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('s_competency_evidence', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('user_id')->index();          // employee (tbluser.id)
            $table->unsignedBigInteger('competency_id')->nullable()->index(); // s_users_skills.id
            $table->string('title', 191);
            // certification | project | document | endorsement | training
            $table->string('evidence_type', 50)->default('document');
            $table->text('description')->nullable();
            $table->string('link', 500)->nullable();
            // verified | pending
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s_competency_evidence');
    }
};
