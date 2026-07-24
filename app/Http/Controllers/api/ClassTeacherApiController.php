<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\school_setup\classteacherModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClassTeacherApiController extends Controller
{
    use GetsJwtToken;

    private function authenticate()
    {
        try {
            if (! $this->jwtToken()->validate()) {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Token Auth Failed',
                    'data' => [],
                ], 401);
            }
        } catch (\Exception $exception) {
            return response()->json([
                'status_code' => 2,
                'message' => $exception->getMessage(),
                'data' => [],
            ], 401);
        }

        return null;
    }

    private function validateContext(Request $request)
    {
        return Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear' => 'required|integer',
        ]);
    }

    private function assignments($subInstituteId, $syear)
    {
        return classteacherModel::from('class_teacher as ct')
            ->select(
                'ct.*',
                'a.title as academic_section_name',
                's.name as standard_name',
                'd.name as division_name',
                DB::raw('concat_ws(" ", u.first_name, u.middle_name, u.last_name) as teacher_name')
            )
            ->join('academic_section as a', 'a.id', '=', 'ct.grade_id')
            ->join('standard as s', 's.id', '=', 'ct.standard_id')
            ->join('division as d', 'd.id', '=', 'ct.division_id')
            ->join('tbluser as u', function ($join) {
                $join->on('u.id', '=', 'ct.teacher_id')->where('u.status', 1);
            })
            ->where([
                'ct.sub_institute_id' => $subInstituteId,
                'ct.syear' => $syear,
            ])
            ->orderBy('a.sort_order')
            ->orderBy('s.sort_order')
            ->orderBy('d.name')
            ->get();
    }

    public function index(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = $this->validateContext($request);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => $validator->messages()->first(),
                'data' => [],
            ], 422);
        }

        $subInstituteId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');

        $academicSections = DB::table('academic_section')
            ->select('id', 'title', 'sort_order')
            ->where('sub_institute_id', $subInstituteId)
            ->orderBy('sort_order')
            ->get();

        $standards = DB::table('standard')
            ->select('id', 'grade_id', 'name', 'sort_order')
            ->where('sub_institute_id', $subInstituteId)
            ->orderBy('sort_order')
            ->get();

        $divisions = DB::table('std_div_map as mapping')
            ->select(
                'division.id',
                'mapping.standard_id',
                'division.name'
            )
            ->join('division', 'division.id', '=', 'mapping.division_id')
            ->where('mapping.sub_institute_id', $subInstituteId)
            ->where('division.sub_institute_id', $subInstituteId)
            ->orderBy('division.name')
            ->get();

        $teachers = DB::table('tbluser as u')
            ->select(
                'u.id',
                DB::raw('concat_ws(" ", u.first_name, u.middle_name, u.last_name) as teacher_name')
            )
            ->join('tbluserprofilemaster as up', 'up.id', '=', 'u.user_profile_id')
            ->where([
                'u.sub_institute_id' => $subInstituteId,
                'up.name' => 'Teacher',
                'u.status' => 1,
            ])
            ->orderBy('u.first_name')
            ->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => [
                'assignments' => $this->assignments($subInstituteId, $syear),
                'academic_sections' => $academicSections,
                'standards' => $standards,
                'divisions' => $divisions,
                'teachers' => $teachers,
            ],
        ]);
    }

    public function store(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear' => 'required|integer',
            'grade_id' => 'required|integer',
            'standard_id' => 'required|integer',
            'division_id' => 'required|integer',
            'teacher_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => $validator->messages()->first(),
                'data' => [],
            ], 422);
        }

        $existing = classteacherModel::from('class_teacher as ct')
            ->join('tbluser as u', 'u.id', '=', 'ct.teacher_id')
            ->where([
                'ct.sub_institute_id' => $request->integer('sub_institute_id'),
                'ct.syear' => $request->integer('syear'),
                'ct.grade_id' => $request->integer('grade_id'),
                'ct.standard_id' => $request->integer('standard_id'),
                'ct.division_id' => $request->integer('division_id'),
                'u.status' => 1,
            ])
            ->select('u.first_name', 'u.last_name')
            ->first();

        if ($existing) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Class Teacher Already Exist to teacher '.trim($existing->first_name.' '.$existing->last_name),
                'data' => [],
            ], 409);
        }

        $record = classteacherModel::create([
            'sub_institute_id' => $request->integer('sub_institute_id'),
            'syear' => $request->integer('syear'),
            'grade_id' => $request->integer('grade_id'),
            'standard_id' => $request->integer('standard_id'),
            'division_id' => $request->integer('division_id'),
            'teacher_id' => $request->integer('teacher_id'),
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'Class Teacher Added Successfully',
            'data' => $record,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear' => 'required|integer',
            'grade_id' => 'required|integer',
            'standard_id' => 'required|integer',
            'division_id' => 'required|integer',
            'teacher_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => $validator->messages()->first(),
                'data' => [],
            ], 422);
        }

        $record = classteacherModel::where([
            'id' => $id,
            'sub_institute_id' => $request->integer('sub_institute_id'),
            'syear' => $request->integer('syear'),
        ])->first();
        if (! $record) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Class Teacher not found',
                'data' => [],
            ], 404);
        }

        $duplicate = classteacherModel::where([
            'sub_institute_id' => $request->integer('sub_institute_id'),
            'syear' => $request->integer('syear'),
            'grade_id' => $request->integer('grade_id'),
            'standard_id' => $request->integer('standard_id'),
            'division_id' => $request->integer('division_id'),
        ])->where('id', '!=', $id)->exists();

        if ($duplicate) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Class Teacher Already Exist',
                'data' => [],
            ], 409);
        }

        $record->update([
            'grade_id' => $request->integer('grade_id'),
            'standard_id' => $request->integer('standard_id'),
            'division_id' => $request->integer('division_id'),
            'teacher_id' => $request->integer('teacher_id'),
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'Class Teacher Updated Successfully',
            'data' => $record->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = $this->validateContext($request);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => $validator->messages()->first(),
                'data' => [],
            ], 422);
        }

        $record = classteacherModel::where([
            'id' => $id,
            'sub_institute_id' => $request->integer('sub_institute_id'),
            'syear' => $request->integer('syear'),
        ])->first();
        if (! $record) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Class Teacher not found',
                'data' => [],
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Class Teacher Deleted Successfully',
            'data' => [],
        ]);
    }
}
