<?php

namespace App\Http\Controllers\api\OrganizationManagement\Compliance;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationManagement\StoreComplianceLibraryRequest;
use App\Http\Requests\OrganizationManagement\UpdateComplianceLibraryRequest;
use App\Models\AuditLog;
use App\Models\OrganizationManagement\ComplianceLibraryRecord;
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
 */
class ComplianceLibraryController extends Controller
{
    /** GET /organization-management/compliance-library */
    public function index(Request $request)
    {
        $tenant = (int) session()->get('sub_institute_id');

        $search = trim((string) $request->input('search', ''));
        $perPage = (int) ($request->input('per_page') ?: 25);
        $perPage = min(200, max(5, $perPage));

        $query = ComplianceLibraryRecord::with(['assignedUser'])
            ->where('sub_institute_id', $tenant);

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
            ->distinct()
            ->orderBy('department')
            ->get(['department'])
            ->map(fn ($row) => ['value' => $row->department, 'label' => $row->department])
            ->values();
        $payload['employees'] = DB::table('tbluser')
            ->where('sub_institute_id', $tenant)
            ->where('status', 1)
            ->orderBy('first_name')
            ->selectRaw('id, TRIM(CONCAT_WS(" ", COALESCE(first_name,""), COALESCE(last_name,""))) as full_name')
            ->get()
            ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->full_name ?: "Employee #{$row->id}"])
            ->values();

        return response()->json($payload);
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

        $data['sub_institute_id'] = $tenant;
        $data['created_by'] = $actorId;
        $data['updated_by'] = $actorId;

        $record = ComplianceLibraryRecord::create($data);

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_library_created',
            'entity_type' => 'org_compliance_library',
            'entity_id' => $record->id,
            'new_values' => $data,
        ]);

        return $this->response($this->present($record), 'Details added successfully', 201);
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

        $data['updated_by'] = $actorId;
        $record->fill($data);
        $record->save();

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_library_updated',
            'entity_type' => 'org_compliance_library',
            'entity_id' => $record->id,
            'new_values' => $data,
        ]);

        return $this->response($this->present($record->fresh(['assignedUser'])), 'Updated successfully');
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
            'department'        => $record->department,
            'assigned_to'       => $record->assigned_to ? (int) $record->assigned_to : null,
            'assigned_user'     => optional($record->assignedUser)->full_name,
            'due_date'          => optional($record->duedate)->toDateString(),
            'attachment_name'   => $record->attachment,
            'attachment_url'    => $record->attachment ? Storage::disk('public')->url('compliance_library/' . $record->attachment) : null,
            'frequency'         => $record->frequency,
            'custom_date'       => $record->custom_frequency_details,
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
