<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Outcomes\OutcomeTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Outcome measurement and the audit log.
 *
 * `effectiveness` is the learning loop made visible: which action types actually
 * moved their metric. It is the number that should eventually inform which
 * recommendation an agent drafts, and it only exists because signals, decisions and
 * outcomes are all persisted.
 */
class OutcomeController extends AiController
{
    public function __construct(private readonly OutcomeTracker $outcomes)
    {
    }

    public function index(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if (! Schema::hasTable('ai_outcomes')) {
                return $this->success('No outcomes recorded.', ['outcomes' => []]);
            }

            $query = DB::table('ai_outcomes')
                ->where('sub_institute_id', $scope->selectedInstituteId);

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            $outcomes = $query->orderByDesc('id')
                ->limit($this->limit($request))
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'case_id' => $row->case_id ? (int) $row->case_id : null,
                    'recommendation_id' => $row->recommendation_id ? (int) $row->recommendation_id : null,
                    'subject_entity_key' => $row->subject_entity_key,
                    'subject_id' => $row->subject_id,
                    'metric_key' => $row->metric_key,
                    'metric_label' => $row->metric_label,
                    'baseline_value' => $row->baseline_value === null ? null : (float) $row->baseline_value,
                    'observed_value' => $row->observed_value === null ? null : (float) $row->observed_value,
                    'delta' => $row->delta === null ? null : (float) $row->delta,
                    'status' => $row->status,
                    'measure_after' => $row->measure_after,
                    'observed_at' => $row->observed_at,
                ])
                ->all();

            return $this->success('Outcomes loaded.', ['outcomes' => $outcomes]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Measure everything whose horizon has passed. Safe to call repeatedly.
     */
    public function measureDue(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if (! $scope->isAdmin) {
                return $this->failure('Only an administrator can run outcome measurement.', 403);
            }

            return $this->success(
                'Outcome measurement complete.',
                $this->outcomes->measureDue($scope, $this->limit($request, 100))
            );
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function effectiveness(Request $request)
    {
        try {
            $scope = $this->scope($request);

            return $this->success('Effectiveness loaded.', [
                'by_action_type' => $this->outcomes->effectivenessByActionType(
                    $scope,
                    $request->input('case_type')
                ),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * The AI audit log. Administrators only — it records who decided what.
     */
    public function auditLogs(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if (! $scope->isAdmin) {
                return $this->failure('Only an administrator can read AI audit logs.', 403);
            }

            if (! Schema::hasTable('ai_audit_logs')) {
                return $this->success('No audit logs.', ['logs' => []]);
            }

            $query = DB::table('ai_audit_logs')
                ->where(function ($inner) use ($scope) {
                    $inner->where('sub_institute_id', $scope->selectedInstituteId)
                        ->orWhereNull('sub_institute_id');
                });

            foreach (['event_type', 'outcome', 'subject_entity_key'] as $filter) {
                if ($request->filled($filter)) {
                    $query->where($filter, $request->input($filter));
                }
            }

            if ($request->filled('subject_id')) {
                $query->where('subject_id', (int) $request->input('subject_id'));
            }

            $logs = $query->orderByDesc('id')
                ->limit($this->limit($request))
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'event_type' => $row->event_type,
                    'actor_type' => $row->actor_type,
                    'actor_id' => $row->actor_id,
                    'actor_label' => $row->actor_label,
                    'subject_entity_key' => $row->subject_entity_key,
                    'subject_id' => $row->subject_id,
                    'related_type' => $row->related_type,
                    'related_id' => $row->related_id,
                    'outcome' => $row->outcome,
                    'message' => $row->message,
                    'payload' => $row->payload ? json_decode($row->payload, true) : null,
                    'created_at' => $row->created_at,
                ])
                ->all();

            return $this->success('Audit logs loaded.', ['logs' => $logs]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }
}
