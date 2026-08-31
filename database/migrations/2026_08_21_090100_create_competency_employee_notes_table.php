<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's `2026_07_28_140000_create_competency_employee_notes_table.php`.
 *
 * Private per-employee notes for the Employee Competency Profile "Notes"
 * panel. One row per (tenant, employee).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('s_competency_employee_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('user_id')->index(); // employee (tbluser.id)
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s_competency_employee_notes');
    }
};
