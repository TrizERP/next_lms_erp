<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records that a teacher confirmed WHOSE answer sheet this is.
 *
 * `identity_source` already says how the sheet was matched (enrollment_no,
 * roll_no, name, manual, unmatched), and `manual` already means "a teacher chose
 * this student". What no existing column can say is that a teacher looked at an
 * AI-suggested match and agreed with it: the suggestion and the confirmation of
 * it are two different facts, and overwriting `identity_source` with "manual"
 * would throw away HOW the match was made.
 *
 * Without this, a re-read of the scan cannot tell a confirmed match from a
 * suggestion, and would be free to replace the confirmed student with whatever
 * the matcher now prefers.
 *
 *  - identity_confirmed_at / identity_confirmed_by -- set when a teacher
 *    confirms the suggested student or picks one by hand; cleared whenever the
 *    assignment changes. NULL means "still only a suggestion".
 *
 * Both are nullable and nothing is back-filled: a sheet a teacher assigned by
 * hand before this existed is already recognised as confirmed through
 * `identity_source = 'manual'`, and a suggestion nobody has confirmed should
 * stay unconfirmed.
 *
 * New sheet STATUS values ('Identifying', 'Awaiting identity') and new
 * `identity_source` values need no schema change: both are plain string(30)
 * columns, not enums.
 *
 * Guarded, so running it twice or against a partly migrated database is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exam_evaluation_sheet')) {
            return;
        }

        // Placed next to `identity_source` for readability, but only when that column is
        // there to anchor to: MySQL refuses AFTER a column that does not exist, and a
        // cosmetic position is not worth failing a migration over.
        $anchored = Schema::hasColumn('exam_evaluation_sheet', 'identity_source');

        Schema::table('exam_evaluation_sheet', function (Blueprint $table) use ($anchored) {
            if (! Schema::hasColumn('exam_evaluation_sheet', 'identity_confirmed_by')) {
                $column = $table->unsignedBigInteger('identity_confirmed_by')->nullable();

                if ($anchored) {
                    $column->after('identity_source');
                }
            }

            if (! Schema::hasColumn('exam_evaluation_sheet', 'identity_confirmed_at')) {
                $column = $table->timestamp('identity_confirmed_at')->nullable();

                if ($anchored) {
                    $column->after('identity_source');
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('exam_evaluation_sheet')) {
            return;
        }

        foreach (['identity_confirmed_at', 'identity_confirmed_by'] as $column) {
            if (Schema::hasColumn('exam_evaluation_sheet', $column)) {
                Schema::table('exam_evaluation_sheet', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
