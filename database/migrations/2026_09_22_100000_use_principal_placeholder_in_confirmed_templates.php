<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the principal's signature follow the session.
 *
 * Morning (C) is signed by Mr. Ajay Singh Chauhan and Afternoon (C/A) by
 * Mrs. Rehana Patni, so the name becomes the << principal >> placeholder rather
 * than fixed text. Scoped to admission_confirmed: that is the only event whose
 * status distinguishes the two sessions, so it is the only one where the
 * placeholder can resolve.
 */
return new class extends Migration
{
    /** Names previously printed as the confirming principal. */
    private const OLD_NAMES = [
        'Mr. Ajay Singh Chauhan',
        'Mrs. Rehana Patni',
        'Mr.P.P.Jose',
        'Mr. P.P. Jose',
        'Mr.P.P. Jose',
        'Mr. P.P.Jose',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }

        $rows = DB::table('email_templates')
            ->where('event_key', 'admission_confirmed')
            ->get(['id', 'html_content']);

        foreach ($rows as $row) {
            $html = str_replace(self::OLD_NAMES, '<< principal >>', (string) $row->html_content);

            // Collapse the case where a template already listed both names.
            $html = preg_replace('/(<< principal >>)(\s*\/\s*<< principal >>)+/', '$1', $html);

            if ($html === $row->html_content) {
                continue;
            }

            DB::table('email_templates')->where('id', $row->id)->update([
                'html_content' => $html,
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')
            ->where('event_key', 'admission_confirmed')
            ->get(['id', 'html_content'])
            ->each(function ($row) {
                DB::table('email_templates')->where('id', $row->id)->update([
                    'html_content' => str_replace('<< principal >>', 'Mr. Ajay Singh Chauhan', (string) $row->html_content),
                    'updated_at'   => now(),
                ]);
            });
    }
};
