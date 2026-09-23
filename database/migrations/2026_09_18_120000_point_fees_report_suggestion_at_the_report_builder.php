<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes "Pending fees report" build an actual report.
 *
 * WHY
 *
 * The suggestion was `action_type = generate`, which is the prose path: it renders a
 * template and hands back text to read and copy. That is right for the three summary
 * actions beside it and wrong for this one, which a school expects to produce a
 * *document* — the thing you preview, edit, refresh, print and send to a family.
 *
 * `/ai-reports/{id}` already does all five, and `AiReportGenerator` already builds fees
 * reports from the module's published layout. Neither was reachable from the assistant,
 * because nothing in the panel ever produced a report to open. This points the action at
 * the report builder so the two halves meet.
 *
 * WHAT DOES NOT CHANGE
 *
 * The other three fees Create actions stay on `generate`, because prose is what they are
 * for. `k12.fees.pending_report` stays published in `ai_templates` — the report builder
 * resolves its layout by module rather than by template key, so nothing references the
 * row any less than it did, and a school that re-points this suggestion back gets the
 * old behaviour with one column.
 *
 * `action_ref` is cleared rather than repointed: a report action names no template, and
 * leaving a stale key there would suggest one is consulted when none is.
 */
return new class extends Migration
{
    private const LABEL = 'Pending fees report';

    public function up(): void
    {
        $this->set('report', null);
    }

    public function down(): void
    {
        $this->set('generate', 'k12.fees.pending_report');
    }

    private function set(string $actionType, ?string $actionRef): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        DB::table('ai_suggestions')
            ->where('module_key', 'fees')
            ->where('label', self::LABEL)
            ->update([
                'action_type' => $actionType,
                'action_ref' => $actionRef,
                'updated_at' => now(),
            ]);
    }
};
