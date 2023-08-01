<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\documentTypeModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\get_string;

class InactiveStudentReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $tblcustom_fields = $this->customFields($request);
        $res['data'] = $tblcustom_fields;
        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "student/inactive_student_report", $res, "view");
    }

  public function bulkIndex(Request $request)
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
        $tblcustom_fields['enrollment_no'] = 'Gr No';
        // $tblcustom_fields['student_name'] = 'Student Name';
        $tblcustom_fields['first_name'] = 'First Name';
        $tblcustom_fields['middle_name'] = 'Middle Name';
        $tblcustom_fields['last_name'] = 'Surname';
        $tblcustom_fields['mobile'] = 'Mobile';
        $tblcustom_fields['father_name'] = 'Father Name';
        $tblcustom_fields['mother_name'] = 'Mother Name';
        $tblcustom_fields['gender'] = 'Gender';
        $tblcustom_fields['dob'] = 'Birthdate';
        $tblcustom_fields['mother_mobile'] = 'Mother Mobile';
        $tblcustom_fields['email'] = 'Email';
        $tblcustom_fields['username'] = 'Username';
        $tblcustom_fields['admission_year'] = 'Admission Year';
        $tblcustom_fields['admission_date'] = 'Admission Date';
        $tblcustom_fields['address'] = 'Address';
        $tblcustom_fields['city'] = 'City';
        $tblcustom_fields['state'] = 'State';
        $tblcustom_fields['pincode'] = 'Pincode';
        $tblcustom_fields['religion'] = 'Religion';
        $tblcustom_fields['student_quota'] = 'Student Quota';
        $tblcustom_fields['cast'] = 'Caste';
        $tblcustom_fields['subcast'] = 'Subcaste';
        $tblcustom_fields['bloodgroup'] = 'Blood Group';
        $tblcustom_fields['adharnumber'] = 'Adhar Number';
        $tblcustom_fields['anuualincome'] = 'Annual Income';
        $tblcustom_fields['roll_no'] = 'Roll No';
        $tblcustom_fields['image'] = 'Image';
        $tblcustom_fields['house'] = 'House';
        $tblcustom_fields['van'] = 'Van';

        $tblcustoms = DB::table("tblcustom_fields")
            ->where(["status" => "1", "table_name" => "tblstudent"])
            ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1)')
            ->pluck("field_label", "field_name");

        $customfieldArray = [];
        foreach ($tblcustoms as $key => $value) {
            $customfieldArray[$key] = $value;
        }


        return array_merge($tblcustom_fields, $customfieldArray);

    }


    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $grade_id = $request->input("grade");
        $standard_id = $request->input("standard");
        $division_id = $request->input("division");
        $order_by = $request->input("order_by");
        $page = $request->input("page");
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $marking_period_id = session()->get('term_id');

        $extra_order_by = '';
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
            'standard.name as standard', 'division.name as division', 'academic_section.title as grade',
            'tblstudent.id as id',
        ];
        $header = [
            'standard'     => 'Standard', 'division' => 'Division', 'grade' => 'Academic Section',
            'student_name' => 'Student Name',
        ];
        $searchArr = ['_'];
        $replaceArr = [' '];

        if ($request->input('dynamicFields') == '') {
            $array = [
                'standard.name as "'.get_string('standard' , 'request').'"', 'division.name as "'.get_string('division' , 'request').'"', 'academic_section.title as grade',
                'tblstudent.id as id',
            ];
            $header = [
                'standard'     => 'Standard', 'division' => 'Division', 'grade' => 'Academic Section',
                'student_name' => 'Student Name',
            ];
            // $res['status_code'] = 0;
            // $res['message'] = "Please select one checkbox atlease to view report";
            // return is_mobile($type, "student_report.index", $res);
        } else {
            $searchArr1 = ['enrollment_no', 'first_name', 'last_name'];
            $replaceArr1 = ['Gr No', 'First Name', 'Surname'];
            foreach ($request->input('dynamicFields') as $key => $value) {
                if ($value != "bloodgroup" && $value != "van") {
                    $array[] = $value;
                }
                $value1 = str_replace($searchArr1, $replaceArr1, $value);
                $value2 = str_replace($searchArr, $replaceArr, $value1);

                $header[$value] = ucfirst($value2);
            }

            $array[] = 'religion.religion_name as religion';
            $array[] = 'house_master.house_name as house';
            $array[] = 'student_quota.title as student_quota';
            $array[] = 'caste.caste_name as cast';
            $array[] = 'blood_group.bloodgroup as bloodgroup';
            $array[] = 'transport_vehicle.title as van';
        }
        $array[] = 'concat_ws(" ",tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) AS "'.get_string('studentname' , 'request').'"';

        $result = DB::table('tblstudent')
            ->select(DB::raw(implode(',', $array)))
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard',function($join) use($marking_period_id){
                $join->on('standard.id', '=', 'tblstudent_enrollment.standard_id')->when($marking_period_id,function($query) use($marking_period_id){
                    $query->where('standard.marking_period_id',$marking_period_id);
                });
            })
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->leftjoin('religion', 'religion.id', '=', 'tblstudent.religion')
            ->leftjoin('house_master', 'house_master.id', '=', 'tblstudent_enrollment.house_id')
            ->leftjoin('student_quota', 'student_quota.id', '=', 'tblstudent_enrollment.student_quota')
            ->leftjoin('caste', 'caste.id', '=', 'tblstudent.cast')
            ->leftjoin('blood_group', 'blood_group.id', '=', 'tblstudent.bloodgroup')
            ->leftjoin('transport_map_student', 'transport_map_student.student_id', '=', 'tblstudent.id')
            ->leftjoin('transport_vehicle', 'transport_vehicle.id', '=', 'transport_map_student.from_bus_id')
            ->where($extraSearchArray)
            ->whereRaw('tblstudent_enrollment.end_date IS NOT NULL')
            ->orderByRaw('tblstudent.id')
            ->get();
        

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['result_report'] = $result;
        $res['grade_id'] = $grade_id;
        $res['standard_id'] = $standard_id;
        $res['division_id'] = $division_id;
// return $res;exit;
        return is_mobile($type, "student/inactive_student_report", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return void
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }
}
