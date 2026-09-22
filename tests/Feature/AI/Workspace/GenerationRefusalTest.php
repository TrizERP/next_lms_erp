<?php

namespace Tests\Feature\AI\Workspace;

use App\Domain\GenerativeAI\GenerationRequest;
use App\Domain\GenerativeAI\GenerationService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A refused generation is an answer, not a crash.
 *
 * `GenerationService::recordRequest()` names the provider the call would have used, and
 * it read `$configuration->provider` — a variable that only exists further down
 * `generate()`, long after the refusal paths that call it. So every refusal of a template
 * with no pinned provider raised "Undefined variable $configuration" and
 * `/api/ai/workspace/generate` answered 500. The refusal itself was correct; the reader
 * never saw it, because the code that writes the row explaining it fell over first.
 *
 * `??` is what hid this: the right-hand side is only evaluated when the template pins no
 * provider, so templates that pin one worked and the estate's own fees templates — which
 * pin none — did not.
 */
class GenerationRefusalTest extends TestCase
{
    public function test_refusing_an_ungrounded_template_returns_a_refusal_rather_than_throwing(): void
    {
        $template = $this->templateWithNoProvider();

        if ($template === null) {
            $this->markTestSkipped('No published template with grounding variables and no pinned provider.');
        }

        // No variables at all, which is precisely the state the grounding guard exists
        // to refuse — and the state the Create tab was in before a module's own data
        // reached it.
        $result = app(GenerationService::class)->generate(
            new GenerationRequest(
                templateKey: $template,
                purpose: 'test:grounding_refusal',
                variables: [],
                domain: 'k12',
            ),
            $this->scope()
        );

        $this->assertFalse($result->succeeded, 'An ungrounded template must be refused, not generated.');
        $this->assertNotNull($result->error);
        $this->assertStringNotContainsStringIgnoringCase(
            'undefined variable',
            (string) $result->error,
            'The refusal must be about the missing data, never about the code that records it.'
        );
    }

    /**
     * A published template that declares grounding and pins no provider.
     *
     * Read from the estate rather than fixtured, because the row that crashed was a real
     * one: every `k12.fees.*` template is published with `provider` null.
     */
    private function templateWithNoProvider(): ?string
    {
        if (! Schema::hasTable('ai_templates')) {
            return null;
        }

        $rows = DB::table('ai_templates')
            ->where('status', 'published')
            ->whereNull('provider')
            ->get(['template_key', 'variables']);

        foreach ($rows as $row) {
            $variables = json_decode((string) $row->variables, true);

            if (! is_array($variables)) {
                continue;
            }

            foreach ($variables as $variable) {
                if (is_array($variable) && ($variable['grounding'] ?? false)) {
                    return (string) $row->template_key;
                }
            }
        }

        return null;
    }

    private function scope(): McpRequestContext
    {
        return new McpRequestContext(
            userId: 7,
            role: 'admin',
            selectedInstituteId: 1,
            allowedInstituteIds: [1],
            userProfileId: null,
            clientId: null,
            academicYear: 2026,
            termId: null,
            isAdmin: true,
            isStudent: false,
        );
    }
}
