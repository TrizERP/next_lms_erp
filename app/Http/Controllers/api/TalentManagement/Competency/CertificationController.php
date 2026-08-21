<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\CertificationController`.
 *
 * The Certification & Compliance Center: list/metrics/filters/export, a single
 * credential's detail/edit/verify/revoke, bulk actions, the requirement +
 * compliance view behind a credential, its documents (stored on the shared
 * evidence table) and its history. Logic, field names and response shapes are
 * unchanged from the source; only tenant/actor context resolution was adapted
 * to the hydrated session, per `ResolvesCompetencyContext`. File uploads keep
 * the source's `digitalocean` disk - the same disk the rest of this ERP's
 * document uploads already use.
 */
class CertificationController extends Controller
{
    use ResolvesCompetencyContext;

    private const TABLE = 's_competency_certifications';
    private const REQUIREMENTS = 's_competency_certification_requirements';
    private const EVIDENCE = 's_competency_evidence';

    /** How near an expiry has to be before the credential counts as "Expiring Soon". */
    private const EXPIRING_WINDOW_DAYS = 60;

    /** Raw DB status -> the label the screen renders. */
    private const STATUS_LABELS = [
        'valid'    => 'Active',
        'expiring' => 'Expiring',
        'expired'  => 'Expired',
        'revoked'  => 'Revoked',
    ];

    private const SORTABLE = [
        'name'         => 'name',
        'status'       => 'status',
        'issued_date'  => 'issued_date',
        'expiry_date'  => 'expiry_date',
        'created_at'   => 'created_at',
    ];

    /** Columns worth reporting in the Audit Center's Change Summary, with the label that card shows. */
    private const CHANGE_LABELS = [
        'name'                => 'Certification Name',
        'user_id'             => 'Holder',
        'competency_id'       => 'Linked Competency',
        'requirement_id'      => 'Requirement',
        'issuing_body'        => 'Issuing Body',
        'certification_type'  => 'Certification Type',
        'credential_id'       => 'Credential ID',
        'department_id'       => 'Department',
        'jobrole'             => 'Job Role',
        'status'              => 'Status',
        'verification_status' => 'Verification Status',
        'issued_date'         => 'Issued Date',
        'expiry_date'         => 'Expiry Date',
        'notes'               => 'Notes',
    ];

    /* ================================================================== *
     * List
     * ================================================================== */

    /** GET /api/competency/certifications */
    public function index(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $perPage = min(max((int) $request->input('per_page', 15), 1), 200);
        $page = max((int) $request->input('page', 1), 1);

        $query = $this->filteredQuery($request, $sid);
        $total = (clone $query)->count();

        $sortKey = (string) $request->input('sort', 'created_at');
        $column = self::SORTABLE[$sortKey] ?? 'id';
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $rows = $query->orderBy($column, $direction)->orderByDesc('id')
            ->forPage($page, $perPage)->get();

        return response()->json([
            'status'     => 1,
            'message'    => 'Certifications fetched successfully',
            'data'       => $this->decorateRows($rows, $sid),
            'pagination' => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => (int) max(ceil($total / $perPage), 1),
            ],
        ]);
    }

    /** GET /api/competency/certifications/export */
    public function export(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $rows = $this->filteredQuery($request, $sid)->orderByDesc('id')->limit(5000)->get();

        return response()->json([
            'status'  => 1,
            'message' => 'Certifications exported successfully',
            'data'    => $this->decorateRows($rows, $sid),
        ]);
    }

    /** GET /api/competency/certifications/metrics */
    public function metrics(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $base = fn () => DB::table(self::TABLE)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at');

        $today = now()->toDateString();
        $window = now()->addDays(self::EXPIRING_WINDOW_DAYS)->toDateString();

        $total = (clone $base())->count();

        $expiringSoon = (clone $base())
            ->whereNotIn('status', ['revoked', 'expired'])
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$today, $window])
            ->count();

        $expired = (clone $base())
            ->where('status', '!=', 'revoked')
            ->where(function ($q) use ($today) {
                $q->where('status', 'expired')
                    ->orWhere(function ($inner) use ($today) {
                        $inner->whereNotNull('expiry_date')->where('expiry_date', '<', $today);
                    });
            })
            ->count();

        $pendingVerification = (clone $base())->where('verification_status', 'pending')->count();

        $compliance = $this->employeeCompliance($sid);

        return response()->json([
            'status'  => 1,
            'message' => 'Certification metrics fetched successfully',
            'data'    => [
                'total'                  => $total,
                'compliant_employees'    => $compliance['compliant'],
                'employees_evaluated'    => $compliance['universe'],
                'compliant_percent'      => $compliance['percent'],
                'expiring_soon'          => $expiringSoon,
                'expiring_window_days'   => self::EXPIRING_WINDOW_DAYS,
                'expired'                => $expired,
                'pending_verification'   => $pendingVerification,
                'revoked'                => (clone $base())->where('status', 'revoked')->count(),
                'verified'               => (clone $base())->where('verification_status', 'verified')->count(),
                'requirements'           => DB::table(self::REQUIREMENTS)
                    ->where('sub_institute_id', $sid)->whereNull('deleted_at')
                    ->where('status', 'active')->count(),
            ],
        ]);
    }

    /** GET /api/competency/certifications/filters */
    public function filters(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $base = fn () => DB::table(self::TABLE)
            ->where('sub_institute_id', $sid)->whereNull('deleted_at');

        $departmentIds = (clone $base())->whereNotNull('department_id')
            ->distinct()->pluck('department_id')->all();

        $departments = [];
        if ($departmentIds) {
            $names = DB::table('s_user_jobrole')
                ->where('sub_institute_id', $sid)
                ->whereIn('department_id', $departmentIds)
                ->whereNull('deleted_at')
                ->pluck('department', 'department_id')
                ->all();

            foreach ($departmentIds as $id) {
                $departments[] = [
                    'value' => (string) $id,
                    'label' => $names[$id] ?? ('Department ' . $id),
                ];
            }
            usort($departments, fn ($a, $b) => strcasecmp($a['label'], $b['label']));
        }

        $simpleList = function (string $column) use ($base) {
            $values = (clone $base())->whereNotNull($column)->where($column, '!=', '')
                ->distinct()->orderBy($column)->pluck($column)->all();

            return array_map(fn ($v) => ['value' => (string) $v, 'label' => (string) $v], $values);
        };

        return response()->json([
            'status'  => 1,
            'message' => 'Certification filters fetched successfully',
            'data'    => [
                'departments'         => $departments,
                'certification_types' => $simpleList('certification_type'),
                'issuing_bodies'      => $simpleList('issuing_body'),
                'jobroles'            => $simpleList('jobrole'),
                'statuses'            => [
                    ['value' => 'valid', 'label' => 'Active'],
                    ['value' => 'expiring', 'label' => 'Expiring'],
                    ['value' => 'expired', 'label' => 'Expired'],
                    ['value' => 'revoked', 'label' => 'Revoked'],
                ],
                'compliance'          => [
                    ['value' => 'compliant', 'label' => 'Compliant'],
                    ['value' => 'expiring', 'label' => 'Expiring Soon'],
                    ['value' => 'non_compliant', 'label' => 'Non-Compliant'],
                ],
                'expiry_windows'      => [
                    ['value' => '30', 'label' => 'Next 30 days'],
                    ['value' => '60', 'label' => 'Next 60 days'],
                    ['value' => '90', 'label' => 'Next 90 days'],
                    ['value' => 'expired', 'label' => 'Already expired'],
                ],
                'verification'        => [
                    ['value' => 'pending', 'label' => 'Pending Verification'],
                    ['value' => 'verified', 'label' => 'Verified'],
                    ['value' => 'rejected', 'label' => 'Rejected'],
                ],
            ],
        ]);
    }

    /* ================================================================== *
     * Single credential
     * ================================================================== */

    /** GET /api/competency/certifications/{id} */
    public function show(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $row = $this->findCertification($id, $sid);
        if (!$row) {
            return response()->json(['status' => 0, 'message' => 'Certification not found'], 404);
        }

        $data = $this->decorateRows(collect([$row]), $sid)[0];
        $data['employee'] = $row->user_id ? $this->employeeCard((int) $row->user_id, $sid) : null;
        $data['requirement'] = $row->requirement_id
            ? DB::table(self::REQUIREMENTS)->where('id', $row->requirement_id)
                ->where('sub_institute_id', $sid)->whereNull('deleted_at')->first()
            : null;
        $data['competency'] = $row->competency_id
            ? DB::table('s_users_skills')->where('id', $row->competency_id)->value('title')
            : null;

        return response()->json([
            'status'  => 1,
            'message' => 'Certification fetched successfully',
            'data'    => $data,
        ]);
    }

    public function store(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'name'                => 'required|string|max:191',
            'user_id'             => 'nullable|integer',
            'user_id_target'      => 'nullable|integer',
            'competency_id'       => 'nullable|integer',
            'requirement_id'      => 'nullable|integer',
            'issuing_body'        => 'nullable|string|max:191',
            'certification_type'  => 'nullable|string|max:100',
            'credential_id'       => 'nullable|string|max:191',
            'department_id'       => 'nullable|integer',
            'jobrole'             => 'nullable|string|max:191',
            'status'              => 'nullable|in:valid,expiring,expired,revoked',
            // verification_status is not accepted on create - see below.
            'issued_date'         => 'nullable|date',
            'expiry_date'         => 'nullable|date',
            'notes'               => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $id = DB::table(self::TABLE)->insertGetId([
            'sub_institute_id'    => $context['sub_institute_id'],
            'name'                => $request->input('name'),
            'user_id'             => $request->input('user_id_target') ?: $request->input('user_id'),
            'competency_id'       => $request->input('competency_id'),
            'requirement_id'      => $request->input('requirement_id'),
            'issuing_body'        => $request->input('issuing_body'),
            'certification_type'  => $request->input('certification_type'),
            'credential_id'       => $request->input('credential_id'),
            'department_id'       => $request->input('department_id'),
            'jobrole'             => $request->input('jobrole'),
            'status'              => $request->input('status', 'valid'),
            // Verification state is SERVER-OWNED: a credential is born
            // 'pending' and only update() (which stamps verified_by/_at) may
            // move it, so an uploaded credential can never verify itself.
            'verification_status' => 'pending',
            'issued_date'         => $request->input('issued_date'),
            'expiry_date'         => $request->input('expiry_date'),
            'notes'               => $request->input('notes'),
            'created_by'          => $context['user_id'],
            'updated_by'          => $context['user_id'],
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $this->logCompetencyActivity(
            $context['sub_institute_id'],
            $context['user_id'],
            'added_certification',
            'Added certification "' . $request->input('name') . '"',
            'certification',
            $id,
            $request->input('name')
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Certification created successfully',
            'data'    => ['id' => $id],
        ], 201);
    }

    /**
     * PUT /api/competency/certifications/{id}
     *
     * Backs Edit, the row menu's "Update Status" and "Revoke", and the
     * Verify / Reject verification actions.
     */
    public function update(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $existing = $this->findCertification($id, $sid);
        if (!$existing) {
            return response()->json(['status' => 0, 'message' => 'Certification not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'                => 'sometimes|required|string|max:191',
            // The credential's holder travels as user_id_target - user_id is
            // the context actor on every /competency/* call.
            'user_id_target'      => 'sometimes|nullable|integer',
            'competency_id'       => 'sometimes|nullable|integer',
            'requirement_id'      => 'sometimes|nullable|integer',
            'issuing_body'        => 'sometimes|nullable|string|max:191',
            'certification_type'  => 'sometimes|nullable|string|max:100',
            'credential_id'       => 'sometimes|nullable|string|max:191',
            'department_id'       => 'sometimes|nullable|integer',
            'jobrole'             => 'sometimes|nullable|string|max:191',
            'status'              => 'sometimes|required|in:valid,expiring,expired,revoked',
            'verification_status' => 'sometimes|required|in:pending,verified,rejected',
            'issued_date'         => 'sometimes|nullable|date',
            'expiry_date'         => 'sometimes|nullable|date',
            'notes'               => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $fields = ['name', 'competency_id', 'requirement_id', 'issuing_body',
            'certification_type', 'credential_id', 'department_id', 'jobrole', 'status',
            'verification_status', 'issued_date', 'expiry_date', 'notes'];

        $update = ['updated_by' => $context['user_id'], 'updated_at' => now()];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $update[$field] = $request->input($field);
            }
        }
        if ($request->has('user_id_target')) {
            $update['user_id'] = $request->input('user_id_target');
        }

        if ($request->has('verification_status')) {
            $newState = $request->input('verification_status');
            $update['verified_by'] = $newState === 'pending' ? null : $context['user_id'];
            $update['verified_at'] = $newState === 'pending' ? null : now();
        }

        DB::table(self::TABLE)->where('id', $id)->update($update);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            $this->updateAction($request),
            $this->updateDescription($request, $existing->name),
            'certification',
            (int) $id,
            $existing->name,
            $this->diffChanges($existing, $update, self::CHANGE_LABELS)
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Certification updated successfully',
            'data'    => ['id' => (int) $id],
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $existing = $this->findCertification($id, $context['sub_institute_id']);
        if (!$existing) {
            return response()->json(['status' => 0, 'message' => 'Certification not found'], 404);
        }

        DB::table(self::TABLE)->where('id', $id)->update([
            'deleted_at' => now(),
            'deleted_by' => $context['user_id'],
        ]);

        $this->logCompetencyActivity(
            $context['sub_institute_id'],
            $context['user_id'],
            'deleted_certification',
            'Deleted certification "' . $existing->name . '"',
            'certification',
            (int) $id,
            $existing->name
        );

        return response()->json(['status' => 1, 'message' => 'Certification deleted successfully']);
    }

    /**
     * POST /api/competency/certifications/bulk
     * action = verify | reject | update_status | revoke | delete
     */
    public function bulk(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:verify,reject,update_status,revoke,delete',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
            'status' => 'required_if:action,update_status|nullable|in:valid,expiring,expired,revoked',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $action = $request->input('action');
        $ids = array_values(array_unique(array_map('intval', $request->input('ids'))));

        $ownedIds = DB::table(self::TABLE)
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')
            ->whereIn('id', $ids)->pluck('id')->all();

        if (!$ownedIds) {
            return response()->json(['status' => 0, 'message' => 'No matching certifications found'], 404);
        }

        $update = ['updated_by' => $context['user_id'], 'updated_at' => now()];

        switch ($action) {
            case 'verify':
                $update += ['verification_status' => 'verified', 'verified_by' => $context['user_id'], 'verified_at' => now()];
                $label = 'Verified';
                break;
            case 'reject':
                $update += ['verification_status' => 'rejected', 'verified_by' => $context['user_id'], 'verified_at' => now()];
                $label = 'Rejected';
                break;
            case 'revoke':
                $update += ['status' => 'revoked'];
                $label = 'Revoked';
                break;
            case 'update_status':
                $update += ['status' => $request->input('status')];
                $label = 'Status updated for';
                break;
            default: // delete
                $update += ['deleted_at' => now(), 'deleted_by' => $context['user_id']];
                $label = 'Deleted';
                break;
        }

        DB::table(self::TABLE)->whereIn('id', $ownedIds)->update($update);

        $count = count($ownedIds);
        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'bulk_' . $action . '_certifications',
            $label . ' ' . $count . ' certification' . ($count === 1 ? '' : 's'),
            'certification',
            null,
            $count . ' certification' . ($count === 1 ? '' : 's')
        );

        return response()->json([
            'status'  => 1,
            'message' => $label . ' ' . $count . ' certification' . ($count === 1 ? '' : 's'),
            'data'    => ['affected' => $count],
        ]);
    }

    /** POST /api/competency/certifications/{id}/notes */
    public function addNote(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $existing = $this->findCertification($id, $sid);
        if (!$existing) {
            return response()->json(['status' => 0, 'message' => 'Certification not found'], 404);
        }

        $validator = Validator::make($request->all(), ['note' => 'required|string|max:2000']);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $author = $context['user_id']
            ? DB::table('tbluser')->where('id', $context['user_id'])->first()
            : null;
        $authorName = $author
            ? (trim(($author->first_name ?? '') . ' ' . ($author->last_name ?? '')) ?: ($author->user_name ?? 'User'))
            : 'System';

        $line = '[' . now()->format('d M Y') . ' - ' . $authorName . '] ' . trim($request->input('note'));
        $notes = trim((string) $existing->notes);
        $notes = $notes === '' ? $line : $notes . "\n" . $line;

        DB::table(self::TABLE)->where('id', $id)->update([
            'notes'      => $notes,
            'updated_by' => $context['user_id'],
            'updated_at' => now(),
        ]);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'commented_certification',
            trim($request->input('note')),
            'certification',
            (int) $id,
            $existing->name
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Note added successfully',
            'data'    => ['notes' => $notes],
        ]);
    }

    /* ================================================================== *
     * Side-panel tabs
     * ================================================================== */

    /** GET /api/competency/certifications/{id}/compliance */
    public function compliance(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $row = $this->findCertification($id, $sid);
        if (!$row) {
            return response()->json(['status' => 0, 'message' => 'Certification not found'], 404);
        }

        $state = $this->complianceState($row);
        $requirements = $this->requirementsFor($sid, $row->jobrole, $row->department_id);
        $matched = $this->matchRequirement($requirements, $row->name, $row->requirement_id);

        $percentElapsed = null;
        if ($row->issued_date && $row->expiry_date) {
            $start = strtotime((string) $row->issued_date);
            $end = strtotime((string) $row->expiry_date);
            if ($end > $start) {
                $percentElapsed = (int) min(100, max(0, round(((time() - $start) / ($end - $start)) * 100)));
            }
        }

        $holder = null;
        if ($row->user_id) {
            $all = DB::table(self::TABLE)
                ->where('sub_institute_id', $sid)->where('user_id', $row->user_id)
                ->whereNull('deleted_at')->get();

            $counts = ['compliant' => 0, 'expiring' => 0, 'non_compliant' => 0];
            foreach ($all as $cert) {
                $counts[$this->complianceState($cert)['key']]++;
            }

            $employeeCompliance = $this->employeeCompliance($sid, (int) $row->user_id);
            $holder = [
                'total'          => $all->count(),
                'compliant'      => $counts['compliant'],
                'expiring'       => $counts['expiring'],
                'non_compliant'  => $counts['non_compliant'],
                'is_compliant'   => $employeeCompliance['compliant'] > 0,
                'missing'        => $employeeCompliance['missing'],
            ];
        }

        return response()->json([
            'status'  => 1,
            'message' => 'Compliance fetched successfully',
            'data'    => [
                'compliance'          => $state['label'],
                'compliance_key'      => $state['key'],
                'reason'              => $state['reason'],
                'days_to_expiry'      => $this->daysToExpiry($row->expiry_date),
                'validity_percent'    => $percentElapsed,
                'issued_date'         => $this->humanDate($row->issued_date),
                'expiry_date'         => $this->humanDate($row->expiry_date),
                'verification_status' => $row->verification_status,
                'verified_at'         => $this->humanDate($row->verified_at),
                'verified_by'         => $row->verified_by
                    ? ($this->userMap([(int) $row->verified_by])[(int) $row->verified_by]['name'] ?? null)
                    : null,
                'requirement'         => $matched ? $this->requirementRow($matched) : null,
                'is_required'         => (bool) $matched,
                'window_days'         => self::EXPIRING_WINDOW_DAYS,
                'holder'              => $holder,
            ],
        ]);
    }

    /** GET /api/competency/certifications/{id}/requirements */
    public function requirements(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $row = $this->findCertification($id, $sid);
        if (!$row) {
            return response()->json(['status' => 0, 'message' => 'Certification not found'], 404);
        }

        $requirements = $this->requirementsFor($sid, $row->jobrole, $row->department_id);

        $held = $row->user_id
            ? DB::table(self::TABLE)
                ->where('sub_institute_id', $sid)->where('user_id', $row->user_id)
                ->whereNull('deleted_at')->get()
            : collect();

        $data = [];
        foreach ($requirements as $requirement) {
            $match = null;
            foreach ($held as $cert) {
                $sameRequirement = $cert->requirement_id && (int) $cert->requirement_id === (int) $requirement->id;
                if ($sameRequirement || $this->sameName($cert->name, $requirement->name)) {
                    $state = $this->complianceState($cert);
                    if (!$match || $state['key'] === 'compliant') {
                        $match = ['cert' => $cert, 'state' => $state];
                    }
                }
            }

            $entry = $this->requirementRow($requirement);
            $entry['is_met'] = $match !== null && $match['state']['key'] !== 'non_compliant';
            $entry['held_certification_id'] = $match ? (int) $match['cert']->id : null;
            $entry['held_status'] = $match ? $match['state']['label'] : 'Not Held';
            $entry['held_expiry'] = $match ? $this->humanDate($match['cert']->expiry_date) : null;
            $entry['is_current'] = $match ? ((int) $match['cert']->id === (int) $row->id) : false;
            $data[] = $entry;
        }

        $met = count(array_filter($data, fn ($r) => $r['is_met']));

        return response()->json([
            'status'  => 1,
            'message' => 'Requirements fetched successfully',
            'data'    => $data,
            'summary' => [
                'total'       => count($data),
                'met'         => $met,
                'unmet'       => count($data) - $met,
                'mandatory'   => count(array_filter($data, fn ($r) => $r['is_mandatory'])),
                'met_percent' => count($data) ? (int) round(($met / count($data)) * 100) : 0,
            ],
        ]);
    }

    /** GET /api/competency/certifications/{id}/history */
    public function history(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $row = $this->findCertification($id, $sid);
        if (!$row) {
            return response()->json(['status' => 0, 'message' => 'Certification not found'], 404);
        }

        $entries = DB::table('s_competency_activity_log')
            ->where('sub_institute_id', $sid)
            ->where('subject_type', 'certification')
            ->where('subject_id', $id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $data = $entries->map(fn ($e) => [
            'id'          => (int) $e->id,
            'action'      => $e->action,
            'description' => $e->description,
            'by'          => $e->actor_name ?: 'System',
            'date'        => $this->humanDate($e->created_at),
            'at'          => $e->created_at,
        ])->all();

        $users = $this->userMap(array_filter([$row->created_by, $row->updated_by, $row->verified_by]));
        $stamps = [
            ['label' => 'Certification recorded', 'by' => $row->created_by, 'at' => $row->created_at],
            ['label' => 'Last updated', 'by' => $row->updated_by, 'at' => $row->updated_at],
            ['label' => 'Verified', 'by' => $row->verified_by, 'at' => $row->verified_at],
        ];

        $audit = [];
        foreach ($stamps as $stamp) {
            if (!$stamp['at']) {
                continue;
            }
            $audit[] = [
                'label' => $stamp['label'],
                'by'    => $stamp['by'] ? ($users[(int) $stamp['by']]['name'] ?? 'User ' . $stamp['by']) : 'System',
                'date'  => $this->humanDate($stamp['at']),
            ];
        }

        return response()->json([
            'status'  => 1,
            'message' => 'History fetched successfully',
            'data'    => $data,
            'audit'   => $audit,
        ]);
    }

    /* ================================================================== *
     * Documents (stored on the shared s_competency_evidence table)
     * ================================================================== */

    /** GET /api/competency/certifications/{id}/documents */
    public function documents(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $row = $this->findCertification($id, $sid);
        if (!$row) {
            return response()->json(['status' => 0, 'message' => 'Certification not found'], 404);
        }

        $rows = DB::table(self::EVIDENCE)
            ->where('sub_institute_id', $sid)
            ->where('certification_id', $id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get();

        $users = $this->userMap($rows->pluck('created_by')->filter()->all());

        $data = $rows->map(fn ($r) => [
            'id'            => (int) $r->id,
            'title'         => $r->title,
            'evidence_type' => $r->evidence_type,
            'description'   => $r->description,
            'link'          => $r->link,
            'file_name'     => $r->file_name,
            'file_url'      => $r->file_path ? $this->fileUrl($r->file_path) : null,
            'url'           => $r->file_path ? $this->fileUrl($r->file_path) : $r->link,
            'status'        => $r->status,
            'uploaded_by'   => $r->created_by ? ($users[(int) $r->created_by]['name'] ?? null) : null,
            'uploaded_on'   => $this->humanDate($r->created_at),
        ])->all();

        return response()->json([
            'status'  => 1,
            'message' => 'Documents fetched successfully',
            'data'    => $data,
        ]);
    }

    /**
     * POST /api/competency/certifications/{id}/documents
     * Accepts either an uploaded file (multipart `file`) or an external `link`.
     */
    public function storeDocument(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $row = $this->findCertification($id, $sid);
        if (!$row) {
            return response()->json(['status' => 0, 'message' => 'Certification not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'         => 'required|string|max:191',
            'evidence_type' => 'nullable|in:certification,project,document,endorsement,training',
            'description'   => 'nullable|string',
            'link'          => 'nullable|string|max:500',
            'file'          => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        if (!$request->hasFile('file') && !$this->activeFilter($request->input('link'))) {
            return response()->json([
                'status'  => 0,
                'message' => 'Attach a file or provide a link',
            ], 422);
        }

        $fileName = null;
        $filePath = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $stored = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName);
            $filePath = 'public/competency_certifications/' . $stored;

            try {
                Storage::disk('digitalocean')->putFileAs('public/competency_certifications/', $file, $stored, 'public');
            } catch (\Throwable $e) {
                return response()->json([
                    'status'  => 0,
                    'message' => 'File upload failed: ' . $e->getMessage(),
                ], 500);
            }
        }

        $evidenceId = DB::table(self::EVIDENCE)->insertGetId([
            'sub_institute_id' => $sid,
            'user_id'          => $row->user_id ?: ($context['user_id'] ?? 0),
            'competency_id'    => $row->competency_id,
            'certification_id' => (int) $id,
            'title'            => $request->input('title'),
            'evidence_type'    => $request->input('evidence_type', 'certification'),
            'description'      => $request->input('description'),
            'link'             => $this->activeFilter($request->input('link')),
            'file_name'        => $fileName,
            'file_path'        => $filePath,
            'status'           => 'pending',
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'added_certification_document',
            'Attached document "' . $request->input('title') . '" to certification "' . $row->name . '"',
            'certification',
            (int) $id,
            $request->input('title')
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Document added successfully',
            'data'    => ['id' => $evidenceId],
        ], 201);
    }

    /** DELETE /api/competency/certifications/{id}/documents/{documentId} */
    public function destroyDocument(Request $request, $id, $documentId)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $document = DB::table(self::EVIDENCE)
            ->where('id', $documentId)
            ->where('sub_institute_id', $sid)
            ->where('certification_id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$document) {
            return response()->json(['status' => 0, 'message' => 'Document not found'], 404);
        }

        DB::table(self::EVIDENCE)->where('id', $documentId)->update([
            'deleted_at' => now(),
            'deleted_by' => $context['user_id'],
        ]);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'deleted_certification_document',
            'Removed document "' . $document->title . '"',
            'certification',
            (int) $id,
            $document->title
        );

        return response()->json(['status' => 1, 'message' => 'Document deleted successfully']);
    }

    /* ================================================================== *
     * Helpers
     * ================================================================== */

    /** Shared filter pipeline for index() and export(). */
    private function filteredQuery(Request $request, int $sid)
    {
        $today = now()->toDateString();

        $query = DB::table(self::TABLE)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at');

        if ($status = $this->activeFilter($request->input('status'))) {
            $query->where('status', $status);
        }

        if ($search = $this->activeFilter($request->input('search'))) {
            $userIds = DB::table('tbluser')
                ->where('sub_institute_id', $sid)
                ->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_no', 'like', "%{$search}%")
                        ->orWhereRaw("CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) like ?", ["%{$search}%"]);
                })
                ->limit(500)
                ->pluck('id')->all();

            $query->where(function ($q) use ($search, $userIds) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('credential_id', 'like', "%{$search}%")
                    ->orWhere('issuing_body', 'like', "%{$search}%");
                if ($userIds) {
                    $q->orWhereIn('user_id', $userIds);
                }
            });
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
        if ($competencyId = $this->activeFilter($request->input('competency_id'))) {
            $query->where('competency_id', $competencyId);
        }
        if ($issuer = $this->activeFilter($request->input('issuing_body'))) {
            $query->where('issuing_body', $issuer);
        }
        if ($verification = $this->activeFilter($request->input('verification_status'))) {
            $query->where('verification_status', $verification);
        }
        if ($userFilter = $this->activeFilter($request->input('user_id_filter'))) {
            $query->where('user_id', $userFilter);
        }
        if ($from = $this->activeFilter($request->input('issued_from'))) {
            $query->where('issued_date', '>=', $from);
        }
        if ($to = $this->activeFilter($request->input('issued_to'))) {
            $query->where('issued_date', '<=', $to);
        }
        if ($from = $this->activeFilter($request->input('expiry_from'))) {
            $query->where('expiry_date', '>=', $from);
        }
        if ($to = $this->activeFilter($request->input('expiry_to'))) {
            $query->where('expiry_date', '<=', $to);
        }

        if ($compliance = $this->activeFilter($request->input('compliance'))) {
            $window = now()->addDays(self::EXPIRING_WINDOW_DAYS)->toDateString();

            if ($compliance === 'non_compliant') {
                $query->where(function ($q) use ($today) {
                    $q->whereIn('status', ['revoked', 'expired'])
                        ->orWhere(function ($inner) use ($today) {
                            $inner->whereNotNull('expiry_date')->where('expiry_date', '<', $today);
                        });
                });
            } elseif ($compliance === 'expiring') {
                $query->whereNotIn('status', ['revoked', 'expired'])
                    ->whereNotNull('expiry_date')
                    ->whereBetween('expiry_date', [$today, $window]);
            } elseif ($compliance === 'compliant') {
                $query->whereNotIn('status', ['revoked', 'expired'])
                    ->where(function ($q) use ($window) {
                        $q->whereNull('expiry_date')->orWhere('expiry_date', '>', $window);
                    });
            }
        }

        if ($window = $this->activeFilter($request->input('expiry_window'))) {
            if ($window === 'expired') {
                $query->where(function ($q) use ($today) {
                    $q->where('status', 'expired')
                        ->orWhere(function ($inner) use ($today) {
                            $inner->whereNotNull('expiry_date')->where('expiry_date', '<', $today);
                        });
                });
            } elseif (is_numeric($window)) {
                $until = now()->addDays((int) $window)->toDateString();
                $query->whereNotIn('status', ['revoked', 'expired'])
                    ->whereNotNull('expiry_date')
                    ->whereBetween('expiry_date', [$today, $until]);
            }
        }

        return $query;
    }

    private function findCertification($id, int $sid)
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->first();
    }

    /** @return array{key:string, label:string, reason:string} */
    private function complianceState($row): array
    {
        $expiry = $row->expiry_date ? strtotime((string) $row->expiry_date) : null;
        $today = strtotime(now()->toDateString());

        if ($row->status === 'revoked') {
            return ['key' => 'non_compliant', 'label' => 'Non-Compliant', 'reason' => 'Credential has been revoked'];
        }
        if ($row->status === 'expired' || ($expiry !== null && $expiry < $today)) {
            return ['key' => 'non_compliant', 'label' => 'Non-Compliant', 'reason' => 'Credential has expired'];
        }
        if ($expiry !== null && $expiry <= strtotime('+' . self::EXPIRING_WINDOW_DAYS . ' days', $today)) {
            return [
                'key'    => 'expiring',
                'label'  => 'Expiring Soon',
                'reason' => 'Expires within ' . self::EXPIRING_WINDOW_DAYS . ' days',
            ];
        }

        return ['key' => 'compliant', 'label' => 'Compliant', 'reason' => 'Credential is valid'];
    }

    private function daysToExpiry($expiryDate): ?int
    {
        if (!$expiryDate) {
            return null;
        }
        $today = strtotime(now()->toDateString());

        return (int) floor((strtotime((string) $expiryDate) - $today) / 86400);
    }

    /** @return array<int, array<string, mixed>> */
    private function decorateRows($rows, int $sid): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $users = $this->userMap($rows->pluck('user_id')->filter()->all());

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

        return $rows->map(function ($r) use ($users, $departments) {
            $employee = $r->user_id ? ($users[(int) $r->user_id] ?? null) : null;
            $state = $this->complianceState($r);

            return [
                'id'                  => (int) $r->id,
                'name'                => $r->name,
                'user_id'             => $r->user_id ? (int) $r->user_id : null,
                'employee_name'       => $employee['name'] ?? null,
                'employee_initials'   => $employee['initials'] ?? null,
                'employee_no'         => $employee['employee_no'] ?? null,
                'department_id'       => $r->department_id ? (int) $r->department_id : null,
                'department'          => $r->department_id ? ($departments[$r->department_id] ?? null) : null,
                'jobrole'             => $r->jobrole,
                'issuing_body'        => $r->issuing_body,
                'certification_type'  => $r->certification_type,
                'credential_id'       => $r->credential_id,
                'competency_id'       => $r->competency_id ? (int) $r->competency_id : null,
                'requirement_id'      => $r->requirement_id ? (int) $r->requirement_id : null,
                'status'              => $r->status,
                'status_label'        => self::STATUS_LABELS[$r->status] ?? ucfirst((string) $r->status),
                'compliance'          => $state['label'],
                'compliance_key'      => $state['key'],
                'compliance_reason'   => $state['reason'],
                'verification_status' => $r->verification_status,
                'issued_date'         => $r->issued_date,
                'expiry_date'         => $r->expiry_date,
                'issued_date_label'   => $this->humanDate($r->issued_date),
                'expiry_date_label'   => $this->humanDate($r->expiry_date),
                'days_to_expiry'      => $this->daysToExpiry($r->expiry_date),
                'notes'               => $r->notes,
                'created_at'          => $r->created_at,
            ];
        })->values()->all();
    }

    /** The Overview panel's employee block. */
    private function employeeCard(int $userId, int $sid): ?array
    {
        $user = DB::table('tbluser')->where('id', $userId)->first();
        if (!$user) {
            return null;
        }

        $first = trim((string) $user->first_name);
        $last = trim((string) $user->last_name);
        $name = trim($first . ' ' . $last) ?: ('User ' . $user->id);

        $roleId = ($user->jobtitle_id ?? 0) ?: $this->firstNumeric($user->allocated_standards ?? null);
        $role = $roleId
            ? DB::table('s_user_jobrole')->where('id', $roleId)->whereNull('deleted_at')->first()
            : null;

        $departmentId = $user->department_id ?? null;
        $department = $role->department ?? null;
        if (!$department && $departmentId) {
            $department = DB::table('s_user_jobrole')
                ->where('sub_institute_id', $sid)->where('department_id', $departmentId)
                ->whereNull('deleted_at')->value('department');
        }

        return [
            'id'          => (int) $user->id,
            'name'        => $name,
            'initials'    => (strtoupper(substr($first ?: $name, 0, 1) . substr($last, 0, 1)) ?: strtoupper(substr($name, 0, 2))),
            'employee_no' => $user->employee_no ?? null,
            'email'       => $user->email ?? null,
            'jobrole'     => $role->jobrole ?? null,
            'department'  => $department,
        ];
    }

    /** Requirements that apply to a given role / department (or org-wide). */
    private function requirementsFor(int $sid, ?string $jobrole, $departmentId)
    {
        return DB::table(self::REQUIREMENTS)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->where(function ($q) use ($jobrole, $departmentId) {
                $q->where(function ($inner) {
                    $inner->where(function ($j) {
                        $j->whereNull('jobrole')->orWhere('jobrole', '');
                    })->whereNull('department_id');
                });
                if ($jobrole) {
                    $q->orWhere('jobrole', $jobrole);
                }
                if ($departmentId) {
                    $q->orWhere('department_id', $departmentId);
                }
            })
            ->orderByDesc('is_mandatory')
            ->orderBy('name')
            ->get();
    }

    private function requirementRow($r): array
    {
        return [
            'id'                    => (int) $r->id,
            'name'                  => $r->name,
            'certification_type'    => $r->certification_type,
            'issuing_body'          => $r->issuing_body,
            'description'           => $r->description,
            'department_id'         => $r->department_id ? (int) $r->department_id : null,
            'jobrole'               => $r->jobrole,
            'is_mandatory'          => (bool) $r->is_mandatory,
            'validity_months'       => $r->validity_months ? (int) $r->validity_months : null,
            'renewal_reminder_days' => $r->renewal_reminder_days ? (int) $r->renewal_reminder_days : null,
            'grace_period_days'     => $r->grace_period_days ? (int) $r->grace_period_days : null,
            'scope'                 => $r->jobrole ?: ($r->department_id ? 'Department' : 'Organisation-wide'),
            'status'                => $r->status,
        ];
    }

    /** Find the requirement a held credential satisfies, by id or by name. */
    private function matchRequirement($requirements, ?string $certName, $requirementId)
    {
        foreach ($requirements as $requirement) {
            if ($requirementId && (int) $requirement->id === (int) $requirementId) {
                return $requirement;
            }
        }
        foreach ($requirements as $requirement) {
            if ($this->sameName($certName, $requirement->name)) {
                return $requirement;
            }
        }

        return null;
    }

    private function sameName(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && strcasecmp(trim($a), trim($b)) === 0;
    }

    /**
     * The "Compliant Employees" KPI - measured against REQUIREMENTS, not
     * against every credential a person happens to hold.
     *
     * @return array{compliant:int, universe:int, percent:int, missing:array}
     */
    private function employeeCompliance(int $sid, ?int $onlyUserId = null): array
    {
        $requirements = DB::table(self::REQUIREMENTS)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->get();

        $certQuery = DB::table(self::TABLE)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->whereNotNull('user_id');

        if ($onlyUserId) {
            $certQuery->where('user_id', $onlyUserId);
        }
        $certs = $certQuery->get()->groupBy('user_id');

        // No `whereNull('deleted_at')` here: unlike the G2G source's `tbluser`,
        // this target's `tbluser` has no `deleted_at` column (it tracks active
        // employees via `status` instead) - matching how every other tbluser
        // lookup in this ported namespace already queries it.
        $employeeQuery = DB::table('tbluser')
            ->where('sub_institute_id', $sid);

        if ($onlyUserId) {
            $employeeQuery->where('id', $onlyUserId);
        }
        $employees = $employeeQuery->limit(2000)
            ->get(['id', 'jobtitle_id', 'department_id', 'allocated_standards']);

        $roleIds = [];
        foreach ($employees as $employee) {
            $roleId = ($employee->jobtitle_id ?? 0) ?: $this->firstNumeric($employee->allocated_standards ?? null);
            if ($roleId) {
                $roleIds[$employee->id] = $roleId;
            }
        }
        $roles = $roleIds
            ? DB::table('s_user_jobrole')->whereIn('id', array_values($roleIds))->whereNull('deleted_at')
                ->pluck('jobrole', 'id')->all()
            : [];

        $compliant = 0;
        $universe = 0;
        $missing = [];

        foreach ($employees as $employee) {
            $held = $certs[$employee->id] ?? collect();
            $jobrole = isset($roleIds[$employee->id]) ? ($roles[$roleIds[$employee->id]] ?? null) : null;
            $departmentId = $employee->department_id ?? null;

            if (!$jobrole && $held->isNotEmpty()) {
                $jobrole = $held->first()->jobrole;
            }
            if (!$departmentId && $held->isNotEmpty()) {
                $departmentId = $held->first()->department_id;
            }

            $applicable = $requirements->filter(function ($requirement) use ($jobrole, $departmentId) {
                $orgWide = (!$requirement->jobrole || $requirement->jobrole === '') && !$requirement->department_id;
                if ($orgWide) {
                    return true;
                }
                if ($requirement->jobrole && $jobrole && strcasecmp($requirement->jobrole, $jobrole) === 0) {
                    return true;
                }

                return $requirement->department_id && $departmentId
                    && (int) $requirement->department_id === (int) $departmentId;
            });

            if ($held->isEmpty() && $applicable->isEmpty()) {
                continue;
            }
            $universe++;

            $isCompliant = true;
            $employeeMissing = [];

            foreach ($applicable as $requirement) {
                if (!$requirement->is_mandatory) {
                    continue;
                }
                $met = false;
                foreach ($held as $cert) {
                    $sameRequirement = $cert->requirement_id && (int) $cert->requirement_id === (int) $requirement->id;
                    if (($sameRequirement || $this->sameName($cert->name, $requirement->name))
                        && $this->complianceState($cert)['key'] !== 'non_compliant') {
                        $met = true;
                        break;
                    }
                }
                if (!$met) {
                    $isCompliant = false;
                    $employeeMissing[] = $requirement->name;
                }
            }

            if ($isCompliant) {
                $compliant++;
            } elseif ($employeeMissing) {
                $missing = array_values(array_unique(array_merge($missing, $employeeMissing)));
            }
        }

        return [
            'compliant' => $compliant,
            'universe'  => $universe,
            'percent'   => $universe ? (int) round(($compliant / $universe) * 100) : 0,
            'missing'   => $missing,
        ];
    }

    /** @return array<int, array{name:string, initials:string, employee_no:?string}> */
    private function userMap(array $ids): array
    {
        $ids = array_values(array_filter(array_unique($ids)));
        if (!$ids) {
            return [];
        }

        return DB::table('tbluser')
            ->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name', 'employee_no'])
            ->mapWithKeys(function ($u) {
                $first = trim((string) $u->first_name);
                $last = trim((string) $u->last_name);
                $name = trim($first . ' ' . $last) ?: ('User ' . $u->id);
                $initials = strtoupper(substr($first ?: $name, 0, 1) . substr($last, 0, 1));

                return [(int) $u->id => [
                    'name'        => $name,
                    'initials'    => $initials ?: strtoupper(substr($name, 0, 2)),
                    'employee_no' => $u->employee_no,
                ]];
            })
            ->all();
    }

    private function updateAction(Request $request): string
    {
        if ($request->has('verification_status')) {
            return 'verified_certification';
        }
        if ($request->input('status') === 'revoked') {
            return 'revoked_certification';
        }
        if ($request->has('status')) {
            return 'updated_certification_status';
        }

        return 'updated_certification';
    }

    private function updateDescription(Request $request, ?string $name): string
    {
        $name = $name ?: 'certification';

        if ($request->has('verification_status')) {
            return ucfirst((string) $request->input('verification_status')) . ' certification "' . $name . '"';
        }
        if ($request->input('status') === 'revoked') {
            return 'Revoked certification "' . $name . '"';
        }
        if ($request->has('status')) {
            return 'Set certification "' . $name . '" to ' . (self::STATUS_LABELS[$request->input('status')] ?? $request->input('status'));
        }

        return 'Updated certification "' . $name . '"';
    }

    private function fileUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        try {
            return Storage::disk('digitalocean')->url($path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function firstNumeric($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        foreach (array_map('trim', explode(',', (string) $value)) as $part) {
            if (is_numeric($part)) {
                return (int) $part;
            }
        }

        return null;
    }

    private function humanDate($value): ?string
    {
        return $value ? date('d M Y', strtotime((string) $value)) : null;
    }
}
