<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\student\studentAttendanceController;
use App\Models\user\tbluserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

use function App\Helpers\SearchStudent;

/**
 * Capture Class Attendance (photo capture) API backing the Next.js screen.
 *
 * The legacy flow is front_desk\classFaceAttendanceController (Blade, session
 * auth): class photos go to the face-matching service, the teacher reviews the
 * suggested Present/Absent list, and the review form posts to
 * studentAttendanceController::saveStudentAttendance. Both stay untouched; this
 * controller is the stateless JSON variant of the same three steps. Identity,
 * school and academic year come from the verified JWT (api.session middleware),
 * never from request parameters.
 */
class FaceAttendanceApiController extends Controller
{
    private const MIN_IMAGES = 3;
    private const MAX_IMAGES = 5;
    private const DEFAULT_MATCH_URL = 'https://harshit20991999-multi-face-detection-modal.hf.space/api/v1/attendance';
    private const CONFIDENCE_THRESHOLD = '0.65';

    /**
     * GET|POST /api/face-attendance/class-options
     *
     * The standard/division pairs this user may take attendance for. Same rules
     * as the App\Helpers\ClassTeacherSearch dropdown the Blade page renders.
     */
    public function classOptions(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 1,
            'message' => 'Success',
            'data' => $this->allowedClasses(),
        ]);
    }

    /**
     * POST /api/face-attendance/capture   (multipart/form-data)
     *
     * Fields: standard_division ("std||div"), date (Y-m-d), images[] (3-5 files).
     * Sends the photos for face matching, keeps them (student_capture_attendance,
     * as the legacy page does) and returns the class list with a suggested
     * Present/Absent per student. Nothing is written to attendance_student here -
     * that happens in save() after the teacher reviews.
     */
    public function capture(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'standard_division' => ['required', 'string', 'regex:/^\d+\|\|\d+$/'],
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'images' => 'required|array|min:' . self::MIN_IMAGES . '|max:' . self::MAX_IMAGES,
            'images.*' => 'required|file|image|max:10240',
        ], [
            'images.min' => 'Upload at least ' . self::MIN_IMAGES . ' images.',
            'images.max' => 'Upload at most ' . self::MAX_IMAGES . ' images.',
            'standard_division.regex' => 'Select a valid standard and division.',
            'date.before_or_equal' => 'Attendance cannot be taken for a future date.',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $date = $request->input('date');
        if ((int) date('w', strtotime($date)) === 0) {
            return $this->fail('Sunday is a holiday, so attendance cannot be taken on that day.', 422);
        }

        [$standardId, $divisionId] = array_map('intval', explode('||', $request->input('standard_division')));
        if (!$this->classAllowed($standardId, $divisionId)) {
            return $this->fail('You are not allowed to take attendance for this class.', 403);
        }

        $subInstituteId = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $students = SearchStudent('', $standardId, $divisionId, $subInstituteId, $syear);
        if (count($students) === 0) {
            return $this->fail('No students found for the selected class.', 404);
        }

        $images = $request->file('images');

        try {
            $matched = $this->matchFaces($images, $standardId, $divisionId, $subInstituteId, $syear, $date);
        } catch (Throwable $e) {
            Log::error('Face attendance matching failed: ' . $e->getMessage());

            return $this->fail('The face matching service is unavailable. Try again in a moment.', 502);
        }

        // Keep the teacher's photos, same folder and table as the legacy page.
        $folder = 'capture_attendance/' . $date . '/' . $standardId . '-' . $divisionId;
        foreach ($images as $image) {
            $fileName = $standardId . '-' . $divisionId . '_' . random_int(10000, 99999) . '.' . $image->getClientOriginalExtension();
            Storage::disk('public')->putFileAs($folder, $image, $fileName);

            DB::table('student_capture_attendance')->insert([
                'sub_institute_id' => $subInstituteId,
                'syear' => $syear,
                'date' => $date,
                'standard_id' => $standardId,
                'division_id' => $divisionId,
                'image' => $fileName,
                'created_on' => now(),
            ]);
        }

        $studentData = [];
        $attendance = [];
        foreach ($students as $student) {
            $studentId = $student['id'];
            $studentData[] = [
                'id' => $studentId,
                'enrollment_no' => $student['enrollment_no'] ?? '',
                'roll_no' => $student['roll_no'] ?? '',
                'first_name' => $student['first_name'] ?? '',
                'middle_name' => $student['middle_name'] ?? '',
                'last_name' => $student['last_name'] ?? '',
                'batch_title' => $student['batch_title'] ?? '',
                'image' => $student['image'] ?? '',
            ];
            $attendance[$studentId] = isset($matched[$studentId]) ? 'P' : 'A';
        }

        $present = count(array_filter($attendance, fn ($code) => $code === 'P'));
        $absent = count($attendance) - $present;

        return response()->json([
            'status' => 1,
            'message' => "Faces matched for $present students; $absent not matched. Review the list and save.",
            'data' => [
                'date' => $date,
                'standard_division' => $standardId . '||' . $divisionId,
                'student_data' => $studentData,
                'attendance_data' => $attendance,
                'matched_count' => $present,
                'unmatched_count' => $absent,
            ],
        ]);
    }

    /**
     * POST /api/face-attendance/save
     *
     * Fields: standard_division, date, student[<id>] = P|A.
     * Same upsert as studentAttendanceController::saveStudentAttendance (one
     * attendance_student row per student/date/class), including the
     * present/absent notification for today's date on first insert.
     */
    public function save(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'standard_division' => ['required', 'string', 'regex:/^\d+\|\|\d+$/'],
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'student' => 'required|array|min:1',
            'student.*' => 'required|in:P,A',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $date = $request->input('date');
        if ((int) date('w', strtotime($date)) === 0) {
            return $this->fail('Sunday is a holiday, so attendance cannot be taken on that day.', 422);
        }

        [$standardId, $divisionId] = array_map('intval', explode('||', $request->input('standard_division')));
        if (!$this->classAllowed($standardId, $divisionId)) {
            return $this->fail('You are not allowed to take attendance for this class.', 403);
        }

        $subInstituteId = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $userId = session()->get('user_id');
        $userProfileId = session()->get('user_profile_id');

        // Only students actually enrolled in this class can be marked.
        $classStudentIds = array_map('strval', array_column(
            SearchStudent('', $standardId, $divisionId, $subInstituteId, $syear),
            'id'
        ));

        $submitted = $request->input('student');
        $total = $present = $saved = 0;

        foreach ($submitted as $studentId => $code) {
            if (!in_array((string) $studentId, $classStudentIds, true)) {
                continue;
            }
            $total++;

            $key = [
                'syear' => $syear,
                'sub_institute_id' => $subInstituteId,
                'student_id' => $studentId,
                'attendance_date' => $date,
                'standard_id' => $standardId,
                'section_id' => $divisionId,
            ];
            $row = $key + [
                'attendance_code' => $code,
                'teacher_id' => $userId,
                'user_group_id' => $userProfileId,
                'created_by' => $userId,
            ];

            $existing = DB::table('attendance_student')->where($key)->first();
            if ($existing) {
                DB::table('attendance_student')->where('id', $existing->id)->update($row);
            } else {
                DB::table('attendance_student')->insert($row);
                if ($date === date('Y-m-d')) {
                    $this->notify($studentId, $code, $date, $userId, $syear, $subInstituteId);
                }
            }

            $saved++;
            if ($code === 'P') {
                $present++;
            }
        }

        if ($saved === 0) {
            return $this->fail('Fail to take attendance', 422);
        }

        return response()->json([
            'status' => 1,
            'message' => "Attendance successfully taken for $present students out of $total",
            'data' => ['present' => $present, 'total' => $total],
        ]);
    }

    /**
     * Calls the face-matching service and returns the matched student ids as a
     * set (id => true). Mirrors the legacy request exactly.
     *
     * @param \Illuminate\Http\UploadedFile[] $images
     */
    private function matchFaces(array $images, int $standardId, int $divisionId, $subInstituteId, $syear, string $date): array
    {
        $multipart = [];
        foreach ($images as $image) {
            $multipart[] = [
                'name' => 'files',
                'contents' => fopen($image->getRealPath(), 'r'),
                'filename' => $image->getClientOriginalName(),
            ];
        }
        array_push(
            $multipart,
            ['name' => 'confidence_threshold', 'contents' => self::CONFIDENCE_THRESHOLD],
            ['name' => 'standard_id', 'contents' => (string) $standardId],
            ['name' => 'division_id', 'contents' => (string) $divisionId],
            ['name' => 'sub_institute_id', 'contents' => (string) $subInstituteId],
            ['name' => 'date_str', 'contents' => $date],
            ['name' => 'year', 'contents' => (string) $syear]
        );

        $client = new \GuzzleHttp\Client([
            'verify' => (bool) env('FACE_ATTENDANCE_VERIFY_SSL', false),
            'timeout' => 120,
        ]);
        $body = $client->request('POST', env('FACE_ATTENDANCE_API_URL', self::DEFAULT_MATCH_URL), [
            'headers' => ['Accept' => 'application/json'],
            'multipart' => $multipart,
        ])->getBody()->getContents();

        $result = json_decode($body, true);
        if (!is_array($result)) {
            throw new \RuntimeException('Unreadable response from face matching service');
        }

        $matched = [];
        foreach ((array) ($result['matched_student'] ?? []) as $studentId) {
            $matched[$studentId] = true;
        }

        return $matched;
    }

    /**
     * Standard/division pairs the caller may take attendance for. Same query
     * logic as App\Helpers\ClassTeacherSearch, returned as rows.
     *
     * @return array<int, array{value:string,standard_id:int,division_id:int,standard_name:string,division_name:string,label:string}>
     */
    private function allowedClasses(): array
    {
        $subInstituteId = session()->get('sub_institute_id');
        $userData = tbluserModel::where('id', session()->get('user_id'))->first();

        if ($this->isAdmin()) {
            // Admins are not tied to a class: every standard/division of the school.
            $rows = DB::table('std_div_map as sdm')
                ->join('standard as s', 's.id', '=', 'sdm.standard_id')
                ->join('division as d', 'd.id', '=', 'sdm.division_id')
                ->selectRaw('s.id as standard_id,d.id as division_id,s.name as standard_name,d.name as division_name')
                ->where('sdm.sub_institute_id', $subInstituteId)
                ->groupBy('sdm.standard_id', 'sdm.division_id')
                ->orderByRaw('s.sort_order,d.name')
                ->get();
        } elseif (!empty($userData) && isset($userData->allocated_standards) && $userData->allocated_standards != '') {
            $standardIds = DB::table('standard')
                ->whereIn('id', array_filter(array_map('intval', explode(',', $userData->allocated_standards))))
                ->where('sub_institute_id', $subInstituteId)
                ->pluck('id')->all();

            $rows = DB::table('std_div_map as sdm')
                ->join('standard as s', 's.id', '=', 'sdm.standard_id')
                ->join('division as d', 'd.id', '=', 'sdm.division_id')
                ->selectRaw('s.id as standard_id,d.id as division_id,s.name as standard_name,d.name as division_name')
                ->where('sdm.sub_institute_id', $subInstituteId)
                ->whereIn('sdm.standard_id', $standardIds)
                ->groupBy('sdm.standard_id', 'sdm.division_id')
                ->orderByRaw('s.sort_order,d.name')
                ->get();
        } else {
            $rows = DB::table('class_teacher as ct')
                ->join('standard as s', function ($join) {
                    $join->whereRaw('ct.standard_id = s.id AND ct.sub_institute_id = s.sub_institute_id');
                })->join('division as d', function ($join) {
                    $join->whereRaw('d.id = ct.division_id AND d.sub_institute_id = ct.sub_institute_id');
                })->selectRaw('ct.standard_id,ct.division_id,s.name as standard_name,d.name as division_name')
                ->where('ct.sub_institute_id', $subInstituteId)
                ->where('syear', session()->get('syear'))
                ->where(function ($q) {
                    if (session()->get('user_profile_name') == 'Teacher') {
                        $q->where('ct.teacher_id', session()->get('user_id'));
                    } elseif (session()->get('profile_parent_id') != '1') {
                        $q->whereRaw('1 != 1');
                    }
                })
                ->orderByRaw('s.sort_order,d.name')
                ->get();
        }

        return $rows->map(fn ($row) => [
            'value' => $row->standard_id . '||' . $row->division_id,
            'standard_id' => (int) $row->standard_id,
            'division_id' => (int) $row->division_id,
            'standard_name' => $row->standard_name,
            'division_name' => $row->division_name,
            'label' => $row->standard_name . ' - ' . $row->division_name,
        ])->values()->all();
    }

    /** Super/multi-school admins (is_admin 1|2, which the session hydrator names 'Super Admin') and the Admin profile. */
    private function isAdmin(): bool
    {
        return in_array(strtoupper((string) session()->get('user_profile_name')), ['ADMIN', 'SUPER ADMIN'], true);
    }

    private function classAllowed(int $standardId, int $divisionId): bool
    {
        foreach ($this->allowedClasses() as $class) {
            if ($class['standard_id'] === $standardId && $class['division_id'] === $divisionId) {
                return true;
            }
        }

        return false;
    }

    /** Reuses the existing attendance notification; a push failure must not undo a saved register. */
    private function notify($studentId, string $code, string $date, $userId, $syear, $subInstituteId): void
    {
        try {
            app(studentAttendanceController::class)->sendNotificationAtt(new Request([
                'student_id' => $studentId,
                'attendance' => $code,
                'date' => $date,
                'created_by' => $userId,
                'syear' => $syear,
                'sub_institute_id' => $subInstituteId,
            ]));
        } catch (Throwable $e) {
            Log::warning('Face attendance notification failed: ' . $e->getMessage());
        }
    }

    private function fail(string $message, int $status, array $errors = []): JsonResponse
    {
        return response()->json(['status' => 0, 'message' => $message, 'errors' => $errors ?: null], $status);
    }
}
