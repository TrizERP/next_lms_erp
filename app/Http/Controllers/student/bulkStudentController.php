<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\school_setup\academic_sectionModel;
use App\Models\school_setup\bloodgroupModel;
use App\Models\school_setup\casteModel;
use App\Models\school_setup\divisionModel;
use App\Models\school_setup\religionModel;
use App\Models\school_setup\standardModel;
use App\Models\settings\tblcustomfieldsModel;
use App\Models\settings\tblfields_dataModel;
use App\Models\student\houseModel;
use App\Models\student\tblstudentEnrollmentModel;
use App\Models\student\tblstudentModel;
use App\Models\student\tblstudentQuotaModel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use function App\Helpers\is_mobile;
use function App\Helpers\accesslog_json;
use Illuminate\Support\Facades\Storage;
use function App\Helpers\get_string;
use DB;

class bulkStudentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return false|Application|Factory|View|RedirectResponse|string
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $tblcustom_fields = $this->customFields($request);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $tblcustom_fields;

        return is_mobile($type, "student/bulk_student_update", $res, "view");
    }

    public function customFields(Request $request)
    {

        $sub_institute_id = $request->session()->get('sub_institute_id');
        // common field names
        $tblcustom_fields['enrollment_no']['name'] = get_string('grno', 'request');
        $tblcustom_fields['first_name']['name'] = get_string('studentname', 'request');
        $tblcustom_fields['father_name']['name'] = get_string('fathername', 'request');
        $tblcustom_fields['division']['name'] = get_string('division', 'request');
        $tblcustom_fields['student_mobile']['name'] = get_string('studentmobile', 'request');
        $tblcustom_fields['anuualincome']['name'] = get_string('annualincome', 'request');
        $tblcustom_fields['uniqueid']['name'] = get_string('uniqueid', 'request');
        $tblcustom_fields['cast']['name'] =  get_string('cast', 'request');
        $tblcustom_fields['middle_name']['name'] = 'Middle Name';
        $tblcustom_fields['last_name']['name'] = 'Surname';
        $tblcustom_fields['mother_name']['name'] = 'Mother Name';
        $tblcustom_fields['mother_mobile']['name'] = 'Mother Mobile';
        $tblcustom_fields['mobile']['name'] = 'mobile';
        $tblcustom_fields['dob']['name'] = 'Birthdate';
        $tblcustom_fields['gender']['name'] = 'Gender';
        $tblcustom_fields['email']['name'] = 'Email';
        $tblcustom_fields['admission_date']['name'] = 'Admission Date';
        $tblcustom_fields['address']['name'] = 'Address';
        $tblcustom_fields['city']['name'] = 'City';
        $tblcustom_fields['state']['name'] = 'State';
        $tblcustom_fields['pincode']['name'] = 'Pincode';
        $tblcustom_fields['house']['name'] = 'House';
        $tblcustom_fields['bloodgroup']['name'] = 'Blood Group';
        $tblcustom_fields['roll_no']['name'] = 'Roll No';
        $tblcustom_fields['image']['name'] = 'Image';

        // common field type
        $tblcustom_fields['enrollment_no']['type'] = 'textbox';
        $tblcustom_fields['first_name']['type'] = 'textbox';
        $tblcustom_fields['middle_name']['type'] = 'textbox';
        $tblcustom_fields['last_name']['type'] = 'textbox';
        //$tblcustom_fields['standard']['type'] = 'dropdown';
        $tblcustom_fields['division']['type'] = 'dropdown';
        $tblcustom_fields['mobile']['type'] = 'textbox';
        $tblcustom_fields['student_mobile']['type'] = 'textbox';
        $tblcustom_fields['father_name']['type'] = 'textbox';
        $tblcustom_fields['mother_name']['type'] = 'textbox';
        $tblcustom_fields['gender']['type'] = 'dropdown';
        $tblcustom_fields['dob']['type'] = 'date';
        $tblcustom_fields['mother_mobile']['type'] = 'textbox';
        $tblcustom_fields['email']['type'] = 'textbox';
        $tblcustom_fields['admission_date']['type'] = 'date';
        $tblcustom_fields['address']['type'] = 'textbox';
        $tblcustom_fields['city']['type'] = 'textbox';
        $tblcustom_fields['state']['type'] = 'textbox';
        $tblcustom_fields['pincode']['type'] = 'textbox';
        $tblcustom_fields['house']['type'] = 'dropdown';
        $tblcustom_fields['cast']['type'] = 'dropdown';
        $tblcustom_fields['bloodgroup']['type'] = 'dropdown';
        $tblcustom_fields['anuualincome']['type'] = 'textbox';
        $tblcustom_fields['roll_no']['type'] = 'textbox';
        $tblcustom_fields['image']['type'] = 'file';
        $tblcustom_fields['uniqueid']['type'] = 'textbox';
        // not common
        if ($sub_institute_id != 257) {
            // field 
            $tblcustom_fields['religion']['name'] = 'Religion';
            $tblcustom_fields['subcast']['name'] = 'Subcaste';
            $tblcustom_fields['adharnumber']['name'] = 'Adhar Number';
            $tblcustom_fields['dise_uid']['name'] = 'Dise U_ID';
            // field type
            $tblcustom_fields['religion']['type'] = 'dropdown';
            $tblcustom_fields['subcast']['type'] = 'textbox';
            $tblcustom_fields['adharnumber']['type'] = 'textbox';
            $tblcustom_fields['dise_uid']['type'] = 'textbox';
        }

        // only in cn
        if ($sub_institute_id == 257) {
            $tblcustom_fields['student_quota']['name'] = get_string('studentquota', 'request');
            $tblcustom_fields['student_quota']['type'] = 'dropdown';
        }
        if (in_array($sub_institute_id,[254,76])) {
            $tblcustom_fields['student_height']['name'] = 'Student Height';
            $tblcustom_fields['student_height']['type'] = 'textbox';

            $tblcustom_fields['student_weight']['name'] = 'Student Weight';
            $tblcustom_fields['student_weight']['type'] = 'textbox';
        }
        $tblcustoms = tblcustomfieldsModel::select(['field_name', 'field_label', 'field_type'])
            ->where(["status" => "1", "table_name" => "tblstudent"])
            ->whereRaw('(sub_institute_id = ' . $sub_institute_id . ' OR common_to_all = 1)')
            ->where('user_type', "bulk_update")
            ->get()
            ->toArray();
        $customfieldArray = [];

        foreach ($tblcustoms as $key => $value) {
            $tblcustom_fields[$value['field_name']]['name'] = $value['field_label'];
            $tblcustom_fields[$value['field_name']]['type'] = $value['field_type'];
        }
        // echo "<pre>";print_r($tblcustom_fields);exit;

        return $tblcustom_fields;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return void
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }

    public function searchStudent(Request $request)
    {
        $grade_id = $request->input("grade");
        $standard_id = $request->input("standard");
        $division_id = $request->input("division");
        $order_by = $request->input("order_by");
        $page = $request->input("page");
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $extraSearchArray = [];
        $extraSearchArray['tblstudent_enrollment.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $extraSearchArray['tblstudent.status'] = 1;
        if ($grade_id != '') {
            $extraSearchArray['tblstudent_enrollment.grade_id'] = $grade_id;
        }
        if ($standard_id != '') {
            $extraSearchArray['tblstudent_enrollment.standard_id'] = $standard_id;
        }
        if ($division_id != '') {
            $extraSearchArray['tblstudent_enrollment.section_id'] = $division_id;
        }
        if ($order_by != '' && $order_by == 'student_name') {
            $extra_order_by = 'tblstudent.first_name';
        } elseif ($order_by != '' && $order_by == 'standard_id') {
            $extra_order_by = 'standard.sort_order';
        } elseif ($order_by != '' && $order_by == 'enrollment_no') {
            $extra_order_by = 'CONVERT(tblstudent.enrollment_no, SIGNED)';
        } elseif ($order_by != '' && $order_by == 'roll_no') {
            $extra_order_by = 'CAST(tblstudent_enrollment.roll_no AS INT)';
        } elseif ($order_by != '' && $order_by == 'last_name') {
            $extra_order_by = 'tblstudent.last_name';
        } else {
            $extra_order_by = 'tblstudent.first_name';
        }

        $array = [
            'tblstudent_enrollment.standard_id as standard',
            'tblstudent_enrollment.section_id as division',
            'tblstudent_enrollment.grade_id as grade',
            'tblstudent_enrollment.roll_no as roll_no',
            'tblstudent.id as id',
            'student_height_weight.height as student_height',
            'student_height_weight.weight as student_weight',
        ];
        //$header = array('student_name' => 'Student Name');
        $header = [
            'standard' => 'Standard',
            'division' => 'Division',
            'grade' => 'Academic Section',
            'student_name' => 'Student Name',
        ];
        $searchArr = ['_'];
        $replaceArr = [' '];
        if ($request->input('dynamicFields') == '') {
            $res['status_code'] = 0;
            $res['message'] = "Please select one checkbox atlease to update student data.";

            return is_mobile($type, "bulk_student_update.index", $res);
        }
        $tblcustom_fields = $tblcustom_fieldsdata = $this->customFields($request);
        $fields['student_name']['name'] = 'Student Name';
        $fields['student_name']['type'] = 'textbox';
        $tblcustom_fields = array_merge($fields, $tblcustom_fields);

        $keyQuotes = '';

        foreach ($request->input('dynamicFields') as $key => $value) {
            if ($value != 'standard' && $value != 'grade' && $value != 'division' && $value != 'roll_no' && $value != 'student_height' && $value != 'student_weight') {
                $array[] = $value;
            }
            $value1 = str_replace($searchArr, $replaceArr, $value);
            $header[$value] = ucfirst($value1);

            $keyQuotes .= "'" . $value . "',";
        }

        $keyQuotes = rtrim($keyQuotes, ",");

        $headerKeys = array_keys($header);

        foreach ($tblcustom_fields as $key => $value) {
            if (!in_array($key, $headerKeys)) {
                unset($tblcustom_fields[$key]);
            }
        }

        //START Check for class teacher assigned standards
        $extraRaw = " 1 = 1 ";
        $classTeacherStdArr = session()->get('classTeacherStdArr');
        if (isset($classTeacherStdArr)) {
            if (count($classTeacherStdArr) > 0) {
                $extraRaw = "standard.id IN (" . implode(",", $classTeacherStdArr) . ")";
            } else {
                $extraRaw = "standard.id IN (' ')";
            }
        }
        $classTeacherDivArr = session()->get('classTeacherDivArr');
        if (isset($classTeacherDivArr)) {
            if (count($classTeacherDivArr) > 0) {
                $extraRaw .= " and division.id IN (" . implode(",", $classTeacherDivArr) . ")";
            }
        }
        //END Check for class teacher assigned standards

        //$extraRaw .= " and tblstudent.id IN (93452,17777,17509)";

        $student_data = tblstudentModel::select($array)
            ->selectRaw("Concat_ws(' ',tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) as student_name,sum(fees_collect.amount) as total_amount,tblstudent_enrollment.house_id as house,tblstudent_enrollment.updated_on")
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->leftjoin("fees_collect", function ($join) {
                $join->on("fees_collect.sub_institute_id", "=", "tblstudent_enrollment.sub_institute_id")
                    ->on("fees_collect.student_id", "=", "tblstudent_enrollment.student_id");
            })
            ->leftjoin("student_height_weight", function ($join) {
                $join->on("student_height_weight.sub_institute_id", "=", "tblstudent_enrollment.sub_institute_id")
                    ->on("student_height_weight.student_id", "=", "tblstudent_enrollment.student_id");
            })
            ->where($extraSearchArray)
            ->whereRaw('tblstudent_enrollment.end_date is NULL')
            ->whereRaw($extraRaw)
            ->orderByRaw($extra_order_by)
            ->groupBy("tblstudent.id")
            ->get();

        $tblstandard = standardModel::where(["sub_institute_id" => $sub_institute_id])
            ->pluck("name", "id")->toArray();

        // $validDivisions = ['A', 'B', 'C', 'D']; // Define your allowed division names

        // $tbldivision = divisionModel::where("sub_institute_id", $sub_institute_id)
        //     ->whereIn("name", $validDivisions) // Filter only valid names
        //     ->pluck("name", "id")
        //     ->toArray();
        
        // edit by riddhi 13/06/2025
        $tbldivision = DB::table('std_div_map')
            ->join('division', 'division.id', '=', 'std_div_map.division_id')
            ->where("std_div_map.standard_id", $standard_id)
            ->pluck("division.name", "division.id")
            ->toArray();

        // $tbldivision = divisionModel::where(["sub_institute_id" => $sub_institute_id])
        //     ->pluck("name", "id")->toArray();

        $tblgrade = academic_sectionModel::where(["sub_institute_id" => $sub_institute_id])
            ->pluck("title", "id")->toArray();

        $tblcustomsfields_data = tblfields_dataModel::select(['display_text', 'display_value', 'field_name'])
            ->join('tblcustom_fields', 'tblfields_data.field_id', '=', 'tblcustom_fields.id')
            ->where(["status" => "1", "table_name" => "tblstudent"])
            ->whereRaw('(sub_institute_id = ' . $sub_institute_id . ' OR common_to_all = 1)')
            ->get()
            ->toArray();

        $religion = religionModel::pluck("religion_name", "id")->toArray();

        $house = houseModel::where('sub_institute_id', $sub_institute_id)->pluck("house_name", "id")->toArray();

        $student_quota = tblstudentQuotaModel::where('sub_institute_id', $sub_institute_id)->pluck(
            "title",
            "id"
        )->toArray();

        $cast = casteModel::pluck("caste_name", "id")->toArray();

        $bloodgroup = bloodgroupModel::pluck("bloodgroup", "id")->toArray();

        foreach ($tblcustomsfields_data as $fdKey => $fdValue) {
            $fieldsData[$fdValue['field_name']][$fdValue['display_value']] = $fdValue['display_text'];
        }

        $fieldsData['standard'] = $tblstandard;
        $fieldsData['division'] = $tbldivision;
        $fieldsData['grade'] = $tblgrade;
        $fieldsData['gender'] = ['F' => 'F', 'M' => 'M'];
        $fieldsData['religion'] = $religion;
        $fieldsData['house'] = $house;
        $fieldsData['student_quota'] = $student_quota;
        $fieldsData['cast'] = $cast;
        $fieldsData['bloodgroup'] = $bloodgroup;


        $res['status_code'] = 1;
        $res['message'] = "Student List";
        $res['student_data'] = $student_data;
        $res['grade_id'] = $grade_id;
        $res['standard_id'] = $standard_id;
        $res['division_id'] = $division_id;
        $res['data'] = $tblcustom_fieldsdata;
        $res['headers'] = $tblcustom_fields;
        $res['fieldsData'] = $fieldsData;

        return is_mobile($type, "student/bulk_student_update", $res, "view");
    }

    public function bulkUpdate(Request $request)
    {

        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $values = $request->post('values');
        $file = $request->file('values');
        $currentDate = date('Y-m-d');

        $fileName = 'email/' . $sub_institute_id . '_' . $currentDate . '.txt';

        $fileData = "{ User ID: {$request->session()->get('user_id')}, Sub Institute ID: {$request->session()->get('sub_institute_id')}, Current Date: " . date('Y-m-d H:i:s') . " }\n";

        // If $values is an array, convert it to string (e.g., JSON or formatted)
        if (is_array($values)) {
            $fileData .= "Values: " . json_encode($values, JSON_PRETTY_PRINT) . "\n";
        } else {
            $fileData .= "Values: " . $values . "\n";
        }

        if (Storage::exists($fileName)) {
            // Append data to the existing file
            Storage::append($fileName, $fileData);
        } else {
            // Create a new file and put the data in it
            Storage::put($fileName, $fileData);
        }
        $healthData = array();

        foreach ($values as $key => $value) {

            $value['id'] = $key;
            $studentEnrollment = array();

            if (isset($value['standard'])) {
                $studentEnrollment['standard_id'] = $value['standard'];
            }
            if (isset($value['division'])) {
                $studentEnrollment['section_id'] = $value['division'];
            }
            if (isset($value['grade'])) {
                $studentEnrollment['grade_id'] = $value['grade'];
            }
            if (isset($value['student_quota'])) {
                $studentEnrollment['student_quota'] = $value['student_quota'];
            }
            if (isset($value['house'])) {
                $studentEnrollment['house_id'] = $value['house'];
            }
            if (isset($value['roll_no'])) {
                $studentEnrollment['roll_no'] = $value['roll_no'];
            }

            if (count($studentEnrollment) > 0) {

                $studentEnrollment['updated_on'] = date('Y-m-d H:i:s');

                DB::enableQueryLog(); // 2024-08-24 required to convert query into sql for json

                $studentEnrollUpdate = tblstudentEnrollmentModel::where(['student_id' => $key, 'syear' => $syear])->update($studentEnrollment);
                // 2024-08-23
                $queries = DB::getQueryLog(); // 2024-08-24 required to convert query into sql for json
                $sendQuery = end($queries); // 2024-08-24 required to convert query into sql for json 
                accesslog_json($sendQuery, 'update', 'Bulk Update Student', $studentEnrollment);
                //2024-08-23
            }

            $this->updateData($value);

            if (isset($value['student_height'])) {
                $healthData[$key]['student_height'] = $value['student_height'];
            }
            if (isset($value['student_weight'])) {
                $healthData[$key]['student_weight'] = $value['student_weight'];
            }
        }
        // added on 2025-03-07 starts
        if (!empty($healthData)) {
            foreach ($healthData as $student_id => $val) {
                $check = DB::table('student_height_weight')->where([
                    'sub_institute_id' => $sub_institute_id,
                    'syear' => $syear,
                    'student_id' => $student_id
                ])->first();

                if (!empty($check) && isset($check->student_id)) {
                    DB::table('student_height_weight')->where([
                        'sub_institute_id' => $sub_institute_id,
                        'syear' => $syear,
                        'student_id' => $student_id
                    ])->update([
                        'height' => $val['student_height'],
                        'weight' => $val['student_weight']
                    ]);
                } else {
                    DB::table('student_height_weight')->insert([
                        'height' => $val['student_height'],
                        'weight' => $val['student_weight'],
                        'sub_institute_id' => $sub_institute_id,
                        'syear' => $syear,
                        'student_id' => $student_id,
                        'created_on' => now(),
                        'created_by' => $user_id,
                    ]);
                }
            }
        }
        // added on 2025-03-07 ends

        if (isset($file)) {
            foreach ($file as $student_id => $req) {
                if (!isset($request->file('values')[$student_id]['image'])) {

                    $dataCustomFields = tblcustomfieldsModel::select('field_name')->where(['status' => "1", 'table_name' => "tblstudent", 'field_type' => "file"])
                        ->whereRaw('(sub_institute_id = ' . $sub_institute_id . ' OR common_to_all = 1)')
                        ->get()
                        ->toArray();
                }

                $files = array();
                $studentEnrollmentData = array();
                $studentEnrollmentData['id'] = $student_id;

                //For compulsory image field
                foreach ($req as $key1 => $val1) {

                    $files = $request->file('values')[$student_id];
                    if (!isset($request->file('values')[$student_id]['image']) && !in_array($key1, ['father_image', 'mother_image'])) {

                        if (!in_array($key1, $dataCustomFields[0])) {
                            if (isset($files[$key1])) {
                                $file = $files[$key1];
                                $originalname = $file->getClientOriginalName();
                                $ext = \File::extension($originalname);
                                $file_name = $student_id . '.' . $ext;
                                $path = $file->storeAs('public/student/', $file_name);
                                $studentEnrollmentData[$key1] = $file_name;
                            }
                        }
                    } 
                    // added on 10-07-2025 by uma
                    elseif (in_array($key1, ['father_image', 'mother_image'])) {
                        // echo "<pre>";print_r($files[$key1]);exit;
                        if ($key1=="father_image") {
                            $prefix = "father";
                        }

                        if ($key1=="mother_image") {
                            $prefix = "mother";
                        }
                        $file = $files[$key1];
                        $originalname = $file->getClientOriginalName();
                        $file_size = $file->getSize();
                        $ext = File::extension($originalname);
                        // $file_name = $name.'.'.$ext;
                        $parent_image =  $prefix . '_' . $student_id . '_' . $sub_institute_id . '.' . $ext;
                        // have to use this for image entering
                        $file_path = 'public/parents_image/' . $parent_image;
                        if (Storage::disk('digitalocean')->exists($file_path)) {
                            Storage::disk('digitalocean')->delete($file_path);
                        }

                        Storage::disk('digitalocean')->putFileAs('public/parents_image/', $file, $parent_image, 'public');
                        $studentEnrollmentData[$key1] = $parent_image;
                    } else {
                        if (isset($files[$key1])) {
                            $file = $files[$key1];
                            $originalname = $file->getClientOriginalName();
                            $ext = \File::extension($originalname);
                            $file_name = $student_id . '.' . $ext;
                            $path = $file->storeAs('public/student/', $file_name);
                            $studentEnrollmentData[$key1] = $file_name;
                        }
                    }
                }
                if (!isset($request->file('values')[$student_id]['image']) && !in_array($key1, ['father_image', 'mother_image'])) {
                    //for custom image fields
                    foreach ($dataCustomFields as $key => $value) {
                        foreach ($dataCustomFields as $key => $value) {
                            $files = $request->file('values')[$student_id];

                            if (isset($files[$value['field_name']])) {
                                $file = $files[$value['field_name']];
                                $originalname = $file->getClientOriginalName();
                                $name = $value['field_name'] . "_" . $student_id . "_" . date('YmdHis') . '_' . $originalname;

                                $file_name = $name;
                                $path = $file->storeAs('public/student/', $file_name);
                                $studentEnrollmentData[$value['field_name']] = $file_name;
                            }
                        }
                    }
                } elseif(!in_array($key1, ['father_image', 'mother_image'])) {
                    $files = $request->file('values')[$student_id]['image'];
                    $file = $request->file('values')[$student_id]['image'];
                    $originalname = $file->getClientOriginalName();
                    $name = $student_id . "_" . date('YmdHis') . '_' . $originalname;

                    $file_name = $name;
                    $path = $file->storeAs('public/student/', $file_name);
                    $studentEnrollmentData['image'] = $file_name;
                }

                // dd($studentEnrollmentData);
                $this->updateData($studentEnrollmentData);
            }
        }

        $res['status_code'] = 1;
        $res['message'] = "Student updated successfully.";

        return is_mobile($type, "bulk_student_update.index", $res);
    }

    public function updateData($data)
    {
        $newRequest = $data;
        $student_id = $newRequest['id'];
        // $sub_institute_id = $request->session()->get('sub_institute_id');
        // $finalArray['sub_institute_id'] = $sub_institute_id;
        // $finalArray['password'] = md5('student');
        // $finalArray['status'] = 1;
        // unset($newRequest['student_image']);
        $finalArray = array();
        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'grade' && $key != 'standard' && $key != 'division' && $key != 'student_quota' && $key != 'id' && $key != 'house' && $key != 'updateData'  && $key != 'roll_no' && $key != 'student_height' && $key != 'student_weight') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                if ($key == 'dob' || $key == 'admission_date') {
                    if (isset($value))
                        $value = date('Y-m-d', strtotime($value));
                    else
                        $value = null;
                }
                $finalArray[$key] = $value;
            }
        }
        // dd($finalArray);
        if (count($finalArray) > 0) {
            $finalArray['updated_on'] = date('Y-m-d H:i:s');
            DB::enableQueryLog(); // 2024-08-24 required to convert query into sql for json
            $data = tblstudentModel::where(['id' => $student_id])->update($finalArray);
            // 2024-08-23
            $queries = DB::getQueryLog(); // 2024-08-24 required to convert query into sql for json
            $sendQuery = end($queries); // 2024-08-24 required to convert query into sql for json 
            accesslog_json($sendQuery, 'update', 'Bulk Update Student', $finalArray);
            //2024-08-23
        }
        return $data;
    }
}
