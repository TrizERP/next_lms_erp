<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The prompt behind the stuck-user assistance popup.
 *
 * WHY A TEMPLATE, NOT A NEW GENERATION PATH
 *
 * `k12.field_edit` (2026_09_10_000002) already established the pattern for a
 * low-stakes, ungoverned-feeling suggestion that still has to run through the
 * platform's own runtime: a Next.js route proxies to `POST /api/ai/generate` naming
 * a template key, never its own prompt. This row is that template for the "are you
 * stuck?" popup — reusing GenerationService -> TemplateRegistry -> SafetyChecker ->
 * ModelClient end to end, so this feature's traffic is visible in Usage & Cost and
 * Activity like every other generative call, rather than being a second, invisible
 * path outside the runtime.
 *
 * WHAT IT IS GROUNDED IN, AND WHAT IT IS NOT
 *
 * The variables below are exactly the shape `usePageAiContext()` already sends to
 * the backend for every other purpose — page title, page type, active filters, the
 * metrics on screen, and the actions the page says are available. Nothing here is
 * free text the user typed; a person who has not asked anything yet has not supplied
 * any. That is also why the system prompt refuses to invent a feature, a field name
 * or a process step that was not named in the context block.
 *
 * OUTPUT SHAPE
 *
 * `json`, and specifically a bare array of short strings — never an object, never a
 * markdown list — because the caller (`app/api/ai/stuck-assist/route.ts`) parses it
 * straight into the popup's suggestion chips with no further extraction step.
 *
 * Run on its own, per this project's convention:
 *
 *   php artisan migrate --path=database/migrations/2026_09_29_100700_seed_stuck_user_help_prompt_template.php
 */
return new class extends Migration
{
    private const TEMPLATE_KEY = 'k12.stuck_user_help';

    private const SYSTEM_PROMPT = <<<'PROMPT'
You write short help-question suggestions for a popup inside a K-12 school management system. It
appears when someone has stayed on one screen for a while without doing anything, and asks whether
they would like help.

Rules, in order of importance:

1. Reply with ONLY a JSON array of 3 to 4 short strings. No object wrapper, no markdown fences, no
   commentary before or after the array. Example shape: ["...", "...", "..."]
2. Each string is a question written from the user's point of view, in plain, everyday language a
   non-technical school administrator, teacher, accountant or parent would use — never a technical
   term, a database field name, a button's internal name, or a developer's word for anything.
3. Ground every suggestion ONLY in the screen name, screen type, active filters, visible figures and
   listed actions you are given below. Never invent a feature, a field, a button, or a process step
   that was not named in that context — if the context is thin, write fewer, more general questions
   rather than a specific one you cannot support.
4. Each suggestion must be answerable as a real question inside this chat — not an instruction to
   the user ("try refreshing the page") and not a request for information from them.
5. Do not repeat the same idea worded two ways. Each suggestion should cover a different likely
   reason someone is stuck on this particular screen.
6. No emoji, no exclamation marks, no false urgency.
PROMPT;

    private const USER_PROMPT = <<<'PROMPT'
Screen: {{page_title}} ({{page_type}})
Module: {{module}}
Filters currently applied: {{filters_summary}}
Figures visible on screen: {{metrics_summary}}
Actions this screen says are available: {{available_actions_summary}}
Time spent without action: {{idle_seconds}} seconds

Write the JSON array now.
PROMPT;

    public function up(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        if (DB::table('ai_templates')->where('template_key', self::TEMPLATE_KEY)->exists()) {
            return;
        }

        DB::table('ai_templates')->insert([
            'template_key' => self::TEMPLATE_KEY,
            'name' => 'Stuck-user help suggestions',
            'domain' => 'k12',
            'category' => 'stuck_user_assist',
            'description' => 'Writes 3-4 plain-language help questions for the "are you stuck?" popup, grounded only in what the current screen actually shows.',
            'version' => 1,
            'status' => 'published',
            'system_prompt' => self::SYSTEM_PROMPT,
            'user_prompt' => self::USER_PROMPT,
            'variables' => json_encode([
                ['key' => 'page_title', 'label' => 'Screen name', 'required' => true, 'type' => 'string'],
                ['key' => 'page_type', 'label' => 'Screen type', 'required' => false, 'type' => 'string'],
                ['key' => 'module', 'label' => 'Module', 'required' => false, 'type' => 'string'],
                ['key' => 'filters_summary', 'label' => 'Active filters', 'required' => false, 'type' => 'string'],
                ['key' => 'metrics_summary', 'label' => 'Visible figures', 'required' => false, 'type' => 'string'],
                ['key' => 'available_actions_summary', 'label' => 'Available actions', 'required' => false, 'type' => 'string'],
                ['key' => 'idle_seconds', 'label' => 'Idle time in seconds', 'required' => false, 'type' => 'string'],
            ]),
            // A bare JSON array of strings — see the class docblock. The caller parses it
            // directly into suggestion chips.
            'output_format' => 'json',
            // Null: whatever the configured driver is, same reasoning as k12.field_edit —
            // pinning a provider here would reintroduce the hard-coding this pattern removes.
            'provider' => null,
            'sub_institute_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        DB::table('ai_templates')
            ->where('template_key', self::TEMPLATE_KEY)
            ->whereNull('sub_institute_id')
            ->delete();
    }
};
