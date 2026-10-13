<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attempt ordinal for the PAL chapter diagnostic ("1st attempt", "2nd
 * attempt", ...), counting submitted sittings only. Frozen by
 * DiagnosticService::submit() at scoring time - never recomputed on read,
 * the same convention this table already uses for every other scored field.
 *
 * Unlike the original pal_diagnostic_attempt/pal_diagnostic_response
 * migrations, this one is NOT a no-op: the column does not exist on any
 * environment yet, so this really does alter the live table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pal_diagnostic_attempt', 'attempt_number')) {
            return;
        }

        Schema::table('pal_diagnostic_attempt', function (Blueprint $table) {
            $table->unsignedInteger('attempt_number')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('pal_diagnostic_attempt', 'attempt_number')) {
            Schema::table('pal_diagnostic_attempt', function (Blueprint $table) {
                $table->dropColumn('attempt_number');
            });
        }
    }
};
