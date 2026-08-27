<?php

namespace Tests\Unit;

use App\Services\Mcp\AiTemplateService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Rendering an AI template against a real admission enquiry.
 *
 * The substitution rules are the ones `fees_collect_controller` already uses on
 * `template_master`, so these assertions are really about staying compatible with
 * templates the school has already designed: `<<token>>` written in the editor,
 * stored HTML-escaped, replaced literally with no evaluation.
 */
class AiTemplateServiceTest extends TestCase
{
    private function call(string $method, ...$args)
    {
        $reflection = new ReflectionMethod(AiTemplateService::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(new AiTemplateService(), ...$args);
    }

    public function test_the_ai_category_is_a_module_name_in_the_existing_library(): void
    {
        // Not a new table and not a new column: `module_name` is how template_master
        // has always categorised, and "AI" is one more value in it.
        $this->assertSame('AI', AiTemplateService::AI_MODULE);
    }

    public function test_plain_tokens_are_replaced_with_real_values(): void
    {
        $html = '<p>Dear <<student_name>>, enquiry <<enquiry_no>>.</p>';

        $rendered = $this->call('substitute', $html, [
            'student_name' => 'Riya Mayur Patel',
            'enquiry_no' => '2022011',
        ]);

        $this->assertSame('<p>Dear Riya Mayur Patel, enquiry 2022011.</p>', $rendered);
    }

    public function test_tokens_stored_html_escaped_by_the_editor_are_also_replaced(): void
    {
        // The designer is a WYSIWYG editor, so a token typed as `<<student_name>>`
        // is saved as `&lt;&lt;student_name&gt;&gt;`. Handling only the raw form
        // would leave the escaped one visible in the output.
        $html = '<p>Dear ' . htmlspecialchars('<<student_name>>') . '.</p>';

        $rendered = $this->call('substitute', $html, ['student_name' => 'Abhi D Raval']);

        $this->assertSame('<p>Dear Abhi D Raval.</p>', $rendered);
        $this->assertStringNotContainsString('&lt;&lt;', $rendered);
    }

    public function test_nothing_in_a_template_is_evaluated(): void
    {
        // Templates are editable by administrators. Literal replacement is the whole
        // mechanism — an expression language here would be a code execution hole.
        $html = '<p><<student_name>></p>';

        $rendered = $this->call('substitute', $html, [
            'student_name' => '{{ 2 + 2 }}',
        ]);

        $this->assertSame('<p>{{ 2 + 2 }}</p>', $rendered);
    }

    public function test_a_token_the_record_cannot_fill_is_reported_rather_than_hidden(): void
    {
        // A template asking for a field the enquiry does not hold should be a visible
        // fact, not a stray `<<...>>` somebody notices after sending.
        $unresolved = $this->call('unresolvedTokens', '<p><<father_name>> and <<blood_group>></p>');

        $this->assertSame(['father_name', 'blood_group'], $unresolved);
    }

    public function test_a_fully_filled_template_reports_no_unresolved_tokens(): void
    {
        $rendered = $this->call('substitute', '<p><<student_name>></p>', [
            'student_name' => 'Riya Mayur Patel',
        ]);

        $this->assertSame([], $this->call('unresolvedTokens', $rendered));
    }

    public function test_token_values_come_from_the_admission_records_own_fields(): void
    {
        // Field names match what AdmissionMcpService::presentAdmissionDetails returns,
        // so a template can only show what the admission flow actually holds.
        $values = $this->call('tokensForEnquiry', [
            'student_name' => 'Riya Mayur Patel',
            'enquiry_no' => '2022011',
            'mobile' => '9685743256',
            'standard_name' => 'Standard 4',
            'status' => 'new',
        ]);

        $this->assertSame('Riya Mayur Patel', $values['student_name']);
        $this->assertSame('2022011', $values['enquiry_no']);
        $this->assertSame('9685743256', $values['mobile']);
        $this->assertSame('Standard 4', $values['standard_name']);
        $this->assertSame('new', $values['status']);
    }

    public function test_a_missing_field_renders_empty_rather_than_as_the_word_null(): void
    {
        $values = $this->call('tokensForEnquiry', [
            'student_name' => 'Riya Mayur Patel',
            'division_name' => null,
        ]);

        $this->assertSame('', $values['division_name']);
        $this->assertSame('', $values['quota_name']);
    }
}
