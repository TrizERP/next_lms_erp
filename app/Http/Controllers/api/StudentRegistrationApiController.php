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
     * Next auto-generated GR No, scoped to admission year: MAX(enrollment_no) + 1
     * across tblstudent rows sharing the same tblstudent.admission_year as the
     * current syear, matching GR No sequencing tied to the student's admission
     * year (a static column on tblstudent, not the per-year enrollment table).
     *
     * Excludes enrollment_no values longer than 8 digits: some historical rows
     * have a mobile number stored in enrollment_no by mistake (10-11 digits),
     * which would otherwise dominate MAX() and produce a nonsensical next
     * number. Real GR numbers in this data are 2-7 digits.
     */
   public function nextEnrollmentNo(Request $request): JsonResponse
{
    if ($error = $this->guard($request)) {
        return $error;
    }

    $next = DB::table('tblstudent')
        ->where('sub_institute_id', $request->sub_institute_id)
        ->where('admission_year', $request->syear)
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
        $studentId = DB::transaction(function () use ($request) {
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

    private function guard(Request $request): ?JsonResponse
    {
        try { if (! $this->jwtToken()->validate()) return response()->json(['status' => 2, 'message' => 'Token Auth Failed', 'data' => []], 401); }
        catch (\Exception $e) { return response()->json(['status' => 2, 'message' => $e->getMessage(), 'data' => []], 401); }
        $validator = Validator::make($request->all(), ['sub_institute_id' => 'required|integer', 'syear' => 'required']);
        return $validator->fails() ? response()->json(['status' => 0, 'message' => $validator->errors()->first(), 'data' => []], 422) : null;
    }
}
