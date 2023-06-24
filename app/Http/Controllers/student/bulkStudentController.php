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
use Illuminate\Support\Facades\Storage;
use function App\Helpers\is_mobile;

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

        $tblcustom_fields['enrollment_no']['name'] = 'Gr No';
        $tblcustom_fields['first_name']['name'] = 'Student Name';
        $tblcustom_fields['middle_name']['name'] = 'Middle Name';
        $tblcustom_fields['last_name']['name'] = 'Surname';
        //$tblcustom_fields['standard']['name'] = 'Standard';
        $tblcustom_fields['division']['name'] = 'Division';
        //$tblcustom_fields['grade']['name'] = 'Academic Section';
        $tblcustom_fields['mobile']['name'] = 'Mobile';
        $tblcustom_fields['father_name']['name'] = 'Father Name';
        $tblcustom_fields['mother_name']['name'] = 'Mother Name';
        $tblcustom_fields['gender']['name'] = 'Gender';
        $tblcustom_fields['dob']['name'] = 'Birthdate';
        $tblcustom_fields['mother_mobile']['name'] = 'Mother Mobile';
        $tblcustom_fields['email']['name'] = 'Email';
        $tblcustom_fields['username']['name'] = 'Username';
        //$tblcustom_fields['username']['name'] = 'Username';
        //$tblcustom_fields['admission_year']['name'] = 'Admission Year';
        $tblcustom_fields['admission_date']['name'] = 'Admission Date';
        $tblcustom_fields['address']['name'] = 'Address';
        $tblcustom_fields['city']['name'] = 'City';
        $tblcustom_fields['state']['name'] = 'State';
        $tblcustom_fields['pincode']['name'] = 'Pincode';
        $tblcustom_fields['religion']['name'] = 'Religion';
        $tblcustom_fields['house']['name'] = 'House';
        $tblcustom_fields['student_quota']['name'] = 'Student Quota';
        $tblcustom_fields['cast']['name'] = 'Caste';
        $tblcustom_fields['subcast']['name'] = 'Subcaste';
        $tblcustom_fields['bloodgroup']['name'] = 'Blood Group';
        $tblcustom_fields['adharnumber']['name'] = 'Adhar Number';
        $tblcustom_fields['anuualincome']['name'] = 'Annual Income';
        $tblcustom_fields['roll_no']['name'] = 'Roll Number';
        $tblcustom_fields['image']['name'] = 'Image';
        $tblcustom_fields['uniqueid']['name'] = 'Unique ID';

        $tblcustom_fields['enrollment_no']['type'] = 'textbox';
        $tblcustom_fields['first_name']['type'] = 'textbox';
        $tblcustom_fields['middle_name']['type'] = 'textbox';
        $tblcustom_fields['last_name']['type'] = 'textbox';
        //$tblcustom_fields['standard']['type'] = 'dropdown';
        $tblcustom_fields['division']['type'] = 'dropdown';
        //$tblcustom_fields['grade']['type'] = 'dropdown';
        $tblcustom_fields['mobile']['type'] = 'textbox';
        $tblcustom_fields['father_name']['type'] = 'textbox';
        $tblcustom_fields['mother_name']['type'] = 'textbox';
        $tblcustom_fields['gender']['type'] = 'dropdown';
        $tblcustom_fields['dob']['type'] = 'date';
        $tblcustom_fields['mother_mobile']['type'] = 'textbox';
        $tblcustom_fields['email']['type'] = 'textbox';
        $tblcustom_fields['username']['type'] = 'textbox';
        //$tblcustom_fields['username']['type'] = 'textbox';
        //$tblcustom_fields['admission_year']['type'] = 'textbox';
        $tblcustom_fields['admission_date']['type'] = 'date';
        $tblcustom_fields['address']['type'] = 'textbox';
        $tblcustom_fields['city']['type'] = 'textbox';
        $tblcustom_fields['state']['type'] = 'textbox';
        $tblcustom_fields['pincode']['type'] = 'textbox';
        $tblcustom_fields['religion']['type'] = 'dropdown';
        $tblcustom_fields['house']['type'] = 'dropdown';
        $tblcustom_fields['student_quota']['type'] = 'dropdown';
        $tblcustom_fields['cast']['type'] = 'dropdown';
        $tblcustom_fields['subcast']['type'] = 'textbox';
        $tblcustom_fields['bloodgroup']['type'] = 'dropdown';
        $tblcustom_fields['adharnumber']['type'] = 'textbox';
        $tblcustom_fields['anuualincome']['type'] = 'textbox';
        $tblcustom_fields['roll_no']['type'] = 'textbox';
        $tblcustom_fields['image']['type'] = 'file';
        $tblcustom_fields['uniqueid']['type'] = 'textbox';

        $tblcustoms = tblcustomfieldsModel::select(['field_name', 'field_label', 'field_type'])
            ->where(["status" => "1", "table_name" => "tblstudent"])
            ->whereRaw('(sub_institute_id = ' . $sub_institute_id . ' OR common_to_all = 1)')
            ->get()
            ->toArray();
        $customfieldArray = [];

        foreach ($tblcustoms as $key => $value) {
            $tblcustom_fields[$value['field_name']]['name'] = $value['field_label'];
            $tblcustom_fields[$value['field_name']]['type'] = $value['field_type'];
        }

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
            $extra_order_by = 'CAST(tblstudent.roll_no AS INT)';
        } else {
            $extra_order_by = 'tblstudent.first_name';
        }

        $array = [
            'tblstudent_enrollment.standard_id as standard',
            'tblstudent_enrollment.section_id as division',
            'tblstudent_enrollment.grade_id as grade', 'tblstudent.id as id',
        ];
        //$header = array('student_name' => 'Student Name');
        $header = [
            'standard' => 'Standard', 'division' => 'Division', 'grade' => 'Academic Section',
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
            if ($value != 'standard' && $value != 'grade' && $value != 'division') {
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
            ->selectRaw("Concat_ws(' ',tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) as student_name,sum(fees_collect.amount) as total_amount,tblstudent_enrollment.house_id as house")
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->leftjoin("fees_collect", function ($join) {
                $join->on("fees_collect.sub_institute_id", "=", "tblstudent_enrollment.sub_institute_id")
                    ->on("fees_collect.student_id", "=", "tblstudent_enrollment.student_id");
            })
            ->where($extraSearchArray)
            ->whereRaw('tblstudent_enrollment.end_date is NULL')
            ->whereRaw($extraRaw)
            ->orderByRaw($extra_order_by)
            ->groupBy("tblstudent.id")
            ->get();

        $tblstandard = standardModel::where(["sub_institute_id" => $sub_institute_id])
            ->pluck("name", "id")->toArray();

        $tbldivision = divisionModel::where(["sub_institute_id" => $sub_institute_id])
            ->pluck("name", "id")->toArray();

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

        $student_quota = tblstudentQuotaModel::where('sub_institute_id', $sub_institute_id)->pluck("title",
            "id")->toArray();

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

    public function bulkUpdate(Request $request) {

        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $values = $request->post('values');
        $file = $request->file('values');
        //dd($file);

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

            if (count($studentEnrollment) > 0) {

                $studentEnrollment['updated_on'] = date('Y-m-d H:i:s');
                tblstudentEnrollmentModel::where(['student_id' => $key, 'syear' => $syear])->update($studentEnrollment);
            }

            $this->updateData($value);

        }


        if (isset($file)) {
            foreach ($file as $student_id => $req) {

                $dataCustomFields = tblcustomfieldsModel::select('field_name')->where(['status' => "1", 'table_name' => "tblstudent", 'field_type' => "file"])
                    ->whereRaw('(sub_institute_id = ' . $sub_institute_id . ' OR common_to_all = 1)')
                    ->get()
                    ->toArray();
                $files = array();
                $studentEnrollmentData = array();
                $studentEnrollmentData['id'] = $student_id;

                //For compulsory image field
                foreach($req as $key1 => $val1)
                {
                    $files = $request->file('values')[$student_id];
                    if( !in_array($key1,$dataCustomFields[0]) )
                    {
                        if (isset($files[$key1])) {
                            $file = $files[$key1];
                            $originalname = $file->getClientOriginalName();
                            $ext = \File::extension($originalname);
                            $file_name = $student_id . '.' . $ext;
                            //$path = $file->storeAs('public/student/', $file_name);
                            $path = Storage::disk('digitalocean')->putFileAs('public/student/', $file, $file_name, 'public');
                            $studentEnrollmentData[$key1] = $file_name;
                        }
                    }
                }

                //for custom image fields
                foreach ($dataCustomFields as $key => $value) {
                    $files = $request->file('values')[$student_id];

                    if (isset($files[$value['field_name']])) {
                        $file = $files[$value['field_name']];
                        $originalname = $file->getClientOriginalName();
                        $name = $value['field_name'] . "_" . $student_id . "_" . date('YmdHis') . '_' . $originalname;

                        $file_name = $name;
                        //$path = $file->storeAs('public/student/', $file_name);
                        $path = Storage::disk('digitalocean')->putFileAs('public/student/', $file, $file_name, 'public');
                        $studentEnrollmentData[$value['field_name']] = $file_name;
                    }

                }
                // dd($studentEnrollmentData);
                $this->updateData($studentEnrollmentData);
            }
        }

        $res['status_code'] = 1;
        $res['message'] = "Student updated successfully.";

        return is_mobile($type, "bulk_student_update.index", $res);

    }

    public function updateData($data) {
        $newRequest = $data;
        $student_id = $newRequest['id'];
        // $sub_institute_id = $request->session()->get('sub_institute_id');
        // $finalArray['sub_institute_id'] = $sub_institute_id;
        // $finalArray['password'] = md5('student');
        // $finalArray['status'] = 1;
        // unset($newRequest['student_image']);
        $finalArray = array();
        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'grade' && $key != 'standard' && $key != 'division' && $key != 'student_quota' && $key != 'id' && $key != 'house') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                if ( $key == 'dob' || $key == 'admission_date') {
                    if(isset($value))
                        $value = date('Y-m-d', strtotime($value));
                    else
                        $value = null;
                }
                $finalArray[$key] = $value;
            }
        }
        // dd($finalArray);
        if(count($finalArray) > 0){
            $finalArray['updated_on'] = date('Y-m-d H:i:s');
            $data = tblstudentModel::where(['id' => $student_id])->update($finalArray);
        }
        return $data;

    }
}
