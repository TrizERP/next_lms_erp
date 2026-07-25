<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\consent\consent_masterModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Stateless JSON API for the Consent module, used by the Next.js frontend.
 *
 * Separate from the three controllers in `App\Http\Controllers\consent`, which
 * are unchanged and keep serving the Blade screens. It exists because those
 * controllers cannot serve an API caller:
 *
 *  - `consent_masterController::create()` calls
 *    `SearchStudent($grade, $standard, $division)` without an institute or
 *    academic year, so the helper falls back to `session()->get(...)`. For a
 *    stateless request those are empty and the helper emits
 *    `where ts.sub_institute_id = and ...`, a SQL syntax error (1064).
 *  - `consent_masterController::store()` and both list controllers read
 *    `sub_institute_id` / `syear` / `user_id` from the session only, so they
 *    would write or filter against the wrong tenant.
 *  - `consent/*` is not in `VerifyCsrfToken::$except`, so its POSTs answer 419.
 *
 * The queries below are ported verbatim from the Blade controllers, with the
 * session lookups replaced by validated request parameters.
 */
class ConsentApiController extends Controller
{
    use GetsJwtToken;

    private function authenticate()
    {
        try {
            if (! $this->jwtToken()->validate()) {
                return response()->json([
                    'status_code' => 2,
                    'message'     => 'Token Auth Failed',
                    'data'        => [],
                ], 401);
            }
        } catch (\Exception $exception) {
            return response()->json([
                'status_code' => 2,
                'message'     => $exception->getMessage(),
                'data'        => [],
            ], 401);
        }

        return null;
    }

