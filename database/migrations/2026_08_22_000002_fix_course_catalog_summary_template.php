<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give the course catalogue summary the catalogue.
 *
 * The v1 template asked the model to describe "which grades and categories are covered
 * and roughly how the courses are spread across them" while passing it only the page
 * title, a filter string and a count. The one variable that carries the actual courses —
 * `records`, which the workspace has been building all along — was never in the prompt.
 *
 * With nothing to read, the model did the only thing it could: it reported an empty
 * catalogue. That output looked like a finding about the school and was really a
 * description of an empty prompt, which is the most damaging kind of wrong this platform
 * can produce.
 *
 * v2 passes the rows, and declares `records` as grounding data so that if they are ever
 * empty again the generation is refused outright instead of narrating the absence.
 *
 * Published as a new version rather than an edit: `ai_generation_requests` records the
 * template version it used, and rewriting v1 in place would make already-stored outputs
 * untraceable to the prompt that produced them.
 */
return new class extends Migration
{
    private const KEY = 'k12.course_catalog_summary';

    public function up(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        $existing = DB::table('ai_templates')
            ->where('template_key', self::KEY)
            ->whereNull('sub_institute_id')
            ->orderByDesc('version')
            ->first();

        if (! $existing) {
            return;
        }

        if ((int) $existing->version >= 2) {
            return;
        }

        $now = now();

        DB::table('ai_templates')->insert([
            'template_key' => self::KEY,
            'version' => 2,
            'name' => 'Course catalogue summary',
            'description' => 'A short plain-language overview of the courses currently listed.',
            'domain' => 'k12',
            'category' => 'report',

            'system_prompt' => 'You write short, factual summaries of a school course catalogue for teachers. '
                . 'Use only the courses, grades and categories given to you. Never invent a course, a grade or a count. '
                . 'If the list you were given is a partial view of a larger catalogue, say so. '
                . 'If no courses are listed, say only that you were not given any — never describe the catalogue as empty, '
                . 'because you cannot tell the difference between an empty catalogue and a list that did not reach you.',

            'user_prompt' => "Summarise this course catalogue for a teacher.\n\n"
                . "Page: {{page_title}}\n"
                . "Catalogue scope: {{filters}}\n"
                . "Distinct subjects in the catalogue: {{record_count}}\n"
                . "Subjects shown below: {{rows_shown}}\n"
                . "This is a partial view: {{is_partial}}\n\n"
                . "Totals:\n{{metrics}}\n\n"
                . "Courses:\n{{records}}\n\n"
                . 'Give a short overview: which grades and categories are covered, roughly how the courses are spread '
                . 'across them, and anything a teacher browsing this catalogue would want to know first. '
                . 'If "This is a partial view" is yes, make clear the totals cover the whole catalogue while the list '
                . 'below is a sample. Keep it under 150 words.',

            // `grounding: true` is what the generation layer checks before calling a
            // model. Without records there is nothing to summarise, and the request is
            // refused rather than answered.
            'variables' => json_encode([
                ['key' => 'records', 'label' => 'Courses', 'required' => true, 'type' => 'text', 'grounding' => true],
                ['key' => 'metrics', 'label' => 'Totals', 'required' => false, 'type' => 'text', 'grounding' => true],
                ['key' => 'page_title', 'label' => 'Page title', 'required' => false, 'type' => 'string'],
                ['key' => 'filters', 'label' => 'Grades available', 'required' => false, 'type' => 'string'],
                ['key' => 'record_count', 'label' => 'Total subjects', 'required' => false, 'type' => 'string'],
                ['key' => 'rows_shown', 'label' => 'Subjects listed', 'required' => false, 'type' => 'string'],
                ['key' => 'is_partial', 'label' => 'Partial view', 'required' => false, 'type' => 'string'],
            ]),

            'output_schema' => null,
            'output_format' => 'text',
            'status' => 'published',

            'safety_rules' => json_encode([
                'Do not invent a course, a grade, a category or a count.',
                'Do not describe the catalogue as empty; say only that no courses were provided.',
            ]),

            'allow_as_evidence' => false,
            'requires_review' => false,
            'sub_institute_id' => null,
            'client_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // v1 stays in the table so existing generation requests still resolve to the
        // prompt they actually used, but is archived so nothing new picks it up.
        DB::table('ai_templates')
            ->where('template_key', self::KEY)
            ->whereNull('sub_institute_id')
            ->where('version', 1)
            ->update(['status' => 'archived', 'updated_at' => $now]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        DB::table('ai_templates')
            ->where('template_key', self::KEY)
            ->whereNull('sub_institute_id')
            ->where('version', 2)
            ->delete();

        DB::table('ai_templates')
            ->where('template_key', self::KEY)
            ->whereNull('sub_institute_id')
            ->where('version', 1)
            ->update(['status' => 'published', 'updated_at' => now()]);
    }
};
