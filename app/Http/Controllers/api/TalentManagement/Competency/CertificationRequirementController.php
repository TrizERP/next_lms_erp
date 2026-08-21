<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\CertificationRequirementController`.
 *
 * Certification requirements (s_competency_certification_requirements) - the
 * policy layer behind the Certification & Compliance Center's "Add
 * Certification Requirement" action and its Requirements tab. Logic, field
 * names and response shapes are unchanged from the source; only tenant/actor
 * context resolution was adapted to the hydrated session, per
 * `ResolvesCompetencyContext`.
 */
class CertificationRequirementController extends Controller
{
    use ResolvesCompetencyContext;

    private const TABLE = 's_competency_certification_requirements';
    private const CERTIFICATIONS = 's_competency_certifications';

    private const SORTABLE = [
        'name'         => 'name',
        'is_mandatory' => 'is_mandatory',
        'status'       => 'status',
        'created_at'   => 'created_at',
    ];

    /**
     * GET /api/competency/certification-requirements
     *
     * Filters: search, status, department_id, jobrole, certification_type,
     *          is_mandatory
     */
    public function index(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $perPage = min(max((int) $request->input('per_page', 50), 1), 200);
        $page = max((int) $request->input('page', 1), 1);

        $query = DB::table(self::TABLE)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at');

        if ($search = $this->activeFilter($request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('issuing_body', 'like', "%{$search}%")
                    ->orWhere('jobrole', 'like', "%{$search}%");
            });
        }
        if ($status = $this->activeFilter($request->input('status'))) {
            $query->where('status', $status);
        }
        if ($departmentId = $this->activeFilter($request->input('department_id'))) {
            $query->where('department_id', $departmentId);
        }
        if ($jobrole = $this->activeFilter($request->input('jobrole'))) {
            $query->where('jobrole', $jobrole);
        }
        if ($type = $this->activeFilter($request->input('certification_type'))) {
            $query->where('certification_type', $type);
        }
        if (($mandatory = $request->input('is_mandatory')) !== null && $mandatory !== '' && $mandatory !== 'all') {
            $query->where('is_mandatory', filter_var($mandatory, FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }

        $total = (clone $query)->count();

        $sortKey = (string) $request->input('sort', 'name');
        $column = self::SORTABLE[$sortKey] ?? 'name';
        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $rows = $query->orderBy($column, $direction)->orderByDesc('id')
            ->forPage($page, $perPage)->get();

        return response()->json([
            'status'     => 1,
            'message'    => 'Certification requirements fetched successfully',
            'data'       => $this->decorateRows($rows, $sid),
            'pagination' => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => (int) max(ceil($total / $perPage), 1),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $id = DB::table(self::TABLE)->insertGetId([
            'sub_institute_id'      => $context['sub_institute_id'],
            'name'                  => $request->input('name'),
            'certification_type'    => $request->input('certification_type'),
            'issuing_body'          => $request->input('issuing_body'),
            'description'           => $request->input('description'),
            'department_id'         => $request->input('department_id'),
            'jobrole'               => $this->activeFilter($request->input('jobrole')),
            'competency_id'         => $request->input('competency_id'),
            'is_mandatory'          => $request->boolean('is_mandatory', true) ? 1 : 0,
            'validity_months'       => $request->input('validity_months'),
            'renewal_reminder_days' => $request->input('renewal_reminder_days', 60),
            'grace_period_days'     => $request->input('grace_period_days'),
            'status'                => $request->input('status', 'active'),
            'created_by'            => $context['user_id'],
            'updated_by'            => $context['user_id'],
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        $this->logCompetencyActivity(
            $context['sub_institute_id'],
            $context['user_id'],
            'added_certification_requirement',
            'Added certification requirement "' . $request->input('name') . '"',
            'certification_requirement',
            $id,
            $request->input('name')
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Certification requirement created successfully',
            'data'    => ['id' => $id],
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $existing = $this->find($id, $sid);
        if (!$existing) {
            return response()->json(['status' => 0, 'message' => 'Certification requirement not found'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules(true));
        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $update = ['updated_by' => $context['user_id'], 'updated_at' => now()];

        foreach (['name', 'certification_type', 'issuing_body', 'description', 'department_id',
            'competency_id', 'validity_months', 'renewal_reminder_days', 'grace_period_days', 'status'] as $field) {
            if ($request->has($field)) {
                $update[$field] = $request->input($field);
            }
        }
        if ($request->has('jobrole')) {
            $update['jobrole'] = $this->activeFilter($request->input('jobrole'));
        }
        if ($request->has('is_mandatory')) {
            $update['is_mandatory'] = $request->boolean('is_mandatory') ? 1 : 0;
        }

        DB::table(self::TABLE)->where('id', $id)->update($update);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'updated_certification_requirement',
            'Updated certification requirement "' . ($request->input('name') ?: $existing->name) . '"',
            'certification_requirement',
            (int) $id,
            $request->input('name') ?: $existing->name
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Certification requirement updated successfully',
            'data'    => ['id' => (int) $id],
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $existing = $this->find($id, $sid);
        if (!$existing) {
            return response()->json(['status' => 0, 'message' => 'Certification requirement not found'], 404);
        }

        DB::table(self::TABLE)->where('id', $id)->update([
            'deleted_at' => now(),
            'deleted_by' => $context['user_id'],
        ]);

        // Held credentials keep their history - only the policy link is cleared.
        DB::table(self::CERTIFICATIONS)
            ->where('sub_institute_id', $sid)
            ->where('requirement_id', $id)
            ->update(['requirement_id' => null, 'updated_at' => now()]);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'deleted_certification_requirement',
            'Deleted certification requirement "' . $existing->name . '"',
            'certification_requirement',
            (int) $id,
            $existing->name
        );

        return response()->json(['status' => 1, 'message' => 'Certification requirement deleted successfully']);
    }

    /**
     * GET /api/competency/certification-requirements/department-options
     *
     * Not part of the original G2G port - added so the "Add Certification
     * Requirement" dialog's Department dropdown lists every real department
     * (from `s_user_jobrole`, the same source `decorateRows()` already
     * resolves names from) rather than only departments that happen to
     * already have a requirement or certification recorded against them.
     * Job roles for the selected department are fetched by the frontend from
     * the existing `GET /competency/career-paths/role-options?department_id=`
     * endpoint (`CareerPathController::roleOptions`) - reused as-is rather
     * than duplicated here.
     */
    public function departmentOptions(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $departments = DB::table('s_user_jobrole')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->whereNotNull('department_id')
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->select('department_id', 'department')
            ->distinct()
            ->get()
            ->unique('department_id')
            ->sortBy('department')
            ->map(fn ($r) => ['value' => (string) $r->department_id, 'label' => $r->department])
            ->values()
            ->all();

        return response()->json([
            'status'  => 1,
            'message' => 'Department options fetched successfully',
            'data'    => $departments,
        ]);
    }

    /* ------------------------------------------------------------------ */

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return [
            'name'                  => $required . '|string|max:191',
            'certification_type'    => 'nullable|string|max:100',
            'issuing_body'          => 'nullable|string|max:191',
            'description'           => 'nullable|string',
            'department_id'         => 'nullable|integer',
            'jobrole'               => 'nullable|string|max:191',
            'competency_id'         => 'nullable|integer',
            'is_mandatory'          => 'nullable|boolean',
            'validity_months'       => 'nullable|integer|min:1|max:600',
            'renewal_reminder_days' => 'nullable|integer|min:0|max:365',
            'grace_period_days'     => 'nullable|integer|min:0|max:365',
            'status'                => 'nullable|in:active,archived',
        ];
    }

    private function find($id, int $sid)
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Adds the department name plus live coverage - how many people already
     * hold the credential this requirement asks for.
     */
    private function decorateRows($rows, int $sid): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $departmentIds = $rows->pluck('department_id')->filter()->unique()->all();
        $departments = [];
        if ($departmentIds) {
            $departments = DB::table('s_user_jobrole')
                ->where('sub_institute_id', $sid)
                ->whereIn('department_id', $departmentIds)
                ->whereNull('deleted_at')
                ->pluck('department', 'department_id')
                ->all();
        }

        $names = $rows->pluck('name')->unique()->all();
        $heldByName = DB::table(self::CERTIFICATIONS)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->whereIn('name', $names)
            ->whereNotIn('status', ['revoked', 'expired'])
            ->select('name', DB::raw('COUNT(DISTINCT user_id) as holders'))
            ->groupBy('name')
            ->pluck('holders', 'name')
            ->all();

        return $rows->map(function ($r) use ($departments, $heldByName) {
            return [
                'id'                    => (int) $r->id,
                'name'                  => $r->name,
                'certification_type'    => $r->certification_type,
                'issuing_body'          => $r->issuing_body,
                'description'           => $r->description,
                'department_id'         => $r->department_id ? (int) $r->department_id : null,
                'department'            => $r->department_id ? ($departments[$r->department_id] ?? null) : null,
                'jobrole'               => $r->jobrole,
                'competency_id'         => $r->competency_id ? (int) $r->competency_id : null,
                'is_mandatory'          => (bool) $r->is_mandatory,
                'validity_months'       => $r->validity_months ? (int) $r->validity_months : null,
                'renewal_reminder_days' => $r->renewal_reminder_days ? (int) $r->renewal_reminder_days : null,
                'grace_period_days'     => $r->grace_period_days ? (int) $r->grace_period_days : null,
                'status'                => $r->status,
                'scope'                 => $r->jobrole
                    ?: ($r->department_id ? ($departments[$r->department_id] ?? 'Department') : 'Organisation-wide'),
                'holders'               => (int) ($heldByName[$r->name] ?? 0),
                'created_at'            => $r->created_at,
            ];
        })->values()->all();
    }
}
