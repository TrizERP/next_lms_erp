<?php

namespace Tests\Unit;

use App\Http\Controllers\api\DocumentTemplateApiController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The AI category lives in the existing document template library.
 *
 * These assertions exist to keep it that way. The assistant reads templates the
 * same designer creates, versioned in the same table, scoped to the same tenant —
 * so the thing that must not regress is the category list itself: dropping `ai`
 * would silently normalise every AI template to `general`, and dropping any of
 * the others would break the screens that already work.
 */
class DocumentTemplateCategoryTest extends TestCase
{
    private function normalize(string $category): string
    {
        $method = new ReflectionMethod(DocumentTemplateApiController::class, 'normalizeCategory');
        $method->setAccessible(true);

        return $method->invoke(new DocumentTemplateApiController(), $category);
    }

    public function test_the_library_offers_an_ai_category(): void
    {
        $this->assertArrayHasKey(
            'ai',
            DocumentTemplateApiController::CATEGORIES,
            'The assistant draws its templates from this category.'
        );

        $this->assertSame('ai', DocumentTemplateApiController::AI_CATEGORY);
    }

    public function test_the_categories_that_already_worked_are_untouched(): void
    {
        // Every category the designer and the Fees documents already use. A missing
        // key here would send existing templates to `general` on their next save.
        foreach (['certificate', 'id_card', 'fees', 'admission', 'exam', 'circular', 'general'] as $category) {
            $this->assertArrayHasKey($category, DocumentTemplateApiController::CATEGORIES);
        }
    }

    public function test_an_ai_template_keeps_its_category_on_save(): void
    {
        // Before `ai` was a listed category this normalised to `general`, which is
        // the whole reason a template saved as an AI template has to be asserted.
        $this->assertSame('ai', $this->normalize('ai'));
    }

    public function test_existing_categories_still_normalise_to_themselves(): void
    {
        $this->assertSame('fees', $this->normalize('fees'));
        $this->assertSame('admission', $this->normalize('admission'));
        $this->assertSame('certificate', $this->normalize('certificate'));
    }

    public function test_an_unknown_category_still_falls_back_to_general(): void
    {
        $this->assertSame('general', $this->normalize('not-a-real-category'));
        $this->assertSame('general', $this->normalize(''));
    }
}
