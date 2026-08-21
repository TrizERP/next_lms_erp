<?php

namespace App\Http\Controllers\api\OrganizationManagement\Disciplinary;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationManagement\StoreDisciplinaryRecordRequest;
use App\Http\Requests\OrganizationManagement\UpdateDisciplinaryRecordRequest;
use App\Models\OrganizationManagement\DisciplinaryRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\settings\discliplinaryManagementController`
 * (index/store/edit/update/destroy - `edit`/`show` are intentionally not ported,
 * matching the source, which never defined a `create()`/`show()` either).
 *
 * G2G authenticated each call via an ad-hoc Sanctum `PersonalAccessToken::findToken()`
 * check and read `sub_institute_id` from the request. Here tenant/actor identity
 * comes from the session hydrated by the `api.session` middleware instead (see
 * App\Http\Middleware\ApiSessionHydrator).
 *
 * BUG FIX vs. source: G2G's `store()` set `created_by = reported_by` (the person
 * who witnessed/reported the incident, not necessarily the person submitting the
 * form). Here `created_by`/`updated_by` are taken from the hydrated session actor,
 * and `reported_by` stays a separate explicit form field, as in G2G.
 */
class DisciplinaryLibraryController extends Controller
{
    /** GET /organization-management/disciplinary-library */
    public function index(Request $request)
    {
        $tenant = (int) session()->get('sub_institute_id');

        $search = trim((string) $request->input('search', ''));
        $department = $request->input('department_id');
        $misconductType = $request->input('misconduct_type');
        $actionTaken = $request->input('action_taken');
        $perPage = (int) ($request->input('per_page') ?: 25);
        $perPage = min(200, max(5, $perPage));

        $query = DisciplinaryRecord::with(['departmentData', 'employeeData', 'witnessData', 'reportByData'])
            ->where('sub_institute_id', $tenant);

        if ($department) {
            $query->where('department_id', $department);
        }
        if ($misconductType) {
            $query->where('misconduct_type', $misconductType);
        }
        if ($actionTaken) {
            $query->where('action_taken', $actionTaken);
        }
        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('location', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('employeeData', function ($q) use ($search) {
                        $q->where(DB::raw('CONCAT_WS(" ", COALESCE(first_name,""), COALESCE(last_name,""))'), 'like', "%{$search}%");
                    });
            });
        }

        $records = $query->orderByDesc('id')->paginate($perPage);

        $records->getCollection()->transform(function (DisciplinaryRecord $record) {
            return $this->present($record);
        });

        $response = $this->response($records, 'Success');
        $payload = $response->getData(true);
        // Fix (2026-08-20, mirrors the same fix in ComplianceLibraryController):
        // `$records` is a LengthAwarePaginator, which serializes as
        // {current_page, data: [...], total, ...} when placed under the 'data'
        // key - so `payload['data']` was the whole paginator object, not the
        // plain record array the frontend expects. Flatten to just the items,
        // with pagination metadata alongside at the top level. Also add
        // `departments`/`employees` options for the create/update form's
        // dropdowns - the frontend (`use-disciplinary-library.ts`) already
        // reads `response.departments`/`response.employees` from this same
        // index() call, but they were never sent.
        $payload['data'] = $records->items();
        $payload['pagination'] = [
            'current_page' => $records->currentPage(),
            'per_page' => $records->perPage(),
            'total' => $records->total(),
            'last_page' => $records->lastPage(),
        ];
        $payload['departments'] = DB::table('hrms_departments')
            ->where('sub_institute_id', $tenant)
            ->where('status', 1)
            ->orderBy('department')
            ->get(['id', 'department'])
            ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->department])
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

    /** POST /organization-management/disciplinary-library */
    public function store(StoreDisciplinaryRecordRequest $request)
    {
        $tenant = (int) session()->get('sub_institute_id');
        $actorId = session()->get('user_id') !== null ? (int) session()->get('user_id') : null;

        $data = $request->validated();
        $data['sub_institute_id'] = $tenant;
        $data['created_by'] = $actorId;
        $data['updated_by'] = $actorId;

        $record = DisciplinaryRecord::create($data);

        return $this->response(
            $this->present($record->fresh(['departmentData', 'employeeData', 'witnessData', 'reportByData'])),
            'Data added successfully',
            201
        );
    }

    /** PUT/PATCH /organization-management/disciplinary-library/{id} */
    public function update(UpdateDisciplinaryRecordRequest $request, $id)
    {
        $tenant = (int) session()->get('sub_institute_id');
        $actorId = session()->get('user_id') !== null ? (int) session()->get('user_id') : null;

        $record = DisciplinaryRecord::where('sub_institute_id', $tenant)->find($id);
        if (!$record) {
            return $this->error('Record not found', 404);
        }

        $data = $request->validated();
        $data['updated_by'] = $actorId;

        $record->fill($data);
        $record->save();

        return $this->response(
            $this->present($record->fresh(['departmentData', 'employeeData', 'witnessData', 'reportByData'])),
            'Data updated successfully'
        );
    }

    /** DELETE /organization-management/disciplinary-library/{id} */
    public function destroy(Request $request, $id)
    {
        $tenant = (int) session()->get('sub_institute_id');
        $actorId = session()->get('user_id') !== null ? (int) session()->get('user_id') : null;

        $record = DisciplinaryRecord::where('sub_institute_id', $tenant)->find($id);
        if (!$record) {
            return $this->error('Record not found', 404);
        }

        $record->deleted_by = $actorId;
        $record->save();
        $record->delete();

        return $this->response(['id' => (int) $id], 'Data deleted successfully');
    }

    /**
     * GET /organization-management/disciplinary-library/departments/{department}/employees
     *
     * Ported from G2G's frontend cascading lookup, which called the generic
     * `GET /table_data?table=tbluser&filters[department_id]=...` endpoint. That
     * generic endpoint doesn't exist in LMS-K12's stateless API, so a small
     * dedicated endpoint is added, scoped to sub_institute_id + status=1.
     */
    public function departmentEmployees(Request $request, $department)
    {
        $tenant = (int) session()->get('sub_institute_id');

        // Fix (2026-08-20): `tbluser` has no `deleted_at` column in LMS-K12
        // (only `status`, see EmployeeDirectoryController's class docblock) -
        // `whereNull('deleted_at')` made every call to this endpoint a
        // guaranteed 500. Removed; `status = 1` already excludes inactive
        // employees.
        //
        // Fix (2026-08-20, follow-up): the response also never matched what
        // the frontend expects - `DisciplinaryOption { value, label }` (same
        // shape as index()'s `employees` list) - it returned raw
        // `{id, name, employee_no}` instead, so even once the crash above was
        // fixed the Employee dropdown in the Incident Registration form still
        // rendered nothing selectable. Mapped to the correct shape below.
        $employees = DB::table('tbluser')
            ->where('sub_institute_id', $tenant)
            ->where('department_id', $department)
            ->where('status', 1)
            ->orderBy('first_name')
            ->selectRaw('id, TRIM(CONCAT_WS(" ", COALESCE(first_name,""), COALESCE(last_name,""))) as full_name, employee_no')
            ->get()
            ->map(fn ($row) => [
                'value' => (string) $row->id,
                'label' => trim(($row->full_name ?: "Employee #{$row->id}") . ($row->employee_no ? " ({$row->employee_no})" : '')),
            ])
            ->values();

        return $this->response($employees, 'Success');
    }

    private function present(DisciplinaryRecord $record): array
    {
        return [
            'id'                 => (int) $record->id,
            'department_id'      => $record->department_id ? (int) $record->department_id : null,
            'department_name'    => optional($record->departmentData)->department,
            'employee_id'        => $record->employee_id ? (int) $record->employee_id : null,
            'employee_name'      => optional($record->employeeData)->full_name,
            'incident_datetime'  => optional($record->incident_datetime)->toDateTimeString(),
            'location'           => $record->location,
            'misconduct_type'    => $record->misconduct_type,
            'description'        => $record->description,
            'witness_id'         => $record->witness_id ? (int) $record->witness_id : null,
            'witness_name'       => optional($record->witnessData)->full_name,
            'action_taken'       => $record->action_taken,
            'remarks'            => $record->remarks,
            'reported_by'        => $record->reported_by ? (int) $record->reported_by : null,
            'reported_by_name'   => optional($record->reportByData)->full_name,
            'date_of_report'     => optional($record->date_of_report)->toDateString(),
            'created_at'         => optional($record->created_at)->toDateTimeString(),
            'updated_at'         => optional($record->updated_at)->toDateTimeString(),
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
