<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The generative field assistant's prompt, moved into central Prompt Management.
 *
 * WHY
 *
 * The sparkle-icon assistant on 40 fields across 14 modules was the one generative
 * call site outside the central runtime. It posted to a Next.js route that built its
 * own prompt in `lib/ai/field-edit/prompt.ts`, took a model name from
 * `GEMINI_MODEL` (defaulting to a hard-coded string), and read a key straight from
 * the environment. So the highest-volume AI traffic in the product used none of the
 * platform's provider pool, none of its prompt versioning, and wrote nothing to its
 * audit or usage tables.
 *
 * With this row in place the Next route becomes a proxy to `POST /api/ai/generate`,
 * and every field edit resolves the same way every other generation does:
 * GenerationService -> TemplateRegistry (this row) -> SafetyChecker ->
 * ModelClient (the `ai_api_keys` pool, rotated, with per-key daily limits) ->
 * `ai_generation_requests` + `ai_generation_outputs` + `ai_audit_logs`.
 *
 * WHAT IS HERE AND WHAT IS NOT
 *
 * The *rules* live here — they are the part an administrator would want to version,
 * review and roll back, and they are identical for every field. The *assembly* of
 * the context block stays in the frontend, because it is mechanical formatting of
 * data the caller already holds (which field type, which sibling values, what length
 * limit) rather than instructions to a model. Splitting it this way also keeps
 * SafetyChecker honest: it inspects `field_value`, `user_instruction` and
 * `related_content` — the parts a user actually supplies — instead of the product's
 * own framing text, which would otherwise be scanned for prompt injection on every
 * request.
 *
 * TENANT OVERRIDE COMES FREE
 *
 * `sub_institute_id` is null, so this is the platform default. TemplateRegistry
 * orders `sub_institute_id IS NULL ASC`, so a school that inserts its own
 * `k12.field_edit` row wins over this one without any code change.
 *
 * Run on its own — never `php artisan migrate` bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_10_000002_seed_field_edit_prompt_template.php
 */
return new class extends Migration
{
    private const TEMPLATE_KEY = 'k12.field_edit';

    /**
     * Reproduced verbatim from `FIELD_EDIT_SYSTEM_PROMPT` in
     * `lib/ai/field-edit/prompt.ts`. Copied rather than reworded on purpose: this
     * migration changes where the prompt lives, not what it says, so the assistant
     * behaves identically the moment the proxy is switched on.
     */
    private const SYSTEM_PROMPT = <<<'PROMPT'
You edit text inside a K-12 school management system. A teacher or administrator has asked you to
change one field on a form.

Rules, in order of importance:

1. Return ONLY the replacement text for the field. No preamble, no sign-off, no explanation, no
   quotation marks around the whole answer, and no markdown code fences.
2. Never invent facts. Do not add or change a date, time, venue, name, amount, mark, percentage,
   syllabus reference or citation that is not already in the text or the context you were given.
   If the instruction cannot be followed without inventing something, do the part you can and
   leave the rest as it was.
3. Follow the user's instruction exactly. If they asked only to fix grammar, do not also reword,
   reorder or shorten.
4. Keep the original language unless you were explicitly asked to translate.
5. Keep the formatting shape you were given — if the input was HTML, return HTML; if it was plain
   text, return plain text; if it was a list, return a list.
6. Content is read by children. No profanity, no scare tactics, no stereotyping, no emoji, and
   nothing that singles out or demeans a student.
7. If the text is already correct for the instruction, return it unchanged rather than inventing a
   difference.
PROMPT;

    /** Assembles the caller's variables into the same shape the frontend used to build. */
    private const USER_PROMPT = <<<'PROMPT'
{{field_guidance}}

Where this field lives:
{{field_context}}
{{related_content}}
Current field content, between the markers:
<<<FIELD
{{field_value}}
FIELD>>>

What the user asked for: {{user_instruction}}

Reply with the replacement field content and nothing else.
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
            'name' => 'Field edit assistant',
            'domain' => 'k12',
            'category' => 'field_edit',
            'description' => 'Rewrites one form field from the user\'s instruction. Used by the sparkle assistant across every module.',
            'version' => 1,
            'status' => 'published',
            'system_prompt' => self::SYSTEM_PROMPT,
            'user_prompt' => self::USER_PROMPT,
            'variables' => json_encode([
                ['key' => 'field_value', 'label' => 'Current field content', 'required' => false, 'type' => 'string'],
                ['key' => 'user_instruction', 'label' => 'What the user asked for', 'required' => true, 'type' => 'string'],
                ['key' => 'field_guidance', 'label' => 'Field-type guidance', 'required' => false, 'type' => 'string'],
                ['key' => 'field_context', 'label' => 'Where the field lives', 'required' => false, 'type' => 'string'],
                ['key' => 'related_content', 'label' => 'Nearby content, for reference', 'required' => false, 'type' => 'string'],
            ]),
            // Text, not JSON: the answer is the field's replacement value, so a
            // schema would only wrap it in something the form has to unwrap again.
            'output_format' => 'text',
            // Null means "whatever the configured driver is". Pinning a provider
            // here would reintroduce, in the database, the hard-coding this row
            // exists to remove.
            'provider' => null,
            // Platform default. A school may insert its own row for this key and
            // TemplateRegistry will prefer it.
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
