<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Capture Photo API (student reference photos used by face attendance) backing
 * the Next.js screen.
 *
 * The legacy flow is front_desk\studentFaceAttendanceController (Blade, session
 * auth): a student uploads their own photos, an Admin uploads them for a student
 * picked by GR no./name, and an Admin lists and deletes them. That controller is
 * untouched; this is its stateless JSON variant. Identity, school and academic
 * year come from the verified JWT (api.session middleware).
 *
 * Rows live in student_capture_photos and files in storage/app/public/capture_photos/<student_id>,
 * exactly where the legacy page and the face-matching service expect them.
 */
class StudentCapturePhotoApiController extends Controller
{
    private const MAX_FILES = 10;

    /**
     * GET|POST /api/capture-photo/list
     *
     * Optional filters: grade, standard, division. A student only ever sees their own photos.
     */
    public function index(Request $request): JsonResponse
    {
        $subInstituteId = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        // Role flags only, so the screen can tailor itself before anything is searched.
        if ($request->boolean('meta_only')) {
            return response()->json([
                'status' => 1,
                'message' => 'Success',
                'data' => ['photos' => [], 'can_manage' => $this->isAdmin(), 'is_student' => $this->isStudent()],
            ]);
        }

        $rows = DB::table('student_capture_photos as s')
            ->join('tblstudent as ts', function ($j) use ($subInstituteId) {
                $j->on('s.student_id', '=', 'ts.id')->where('ts.sub_institute_id', $subInstituteId);
            })
            ->join('tblstudent_enrollment as se', function ($j) use ($syear) {
                $j->on('s.student_id', '=', 'se.student_id')->where('se.syear', $syear);
            })
            ->join('standard as std', function ($j) use ($subInstituteId) {
                $j->on('se.standard_id', '=', 'std.id')->where('std.sub_institute_id', $subInstituteId);
            })
            ->join('division as dev', function ($j) use ($subInstituteId) {
                $j->on('se.section_id', '=', 'dev.id')->where('dev.sub_institute_id', $subInstituteId);
            })
            ->whereNull('se.end_date')
            ->where('s.sub_institute_id', $subInstituteId)
            ->where('s.syear', $syear)
            ->selectRaw("s.id,s.student_id,s.stu_image,s.type_id,s.created_on,
                CONCAT_WS(' ',COALESCE(ts.first_name,'-'),COALESCE(ts.middle_name,'-'),COALESCE(ts.last_name,'-')) as full_name,
                ts.enrollment_no,ts.mobile,std.name as standard_name,dev.name as division_name")
            ->when($this->isStudent(), fn ($q) => $q->where('s.student_id', session()->get('user_id')))
            ->when(!$this->isStudent() && $request->filled('grade'), fn ($q) => $q->where('se.grade_id', $request->input('grade')))
            ->when(!$this->isStudent() && $request->filled('standard'), fn ($q) => $q->where('se.standard_id', $request->input('standard')))
            ->when(!$this->isStudent() && $request->filled('division'), fn ($q) => $q->where('se.section_id', $request->input('division')))
            ->orderBy('s.id', 'desc')
            ->get()
            ->map(function ($row) {
                $row->image_url = asset('storage/capture_photos/' . $row->student_id . '/' . $row->stu_image);

                return $row;
            });

        return response()->json([
            'status' => 1,
            'message' => 'Success',
            'data' => [
                'photos' => $rows->values()->all(),
                'can_manage' => $this->isAdmin(),
                'is_student' => $this->isStudent(),
            ],
        ]);
    }

    /**
     * GET|POST /api/capture-photo/students?value=<text>   (admin)
     *
     * Same lookup as the legacy search_student_name (active, currently enrolled
     * students of this year, matched on GR no. or name), with bound parameters.
     */
    public function searchStudents(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return $this->fail('Only an admin can pick a student.', 403);
        }

        $value = trim((string) $request->input('value'));
        if (mb_strlen($value) < 3) {
            return response()->json(['status' => 1, 'message' => 'Success', 'data' => []]);
        }

        $like = '%' . $value . '%';
        $students = DB::table('tblstudent as ts')
            ->join('tblstudent_enrollment as se', 'ts.id', '=', 'se.student_id')
            ->whereNull('se.end_date')
            ->where('se.sub_institute_id', session()->get('sub_institute_id'))
            ->where('se.syear', session()->get('syear'))
            ->where('ts.status', 1)
            ->where(function ($q) use ($like) {
                $q->where('ts.enrollment_no', 'like', $like)
                    ->orWhereRaw('CONCAT_WS(" ",ts.first_name,ts.middle_name,ts.last_name) LIKE ?', [$like]);
            })
            ->selectRaw('ts.id, CONCAT(ts.enrollment_no, " / ", CONCAT_WS(" ",ts.first_name,ts.middle_name,ts.last_name)) as student')
            ->orderBy('ts.first_name')
            ->limit(20)
            ->get();

        return response()->json(['status' => 1, 'message' => 'Success', 'data' => $students]);
    }

