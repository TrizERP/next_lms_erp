<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add STORED generated columns projected out of the `answer` JSON envelope so
     * exam-paper assembly can filter by bloom/difficulty without parsing LONGTEXT
     * per row. Also adds the hot-path indexes and the C8 idempotency unique index
     * described in the Question Generation Prompt Pack v2.0 (section 6).
     *
     * Legacy hand-authored rows have NULL `answer`, so `g_content_hash` is NULL and
     * the unique index treats each NULL as distinct — existing rows will not collide.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lms_question_master')) {
            return;
        }

        Schema::table('lms_question_master', function (Blueprint $table) {
            if (!Schema::hasColumn('lms_question_master', 'g_bloom')) {
                $table->string('g_bloom', 12)
                    ->nullable()
                    ->after('answer')
                    ->generatedAs("JSON_UNQUOTE(JSON_EXTRACT(`answer`, '$.bloom_level'))")
                    ->stored();
            }
            if (!Schema::hasColumn('lms_question_master', 'g_difficulty')) {
                $table->string('g_difficulty', 8)
                    ->nullable()
                    ->after('g_bloom')
                    ->generatedAs("JSON_UNQUOTE(JSON_EXTRACT(`answer`, '$.difficulty'))")
                    ->stored();
            }
            if (!Schema::hasColumn('lms_question_master', 'g_dok')) {
                $table->unsignedTinyInteger('g_dok')
                    ->nullable()
                    ->after('g_difficulty')
                    ->generatedAs("JSON_EXTRACT(`answer`, '$.dok_level')")
                    ->stored();
            }
            if (!Schema::hasColumn('lms_question_master', 'g_content_hash')) {
                $table->char('g_content_hash', 64)
                    ->nullable()
                    ->after('g_dok')
                    ->generatedAs("JSON_UNQUOTE(JSON_EXTRACT(`answer`, '$.content_hash'))")
                    ->stored();
            }
        });

        // concept_id carries the entire generation + Pal Quiz path and had no index.
        if (!$this->indexExists('idx_qm_concept')) {
            Schema::table('lms_question_master', function (Blueprint $table) {
                $table->index(['concept_id', 'status', 'question_type_id'], 'idx_qm_concept');
            });
        }

        // exam-paper assembly hot path.
        if (!$this->indexExists('idx_qm_blueprint')) {
            Schema::table('lms_question_master', function (Blueprint $table) {
                $table->index(
                    ['chapter_id', 'question_type_id', 'g_bloom', 'g_difficulty', 'status'],
                    'idx_qm_blueprint'
                );
            });
        }

        // C8 idempotency. Global bank: tenant is NOT part of uniqueness.
        // NOTE: this is a one-way door — decide NULL semantics before creating
        // (see prompt pack open question 4).
        if (!$this->indexExists('uq_qm_concept_hash')) {
            Schema::table('lms_question_master', function (Blueprint $table) {
                $table->unique(
                    ['concept_id', 'question_type_id', 'g_content_hash'],
                    'uq_qm_concept_hash'
                );
            });
        }
    }

    /**
     * Index existence check via information_schema (Schema::hasIndex is unavailable
     * in this Laravel version).
     */
    protected function indexExists(string $name): bool
    {
        try {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::connection()->getDatabaseName())
                ->where('table_name', 'lms_question_master')
                ->where('index_name', $name)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('lms_question_master')) {
            return;
        }

        Schema::table('lms_question_master', function (Blueprint $table) {
            if ($this->indexExists('uq_qm_concept_hash')) {
                $table->dropUnique('uq_qm_concept_hash');
            }
            if ($this->indexExists('idx_qm_blueprint')) {
                $table->dropIndex('idx_qm_blueprint');
            }
            if ($this->indexExists('idx_qm_concept')) {
                $table->dropIndex('idx_qm_concept');
            }
        });

        Schema::table('lms_question_master', function (Blueprint $table) {
            if (Schema::hasColumn('lms_question_master', 'g_content_hash')) {
                $table->dropColumn('g_content_hash');
            }
            if (Schema::hasColumn('lms_question_master', 'g_dok')) {
                $table->dropColumn('g_dok');
            }
            if (Schema::hasColumn('lms_question_master', 'g_difficulty')) {
                $table->dropColumn('g_difficulty');
            }
            if (Schema::hasColumn('lms_question_master', 'g_bloom')) {
                $table->dropColumn('g_bloom');
            }
        });
    }
};
