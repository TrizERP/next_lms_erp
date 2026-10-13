<?php

namespace App\Http\Controllers\api\alumni;

use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use App\Services\Alumni\AlumniEngagementService;
use App\Services\Alumni\AlumniService;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Alumni engagement log and score (spec sections 21-22) - staff side.
 *
 *   GET  alumni/engagement/overview            weights, levels, summary counts
 *   GET  alumni/engagement/leaderboard         highest scores first (?limit=)
 *   GET  alumni/engagement/activities          ?alumni_id=&type= (paginated)
 *   POST alumni/engagement/record              log an engagement by hand (guest lecture, campus visit, ...)
 *   POST alumni/engagement/activities/{id}/delete   remove a manual entry (automatic ones are protected)
 *   GET  alumni/{id}/engagement                one alumnus: score, level, recent activity
 *
 * Automatic sources (a completed mentoring session, a published opportunity, later events and donations) write
 * through AlumniEngagementService with a reference, so they are idempotent; only entries without a reference - the
 * ones staff typed in - can be deleted here, so the audit trail of automatic credit cannot be quietly edited away.
 */
class AlumniEngagementApiController extends Controller
{
    use AlumniApiSupport;

    public function __construct(private AlumniService $alumni, private AlumniEngagementService $engagement)
    {
    }

    public function overview(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $since = now()->subMonths($this->engagement->windowMonths($t));
        $base = fn () => DB::table('alumni_activity')->where('sub_institute_id', $t)->whereIn('activity_type', array_keys(AlumniEngagementService::TYPES))->where('occurred_at', '>=', $since);

        return $this->ok([
            'types' => collect(AlumniEngagementService::TYPES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'points' => $this->engagement->weights($t)[$key]])->values(),
            'window_months' => $this->engagement->windowMonths($t),
            'levels' => array_merge(AlumniEngagementService::DEFAULT_LEVELS, array_intersect_key((array) $this->alumni->setting($t, 'engagement_levels', []), AlumniEngagementService::DEFAULT_LEVELS)),
            'summary' => [
                'engaged_alumni' => (int) $base()->distinct()->count('alumni_id'),
                'activities' => (int) $base()->count(),
                'guest_speakers' => (int) $base()->where('activity_type', 'guest_lecture')->distinct()->count('alumni_id'),
                'active_mentors' => (int) DB::table('alumni_mentor_profiles')->where('sub_institute_id', $t)->where('status', 'active')->count(),
                'by_type' => $base()->groupBy('activity_type')->selectRaw('activity_type as type, COUNT(*) as total')->pluck('total', 'type'),
            ],
        ]);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $limit = max(1, min(100, (int) ($request->input('limit') ?: 25)));
        $scores = $this->engagement->scores($t);
        arsort($scores);
        $top = array_slice($scores, 0, $limit, true);
        $people = DB::table('alumni')->where('sub_institute_id', $t)->whereIn('id', array_keys($top))->get(['id', 'full_name', 'alumni_code', 'batch', 'status'])->keyBy('id');
        $rows = [];
        foreach ($top as $id => $score) {
            if (! isset($people[$id])) {
                continue;
            }
            $rows[] = ['alumni_id' => (int) $id, 'full_name' => $people[$id]->full_name, 'alumni_code' => $people[$id]->alumni_code, 'batch' => $people[$id]->batch,
                'score' => $score, 'level' => $this->engagement->level($t, $score), 'level_label' => AlumniEngagementService::LEVEL_LABELS[$this->engagement->level($t, $score)]];
        }

