<?php

namespace Tests\Unit;

use App\Services\Mcp\AiReportGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * How a saved report finds its own figures again.
 *
 * `template_master` has no metadata column, so a generated report records what
 * produced it inside its own HTML and the refresh path reads it back from there.
 * These tests are about the two things that makes possible and the one thing it must
 * never do:
 *
 *   - a report can reproduce its own query months later, and
 *   - a refresh replaces the figures and *only* the figures, because by then somebody
 *     has written a covering note around them.
 *
 * Extends PHPUnit's TestCase rather than Tests\TestCase, matching
 * FeesArrearsServiceTest: everything here is string work with no database and no
 * container, so it still runs when the application cannot boot. Nothing in this file
 * touches `template_master` — the rows in this estate are real.
 */
class AiReportRefreshTest extends TestCase
{
    private function generator(): AiReportGenerator
    {
        // The three services this class composes are only reached through rowsFor(),
        // which none of these tests calls, so it is built without the container.
        return (new ReflectionClass(AiReportGenerator::class))->newInstanceWithoutConstructor();
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function call(string $method, array $args): mixed
    {
        $handle = (new ReflectionClass(AiReportGenerator::class))->getMethod($method);
        $handle->setAccessible(true);

        return $handle->invokeArgs($this->generator(), $args);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, columns: array<int, string>, source: string}
     */
    private function data(array $rows, string $source = 'admissions.listEnquiries'): array
    {
        return [
            'rows' => $rows,
            'columns' => $rows === [] ? [] : array_keys($rows[0]),
            'source' => $source,
        ];
    }

    private function report(array $arguments = ['standard_id' => 7]): string
    {
        return $this->call('composeHtml', [
            'Admissions report — 2 rows',
            'who has not confirmed?',
            'admissions',
            $this->data([
                ['student_name' => 'Riya Mayur Patel', 'status' => 'Enquiry'],
                ['student_name' => 'Abhi D Raval', 'status' => 'Confirmed'],
            ]),
            $arguments,
        ]);
    }

    public function test_a_generated_report_records_which_module_and_tool_produced_it(): void
    {
        $html = $this->report();

        $figures = $this->generator()->describeFigures($html);

        $this->assertNotNull($figures);
        $this->assertSame('admissions', $figures['module']);
        // The source tool, not just the module: two tools can report on admissions and
        // "where did this come from" has to name the one that was actually called.
        $this->assertSame('admissions.listEnquiries', $figures['source']);
        $this->assertNotSame('', $figures['generated_at']);
    }

    public function test_the_marker_spans_exactly_the_generated_container(): void
    {
        $html = $this->report();

        $marker = $this->call('readMarker', [$html]);

        $this->assertIsArray($marker);

        $span = substr($html, $marker['start'], $marker['length']);

        // The offsets refresh() splices at: the span must start at the container and
        // end at its closing tag, or a refresh writes into the surrounding document.
        $this->assertStringStartsWith('<div data-ai-report="admissions"', $span);
        $this->assertStringEndsWith('</div>', $span);
        $this->assertStringContainsString('Riya Mayur Patel', $span);
    }

    public function test_refreshing_replaces_the_figures_and_leaves_the_prose_alone(): void
    {
        // The document as it looks once somebody has actually used it: a covering
        // paragraph above the table and a signature below. This is the case that makes
        // the splice necessary — regenerating the report would discard both.
        $note = '<p>Board pack, item 4. Figures as at the date shown below.</p>';
        $signature = '<p>Prepared by the admissions office.</p>';
        $html = $note . $this->report() . $signature;

        $marker = $this->call('readMarker', [$html]);
        $fresh = $this->call('figuresBlock', [
            'admissions',
            $this->data([['student_name' => 'Neha S Shah', 'status' => 'Confirmed']]),
            $marker['arguments'],
        ]);

        $refreshed = substr_replace($html, $fresh, $marker['start'], $marker['length']);

        $this->assertStringStartsWith($note, $refreshed);
        $this->assertStringEndsWith($signature, $refreshed);
        $this->assertStringContainsString('Neha S Shah', $refreshed);
        $this->assertStringNotContainsString('Riya Mayur Patel', $refreshed);
        // And it is still a report: the replacement carries its own marker, so the
        // refreshed document can be refreshed again.
        $this->assertNotNull($this->generator()->describeFigures($refreshed));
    }

    public function test_a_div_added_inside_the_container_does_not_truncate_the_splice(): void
    {
        // An administrator wrapping part of the table in a div of their own. Stopping
        // at the first `</div>` would leave the tail of the old table in the document,
        // below the new one.
        $html = str_replace(
            '<table',
            '<div class="scroll"><table',
            $this->report()
        );
        $html = str_replace('</table>', '</table></div>', $html) . '<p>After.</p>';

        $marker = $this->call('readMarker', [$html]);
        $span = substr($html, $marker['start'], $marker['length']);

        $this->assertStringEndsWith('</div>', $span);
        $this->assertStringContainsString('class="scroll"', $span);
        // The paragraph after the container is outside the span, so it survives.
        $this->assertStringNotContainsString('After.', $span);
    }

    public function test_a_report_reproduces_its_own_query_rather_than_the_default_view(): void
    {
        $marker = $this->call('readMarker', [$this->report(['standard_id' => 7, 'status' => 'pending'])]);

        // Without this a refresh would quietly widen the report: the same title and
        // the same heading over a different population.
        $this->assertSame(['standard_id' => 7, 'status' => 'pending'], $marker['arguments']);
    }

    public function test_the_module_and_the_question_are_not_carried_as_query_filters(): void
    {
        $marker = $this->call('readMarker', [
            $this->report(['module' => 'admissions', 'question' => 'who?', 'standard_id' => 3]),
        ]);

        $this->assertSame(['standard_id' => 3], $marker['arguments']);
    }

    public function test_structure_and_oversized_payloads_are_refused_from_the_marker(): void
    {
        $marker = $this->call('readMarker', [
            $this->report(['nested' => ['a' => 1], 'standard_id' => 4]),
        ]);

        // A refresh hands these arguments to a live service. Only scalars make that
        // trip; anything else is dropped rather than reconstructed.
        $this->assertSame(['standard_id' => 4], $marker['arguments']);

        $this->assertSame('{}', $this->call('encodeArguments', [['huge' => str_repeat('x', 3000)]]));
    }

    public function test_a_document_whose_table_was_deleted_is_not_refreshable(): void
    {
        // The honest outcome: refresh has no anchor, so the page must not offer it.
        // Silently appending a fresh table to the bottom of an edited document would
        // be the alternative, and it would be worse.
        $this->assertNull($this->call('readMarker', ['<h2>Admissions report</h2><p>Deleted.</p>']));
        $this->assertNull($this->generator()->describeFigures('<p>Nothing here.</p>'));
    }

    public function test_a_marker_naming_an_unsupported_module_is_ignored(): void
    {
        // The module read back out of a document decides which service a refresh
        // calls, so it is checked against the whitelist on the way in as well as out.
        $html = str_replace('data-ai-report="admissions"', 'data-ai-report="payroll"', $this->report());

        $this->assertNull($this->call('readMarker', [$html]));
    }

    public function test_row_values_are_escaped_in_the_figures(): void
    {
        $html = $this->call('figuresBlock', [
            'admissions',
            $this->data([['student_name' => '<script>alert(1)</script>', 'status' => 'Enquiry']]),
            [],
        ]);

        // The report is edited by an administrator and rendered back into a page, so a
        // student's name must never be able to arrive as markup.
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
