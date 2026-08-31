<?php

namespace App\Http\Controllers\AI;

use App\Domain\GenerativeAI\GenerationRequest;
use App\Domain\GenerativeAI\GenerationService;
use App\Domain\Templates\PromptTemplate;
use App\Domain\Templates\TemplateRegistry;
use Illuminate\Http\Request;
use Throwable;

/**
 * The generation API.
 *
 * Callers name a template, not a prompt. That is what keeps generation governable:
 * an administrator can see and change every prompt the platform can issue, and a
 * caller cannot smuggle instructions in through a free-text prompt field.
 *
 * Responses always carry `is_generated: true` so the frontend can badge them.
 */
class GenerationController extends AiController
{
    public function __construct(
        private readonly GenerationService $generation,
        private readonly TemplateRegistry $templates,
    ) {
    }

    public function templates(Request $request)
    {
        try {
            $scope = $this->scope($request);

            return $this->success('Templates loaded.', [
                'templates' => array_map(
                    fn (PromptTemplate $template) => $template->toArray(),
                    $this->templates->all($scope->selectedInstituteId, $request->input('category'))
                ),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function generate(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'template_key' => 'required|string|max:120',
                'purpose' => 'required|string|max:120',
                'variables' => 'nullable|array',
                'domain' => 'nullable|string|max:40',
                'subject_entity_key' => 'nullable|string|max:100',
                'subject_id' => 'nullable|integer|min:1',
                'case_id' => 'nullable|integer|min:1',
            ]);

            $generationRequest = new GenerationRequest(
                templateKey: $validated['template_key'],
                purpose: $validated['purpose'],
                variables: $validated['variables'] ?? [],
                domain: $validated['domain'] ?? 'k12',
                subjectEntityKey: $validated['subject_entity_key'] ?? null,
                subjectId: $validated['subject_id'] ?? null,
                caseId: $validated['case_id'] ?? null,
            );

            $result = $this->generation->generate($generationRequest, $scope);

            if (! $result->succeeded) {
                return $this->failure($result->error ?? 'Generation failed.', 422, $result->safetyReport);
            }

            // A generation that came back but failed safety or validation is returned
            // as a failure with its report, not as usable content.
            if (! $result->isUsable()) {
                return $this->failure(
                    $result->safetyPassed
                        ? 'The generated content did not match the expected format.'
                        : 'The generated content did not pass safety checks.',
                    422,
                    [
                        'schema_errors' => $result->schemaErrors,
                        'safety_report' => $result->safetyReport,
                    ]
                );
            }

            return $this->success('Content generated.', $result->toArray());
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Mark generated content as reviewed by a person — the only route by which it
     * can ever become a candidate for verification.
     */
    public function review(Request $request, int $output)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'status' => 'required|string|in:accepted,edited,rejected',
                'note' => 'nullable|string|max:1000',
            ]);

            if (! $this->generation->review($output, $validated['status'], $scope, $validated['note'] ?? null)) {
                return $this->failure('No such generated output.', 404);
            }

            return $this->success('Review recorded.');
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }
}
