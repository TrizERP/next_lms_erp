<?php

use App\Services\EmailTemplateService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes monospace wrapping around placeholders in saved templates.
 *
 * The rich-text editor treats "<< session >>" as code and wraps it in <code> or
 * a monospace span. The merged value inherits that font, so the word arrives in
 * the mail in a different typeface from the sentence around it. New saves are
 * cleaned by the controller; this repairs what is already stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')
            ->select('id', 'subject', 'html_content')
            ->orderBy('id')
            ->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    $html = EmailTemplateService::stripPlaceholderFormatting($row->html_content);
                    $subject = EmailTemplateService::stripPlaceholderFormatting($row->subject);

                    if ($html === $row->html_content && $subject === $row->subject) {
                        continue;
                    }

                    DB::table('email_templates')->where('id', $row->id)->update([
                        'html_content' => $html,
                        'subject'      => $subject,
                        'updated_at'   => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Re-adding the monospace wrapper would be reintroducing the defect.
    }
};
