<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Cases\CaseBuilder;
use App\Domain\AI\Evidence\EvidenceStore;
use App\Domain\AI\Explanations\ExplanationBuilder;
use App\Domain\AI\Outcomes\OutcomeTracker;
use App\Domain\AI\Recommendations\RecommendationDrafter;
use App\Domain\AI\Signals\SignalStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reads the intelligence chain: signals, cases, evidence, explanations,
 * recommendations and outcomes.
 *
 * Every method is a read, and every read is scoped through the request's
 * McpRequestContext. A case that belongs to another school is not "forbidden" — it
 * simply is not found, which avoids confirming that it exists.
 */
class CaseController extends AiController
{
    public function __construct(
        private readonly CaseBuilder $cases,
        private readonly EvidenceStore $evidence,
        private readonly ExplanationBuilder $explanations,
        private readonly RecommendationDrafter $recommendations,
        private readonly SignalStore $signals,
        private readonly OutcomeTracker $outcomes,
    ) {
    }

    public function signals(Request $request)
    {
        try {
            $scope = $this->scope($request);

            return $this->success('Signals loaded.', [
                'signals' => $this->signals->open(
                    $scope,
                    $request->input('signal_key'),
                    $request->input('min_severity'),
                    $this->limit($request)
                ),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function index(Request $request)
    {
        try {
            $scope = $this->scope($request);

            return $this->success('Cases loaded.', [
                'cases' => $this->cases->list(
                    $scope,
                    $request->input('case_type'),
                    $request->input('status'),
                    $request->input('min_severity'),
                    $this->limit($request)
                ),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * The full picture for one case — what the student profile's AI Insights tab
     * renders in a single request rather than five.
     */
    public function show(Request $request, int $case)
    {
        try {
            $scope = $this->scope($request);
            $record = $this->cases->find($case, $scope);

            if (! $record) {
                return $this->failure('No such case.', 404);
            }

            return $this->success('Case loaded.', [
                'case' => $record,
                'evidence' => $this->evidence->forCase($case, $scope),
                'explanation' => $this->explanations->latestForCase($case, $scope),
                'recommendations' => $this->recommendations->forCase($case, $scope),
                'hypotheses' => $this->hypotheses($case, $scope->selectedInstituteId),
                'outcomes' => $this->outcomes->forSubject(
                    $record['subject_entity_key'],
                    $record['subject_id'],
                    $scope
                ),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function evidence(Request $request, int $case)
    {
        try {
            $scope = $this->scope($request);

            if (! $this->cases->find($case, $scope)) {
                return $this->failure('No such case.', 404);
            }

            return $this->success('Evidence loaded.', [
                'evidence' => $this->evidence->forCase($case, $scope),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function explanation(Request $request, int $case)
    {
        try {
            $scope = $this->scope($request);

            if (! $this->cases->find($case, $scope)) {
                return $this->failure('No such case.', 404);
            }

            return $this->success('Explanation loaded.', [
                'explanation' => $this->explanations->latestForCase(
                    $case,
                    $scope,
                    $request->input('audience', 'teacher')
                ),
                // Refused explanations are returned too — an administrator needs to
                // see what the system declined to say, and why.
                'history' => $request->boolean('include_history')
                    ? $this->explanations->allForCase($case, $scope)
                    : null,
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function recommendations(Request $request, int $case)
    {
        try {
            $scope = $this->scope($request);

            if (! $this->cases->find($case, $scope)) {
                return $this->failure('No such case.', 404);
            }

            return $this->success('Recommendations loaded.', [
                'recommendations' => $this->recommendations->forCase($case, $scope),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function updateStatus(Request $request, int $case)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'status' => 'required|string|in:open,analysing,awaiting_decision,in_progress,closed,dismissed',
            ]);

            if (! $this->cases->updateStatus($case, $validated['status'], $scope)) {
                return $this->failure('No such case.', 404);
            }

            return $this->success('Case updated.', ['case' => $this->cases->find($case, $scope)]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Everything the intelligence layer knows about one subject — the payload behind
     * a student profile's AI tabs.
     */
    public function forSubject(Request $request, string $entity, int $id)
    {
        try {
            $scope = $this->scope($request);

            $cases = collect($this->cases->list($scope, null, $request->input('status'), null, 50))
                ->where('subject_entity_key', $entity)
                ->where('subject_id', $id)
                ->values()
                ->all();

            return $this->success('Subject intelligence loaded.', [
                'entity' => $entity,
                'id' => $id,
                'cases' => $cases,
                'signals' => $this->signals->historyFor($entity, $id, $scope),
                'evidence' => $this->evidence->forSubject($entity, $id, $scope, null, 50),
                'outcomes' => $this->outcomes->forSubject($entity, $id, $scope),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    private function hypotheses(int $caseId, int $subInstituteId): array
    {
        if (! Schema::hasTable('ai_hypotheses')) {
            return [];
        }

        return DB::table('ai_hypotheses')
            ->where('case_id', $caseId)
            ->where('sub_institute_id', $subInstituteId)
            ->orderByDesc('confidence')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'statement' => $row->statement,
                'rationale' => $row->rationale,
                'confidence' => $row->confidence === null ? null : (float) $row->confidence,
                'status' => $row->status,
            ])
            ->all();
    }
}
