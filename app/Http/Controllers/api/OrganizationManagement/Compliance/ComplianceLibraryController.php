<?php

namespace App\Http\Controllers\api\OrganizationManagement\Compliance;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationManagement\StoreComplianceLibraryRequest;
use App\Http\Requests\OrganizationManagement\UpdateComplianceLibraryRequest;
use App\Models\AuditLog;
use App\Models\OrganizationManagement\ComplianceCategory;
use App\Models\OrganizationManagement\ComplianceEvidence;
use App\Models\OrganizationManagement\ComplianceLibraryRecord;
use App\Models\OrganizationManagement\ComplianceTemplate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Ported from G2G's `App\Http\Controllers\settings\instituteDetailController`
 * `formName == 'complaince_library'` branch (index/store/update/destroy). That
 * controller mixed several unrelated form-name-dispatched features together and
 * used raw `DB::table('master_compliance')` queries with no Eloquent model; this
 * is a standalone controller against the new `org_compliance_library` table
 * (NOT the pre-existing, unrelated `master_compliance` SQAA table).
 *
 * Tenant/actor identity is read from the session hydrated by the `api.session`
 * middleware (see App\Http\Middleware\ApiSessionHydrator), not from G2G's
 * request-supplied `sub_institute_id`/`user_id`/ad-hoc Sanctum token checks.
 *
 * Storage: G2G wrote to a DigitalOcean Spaces disk (`digitalocean`). LMS-K12's
 * established convention for this generation of ported modules (see
 * OnboardingDocumentController::storeUpload) is the local `public` disk, so
 * attachments are written there instead - same convention as every other
 * recently-ported upload, not a new storage config.
 *
 * Compliance Management, frontend-completion pass (2026-09-28): the DB
 * foundation (category/department_id/status/priority/recurrence columns,
 * compliance_evidence, compliance_categories, compliance_templates) landed in
 * an earlier pass with no controller wiring. This pass wires index/store/
 * update to the new fields and adds show/complete/dashboard/calendar/my/
 * overdue - see each method's own docblock. index/store/update/destroy's
 * EXISTING request/response shape for already-shipped fields is unchanged;
 * new fields are additive.
 */
class ComplianceLibraryController extends Controller
{
    private function tenant(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    private function actorId(): ?int
    {
        $userId = session()->get('user_id');

        return $userId !== null ? (int) $userId : null;
    }

    /** GET /organization-management/compliance-library */
    public function index(Request $request)
    {
        $tenant = $this->tenant();

        $search = trim((string) $request->input('search', ''));
        $perPage = (int) ($request->input('per_page') ?: 25);
        $perPage = min(200, max(5, $perPage));

        $query = ComplianceLibraryRecord::with(['assignedUser', 'category', 'departmentData'])
            ->withCount('evidence')
            ->where('sub_institute_id', $tenant);

        $this->applyFilters($query, $request);

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('standard_name', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('frequency', 'like', "%{$search}%");
            });
        }

        $records = $query->orderByDesc('id')->paginate($perPage);

        $records->getCollection()->transform(function (ComplianceLibraryRecord $record) {
            return $this->present($record);
        });

