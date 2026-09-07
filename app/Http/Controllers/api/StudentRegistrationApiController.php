<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\Graph\GraphSync;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StudentRegistrationApiController extends Controller
{
    use GetsJwtToken;

    public function metadata(Request $request): JsonResponse
    {
        if ($error = $this->guard($request)) return $error;
        $tenant = $request->sub_institute_id;
        return response()->json(['status' => 1, 'message' => 'Success', 'data' => [
            'quotas' => DB::table('student_quota')->select('id', 'title as name')->where('sub_institute_id', $tenant)->orderBy('sort_order')->get(),
            'houses' => DB::table('house_master')->select('id', 'house_name as name')->where('sub_institute_id', $tenant)->where('syear', $request->syear)->orderBy('sort_order')->get(),
        ]]);
    }

    /**
     * Next auto-generated GR No: MAX(enrollment_no) + 1 across all tblstudent
     * rows for this tenant, deliberately NOT scoped to admission_year/syear.
     * GR No. is a single continuous sequence for the institute — it must not
     * restart or change format when the admission year changes, and existing
     * enrollment numbers from earlier years remain part of the same series.
     */
   public function nextEnrollmentNo(Request $request): JsonResponse
{
    if ($error = $this->guard($request)) {
        return $error;
    }

    $next = DB::table('tblstudent')
        ->where('sub_institute_id', $request->sub_institute_id)
        ->whereNotNull('enrollment_no')
        ->where('enrollment_no', '!=', '')
        ->selectRaw('MAX(CAST(enrollment_no AS UNSIGNED)) + 1 AS next_enrollment_no')
        ->value('next_enrollment_no');

    return response()->json([
        'status' => 1,
        'message' => 'Success',
        'data' => [
            'enrollment_no' => (string) ($next ?: 1001),
        ],
    ]);
}

    public function store(Request $request): JsonResponse
    {
        if ($error = $this->guard($request)) return $error;
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100', 'last_name' => 'nullable|string|max:100',
            'enrollment_no' => 'required|string|max:100', 'gender' => 'required|string|max:20', 'dob' => 'nullable|date',
            'grade' => 'required|integer', 'standard' => 'required|integer', 'division' => 'required|integer',
            'email' => 'nullable|email|max:255', 'mobile' => 'nullable|regex:/^[0-9]{10}$/',
        ], [
            'mobile.regex' => 'Enter a valid 10-digit mobile number.',
        ]);
        if ($validator->fails()) return response()->json(['status' => 0, 'message' => $validator->errors()->first(), 'data' => []], 422);
        $duplicate = DB::table('tblstudent')->where('sub_institute_id', $request->sub_institute_id)->where('enrollment_no', $request->enrollment_no)->exists();
        if ($duplicate) return response()->json(['status' => 0, 'message' => 'GR No. already exists.', 'data' => []], 422);
        // The exists() check above is only a fast-path courtesy: two concurrent
        // requests can both pass it for the same enrollment_no before either
        // inserts. The `tblstudent_sub_institute_enrollment_no_unique` DB
        // constraint is the real guard — a violation here means a race was
        // lost, not a server error, so it maps to the same 422 the client
        // already knows how to react to (refetch next-enrollment-no + retry).
        try {
            $studentId = $this->insertStudent($request);
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                return response()->json(['status' => 0, 'message' => 'GR No. already exists.', 'data' => []], 422);
            }
            throw $e;
        }

        // Transactional outbox: the AFTER INSERT triggers on tblstudent and
        // tblstudent_enrollment queued the graph events into `sync_log` inside
        // the transaction above, so they committed atomically with the student
        // and a crash after COMMIT cannot lose them. This call only pushes them
        // to Neo4j now, so the student is in the Browser by the time this
        // responds rather than at the next scheduled drain. Never throws —
        // anything undelivered stays PENDING for `neo4j:drain`.
        app(GraphSync::class)->flushRecord('tblstudent', (int) $studentId);

        return response()->json(['status' => 1, 'message' => 'Student successfully created.', 'data' => ['id' => $studentId]], 201);
    }

    private function insertStudent(Request $request): int
    {
        return DB::transaction(function () use ($request) {
            $profile = DB::table('tbluserprofilemaster')->where('sub_institute_id', $request->sub_institute_id)->where('name', 'Student')->value('id');
            $studentId = DB::table('tblstudent')->insertGetId([
                'enrollment_no' => $request->enrollment_no, 'first_name' => trim($request->first_name),
                'middle_name' => trim((string) $request->middle_name), 'last_name' => trim((string) $request->last_name),
                'father_name' => trim((string) $request->father_name), 'mother_name' => trim((string) $request->mother_name),
                'gender' => $request->gender, 'dob' => $request->dob ?: null, 'mobile' => $request->mobile,
                'email' => $request->email, 'address' => $request->address, 'bloodgroup' => $request->bloodgroup,
                'password' => md5('student'), 'user_profile_id' => $profile, 'status' => 1,
                'sub_institute_id' => $request->sub_institute_id, 'marking_period_id' => $request->term_id,
                'admission_year' => $request->syear,
            ]);
            DB::table('tblstudent_enrollment')->insert([
                'student_id' => $studentId, 'grade_id' => $request->grade, 'standard_id' => $request->standard,
                'section_id' => $request->division, 'syear' => $request->syear, 'student_quota' => $request->student_quota,
                'house_id' => $request->house, 'roll_no' => $request->roll_no, 'start_date' => date('Y-m-d'),
                'term_id' => $request->term_id, 'enrollment_code' => 1, 'sub_institute_id' => $request->sub_institute_id,
            ]);
            return $studentId;
        });
    }

    private function guard(Request $request): ?JsonResponse
    {
        try { if (! $this->jwtToken()->validate()) return response()->json(['status' => 2, 'message' => 'Token Auth Failed', 'data' => []], 401); }
        catch (\Exception $e) { return response()->json(['status' => 2, 'message' => $e->getMessage(), 'data' => []], 401); }
        $validator = Validator::make($request->all(), ['sub_institute_id' => 'required|integer', 'syear' => 'required']);
        return $validator->fails() ? response()->json(['status' => 0, 'message' => $validator->errors()->first(), 'data' => []], 422) : null;
    }
}
