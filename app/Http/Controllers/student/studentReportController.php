<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\get_string;

class studentReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        // $tblcustom_fields = DB::table("tblcustom_fields")
        // ->where(["sub_institute_id" => session()->get('sub_institute_id'),"table_name" => "tblstudent"])
        // ->pluck("field_label", "field_name");

        // $tblcustom_fields['enrollment_no'] = 'Enrollment No';
        // $tblcustom_fields['first_name'] = 'First Name';
        // $tblcustom_fields['middle_name'] = 'Middle Name';
        // $tblcustom_fields['last_name'] = 'Last Name';
        // $tblcustom_fields['father_name'] = 'Father Name';
        // $tblcustom_fields['mother_name'] = 'Mother Name';
        // $tblcustom_fields['gender'] = 'Gender';
        // $tblcustom_fields['dob'] = 'Birthdate';
        // $tblcustom_fields['mobile'] = 'Mobile';
        // $tblcustom_fields['mother_mobile'] = 'Mother Mobile';
        // $tblcustom_fields['email'] = 'Email';
        // $tblcustom_fields['username'] = 'Username';
        // $tblcustom_fields['admission_year'] = 'Admission Year';
        // $tblcustom_fields['admission_date'] = 'Admission Date';
        // $tblcustom_fields['city'] = 'City';
        // $tblcustom_fields['state'] = 'State';
        // $tblcustom_fields['address'] = 'Address';
        // $tblcustom_fields['pincode'] = 'Pincode';

        $tblcustom_fields = $this->customFields($request);
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $tblcustom_fields;

        return is_mobile($type, "student/show_student_report", $res, "view");
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
        //$tblcustom_fields['enrollment_no'] = get_string('grno','request');
        //$tblcustom_fields['student_name'] = 'Student Name';
        $tblcustom_fields['roll_no'] = 'Roll No';
        $tblcustom_fields['first_name'] = 'First Name';
        $tblcustom_fields['middle_name'] = 'Middle Name';
        $tblcustom_fields['last_name'] = 'Surname';
        $tblcustom_fields['dob'] = 'Birthdate';
        $tblcustom_fields['mobile'] = 'Mobile';
        $tblcustom_fields['address'] = 'Address';
        $tblcustom_fields['city'] = 'City';
        $tblcustom_fields['state'] = 'State';
        $tblcustom_fields['pincode'] = 'Pincode';        
        $tblcustom_fields['student_mobile'] = get_string('studentmobile','request');
        $tblcustom_fields['mother_mobile'] = 'Mother Mobile';
        $tblcustom_fields['father_name'] = 'Father Name';
        $tblcustom_fields['mother_name'] = 'Mother Name';
        $tblcustom_fields['gender'] = 'Gender';
        $tblcustom_fields['studentbatch'] = 'Batch';
        $tblcustom_fields['email'] = 'Email';
        $tblcustom_fields['username'] = 'Username';
        $tblcustom_fields['uniqueid'] = get_string('uniqueid','request');        
        $tblcustom_fields['admission_year'] = 'Admission Year';
        $tblcustom_fields['admission_date'] = 'Admission Date';
        $tblcustom_fields['religion'] = 'Religion';
        $tblcustom_fields['student_quota'] = 'Student Quota';
        $tblcustom_fields['cast'] = 'Caste';
        $tblcustom_fields['subcast'] = 'Subcaste';
        $tblcustom_fields['bloodgroup'] = 'Blood Group';
        $tblcustom_fields['adharnumber'] = 'Adhar Number';
        $tblcustom_fields['anuualincome'] = get_string('anuualincome','request');
        $tblcustom_fields['image'] = 'Image';
        $tblcustom_fields['house'] = get_string('house','request');
        $tblcustom_fields['amount'] = 'Amount';
        $tblcustom_fields['van'] = 'Van(Shift Wise)';
        $tblcustom_fields['distance'] = 'Distance';
        $tblcustom_fields['optional_subjects'] = 'Optional Subjects';        
        $tblcustom_fields['nationality'] = get_string('nationality','request');
        $tblcustom_fields['place_of_birth'] = get_string('birthplace','request');

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
        $marking_period_id=session()->get('term_id');

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
            'tblstudent.enrollment_no as enrollment_no', 'tblstudent.id as id', 'standard.name as standard', 'division.name as division',// 'academic_section.title as grade',
        ];
        $header = [
            'enrollment_no' => get_string('grno','request'), 'student_name' => 'Student Name', 'standard' => get_string('standard','request'), 'division' => get_string('division','request'),// 'grade' => get_string('academicsection','request'),
            
        ];//,'id' => 'Stu_ID'

        $searchArr = ['_'];
        $replaceArr = [' '];

        if ($request->input('dynamicFields') == '') {
            $array = [
                'tblstudent.enrollment_no as enrollment_no', 'tblstudent.id as id', 'standard.name as standard', 'division.name as division',// 'academic_section.title as grade',
            ];
            $header = [
                'enrollment_no' => get_string('grno','request'), 'student_name' => 'Student Name', 'standard' => get_string('standard','request'), 'division' => get_string('division','request'),// 'grade' => get_string('academicsection','request'),
                
            ];//'id' => 'Stu_ID',
            // $res['status_code'] = 0;
            // $res['message'] = "Please select one checkbox atlease to view report";
            // return is_mobile($type, "student_report.index", $res);
        } else {
            $searchArr1 = ['first_name', 'last_name', 'place_of_birth', 'student_mobile','optional_subjects'];
            $replaceArr1 = ['First Name', 'Surname', get_string('birthplace','request'), get_string('studentmobile','request'),'Optional Subjects'];

            foreach ($request->input('dynamicFields') as $key => $value) {
                if ($value != "bloodgroup" && $value != "van" && $value != "optional_subjects") {
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
            $array[] = 'CONCAT(transport_vehicle.vehicle_number, " (", transport_school_shift.shift_title, ")") as van';
            $array[] = 'tblstudent.place_of_birth as place_of_birth';
            $array[] = 'tblstudent.student_mobile as studentmobile';
            $array[] = 'GROUP_CONCAT(IFNULL(subject.subject_name, "-")) as optional_subjects';
            $array[] = 'batch.title as studentbatch';            
        }
        $array[] = 'concat_ws(" ",tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) AS student_name';

        $student_data = DB::table('tblstudent')
            ->select(DB::raw(implode(',', $array)))
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard',function($join) use($marking_period_id){
                $join->on( 'standard.id', '=', 'tblstudent_enrollment.standard_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('standard.marking_period_id',$marking_period_id);
                // });
            })
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->leftjoin('religion', 'religion.id', '=', 'tblstudent.religion')
            ->leftjoin('house_master', 'house_master.id', '=', 'tblstudent_enrollment.house_id')
            ->leftjoin('student_quota', 'student_quota.id', '=', 'tblstudent_enrollment.student_quota')
            ->leftjoin('caste', 'caste.id', '=', 'tblstudent.cast')
            ->leftjoin('blood_group', 'blood_group.id', '=', 'tblstudent.bloodgroup')
            ->leftjoin('batch', 'tblstudent.studentbatch', '=', 'batch.id')
            ->leftjoin('transport_map_student', 'transport_map_student.student_id', '=', 'tblstudent.id')
            //->leftjoin('transport_vehicle', 'transport_vehicle.id', '=', 'transport_map_student.from_bus_id')
            ->leftjoin('transport_vehicle', function($join) {
                $join->on('transport_vehicle.id', '=', 'transport_map_student.from_bus_id')
                     ->where('transport_vehicle.sub_institute_id', '=', DB::raw('tblstudent_enrollment.sub_institute_id'));
            })
            ->leftjoin('student_optional_subject',function($join){
                $join->on('student_optional_subject.student_id', '=', 'tblstudent.id')->where('student_optional_subject.syear',session()->get('syear')); 
            })            
            ->leftjoin('subject', 'student_optional_subject.subject_id', '=', 'subject.id')
            ->leftJoin('transport_school_shift', 'transport_vehicle.school_shift', '=', 'transport_school_shift.id')
            ->where($extraSearchArray)
            ->whereRaw('tblstudent_enrollment.end_date is NULL')
            ->orderByRaw($extra_order_by)
            ->groupBy('tblstudent.id')
            ->get();

        $res['status_code'] = 1;
        $res['message'] = "Student List";
        $res['student_data'] = $student_data;
        $res['grade_id'] = $grade_id;
        $res['standard_id'] = $standard_id;
        $res['division_id'] = $division_id; 
        $res['data'] = $this->customFields($request);
        $res['headers'] = $header;

        return is_mobile($type, "student/show_student_report", $res, "view");

    }

    public function underDevelopment()
    {
        return view("under_development");
    }

    public function firstpage_school()
    {
        return view("firstpage_school");
    }

    public function firstpage_student()
    {
        return view("firstpage_student");
    }

    public function firstpage_teacher()
    {
        return view("firstpage_teacher");
    }
}
