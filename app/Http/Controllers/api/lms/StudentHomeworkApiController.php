<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Models\student\studentHomeworkModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\getStudents;
use App\Models\school_setup\subjectModel;

class StudentHomeworkApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $user_profile_name = $request->input('user_profile_name');
        $user_id = $request->input('user_id');
        $standard_id = $request->input('standard_id');
        $subject_id = $request->input('subject_id');
        $division_id = $request->input('division_id');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $type = $request->input('type', 'Homework');

        $query = DB::table('homework as h')
            ->join('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.id = h.student_id AND ts.sub_institute_id = h.sub_institute_id');
            })
            ->join('standard as s', function ($join) {
                $join->whereRaw('h.standard_id = s.id AND h.sub_institute_id = s.sub_institute_id');
            })
            ->join('division as d', function ($join) {
                $join->whereRaw('d.id = h.division_id AND h.sub_institute_id = d.sub_institute_id');
            })
            ->join('subject as ss', function ($join) {
                $join->whereRaw('ss.id = h.subject_id AND ss.sub_institute_id = h.sub_institute_id');
            })
            ->selectRaw('h.id, h.title, h.description, h.date, h.submission_date, h.student_id, h.standard_id, h.division_id, h.subject_id, h.image, h.type, s.name as standard_name, d.name as division_name, ss.subject_name')
            ->where('h.sub_institute_id', $sub_institute_id)
            ->where('h.syear', $syear)
            ->where('h.type', $type);

        if ($user_profile_name == "Student") {
            $query->where('h.student_id', $user_id);
        } elseif ($user_profile_name == "Teacher") {
            $query->where(function ($q) use ($user_id, $syear, $sub_institute_id, $standard_id) {
                $q->whereIn('h.standard_id', function ($sub_query) use ($user_id, $syear, $sub_institute_id) {
                    $sub_query->select('standard_id')
                        ->from('timetable')
                        ->where('teacher_id', $user_id)
                        ->where('syear', $syear)
                        ->where('sub_institute_id', $sub_institute_id);
                });
                if ($standard_id) {
                    $q->where('h.standard_id', $standard_id);
                }
            });
        }

        if ($standard_id) {
            $query->where('h.standard_id', $standard_id);
        }
        if ($subject_id) {
            $query->where('h.subject_id', $subject_id);
        }
        if ($division_id) {
            $query->where('h.division_id', $division_id);
        }
        if ($from_date) {
            $query->where('h.date', '>=', $from_date);
        }
        if ($to_date) {
            $query->where('h.date', '<=', $to_date);
        }

        $data = $query->orderBy('h.id', 'DESC')->get()->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $data,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear' => 'required|numeric',
            'students' => 'required',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'standard_id' => 'required|numeric',
            'subject_id' => 'required|numeric',
            'submission_date' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $students = $this->parseCsvIds($request->input('students'));
        $title = $request->input('title');
        $description = $request->input('description');
        $standard_id = $request->input('standard_id');
        $division_id = $request->input('division_id');
        $subject_id = $request->input('subject_id');
        $submission_date = $request->input('submission_date');
        $teacher_id = $request->input('teacher_id');

        $student_details = getStudents($students, $sub_institute_id, $syear);

        if (empty($student_details)) {
            return response()->json([
                'status_code' => 0,
                'message' => "Students Not Found with syear $syear",
            ], 404);
        }

        $file_name = $file_size = $ext = "";
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = "homework-" . ($request->user_name ?? 'api') . date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name . '.' . $ext;
            $file->storeAs('public/student/', $file_name);
        }

        $inserted_ids = [];
        foreach ($student_details as $id => $arr) {
            $student_level = 'Easy';
            $getStudentLastEntry = studentHomeworkModel::where([
                'student_id' => $arr['id'],
                'standard_id' => $standard_id,
                'subject_id' => $subject_id,
                'syear' => $syear,
                'sub_institute_id' => $sub_institute_id,
            ])->orderBy('id', 'desc')->first();

            if ($getStudentLastEntry && $getStudentLastEntry->student_level !== null && $getStudentLastEntry->student_level != '') {
                $student_level = $getStudentLastEntry->student_level;
            } else {
                $getPAL = DB::table('result_personalize_marks')
                    ->where([
                        'student_id' => $arr['id'],
                        'standard_id' => $standard_id,
                        'subject_id' => $subject_id,
                        'syear' => $syear,
                        'sub_institute_id' => $sub_institute_id,
                    ])->orderBy('id', 'desc')->first();
                if ($getPAL && $getPAL->student_level !== null && $getPAL->student_level != '') {
                    $student_level = $getPAL->student_level;
                }
            }

            $addhomeworkArray = [
                'sub_institute_id' => $sub_institute_id,
                'student_id' => $arr['id'],
                'title' => $title,
                'description' => $description,
                'standard_id' => $arr['standard_id'],
                'division_id' => $arr['section_id'],
                'subject_id' => $subject_id,
                'date' => date('Y-m-d'),
                'submission_date' => $submission_date,
                'syear' => $syear,
                'type' => "Homework",
                'image' => $file_name,
                'image_size' => $file_size,
                'image_type' => $ext,
                'created_ip' => $request->ip(),
                'created_by' => $teacher_id ?? ($request->header('X-User-Id') ?? null),
                'prompt' => $request->input('prompt'),
                'student_level' => $student_level,
            ];

            $insertedId = studentHomeworkModel::insertGetId($addhomeworkArray);
            $inserted_ids[] = $insertedId;
        }

        return response()->json([
            'status_code' => 1,
            'message' => "Homework Added successfully",
            'homework_ids' => $inserted_ids,
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');

        $homework = DB::table('homework as h')
            ->join('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.id = h.student_id AND ts.sub_institute_id = h.sub_institute_id');
            })
            ->join('standard as s', function ($join) {
                $join->whereRaw('h.standard_id = s.id AND h.sub_institute_id = s.sub_institute_id');
            })
            ->join('division as d', function ($join) {
                $join->whereRaw('d.id = h.division_id AND h.sub_institute_id = d.sub_institute_id');
            })
            ->join('subject as ss', function ($join) {
                $join->whereRaw('ss.id = h.subject_id AND ss.sub_institute_id = h.sub_institute_id');
            })
            ->selectRaw('h.*, s.name as standard_name, d.name as division_name, ss.subject_name, CONCAT_WS(" ", ts.first_name, ts.middle_name, ts.last_name) as student_name')
            ->where('h.id', $id)
            ->when($sub_institute_id, function ($q) use ($sub_institute_id) {
                $q->where('h.sub_institute_id', $sub_institute_id);
            })
            ->when($syear, function ($q) use ($syear) {
                $q->where('h.syear', $syear);
            })
            ->first();

        if (!$homework) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Homework not found',
            ], 404);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => (array) $homework,
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'sub_institute_id' => 'required|numeric',
            'syear' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');

        $homework = studentHomeworkModel::where('id', $id)
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->first();

        if (!$homework) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Homework not found',
            ], 404);
        }

        $updateData = [];
        $allowedFields = ['title', 'description', 'subject_id', 'standard_id', 'division_id', 'submission_date', 'image', 'prompt', 'student_level'];

        foreach ($allowedFields as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        if (!empty($updateData)) {
            $homework->update($updateData);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Homework updated successfully',
            'data' => $homework->toArray(),
        ], 200);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');

        $homework = studentHomeworkModel::where('id', $id)
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->first();

        if (!$homework) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Homework not found',
            ], 404);
        }

        $homework->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Homework deleted successfully',
        ], 200);
    }

    public function teacherHomework(Request $request): JsonResponse
    {
        $teacher_id = $request->input('teacher_id');
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $type = $request->input('type', 'Homework');

        if (!$teacher_id || !$sub_institute_id || !$syear) {
            return response()->json([
                'status' => 0,
                'message' => 'Parameter Missing',
            ], 422);
        }

        $server = $request->getSchemeAndHttpHost();

        $data = DB::table('homework as h')
            ->join('tblstudent as ts', function ($join) {
                $join->on('ts.id', '=', 'h.student_id')->on('ts.sub_institute_id', '=', 'h.sub_institute_id');
            })
            ->join('standard as s', function ($join) {
                $join->on('h.standard_id', '=', 's.id')->on('h.sub_institute_id', '=', 's.sub_institute_id');
            })
            ->join('division as d', function ($join) {
                $join->on('d.id', '=', 'h.division_id')->on('d.sub_institute_id', '=', 'h.sub_institute_id');
            })
            ->join('subject as ss', function ($join) {
                $join->on('ss.id', '=', 'h.subject_id')->on('ss.sub_institute_id', '=', 'h.sub_institute_id');
            })
            ->selectRaw("h.id,h.title,h.description,h.date,
                if(h.image = '','',concat('$server/storage/student/',h.image)) as file_name,
                s.name AS standard_name,d.name AS division_name,ss.subject_name,
                CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS student_name,
                ts.enrollment_no,ts.mobile,h.type")
            ->where('h.sub_institute_id', $sub_institute_id)
            ->where('h.syear', $syear)
            ->where('h.created_by', $teacher_id)
            ->where('h.type', $type)
            ->when($from_date, function ($q) use ($from_date) {
                $q->where('h.date', '>=', $from_date);
            })
            ->when($to_date, function ($q) use ($to_date) {
                $q->where('h.date', '<=', $to_date);
            })
            ->orderBy('date', 'DESC')
            ->get()
            ->toArray();

        return response()->json([
            'status' => 1,
            'message' => "Success",
            'data' => $data,
        ], 200);
    }

    public function studentHomework(Request $request): JsonResponse
    {
        $student_id = $request->input('student_id');
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $type = $request->input('type', 'Homework');

        if (!$student_id || !$sub_institute_id || !$syear) {
            return response()->json([
                'status' => 0,
                'message' => 'Parameter Missing',
            ], 422);
        }

        $server = $request->getSchemeAndHttpHost();

        $data = DB::table('homework as h')
            ->join('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.id = h.student_id AND ts.sub_institute_id = h.sub_institute_id');
            })
            ->join('standard as s', function ($join) {
                $join->whereRaw('h.standard_id = s.id AND h.sub_institute_id = s.sub_institute_id');
            })
            ->join('division as d', function ($join) {
                $join->whereRaw('d.id = h.division_id AND h.sub_institute_id = d.sub_institute_id');
            })
            ->join('subject as ss', function ($join) {
                $join->whereRaw('ss.id = h.subject_id AND ss.sub_institute_id = h.sub_institute_id');
            })
            ->leftJoin('tbluser as tu', function ($join) {
                $join->whereRaw('tu.id = h.created_by')->where('tu.status', 1);
            })
            ->selectRaw("h.id,h.title,h.description,h.created_by,DATE_FORMAT(h.date,'%d-%m-%Y') AS date,
                if(h.image = '','',concat('$server/storage/student/',h.image)) as file_name,
                s.name AS standard_name,d.name AS division_name,ss.subject_name,
                CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS student_name,
                ts.enrollment_no,ts.mobile,h.type,
                if(tu.image IS NOT NULL AND tu.image != '',concat('$server/storage/student/',tu.image),
                '$server/storage/student/noimages.png') as user_image")
            ->where('h.sub_institute_id', $sub_institute_id)
            ->where('h.syear', $syear)
            ->where('h.student_id', $student_id)
            ->where('h.type', $type)
            ->orderBy('h.date', 'DESC')
            ->get()
            ->toArray();

        if (count($data) > 0) {
            return response()->json([
                'status' => 1,
                'message' => "Success",
                'data' => $data,
            ], 200);
        }

        return response()->json([
            'status' => 0,
            'message' => "No homework found",
        ], 404);
    }

    public function subjects(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $student_id = $request->input('student_id');
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');

        if (!$student_id || !$sub_institute_id || !$syear) {
            return response()->json([
                'status' => 0,
                'message' => 'Parameter Missing',
            ], 422);
        }

        $stud_data = DB::table('tblstudent_enrollment')
            ->where('student_id', $student_id)
            ->where('syear', $syear)
            ->where('sub_institute_id', $sub_institute_id)
            ->get()
            ->toArray();

        if (count($stud_data) > 0) {
            $standard_id = $stud_data[0]->standard_id;
            $section_id = $stud_data[0]->section_id;

            $data = DB::table('timetable as t')
                ->join('sub_std_map as s', function ($join) {
                    $join->on('s.subject_id', '=', 't.subject_id');
                })
                ->join('tbluser as tu', function ($join) {
                    $join->on('tu.id', '=', 't.teacher_id')->where('tu.status', 1);
                })
                ->selectRaw('display_name AS subject_name, elective_subject, allow_grades, t.teacher_id, CONCAT_WS(" ", tu.first_name, tu.middle_name, tu.last_name) as teacher_name')
                ->where('t.syear', $syear)
                ->where('t.sub_institute_id', $sub_institute_id)
                ->where('t.standard_id', $standard_id)
                ->where('t.division_id', $section_id)
                ->groupBy('t.subject_id')
                ->orderBy('display_name')
                ->get()
                ->toArray();

            return response()->json([
                'status' => 1,
                'message' => 'Success',
                'data' => $data,
            ], 200);
        }

        return response()->json([
            'status' => 0,
            'message' => 'Wrong Parameters',
        ], 404);
    }

    public function getSubjects(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');

        if (!$sub_institute_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'sub_institute_id is required',
            ], 422);
        }

        $subjects = subjectModel::select('id', 'subject_name')
            ->where('sub_institute_id', $sub_institute_id)
            ->get()
            ->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'subjects' => $subjects,
        ], 200);
    }

    private function parseCsvIds($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value), fn ($id) => $id > 0));
        }

        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', $value)), fn ($part) => $part !== '');

        return array_values(array_filter(array_map('intval', $parts), fn ($id) => $id > 0));
    }

    public function getChapters(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $subject_id = $request->input('subject_id');
        $standard_id = $request->input('standard_id');

        if (!$sub_institute_id || !$subject_id || !$standard_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'sub_institute_id, subject_id and standard_id are required',
            ], 422);
        }

        $chapters = DB::table('chapter_master')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('subject_id', $subject_id)
            ->where('standard_id', $standard_id)
            ->get()
            ->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'chapters' => $chapters,
        ], 200);
    }
}
