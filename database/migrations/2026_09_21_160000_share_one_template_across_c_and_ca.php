<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merges the separate C and C/A admission-confirmed templates into one.
 *
 * The only difference between them was the word Morning / Afternoon, which is
 * now the << session >> placeholder, resolved per status code at send time. The
 * surviving row has its status cleared so it answers for both, and the other is
 * deactivated rather than deleted so nothing an admin wrote is destroyed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }

        $pairs = DB::table('email_templates')
            ->where('event_key', 'admission_confirmed')
            ->whereIn('status_code', ['C', 'C/A'])
            ->orderBy('id')
            ->get()
            ->groupBy(function ($row) {
                // Rows only merge when they cover the same audience.
                return $row->sub_institute_id . '|' . ($row->standard_ids ?? '') . '|' . ($row->is_letter ?? 0);
            });

        foreach ($pairs as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $keep = $group->firstWhere('status_code', 'C') ?: $group->first();

            DB::table('email_templates')->where('id', $keep->id)->update([
                'status_code'  => null,
                'name'         => $this->sharedName($keep->name),
                'html_content' => $this->useSessionPlaceholder($keep->html_content),
                'subject'      => $this->useSessionPlaceholder($keep->subject),
                'remarks'      => trim((string) $keep->remarks . ' Shared by C and C/A via << session >>.'),
                'updated_at'   => now(),
            ]);

            foreach ($group as $row) {
                if ($row->id === $keep->id) {
                    continue;
                }

                DB::table('email_templates')->where('id', $row->id)->update([
                    'status'     => 0,
                    'remarks'    => trim((string) $row->remarks . ' Superseded by template #' . $keep->id . ' (C and C/A now share one layout).'),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Re-splitting would have to guess which wording belonged to which
        // status, so the merge is left in place and the old rows stay available
        // to reactivate by hand.
    }

    private function useSessionPlaceholder(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return preg_replace('/\b(Morning|Afternoon)(\s*Session)/i', '<< session >>$2', $html);
    }

    private function sharedName(?string $name): string
    {
        $name = trim(preg_replace('/\s*-\s*(Morning|Afternoon)\s*Session\s*$/i', '', (string) $name));

        return $name !== '' ? $name . ' - Morning / Afternoon' : 'Admission Confirmed - Morning / Afternoon';
    }
};
