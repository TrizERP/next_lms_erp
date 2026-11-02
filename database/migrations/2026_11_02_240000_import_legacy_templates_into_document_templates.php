<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copies the old ERP's templates (`template_master`, the Template Management screen) into
 * `document_templates`, so they show in the new Document Templates gallery
 * (/modules/document-templates/operations).
 *
 * - A copy only: `template_master` is not changed.
 * - Per school: each row keeps its sub_institute_id. School 0 (the shared defaults) is skipped.
 * - Only active rows with HTML content. AI, testing, study-deck and SMS template rows are skipped:
 *   they are not printable documents.
 * - Category is mapped from the old module name; anything unrecognised goes to "general".
 * - Safe to run again: a copy is recognised by the "template_master #<id>" note in its description.
 */
return new class extends Migration
{
    private const SKIP_MODULES = ['AI', 'testing', 'study_deck', 'SMS Template'];
    private const MARK = 'template_master #';

    public function up(): void
    {
        if (! Schema::hasTable('template_master') || ! Schema::hasTable('document_templates')) {
            return;
        }

        $already = DB::table('document_templates')
            ->where('description', 'like', 'Imported from the old ERP template ('.self::MARK.'%')
            ->pluck('description')
            ->map(fn ($d) => preg_match('/'.preg_quote(self::MARK, '/').'(\d+)/', (string) $d, $m) ? (int) $m[1] : 0)
            ->flip();

        DB::table('template_master')
            ->where('sub_institute_id', '>', 0)
            ->where('status', 1)
            ->whereNotIn('module_name', self::SKIP_MODULES)
            ->whereNotNull('html_content')
            ->where('html_content', '!=', '')
            ->orderBy('id')
            ->chunkById(25, function ($rows) use ($already) {
                $insert = [];
                foreach ($rows as $row) {
                    if (isset($already[(int) $row->id])) {
                        continue;
                    }
                    $stamp = $row->created_on ?: now();
                    $insert[] = [
                        'sub_institute_id' => (int) $row->sub_institute_id,
                        'name' => mb_substr(trim((string) ($row->title ?: $row->module_name)), 0, 255),
                        'category' => $this->category((string) $row->module_name),
                        'description' => 'Imported from the old ERP template ('.self::MARK.$row->id.', '.$row->module_name.').',
                        'content' => $row->html_content,
                        'version' => 1,
                        'status' => 'published',
                        'syear' => null,
                        'created_by' => $row->created_by ? (int) $row->created_by : null,
                        'updated_by' => null,
                        'created_at' => $stamp,
                        'updated_at' => $stamp,
                    ];
                }
                if ($insert !== []) {
                    DB::table('document_templates')->insert($insert);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        DB::table('document_templates')
            ->where('description', 'like', 'Imported from the old ERP template ('.self::MARK.'%')
            ->delete();
    }

    private function category(string $module): string
    {
        $m = strtolower($module);

        return match (true) {
            str_contains($m, 'certificate'), str_contains($m, 'bonafide') => 'certificate',
            str_contains($m, 'fees') => 'fees',
            str_contains($m, 'result') => 'exam',
            default => 'general',
        };
    }
};
