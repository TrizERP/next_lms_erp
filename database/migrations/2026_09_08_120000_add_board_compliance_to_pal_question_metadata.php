<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completes the BOARD_COMPLIANCE half of an item's dual fitness.
 *
 * pal_question_metadata already modelled PAL calibration in full — irt_a/b/c,
 * discrimination_index, response_count, psychometrics_derived_at — and carried
 * only three board fields (board, grade_band, stage). That is not enough to
 * place an item in a paper: a board blueprint is written as "20 marks of short
 * answer, 15 of case-based", so an item with no category and no marks cannot be
 * selected for one however well calibrated it is.
 *
 * The point of the split is that these are DIFFERENT fitness criteria over the
 * same row, not two grades of quality. An item can be:
 *
 *   - calibrated but not board-fit  (great discrimination, no blueprint category)
 *   - board-fit but not calibrated  (a valid 3-mark short answer nobody has sat)
 *   - both, or neither
 *
 * which is why they are separate columns and separate predicates rather than
 * one "quality" score. See QuestionMetadata::palCalibration() /
 * boardCompliance().
 *
 * All nullable and not backfilled: existing rows predate the vocabulary, and a
 * guessed blueprint category would put an item into a real exam paper on the
 * strength of an assumption.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pal_question_metadata')) {
            return;
        }

        Schema::table('pal_question_metadata', function (Blueprint $table) {
            if (! Schema::hasColumn('pal_question_metadata', 'blueprint_category')) {
                $table->string('blueprint_category', 32)->nullable()->after('board');
            }

            if (! Schema::hasColumn('pal_question_metadata', 'marks')) {
                // Decimal, not integer: half marks are ordinary in Indian
                // board papers.
                $table->decimal('marks', 4, 2)->nullable()->after('blueprint_category');
            }

            if (! Schema::hasColumn('pal_question_metadata', 'learning_outcome_ref')) {
                // -> lms_learning_outcomes.id. No FK: pal_* tables reference the
                // legacy LMS estate by id throughout without constraining it,
                // and an outcome can be retired while tagged rows remain.
                $table->unsignedBigInteger('learning_outcome_ref')->nullable()->after('marks');
            }
        });

        // Guarded like the column adds above it. Without this, a re-run after a
        // partial failure dies on "Duplicate key name" — the column checks pass
        // and the index add does not, so the migration can never complete.
        if (! $this->hasIndex('pal_question_metadata', 'pal_question_board_blueprint_idx')) {
            Schema::table('pal_question_metadata', function (Blueprint $table) {
                // The query this serves is "items for THIS board's blueprint",
                // never blueprint category across every board at once.
                $table->index(['board', 'blueprint_category'], 'pal_question_board_blueprint_idx');
            });
        }
    }

    /**
     * Laravel's schema builder has no hasIndex(), so ask the database.
     *
     * Deliberately a plain SHOW INDEX rather than the Doctrine schema manager,
     * which needs doctrine/dbal installed; this estate is MySQL throughout.
     */
    private function hasIndex(string $table, string $index): bool
    {
        foreach (DB::select("SHOW INDEX FROM `{$table}`") as $row) {
            if (($row->Key_name ?? null) === $index) {
                return true;
            }
        }

        return false;
    }

    public function down(): void
    {
        if (! Schema::hasTable('pal_question_metadata')) {
            return;
        }

        Schema::table('pal_question_metadata', function (Blueprint $table) {
            $table->dropIndex('pal_question_board_blueprint_idx');
        });

        Schema::table('pal_question_metadata', function (Blueprint $table) {
            foreach (['blueprint_category', 'marks', 'learning_outcome_ref'] as $column) {
                if (Schema::hasColumn('pal_question_metadata', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