    private function validateContext(Request $request)
    {
        return Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear'            => 'required|integer',
        ]);
    }

    private function failed($message, $status = 422)
    {
        return response()->json([
            'status_code' => 0,
            'message'     => $message,
            'data'        => [],
        ], $status);
    }

    /**
     * GET api/consents/students
     *
     * The student picker for Consent master. This is the stateless equivalent
     * of `SearchStudent()` as that screen calls it: active enrolments of the
     * institute and year, optionally narrowed by grade / standard / division.
     */
    public function students(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = $this->validateContext($request);
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $subInstituteId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');

        $students = DB::table('tblstudent as ts')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->on('se.student_id', '=', 'ts.id')
                    ->on('se.sub_institute_id', '=', 'ts.sub_institute_id');
            })
            ->join('academic_section as acs', function ($join) {
                $join->on('acs.id', '=', 'se.grade_id')
                    ->on('acs.sub_institute_id', '=', 'se.sub_institute_id');
            })
            ->join('standard as s', function ($join) {
                $join->on('s.id', '=', 'se.standard_id')
                    ->on('s.sub_institute_id', '=', 'se.sub_institute_id');
            })
            ->join('division as d', function ($join) {
                $join->on('d.id', '=', 'se.section_id')
                    ->on('d.sub_institute_id', '=', 'se.sub_institute_id');
            })
            ->selectRaw("ts.id as student_id, ts.enrollment_no, ts.mobile, ts.first_name,
                ts.middle_name, ts.last_name, se.roll_no,
                s.name as standard_name, d.name as division_name, acs.title as grade_name")
            ->where('ts.sub_institute_id', $subInstituteId)
            ->where('se.syear', $syear)
            ->whereNull('se.end_date')
            ->when($request->input('grade'), function ($query, $grade) {
                $query->where('acs.id', $grade);
            })
            ->when($request->input('standard'), function ($query, $standard) {
                $query->where('s.id', $standard);
            })
            ->when($request->input('division'), function ($query, $division) {
                $query->where('d.id', $division);
            })
            ->orderBy('s.sort_order')
            ->orderBy('d.id')
            ->orderBy('se.roll_no')
            ->get();

        return response()->json([
            'status_code' => 1,
            'message'     => 'Success',
            'data'        => ['students' => $students],
        ]);
    }

    /**
     * GET api/consents
     *
     * Issued consents. Same query and column aliases as
     * `delete_consent_masterController::create()` and
     * `report_consent_masterController::create()`, which are identical to each
     * other; the delete screen simply also renders a checkbox column.
     */
    public function index(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = $this->validateContext($request);
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $subInstituteId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $consents = DB::table('consent_master as CM')
            ->join('tblstudent as s', function ($join) {
                $join->on('s.id', '=', 'CM.student_id')
                    ->on('s.sub_institute_id', '=', 'CM.sub_institute_id');
            })
            ->join('tblstudent_enrollment as SE', function ($join) use ($syear) {
                $join->on('SE.student_id', '=', 's.id')->where('SE.syear', '=', $syear);
            })
            ->join('standard as CS', 'CS.id', '=', 'SE.standard_id')
            ->join('academic_section as SG', function ($join) use ($subInstituteId) {
                $join->on('SG.id', '=', 'CS.grade_id')
                    ->where('SG.sub_institute_id', '=', $subInstituteId);
            })
            ->join('division as SS', 'SS.id', '=', 'SE.section_id')
            ->leftJoin('tbluser as ta', function ($join) {
                $join->on('ta.id', '=', 'CM.created_by')->where('ta.status', 1);
            })
            ->selectRaw("CM.ID AS CHECKBOX, CM.*, s.enrollment_no,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS FULL_NAME,
                s.mobile AS SMS_NO, SG.title AS GRADE_ID,
                CONCAT_WS('/', CS.name, SS.name) AS STANDARD,
                CONCAT_WS(' ', ta.first_name, ta.last_name) AS created_by,
                DATE_FORMAT(CM.date, '%d-%m-%Y') AS consent_date,
                IF(CM.accountable_status = 'Accountable', 'Account', 'Not Account') AS account_status")
            ->where('CM.syear', $syear)
            ->where('CM.sub_institute_id', $subInstituteId)
            ->when($standard, function ($query, $standard) {
                $query->where('CM.standard_id', $standard);
            })
            ->when($division, function ($query, $division) {
                $query->where('CM.division_id', $division);
            })
            // The Blade controllers apply the range only when both bounds are set.
            ->when($fromDate && $toDate, function ($query) use ($fromDate, $toDate) {
                $query->whereBetween(DB::raw("DATE_FORMAT(CM.date, '%Y-%m-%d')"), [$fromDate, $toDate]);
            })
            ->orderByDesc('CM.ID')
            ->get();

        return response()->json([
            'status_code' => 1,
            'message'     => 'Success',
            'data'        => ['consents' => $consents],
        ]);
    }

    /**
     * POST api/consents
     *
     * Reproduces `consent_masterController::store()`: one row per selected
     * student, all sharing the title, date and accountable status.
     */
    public function store(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id'   => 'required|integer',
            'syear'              => 'required|integer',
            'user_id'            => 'required|integer',
            'title'              => 'required|string|max:50',
            'date'               => 'required|date',
            'accountable_status' => 'required|string|max:50',
            'standard_id'        => 'nullable|integer',
            'division_id'        => 'nullable|integer',
            'students'           => 'required|array|min:1',
            'students.*'         => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $now = now();
        $rows = [];
        foreach ($request->input('students') as $studentId) {
            $rows[] = [
                'student_id'         => (int) $studentId,
                'sub_institute_id'   => $request->integer('sub_institute_id'),
                'syear'              => $request->integer('syear'),
                'title'              => $request->input('title'),
                'standard_id'        => $request->input('standard_id') ?: null,
                'division_id'        => $request->input('division_id') ?: null,
                'date'               => $request->input('date'),
                'accountable_status' => $request->input('accountable_status'),
                'created_by'         => $request->integer('user_id'),
                'created_on'         => $now,
                'created_ip'         => $request->ip(),
            ];
        }

        consent_masterModel::insert($rows);

        return response()->json([
            'status_code' => 1,
            'message'     => 'Consent added successfully.',
            'data'        => ['inserted' => count($rows)],
        ]);
    }

    /**
     * POST api/consents/delete
     *
     * `students[]` carries consent_master row ids, matching the Blade form.
     * Scoped to the caller's institute and year so one tenant cannot delete
     * another's rows — the Blade `store()` deletes by id alone.
     */
    public function destroy(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear'            => 'required|integer',
            'students'         => 'required|array|min:1',
            'students.*'       => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $deleted = DB::table('consent_master')
            ->whereIn('ID', $request->input('students'))
            ->where('sub_institute_id', $request->integer('sub_institute_id'))
            ->where('syear', $request->integer('syear'))
            ->delete();

        if (! $deleted) {
            return $this->failed('No matching consent was found to delete.', 404);
        }

        return response()->json([
            'status_code' => 1,
            'message'     => 'Consent deleted successfully.',
            'data'        => ['deleted' => $deleted],
        ]);
    }
}
