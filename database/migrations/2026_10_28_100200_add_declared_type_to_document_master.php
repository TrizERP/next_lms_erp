<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the uploader said the document is (question_paper | curriculum | general | textbook).
 * Kept apart from document_type, which the AI classifier overwrites with its own free-text label.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_master') && !Schema::hasColumn('document_master', 'declared_type')) {
            Schema::table('document_master', function (Blueprint $table) {
                $table->string('declared_type', 24)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('document_master') && Schema::hasColumn('document_master', 'declared_type')) {
            Schema::table('document_master', function (Blueprint $table) {
                $table->dropColumn('declared_type');
            });
        }
    }
};
