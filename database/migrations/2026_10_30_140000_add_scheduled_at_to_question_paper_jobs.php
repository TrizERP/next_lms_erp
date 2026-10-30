<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_paper_jobs', function (Blueprint $table) {
            $table->dateTime('scheduled_at')->nullable()->after('academic_year');
            $table->index(['scheduled_at', 'status'], 'idx_qpj_scheduled');
        });
    }

    public function down(): void
    {
        Schema::table('question_paper_jobs', function (Blueprint $table) {
            $table->dropIndex('idx_qpj_scheduled');
            $table->dropColumn('scheduled_at');
        });
    }
};
