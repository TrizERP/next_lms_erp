<?php

namespace Tests\Unit;

use App\Domain\GenerativeAI\GroundingCheck;
use App\Domain\Templates\PromptTemplate;
use PHPUnit\Framework\TestCase;

/**
 * The rule that stops a model describing an empty prompt as if it were an empty school.
 *
 * This is regression cover for a real failure: asked to "summarise the catalogue", the
 * generator received no courses and produced "This course catalogue currently shows no
 * courses or available grades" for an institute holding 208 of them. The sentence was
 * fluent, confident, and about the prompt rather than the data — the worst shape a wrong
 * answer can take, because nothing about it looks wrong.
 *
 * Framework-free, like the other unit tests here.
 */
class GroundingCheckTest extends TestCase
{
    private function template(array $variables): PromptTemplate
    {
        return new PromptTemplate(
            id: 1,
            key: 'k12.course_catalog_summary',
            name: 'Course catalogue summary',
            version: 2,
            userPrompt: 'Summarise {{records}}',
            variables: $variables,
        );
    }

    private function grounded(): PromptTemplate
    {
        return $this->template([
            ['key' => 'records', 'label' => 'Courses', 'grounding' => true],
            ['key' => 'metrics', 'label' => 'Totals', 'grounding' => true],
            ['key' => 'page_title', 'label' => 'Title'],
        ]);
    }

    /**
     * The exact payload that produced the bug.
     */
    public function test_the_workspace_empty_placeholders_do_not_count_as_content(): void
    {
        $report = GroundingCheck::inspect($this->grounded(), [
            'records' => 'none listed',
            'metrics' => 'none reported',
            'record_count' => '0',
            'page_title' => 'Course Catalog',
        ]);

        $this->assertTrue($report['required']);
        $this->assertFalse($report['grounded'], 'A page that reported nothing must not be summarised.');
        $this->assertSame(['records', 'metrics'], $report['empty']);
    }

    /**
     * Every spelling of "nothing" a page might send.
     */
    public function test_empty_spellings_are_all_treated_as_empty(): void
    {
        foreach (['', ' ', '-', '—', 'none', 'None.', 'N/A', 'null', 'no records', 'NO DATA', '0', []] as $value) {
            $report = GroundingCheck::inspect($this->grounded(), ['records' => $value, 'metrics' => '']);

            $this->assertFalse(
                $report['grounded'],
                sprintf('%s should not count as grounding data.', var_export($value, true))
            );
        }
    }

    /**
     * One grounded variable is enough — a catalogue can be summarised from its rows even
     * if the totals tiles are empty.
     */
    public function test_one_populated_variable_is_enough(): void
    {
        $report = GroundingCheck::inspect($this->grounded(), [
            'records' => "- Physics (grades: 11, 12)",
            'metrics' => 'none reported',
        ]);

        $this->assertTrue($report['grounded']);
        $this->assertSame(['records'], $report['present']);
        $this->assertSame(['metrics'], $report['empty']);
    }

    /**
     * A template that declares no grounding variables is unaffected. Plenty of templates
     * legitimately write from instructions alone, and this rule must not block them.
     */
    public function test_a_template_without_grounding_variables_is_never_blocked(): void
    {
        $report = GroundingCheck::inspect(
            $this->template([['key' => 'tone', 'label' => 'Tone']]),
            []
        );

        $this->assertFalse($report['required']);
        $this->assertTrue($report['grounded']);
    }

    /**
     * A zero count is absence; a positive one is data.
     */
    public function test_numeric_grounding_respects_zero(): void
    {
        $template = $this->template([['key' => 'record_count', 'label' => 'Rows', 'grounding' => true]]);

        $this->assertFalse(GroundingCheck::inspect($template, ['record_count' => 0])['grounded']);
        $this->assertFalse(GroundingCheck::inspect($template, ['record_count' => '0'])['grounded']);
        $this->assertTrue(GroundingCheck::inspect($template, ['record_count' => 43])['grounded']);
    }

    /**
     * The refusal has to name what was missing, or someone goes looking for a fault that
     * is really a missing input.
     */
    public function test_the_refusal_names_the_missing_variables(): void
    {
        $template = $this->grounded();
        $report = GroundingCheck::inspect($template, ['records' => 'none listed', 'metrics' => 'none reported']);

        $message = GroundingCheck::refusalMessage($template, $report);

        $this->assertStringContainsString('Course catalogue summary', $message);
        $this->assertStringContainsString('records', $message);
        $this->assertStringContainsString('metrics', $message);
    }
}
