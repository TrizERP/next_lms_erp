<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The one template the new "learn this concept visually" PAL feature calls.
 *
 * `output_format = 'image'` is what routes a `/api/ai/generate` call for this key
 * through GenerationService::generateImageOutput() instead of the text path — see that
 * method and TemplateCatalog::OUTPUT_FORMATS. That method resolves its configuration
 * against the `image_generation` CAPABILITY `AiModuleRegistry` already declared
 * ("Declared for configuration; no caller yet.") — this migration is that caller.
 * `ai_templates.module_key` below is a different axis (the PRODUCT module, per
 * `AiConfigurationResolver`'s own distinction between the two) — set to `pal` so a
 * school could later give PAL's own image generation a different model/key than the
 * `image_generation` capability's general configuration, without that being required
 * for this to work today. Nothing here changes what any other module or template does.
 *
 * Only `concept_name` is a required (grounding) variable: the frontend has that for
 * every concept, but chapter name, description, subject and grade are not all
 * available from the Learn page's existing data path for every concept, so the prompt
 * is written to still produce a real, concept-specific image from the name alone when
 * the rest are blank.
 *
 * Run it on its own:
 *
 *   php artisan migrate --path=database/migrations/2026_09_30_100000_seed_pal_concept_visual_ai_template.php
 */
return new class extends Migration
{
    private const TEMPLATE_KEY = 'k12.pal_concept_visual';

    public function up(): void
    {
        if (! Schema::hasTable('ai_templates') || ! Schema::hasColumn('ai_templates', 'kind')) {
            return;
        }

        $row = [
            'template_key' => self::TEMPLATE_KEY,
            'name' => 'PAL Concept Visual',
            'description' => 'A generated educational image explaining one PAL concept, for the Learn page\'s '
                .'"learn this concept visually" entry point.',
            'module_key' => 'pal',
            'domain' => 'k12',
            'category' => 'lesson',
            'kind' => 'prompt',
            'version' => 1,
            'status' => 'published',
            'output_format' => 'image',
            'system_prompt' => 'You produce a single educational illustration for a K-12 maths concept, for a '
                .'student meeting it for the first time. The image must make the mathematical relationship in '
                .'the concept visually obvious on its own — not a decorative or generic picture, not text-only, '
                .'not a chart of unrelated data. Use whichever concrete representation actually fits the '
                .'concept (for example: a fraction as shaded parts of a shape; a percentage as a 100-grid or a '
                .'bar; a ratio as two grouped sets of objects; an equation as a balance or a number line; a '
                .'geometric shape with its labelled dimensions; a probability event as a spinner, dice or '
                .'labelled outcomes; an integer operation as movement on a number line or grouped positive/'
                .'negative counters). Keep it clean, flat, high-contrast, age-appropriate, and free of any '
                .'unrelated branding or clutter. Alongside the image, return one short caption (2-3 plain '
                .'sentences) that describes exactly what the image shows and how it demonstrates the concept — '
                .'never a caption that could apply to a different image.',
            'user_prompt' => 'Generate the illustration for this concept.'."\n\n"
                .'Concept: {{concept_name}}'."\n"
                .'Chapter: {{chapter_name}}'."\n"
                .'What the concept covers: {{description}}'."\n\n"
                .'If the chapter or description above is blank, use the concept name alone to work out the '
                .'right mathematical representation — never fall back to a generic or unrelated image.',
            'variables' => json_encode([
                ['key' => 'concept_name', 'label' => 'Concept name', 'required' => true, 'type' => 'string', 'grounding' => true],
                ['key' => 'chapter_name', 'label' => 'Chapter name', 'required' => false, 'type' => 'string'],
                ['key' => 'description', 'label' => 'Concept description', 'required' => false, 'type' => 'text'],
            ]),
            'safety_rules' => json_encode([
                'No text baked into the image beyond labels/numbers the diagram itself needs.',
                'No people, faces, or anything unrelated to the mathematical relationship being taught.',
            ]),
            'requires_review' => 0,
            'allow_as_evidence' => 0,
            'sub_institute_id' => null,
            'client_id' => null,
        ];

        $existing = DB::table('ai_templates')
            ->where('template_key', self::TEMPLATE_KEY)
            ->whereNull('sub_institute_id')
            ->first();

        if ($existing !== null) {
            DB::table('ai_templates')->where('id', $existing->id)->update($row + ['updated_at' => now()]);

            return;
        }

        DB::table('ai_templates')->insert($row + ['created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        DB::table('ai_templates')
            ->where('template_key', self::TEMPLATE_KEY)
            ->whereNull('sub_institute_id')
            ->update(['status' => 'retired', 'updated_at' => now()]);
    }
};