        $response = $this->response($records, 'Success');
        $payload = $response->getData(true);
        // Fix (2026-08-20): `$records` is a LengthAwarePaginator, which
        // serializes as {current_page, data: [...], total, ...} when placed
        // under the 'data' key - so `payload['data']` was the whole paginator
        // object, not the plain record array the frontend's
        // `ComplianceListResponse.data: ComplianceApiRecord[]` type expects
        // (`records` state would end up holding a non-array object). Flatten
        // to just the items, with pagination metadata alongside at the top
        // level (same shape as EmployeeDirectoryController::index()).
        $payload['data'] = $records->items();
        $payload['pagination'] = [
            'current_page' => $records->currentPage(),
            'per_page' => $records->perPage(),
            'total' => $records->total(),
            'last_page' => $records->lastPage(),
        ];
        // Options for the create/update form's Department and Assigned Employee
        // dropdowns (fix, 2026-08-20: the frontend already expected these keys
        // - ComplianceForm/useComplianceLibrary both reference `departments`/
        // `employees` - but index() never sent them, so both fields silently
        // fell back to hardcoded mock data client-side). `department` on this
        // table is a free-text column (not an FK), so its option value is the
        // department name itself, matching what gets saved; `assigned_to` is a
        // real `tbluser.id` FK, so its option value is the numeric id.
        $payload['departments'] = DB::table('hrms_departments')
            ->where('sub_institute_id', $tenant)
            ->where('status', 1)
            ->whereNotNull('department')
            ->select('id', 'department')
            ->distinct()
            ->orderBy('department')
            ->get()
            ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->department, 'name' => $row->department])
            ->values();
        $payload['employees'] = DB::table('tbluser')
            ->where('sub_institute_id', $tenant)
            ->where('status', 1)
            ->orderBy('first_name')
            ->selectRaw('id, department_id, TRIM(CONCAT_WS(" ", COALESCE(first_name,""), COALESCE(last_name,""))) as full_name')
            ->get()
            ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->full_name ?: "Employee #{$row->id}", 'department_id' => $row->department_id ? (int) $row->department_id : null])
            ->values();
        $payload['categories'] = ComplianceCategory::availableFor($tenant)->active()
            ->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (ComplianceCategory $category) => ['value' => (string) $category->id, 'label' => $category->name])
            ->values();
        $payload['templates'] = ComplianceTemplate::with('category')->availableFor($tenant)->active()
            ->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (ComplianceTemplate $template) => [
                'id' => (int) $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'category_id' => $template->category_id ? (int) $template->category_id : null,
                'default_frequency' => $template->default_frequency,
                'default_custom_frequency_details' => $template->default_custom_frequency_details,
                'default_priority' => $template->default_priority,
            ])
            ->values();

        return response()->json($payload);
    }

    /** GET /organization-management/compliance-library/dashboard */
    public function dashboard(Request $request)
    {
        $tenant = $this->tenant();

        // Minimal columns for a per-row live status derivation - school-scale
        // data (hundreds to low thousands of records), so pulling this much
        // into PHP is far simpler and less error-prone than re-implementing
        // ComplianceLibraryRecord::deriveStatus()'s rules in raw SQL, and
        // still cheap. Re-evaluate if a tenant's row count grows enough to
        // matter (see the audit's §32 note on this).
        $rows = ComplianceLibraryRecord::where('sub_institute_id', $tenant)
            ->get(['id', 'duedate', 'status', 'priority', 'department', 'department_id', 'category_id']);

        $now = Carbon::today();
        $counters = [
            'total' => $rows->count(),
            'upcoming' => 0,
            'due_this_month' => 0,
            'due_soon' => 0,
            'overdue' => 0,
            'completed' => 0,
            'critical' => 0,
            'pending_verification' => 0,
        ];

        foreach ($rows as $row) {
            $derived = $row->deriveStatus();

            if ($derived === ComplianceLibraryRecord::STATUS_UPCOMING) $counters['upcoming']++;
            if ($derived === ComplianceLibraryRecord::STATUS_DUE_SOON) $counters['due_soon']++;
            if ($derived === ComplianceLibraryRecord::STATUS_OVERDUE) $counters['overdue']++;
            if ($derived === ComplianceLibraryRecord::STATUS_COMPLETED) $counters['completed']++;
            if ($derived === ComplianceLibraryRecord::STATUS_PENDING_VERIFICATION) $counters['pending_verification']++;
            if ($row->priority === ComplianceLibraryRecord::PRIORITY_CRITICAL && $derived !== ComplianceLibraryRecord::STATUS_COMPLETED) $counters['critical']++;
            if ($row->duedate && $row->duedate->isSameMonth($now) && $derived !== ComplianceLibraryRecord::STATUS_COMPLETED) $counters['due_this_month']++;
        }

        $pendingEvidence = ComplianceEvidence::where('sub_institute_id', $tenant)
            ->where('verification_status', ComplianceEvidence::STATUS_PENDING)
            ->count();

        $statusDistribution = $rows->groupBy(fn (ComplianceLibraryRecord $row) => $row->deriveStatus())
            ->map->count();

        $departmentDistribution = ComplianceLibraryRecord::where('org_compliance_library.sub_institute_id', $tenant)
            ->join('hrms_departments', 'hrms_departments.id', '=', 'org_compliance_library.department_id')
            ->select('hrms_departments.department as label', DB::raw('count(*) as value'))
            ->groupBy('hrms_departments.department')
            ->orderByDesc('value')
            ->get();

        $categoryDistribution = ComplianceLibraryRecord::where('org_compliance_library.sub_institute_id', $tenant)
            ->join('compliance_categories', 'compliance_categories.id', '=', 'org_compliance_library.category_id')
            ->select('compliance_categories.name as label', DB::raw('count(*) as value'))
            ->groupBy('compliance_categories.name')
            ->orderByDesc('value')
            ->get();

        return $this->response([
            'kpis' => $counters + ['pending_evidence_verification' => $pendingEvidence],
            'status_distribution' => $statusDistribution,
            'department_distribution' => $departmentDistribution,
            'category_distribution' => $categoryDistribution,
        ]);
    }

    /** GET /organization-management/compliance-library/calendar?year=&month= */
    public function calendar(Request $request)
    {
        $tenant = $this->tenant();

        $year = (int) ($request->input('year') ?: now()->year);
        $month = (int) ($request->input('month') ?: now()->month);
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $records = ComplianceLibraryRecord::with(['assignedUser', 'category'])
            ->where('sub_institute_id', $tenant)
            ->whereBetween('duedate', [$start->toDateString(), $end->toDateString()])
            ->orderBy('duedate')
            ->get();

        return $this->response($records->map(fn (ComplianceLibraryRecord $record) => [
            'id' => (int) $record->id,
            'name' => $record->name,
            'due_date' => optional($record->duedate)->toDateString(),
            'status' => $record->deriveStatus(),
            'priority' => $record->priority,
            'category_name' => optional($record->category)->name,
            'assigned_user' => optional($record->assignedUser)->full_name,
        ])->values());
    }

    /** GET /organization-management/compliance-library/my */
    public function my(Request $request)
    {
        $tenant = $this->tenant();
        $actorId = $this->actorId();

        $query = ComplianceLibraryRecord::with(['category', 'departmentData'])
            ->withCount('evidence')
            ->where('sub_institute_id', $tenant)
            ->where('assigned_to', $actorId);

        $this->applyFilters($query, $request);

        $records = $query->orderByDesc('duedate')->get();
        $presented = $records->map(fn (ComplianceLibraryRecord $record) => $this->present($record))->values();

        $summary = ['total' => $records->count(), 'upcoming' => 0, 'due_soon' => 0, 'overdue' => 0, 'completed' => 0];
        foreach ($records as $record) {
            $derived = $record->deriveStatus();
            if ($derived === ComplianceLibraryRecord::STATUS_UPCOMING) $summary['upcoming']++;
            if ($derived === ComplianceLibraryRecord::STATUS_DUE_SOON) $summary['due_soon']++;
            if ($derived === ComplianceLibraryRecord::STATUS_OVERDUE) $summary['overdue']++;
            if ($derived === ComplianceLibraryRecord::STATUS_COMPLETED) $summary['completed']++;
        }

        return $this->response(['records' => $presented, 'summary' => $summary]);
    }

    /** GET /organization-management/compliance-library/overdue */
    public function overdue(Request $request)
    {
        $tenant = $this->tenant();

        $query = ComplianceLibraryRecord::with(['assignedUser', 'category', 'departmentData'])
            ->where('sub_institute_id', $tenant)
            ->whereNotNull('duedate')
            ->where('duedate', '<', now()->toDateString())
            ->whereNotIn('status', [ComplianceLibraryRecord::STATUS_COMPLETED, ComplianceLibraryRecord::STATUS_NOT_APPLICABLE]);

        $this->applyFilters($query, $request);

        $records = $query->orderBy('duedate')->get();

        return $this->response($records->map(function (ComplianceLibraryRecord $record) {
            $data = $this->present($record);
            $data['days_overdue'] = $record->duedate ? (int) $record->duedate->diffInDays(now(), true) : null;

            return $data;
        })->values());
    }

    /** GET /organization-management/compliance-library/{id} */
    public function show(Request $request, $id)
    {
        $tenant = $this->tenant();

        $record = ComplianceLibraryRecord::with(['assignedUser', 'category', 'departmentData', 'parentCycle', 'childCycles'])
            ->where('sub_institute_id', $tenant)
            ->find($id);
        if (!$record) {
            return $this->error('Record not found', 404);
        }

        $evidence = ComplianceEvidence::with(['uploadedByUser', 'verifiedByUser'])
            ->where('compliance_id', $record->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ComplianceEvidence $item) => [
                'id' => (int) $item->id,
                'file_name' => $item->file_name,
                'file_url' => Storage::disk('public')->url($item->file_path),
                'document_type' => $item->document_type,
                'description' => $item->description,
                'expiry_date' => optional($item->expiry_date)->toDateString(),
                'verification_status' => $item->verification_status,
                'rejection_reason' => $item->rejection_reason,
                'uploaded_by_name' => optional($item->uploadedByUser)->full_name,
                'verified_by_name' => optional($item->verifiedByUser)->full_name,
                'verified_at' => optional($item->verified_at)->toDateTimeString(),
                'created_at' => optional($item->created_at)->toDateTimeString(),
            ])->values();

        // Activity trail: AuditLog::record() has fired for this entity since
        // the earlier foundation pass (create/update/delete) and this pass's
        // new actions (evidence upload/verify/reject, complete). Filtered by
        // sub_institute_id too, defense-in-depth against entity_id collisions
        // across tenants (entity_id alone is not globally unique).
        $activity = DB::table('system_audit_logs')
            ->where('entity_type', 'org_compliance_library')
            ->where('entity_id', (string) $record->id)
            ->where('sub_institute_id', $tenant)
            ->orderBy('created_at')
            ->get(['action', 'actor_name', 'created_at', 'new_values'])
            ->map(fn ($row) => [
                'action' => $row->action,
                'actor_name' => $row->actor_name,
                'created_at' => $row->created_at,
                'details' => $row->new_values,
            ])->values();

        $cycles = collect([$record])
            ->concat($record->childCycles)
            ->when($record->parentCycle, fn ($collection) => $collection->push($record->parentCycle))
            ->unique('id')
            ->sortBy('duedate')
            ->map(fn (ComplianceLibraryRecord $cycle) => [
                'id' => (int) $cycle->id,
                'due_date' => optional($cycle->duedate)->toDateString(),
                'status' => $cycle->deriveStatus(),
                'is_current' => $cycle->id === $record->id,
            ])->values();

        return $this->response([
            'record' => $this->present($record),
            'evidence' => $evidence,
            'activity' => $activity,
            'cycles' => $cycles,
        ]);
    }

    /** POST /organization-management/compliance-library */
    public function store(StoreComplianceLibraryRequest $request)
    {
        $tenant = (int) session()->get('sub_institute_id');
        $actorId = session()->get('user_id') !== null ? (int) session()->get('user_id') : null;

        $data = $request->validated();
        $data['duedate'] = date('Y-m-d', strtotime($data['duedate']));

        $data['attachment'] = null;
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $this->storeAttachment($request, $tenant);
        }

        $this->syncDepartmentFields($data, $tenant);

        $data['sub_institute_id'] = $tenant;
        $data['created_by'] = $actorId;
        $data['updated_by'] = $actorId;

        $record = ComplianceLibraryRecord::create($data);
        $record->status = $record->deriveStatus();
        $record->save();

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_library_created',
            'entity_type' => 'org_compliance_library',
            'entity_id' => $record->id,
            'new_values' => $data,
        ]);

        return $this->response($this->present($record->fresh(['assignedUser', 'category', 'departmentData'])), 'Details added successfully', 201);
    }

    /** PUT/PATCH /organization-management/compliance-library/{id} */
    public function update(UpdateComplianceLibraryRequest $request, $id)
    {
        $tenant = (int) session()->get('sub_institute_id');
        $actorId = session()->get('user_id') !== null ? (int) session()->get('user_id') : null;

        $record = ComplianceLibraryRecord::where('sub_institute_id', $tenant)->find($id);
        if (!$record) {
            return $this->error('Record not found', 404);
        }

        $data = $request->validated();
        if (array_key_exists('duedate', $data) && $data['duedate']) {
            $data['duedate'] = date('Y-m-d', strtotime($data['duedate']));
        }

        // Same as G2G: attachment is preserved unless a new file is uploaded,
        // and the old file is passed back explicitly by the client as
        // `oldAttachment` (rather than re-deriving it from the current row).
        $oldAttachment = $request->input('oldAttachment');
        unset($data['oldAttachment']);
        $data['attachment'] = $oldAttachment ?: $record->attachment;

        if ($request->hasFile('attachment')) {
            if ($oldAttachment && Storage::disk('public')->exists('compliance_library/' . $oldAttachment)) {
                Storage::disk('public')->delete('compliance_library/' . $oldAttachment);
            }

            $data['attachment'] = $this->storeAttachment($request, $tenant);
        }

        $this->syncDepartmentFields($data, $tenant);

        $data['updated_by'] = $actorId;
        $record->fill($data);

        // A manual status in the payload wins (e.g. an explicit "Not
        // Applicable"); otherwise re-derive from the (possibly just-changed)
        // due date so editing the date keeps status honest.
        if (!array_key_exists('status', $data) || !$data['status']) {
            $record->status = $record->deriveStatus();
        }

        $record->save();

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_library_updated',
            'entity_type' => 'org_compliance_library',
            'entity_id' => $record->id,
            'new_values' => $data,
        ]);

        return $this->response($this->present($record->fresh(['assignedUser', 'category', 'departmentData'])), 'Updated successfully');
    }

    /**
     * POST /organization-management/compliance-library/{id}/complete
     *
     * Marks this cycle Completed and, for a recurring frequency (anything but
     * One-Time/unset), generates the next cycle as a new row linked via
     * `parent_compliance_id` - see ComplianceLibraryRecord::calculateNextDueDate()
     * for why the next due date is anchored to THIS cycle's own due date
     * rather than today/the completion date. Evidence is not required to
     * complete (no business rule in the brief mandates it); the frontend may
     * still prompt for it before calling this.
     */
    public function complete(Request $request, $id)
    {
        $tenant = $this->tenant();
        $actorId = $this->actorId();

        $record = ComplianceLibraryRecord::where('sub_institute_id', $tenant)->find($id);
        if (!$record) {
            return $this->error('Record not found', 404);
        }

        $data = $request->validate([
            'completion_note' => 'nullable|string|max:1000',
            'completion_date' => 'nullable|date',
        ]);

        $completedAt = !empty($data['completion_date']) ? Carbon::parse($data['completion_date']) : now();

        $record->status = ComplianceLibraryRecord::STATUS_COMPLETED;
        $record->completed_at = $completedAt;
        $record->completed_by = $actorId;

        $nextDue = $record->calculateNextDueDate();
        $newCycle = null;

        if ($nextDue) {
            $record->next_due_date = $nextDue;
            $record->save();

            $newCycle = ComplianceLibraryRecord::create([
                'name' => $record->name,
                'description' => $record->description,
                'standard_name' => $record->standard_name,
                'category_id' => $record->category_id,
                'department' => $record->department,
                'department_id' => $record->department_id,
                'assigned_to' => $record->assigned_to,
                'duedate' => $nextDue->toDateString(),
                'frequency' => $record->frequency,
                'custom_frequency_details' => $record->custom_frequency_details,
                'priority' => $record->priority,
                'status' => ComplianceLibraryRecord::STATUS_UPCOMING,
                'parent_compliance_id' => $record->id,
                'sub_institute_id' => $tenant,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $newCycle->status = $newCycle->deriveStatus();
            $newCycle->save();
        } else {
            $record->save();
        }

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_library_completed',
            'entity_type' => 'org_compliance_library',
            'entity_id' => $record->id,
            'new_values' => [
                'completion_note' => $data['completion_note'] ?? null,
                'completed_at' => $completedAt->toDateTimeString(),
                'next_cycle_id' => optional($newCycle)->id,
                'next_due_date' => optional($nextDue)->toDateString(),
            ],
        ]);

        return $this->response([
            'record' => $this->present($record->fresh(['assignedUser', 'category', 'departmentData'])),
            'next_cycle' => $newCycle ? $this->present($newCycle->fresh(['assignedUser', 'category', 'departmentData'])) : null,
        ], 'Compliance marked as completed');
    }

    /** DELETE /organization-management/compliance-library/{id} */
    public function destroy(Request $request, $id)
    {
        $tenant = (int) session()->get('sub_institute_id');
        $actorId = session()->get('user_id') !== null ? (int) session()->get('user_id') : null;

        $record = ComplianceLibraryRecord::where('sub_institute_id', $tenant)->find($id);
        if (!$record) {
            return $this->error('Record not found', 404);
        }

        $record->deleted_by = $actorId;
        $record->save();
        $record->delete();

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_library_deleted',
            'entity_type' => 'org_compliance_library',
            'entity_id' => (int) $id,
        ]);

        return $this->response(['id' => (int) $id], 'Deleted successfully');
    }

    private function applyFilters($query, Request $request): void
    {
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($departmentId = $request->input('department_id')) {
            $query->where('department_id', $departmentId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }
        if ($frequency = $request->input('frequency')) {
            $query->where('frequency', $frequency);
        }
        if ($assignedTo = $request->input('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }
        if ($from = $request->input('due_date_from')) {
            $query->whereDate('duedate', '>=', $from);
        }
        if ($to = $request->input('due_date_to')) {
            $query->whereDate('duedate', '<=', $to);
        }
    }

    /**
     * Keeps the legacy free-text `department` and the new `department_id` FK
     * in step with each other, whichever one the caller actually sent -
     * department_id wins when both are present (it's the trustworthy one).
     */
    private function syncDepartmentFields(array &$data, int $tenant): void
    {
        if (!empty($data['department_id'])) {
            $department = DB::table('hrms_departments')
                ->where('id', $data['department_id'])
                ->where('sub_institute_id', $tenant)
                ->value('department');

            if ($department) {
                $data['department'] = $department;
            } else {
                // A department_id that doesn't belong to this tenant is not a
                // usable FK value - drop it rather than let a cross-tenant id
                // slip into the FK column.
                unset($data['department_id']);
            }
        }
    }

    private function storeAttachment(Request $request, int $tenant): string
    {
        $file = $request->file('attachment');
        $filename = time() . '_' . $file->getClientOriginalName();

        // 'public' is the disk every other upload in this app writes to
        // (see OnboardingDocumentController::storeUpload).
        Storage::disk('public')->putFileAs('compliance_library', $file, $filename);

        return $filename;
    }

    /**
     * Response keys match the ported frontend's `ComplianceApiRecord` type
     * (`due_date`/`custom_date`/`attachment_name`), which differ from this
     * record's own column names (`duedate`/`custom_frequency_details`/
     * `attachment`) - see `toBackendPayload()` in the frontend's
     * `compliance-library-api.ts` for the inverse mapping on write.
     */
    private function present(ComplianceLibraryRecord $record): array
    {
        return [
            'id'                => (int) $record->id,
            'name'              => $record->name,
            'description'       => $record->description,
            'standard_name'     => $record->standard_name,
            'category_id'       => $record->category_id ? (int) $record->category_id : null,
            'category_name'     => optional($record->category)->name,
            'department'        => $record->department,
            'department_id'     => $record->department_id ? (int) $record->department_id : null,
            'assigned_to'       => $record->assigned_to ? (int) $record->assigned_to : null,
            'assigned_user'     => optional($record->assignedUser)->full_name,
            'due_date'          => optional($record->duedate)->toDateString(),
            'next_due_date'     => optional($record->next_due_date)->toDateString(),
            'attachment_name'   => $record->attachment,
            'attachment_url'    => $record->attachment ? Storage::disk('public')->url('compliance_library/' . $record->attachment) : null,
            'frequency'         => $record->frequency,
            'custom_date'       => $record->custom_frequency_details,
            'priority'          => $record->priority,
            'status'            => $record->status,
            'derived_status'    => $record->deriveStatus(),
            'completed_at'      => optional($record->completed_at)->toDateTimeString(),
            'parent_compliance_id' => $record->parent_compliance_id ? (int) $record->parent_compliance_id : null,
            'evidence_count'    => $record->evidence_count ?? null,
            'created_at'        => optional($record->created_at)->toDateTimeString(),
            'updated_at'        => optional($record->updated_at)->toDateTimeString(),
        ];
    }

    private function response($data, string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'status'  => 1,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    private function error(string $message, int $code = 400)
    {
        return response()->json([
            'status'  => 0,
            'message' => $message,
        ], $code);
    }
}
