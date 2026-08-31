<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Course Catalog: curated prompts, and a template for the Create tab.
 *
 * The coverage migration gave `course-master` two generic prompts about lesson plans.
 * They are wrong for this page — the catalogue is where a teacher browses what exists,
 * not where they audit planning coverage. These replace them with questions someone
 * actually standing on that screen would ask.
 *
 * The data-specific half — "What courses are available for Grade 5?", "Show me the
 * courses under STEM Resources" — is *not* seeded here, because it depends on which
 * grades and categories that institute actually has. Those are derived at request time
 * from the facets the page publishes, so they stay correct as the catalogue changes and
 * differ correctly between one school and the next.
 *
 * The template gives the Create tab something to offer. Without a published template
 * row the tab resolves to nothing and CapabilityResolver correctly hides it.
 */
return new class extends Migration
{
    private const MODULE = 'course-master';

    private function suggestions(): array
    {
        return [
            // [capability, label, action_type, action_ref, prompt, sort]
            ['conversational', 'What categories are available?', 'prompt', null,
                'What course categories are available in the catalogue, and what is in each one?', 10],
            ['conversational', 'Which courses suit what I teach?', 'prompt', null,
                'Based on the classes and subjects I teach, which courses in this catalogue are most relevant to me?', 20],
            ['conversational', 'Which courses have the most content?', 'prompt', null,
                'Which courses in this catalogue have the most chapters and content available?', 30],
            ['generative', 'Summarise the catalogue', 'generate', 'k12.course_catalog_summary', null, 10],
        ];
    }

    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('ai_modules')) {
            // The catalogue can now offer a Create action, which the coverage migration
            // had no reason to enable.
            $module = DB::table('ai_modules')
                ->where('module_key', self::MODULE)
                ->whereNull('sub_institute_id')
                ->first();

            if ($module) {
                $capabilities = json_decode($module->capabilities, true);
                $capabilities = is_array($capabilities) ? $capabilities : [];
                $capabilities['generative'] = true;

                DB::table('ai_modules')
                    ->where('id', $module->id)
                    ->update([
                        'label' => 'Course Catalog',
                        'description' => 'Browse courses by grade and category.',
                        'capabilities' => json_encode($capabilities),
                        'updated_at' => $now,
                    ]);
            }
        }

        if (Schema::hasTable('ai_suggestions')) {
            // Replace this module's baseline set rather than appending, so the generic
            // lesson-plan prompts do not sit alongside the catalogue ones.
            DB::table('ai_suggestions')
                ->where('module_key', self::MODULE)
                ->whereNull('sub_institute_id')
                ->delete();

            foreach ($this->suggestions() as [$capability, $label, $actionType, $actionRef, $prompt, $sort]) {
                DB::table('ai_suggestions')->insert([
                    'module_key' => self::MODULE,
                    'capability' => $capability,
                    'label' => $label,
                    'description' => null,
                    'icon' => null,
                    'action_type' => $actionType,
                    'action_ref' => $actionRef,
                    'prompt' => $prompt,
                    'payload' => null,
                    'requires_entity' => false,
                    'allowed_roles' => null,
                    'required_permissions' => null,
                    'sort_order' => $sort,
                    'status' => 1,
                    'sub_institute_id' => null,
                    'client_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('ai_templates')) {
            $exists = DB::table('ai_templates')
                ->where('template_key', 'k12.course_catalog_summary')
                ->whereNull('sub_institute_id')
                ->exists();

            if (! $exists) {
                DB::table('ai_templates')->insert([
                    'template_key' => 'k12.course_catalog_summary',
                    'version' => 1,
                    'name' => 'Course catalogue summary',
                    'description' => 'A short plain-language overview of the courses currently listed.',
                    'domain' => 'k12',
                    'category' => 'report',
                    'system_prompt' => 'You write short, factual summaries of a school course catalogue for teachers. '
                        . 'Use only the courses, grades and categories given to you. Never invent a course, a grade or a count. '
                        . 'If the list you were given is a partial view of a larger catalogue, say so.',
                    'user_prompt' => "Summarise this course catalogue for a teacher.\n\n"
                        . "Page: {{page_title}}\n"
                        . "Grades available: {{filters}}\n"
                        . "Courses shown: {{record_count}}\n\n"
                        . 'Give a short overview: which grades and categories are covered, roughly how the courses are spread across them, '
                        . 'and anything a teacher browsing this catalogue would want to know first. Keep it under 150 words.',
                    'output_format' => 'text',
                    'output_schema' => null,
                    'status' => 'published',
                    'sub_institute_id' => null,
                    'client_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_suggestions')) {
            DB::table('ai_suggestions')
                ->where('module_key', self::MODULE)
                ->whereNull('sub_institute_id')
                ->delete();
        }

        if (Schema::hasTable('ai_templates')) {
            DB::table('ai_templates')
                ->where('template_key', 'k12.course_catalog_summary')
                ->whereNull('sub_institute_id')
                ->delete();
        }
    }
};
