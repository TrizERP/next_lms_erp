<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Recommendations\FeesNextBestActionResolver;
use App\Http\Controllers\AI\Concerns\RequiresFeesStaffAccess;
use Illuminate\Http\Request;
use Throwable;

/**
 * The Fees "Recommendation" panel's Recommendation Intelligence section —
 * "what's the single most relevant next action for this subject?". A
 * ranking read over FeesNextBestActionResolver; see that class for which
 * sources it ranks and why Capabilities 1-2's cohort-level cards aren't
 * among them.
 *
 * `audience` trims by caller, since the resolver itself returns everything
 * it finds regardless of who's asking: a parent must never be shown a
 * staff-only `workflow_key` such as `fees_collection`. There is no
 * established role-to-audience mapping on McpRequestContext yet (the one
 * existing "parent" surface concept, MobileAppService::SURFACES, is for a
 * different feature — homescreen config, not access control), so this is
 * an explicit caller-declared parameter rather than an inferred one.
 */
class FeesNextBestActionController extends AiController
{
    use RequiresFeesStaffAccess;

    /** workflow_key values a parent-facing caller must never see. */
    private const STAFF_ONLY_WORKFLOW_KEYS = ['fees_collection'];

    public function __construct(private readonly FeesNextBestActionResolver $resolver)
    {
    }

    public function index(Request $request)
    {
        try {
            $scope = $this->scope($request);
            // `audience=parent` below is a display-trim hint from an already-
            // authorized caller, never a substitute for this check — a raw
            // student token is refused outright, regardless of `audience`.
            $this->assertStaffAccess($scope);

            $validated = $request->validate([
                'subject_entity_key' => 'required|string|max:50',
                'subject_id' => 'required|integer',
                'audience' => 'nullable|in:staff,parent',
            ]);

            $result = $this->resolver->topPickFor(
                $validated['subject_entity_key'],
                $validated['subject_id'],
                $scope
            );

            if (($validated['audience'] ?? 'staff') === 'parent') {
                $result = $this->trimForParent($result);
            }

            return $this->success('Fees next-best-action loaded.', [
                'subject_entity_key' => $validated['subject_entity_key'],
                'subject_id' => $validated['subject_id'],
                ...$result,
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    private function trimForParent(array $result): array
    {
        $workflowKey = $result['top_pick']['workflow_key'] ?? null;

        if ($workflowKey !== null && in_array($workflowKey, self::STAFF_ONLY_WORKFLOW_KEYS, true)) {
            return [
                'available' => false,
                'reason' => 'The only action found for this subject is staff-only and is not shown to this audience.',
                'top_pick' => null,
            ];
        }

        return $result;
    }
}