        return $this->ok($rows);
    }

    public function activities(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['engagement_type' => ['nullable', Rule::in(array_keys(AlumniEngagementService::TYPES))], 'alumni_id' => 'nullable|integer|min:1', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $q = DB::table('alumni_activity as x')->join('alumni as a', function ($j) {
            $j->on('a.id', '=', 'x.alumni_id')->on('a.sub_institute_id', '=', 'x.sub_institute_id');
        })->where('x.sub_institute_id', $t)->whereIn('x.activity_type', array_keys(AlumniEngagementService::TYPES));
        if ($request->filled('engagement_type')) {
            $q->where('x.activity_type', $request->input('engagement_type'));
        }
        if ($request->filled('alumni_id')) {
            $q->where('x.alumni_id', (int) $request->input('alumni_id'));
        }
        $total = (clone $q)->count();
        $per = (int) ($request->input('per_page') ?: 25);
        $page = (int) ($request->input('page') ?: 1);
        $rows = $q->orderByDesc('x.occurred_at')->orderByDesc('x.id')->forPage($page, $per)
            ->get(['x.id', 'x.alumni_id', 'x.activity_type', 'x.title', 'x.detail', 'x.points', 'x.ref_type', 'x.occurred_at', 'a.full_name', 'a.alumni_code']);

        return $this->ok(['rows' => $rows, 'pagination' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'last_page' => (int) max(1, ceil($total / $per))]]);
    }

    public function record(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'alumni_id' => 'required|integer|min:1', 'engagement_type' => ['required', Rule::in(array_keys(AlumniEngagementService::TYPES))],
            'title' => 'required|string|max:255', 'occurred_on' => 'nullable|date|before_or_equal:today',
            'points' => 'nullable|integer|between:0,100', 'detail' => 'nullable|string|max:2000',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $alumnus = $this->alumni->find($t, (int) $request->input('alumni_id'));
        if (! $alumnus) {
            return $this->validationError(['alumni_id' => ['That alumnus was not found.']]);
        }
        $id = $this->engagement->record($t, (int) $alumnus->id, (string) $request->input('engagement_type'), (string) $request->input('title'), $this->userId($request),
            $request->input('occurred_on'), $request->filled('points') ? (int) $request->input('points') : null, null, null, $request->input('detail'));
        AuditTrail::record($t, 'alumni', 'alumni.engagement', 'record', ['entity_type' => 'alumni_activity', 'entity_id' => $id, 'after' => ['alumni_id' => (int) $alumnus->id, 'type' => $request->input('engagement_type')]], $request);
        $score = $this->engagement->scores($t, [(int) $alumnus->id])[(int) $alumnus->id];

        return $this->ok(['id' => $id, 'score' => $score, 'level' => $this->engagement->level($t, $score)], 'Engagement recorded.', 201);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $row = DB::table('alumni_activity')->where('sub_institute_id', $t)->where('id', (int) $id)->whereIn('activity_type', array_keys(AlumniEngagementService::TYPES))->first();
        if (! $row) {
            return $this->notFound('Engagement entry');
        }
        if ($row->ref_type !== null) {
            return response()->json(['status_code' => 0, 'message' => 'This credit was recorded automatically (' . str_replace('_', ' ', $row->ref_type) . ') and cannot be deleted here.'], 409);
        }
        DB::table('alumni_activity')->where('id', $row->id)->delete();
        AuditTrail::record($t, 'alumni', 'alumni.engagement', 'delete', ['entity_type' => 'alumni_activity', 'entity_id' => (int) $id, 'before' => ['alumni_id' => (int) $row->alumni_id, 'type' => $row->activity_type, 'points' => (int) $row->points]], $request);

        return $this->ok(null, 'Entry removed.');
    }

    public function forAlumnus(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        if (! $this->alumni->find($t, (int) $id)) {
            return $this->notFound();
        }
        $score = $this->engagement->scores($t, [(int) $id])[(int) $id];
        $level = $this->engagement->level($t, $score);

        return $this->ok([
            'score' => $score, 'level' => $level, 'level_label' => AlumniEngagementService::LEVEL_LABELS[$level], 'window_months' => $this->engagement->windowMonths($t),
            'recent' => DB::table('alumni_activity')->where('sub_institute_id', $t)->where('alumni_id', (int) $id)->whereIn('activity_type', array_keys(AlumniEngagementService::TYPES))
                ->orderByDesc('occurred_at')->limit(20)->get(['id', 'activity_type', 'title', 'points', 'ref_type', 'occurred_at']),
        ]);
    }
}