    /**
     * POST /api/capture-photo/store   (multipart/form-data)
     *
     * Fields: stu_image[] (image files), student_id (admin only; students always
     * upload for themselves), type_id (optional). Photos are added, never replaced,
     * as in the legacy page.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'stu_image' => 'required|array|min:1|max:' . self::MAX_FILES,
            'stu_image.*' => 'required|file|mimes:jpg,jpeg,png,webp|max:10240',
            'student_id' => 'nullable|integer',
            'type_id' => 'nullable|integer',
        ], [
            'stu_image.required' => 'Choose at least one photo.',
            'stu_image.max' => 'Upload at most ' . self::MAX_FILES . ' photos at a time.',
            'stu_image.*.mimes' => 'Photos must be JPG, PNG or WebP images.',
            'stu_image.*.max' => 'Each photo must be 10 MB or smaller.',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $subInstituteId = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if ($this->isAdmin()) {
            $studentId = (int) $request->input('student_id');
            if ($studentId <= 0) {
                return $this->fail('Select a student first.', 422);
            }
            $exists = DB::table('tblstudent')->where('id', $studentId)->where('sub_institute_id', $subInstituteId)->exists();
            if (!$exists) {
                return $this->fail('Student not found.', 404);
            }
        } elseif ($this->isStudent()) {
            $studentId = (int) session()->get('user_id');
        } else {
            return $this->fail('Only a student or an admin can add photos.', 403);
        }

        $stored = 0;
        foreach ($request->file('stu_image') as $file) {
            $fileName = $studentId . '_' . random_int(10000, 99999) . '.' . $file->getClientOriginalExtension();
            Storage::disk('public')->putFileAs('capture_photos/' . $studentId, $file, $fileName);

            DB::table('student_capture_photos')->insert([
                'syear' => $syear,
                'sub_institute_id' => $subInstituteId,
                'student_id' => $studentId,
                'stu_image' => $fileName,
                'type_id' => $request->input('type_id'),
                'created_on' => now(),
            ]);
            $stored++;
        }

        return response()->json([
            'status' => 1,
            'message' => $stored === 1 ? 'Record Added' : "$stored photos added",
            'data' => ['student_id' => $studentId, 'count' => $stored],
        ]);
    }

    /**
     * POST /api/capture-photo/delete   (admin)
     *
     * Fields: ids[] of student_capture_photos rows. Only this school's rows are touched.
     */
    public function destroy(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return $this->fail('Only an admin can delete photos.', 403);
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail('Select at least one photo to delete.', 422);
        }

        $photos = DB::table('student_capture_photos')
            ->whereIn('id', $request->input('ids'))
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->get();

        foreach ($photos as $photo) {
            Storage::disk('public')->delete('capture_photos/' . $photo->student_id . '/' . $photo->stu_image);
            DB::table('student_capture_photos')->where('id', $photo->id)->delete();
        }

        if ($photos->isEmpty()) {
            return $this->fail('Photo not found.', 404);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Image deleted successfully',
            'data' => ['deleted' => $photos->count()],
        ]);
    }

    /** The Admin profile and Super/multi-school admins (named 'Super Admin' by the session hydrator). */
    private function isAdmin(): bool
    {
        return in_array(strtoupper((string) session()->get('user_profile_name')), ['ADMIN', 'SUPER ADMIN'], true);
    }

    private function isStudent(): bool
    {
        return (bool) session()->get('is_student') || strtoupper((string) session()->get('user_profile_name')) === 'STUDENT';
    }

    private function fail(string $message, int $status, array $errors = []): JsonResponse
    {
        return response()->json(['status' => 0, 'message' => $message, 'errors' => $errors ?: null], $status);
    }
}
