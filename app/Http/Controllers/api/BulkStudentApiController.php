<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BulkStudentApiController extends Controller
{
    use GetsJwtToken;

    private const STUDENT_FIELDS = [
        'enrollment_no' => ['label' => 'GR No.', 'type' => 'text'],
        'first_name' => ['label' => 'First Name', 'type' => 'text'],
        'middle_name' => ['label' => 'Middle Name', 'type' => 'text'],
        'last_name' => ['label' => 'Last Name', 'type' => 'text'],
        'father_name' => ['label' => 'Father Name', 'type' => 'text'],
        'mother_name' => ['label' => 'Mother Name', 'type' => 'text'],
        'mobile' => ['label' => 'Mobile', 'type' => 'text'],
        'student_mobile' => ['label' => 'Student Mobile', 'type' => 'text'],
        'mother_mobile' => ['label' => 'Mother Mobile', 'type' => 'text'],
        'dob' => ['label' => 'Birthdate', 'type' => 'date'],
        'gender' => ['label' => 'Gender', 'type' => 'select'],
        'email' => ['label' => 'Email', 'type' => 'email'],
        'admission_date' => ['label' => 'Admission Date', 'type' => 'date'],
        'address' => ['label' => 'Address', 'type' => 'text'],
        'city' => ['label' => 'City', 'type' => 'text'],
        'state' => ['label' => 'State', 'type' => 'text'],
        'pincode' => ['label' => 'Pincode', 'type' => 'text'],
        'bloodgroup' => ['label' => 'Blood Group', 'type' => 'select'],
        'uniqueid' => ['label' => 'Unique ID', 'type' => 'text'],
    ];

    private const ENROLLMENT_FIELDS = [
        'grade' => ['label' => 'Academic Section', 'type' => 'select', 'column' => 'grade_id'],
        'standard' => ['label' => 'Standard', 'type' => 'select', 'column' => 'standard_id'],
        'division' => ['label' => 'Division', 'type' => 'select', 'column' => 'section_id'],
        'roll_no' => ['label' => 'Roll No.', 'type' => 'text', 'column' => 'roll_no'],
        'house' => ['label' => 'House', 'type' => 'select', 'column' => 'house_id'],
        'student_quota' => ['label' => 'Student Quota', 'type' => 'select', 'column' => 'student_quota'],
    ];

    public function metadata(Request $request): JsonResponse
    {
        if ($authError = $this->authenticate()) {
            return $authError;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $tenantId = (int) $request->input('sub_institute_id');
        $fields = [];
        foreach (self::STUDENT_FIELDS + self::ENROLLMENT_FIELDS as $key => $definition) {
            $fields[] = ['key' => $key] + $definition;
        }

        return response()->json([
            'status' => 1,
            'message' => 'Bulk student metadata.',
            'data' => [
                'fields' => $fields,
                'options' => [
                    'grade' => $this->options('academic_section', 'title', $tenantId),
                    'standard' => $this->options('standard', 'name', $tenantId),
                    'division' => $this->options('division', 'name', $tenantId),
                    'house' => DB::table('house_master')
                        ->where('sub_institute_id', $tenantId)
                        ->select('id', 'house_name as label')->orderBy('house_name')->get(),
                    'student_quota' => DB::table('student_quota')
                        ->where('sub_institute_id', $tenantId)
                        ->select('id', 'title as label')->orderBy('title')->get(),
                    'bloodgroup' => DB::table('blood_group')
                        ->select('id', 'bloodgroup as label')->orderBy('bloodgroup')->get(),
                    'gender' => [
                        ['id' => 'M', 'label' => 'Male'],
                        ['id' => 'F', 'label' => 'Female'],
                    ],
                ],
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        if ($authError = $this->authenticate()) {
            return $authError;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => ['required', 'integer'],
            'syear' => ['required'],
            'grade' => ['nullable', 'integer'],
            'standard' => ['nullable', 'integer'],
            'division' => ['nullable', 'integer'],
            'order_by' => ['nullable', 'in:student_name,standard_id,enrollment_no,roll_no,last_name'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $selectedFields = array_values(array_unique($request->input('fields')));
        $allowedFields = array_keys(self::STUDENT_FIELDS + self::ENROLLMENT_FIELDS);
        if (array_diff($selectedFields, $allowedFields)) {
            return response()->json(['status' => 0, 'message' => 'Invalid update field.', 'data' => []], 422);
        }

        $query = DB::table('tblstudent')
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->where('tblstudent_enrollment.sub_institute_id', $request->input('sub_institute_id'))
            ->where('tblstudent_enrollment.syear', $request->input('syear'))
            ->where('tblstudent.status', 1)
            ->whereNull('tblstudent_enrollment.end_date')
            ->when($request->filled('grade'), fn ($q) => $q->where('tblstudent_enrollment.grade_id', $request->input('grade')))
            ->when($request->filled('standard'), fn ($q) => $q->where('tblstudent_enrollment.standard_id', $request->input('standard')))
            ->when($request->filled('division'), fn ($q) => $q->where('tblstudent_enrollment.section_id', $request->input('division')));

        $select = [
            'tblstudent.id',
            DB::raw("CONCAT_WS(' ', tblstudent.first_name, tblstudent.middle_name, tblstudent.last_name) as student_name"),
            'tblstudent_enrollment.updated_on',
        ];
        foreach ($selectedFields as $field) {
            if (isset(self::STUDENT_FIELDS[$field])) {
                $select[] = 'tblstudent.'.$field;
            } else {
                $select[] = 'tblstudent_enrollment.'.self::ENROLLMENT_FIELDS[$field]['column'].' as '.$field;
            }
        }

        $orderMap = [
            'student_name' => 'tblstudent.first_name',
            'standard_id' => 'standard.sort_order',
            'enrollment_no' => 'tblstudent.enrollment_no',
            'roll_no' => 'tblstudent_enrollment.roll_no',
            'last_name' => 'tblstudent.last_name',
        ];
        $orderColumn = $orderMap[$request->input('order_by', 'student_name')];
        $students = $query->select($select)->orderBy($orderColumn)->get();

        return response()->json([
            'status' => 1,
            'message' => 'Student List',
            'data' => ['students' => $students, 'fields' => $selectedFields],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        if ($authError = $this->authenticate()) {
            return $authError;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => ['required', 'integer'],
            'syear' => ['required'],
            'user_id' => ['nullable', 'integer'],
            'students' => ['required', 'array', 'min:1'],
            'students.*.id' => ['required', 'integer'],
            'students.*.values' => ['required', 'array'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $tenantId = (int) $request->input('sub_institute_id');
        $syear = $request->input('syear');

        DB::transaction(function () use ($request, $tenantId, $syear) {
            foreach ($request->input('students') as $row) {
                $studentId = (int) $row['id'];
                $studentValues = [];
                $enrollmentValues = [];

                foreach ($row['values'] as $field => $value) {
                    if (isset(self::STUDENT_FIELDS[$field])) {
                        $studentValues[$field] = $value === '' ? null : $value;
                    } elseif (isset(self::ENROLLMENT_FIELDS[$field])) {
                        $enrollmentValues[self::ENROLLMENT_FIELDS[$field]['column']] = $value === '' ? null : $value;
                    }
                }

                if ($studentValues) {
                    $studentValues['updated_on'] = now();
                    DB::table('tblstudent')->where('id', $studentId)
                        ->where('sub_institute_id', $tenantId)->update($studentValues);
                }
                if ($enrollmentValues) {
                    $enrollmentValues['updated_on'] = now();
                    DB::table('tblstudent_enrollment')->where('student_id', $studentId)
                        ->where('sub_institute_id', $tenantId)->where('syear', $syear)
                        ->update($enrollmentValues);
                }
            }
        });

        return response()->json(['status' => 1, 'message' => 'Students updated successfully.', 'data' => []]);
    }

    private function authenticate(): ?JsonResponse
    {
        try {
            if ($this->jwtToken()->validate()) {
                return null;
            }
            return response()->json(['status' => 2, 'message' => 'Token Auth Failed', 'data' => []], 401);
        } catch (\Exception $exception) {
            return response()->json(['status' => 2, 'message' => $exception->getMessage(), 'data' => []], 401);
        }
    }

    private function validationError($validator): JsonResponse
    {
        return response()->json([
            'status' => 0,
            'message' => 'Invalid request parameters.',
            'errors' => $validator->errors(),
            'data' => [],
        ], 422);
    }

    private function options(string $table, string $label, int $tenantId)
    {
        return DB::table($table)->where('sub_institute_id', $tenantId)
            ->select('id', $label.' as label')->orderBy($label)->get();
    }
}
