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
        $tblcustom_fields = $this->customFields($request);
        // echo "<pre>";print_r($tblcustom_fields);exit;
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

        $tblcustoms = DB::table("tblcustom_fields")
        ->whereRaw("status=1 AND (common_to_all= 1 or sub_institute_id=$sub_institute_id) AND is_deleted != 'Y'")
        ->where('user_type','student')
        ->orderByRaw('tab_sort_order,sort_order')
        ->get()->toArray();    
        
        $headerType =[];
        foreach ($tblcustoms as $key => $value) {
            $headerType[$value->column_header][]=$value;
        }
        return $headerType;
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

       // Define default values and mappings
        $extraSearchArray = [
            'tblstudent_enrollment.sub_institute_id' => $sub_institute_id,
            'tblstudent_enrollment.syear' => $syear,
            'tblstudent.status' => 1,
        ];
        $searchFieldsMapping = [
            'standard_id' => 'standard.sort_order',
            'enrollment_no' => 'CONVERT(tblstudent.enrollment_no, SIGNED)',
            'roll_no' => 'CAST(tblstudent_enrollment.roll_no AS INT)',
        ];
        $defaultOrderBy = 'tblstudent.first_name';

        // Map dynamic fields and headers
        $res['dynamicFields'] = $dynamicFields = $request->input('dynamicFields') ?? [];
        $searchArr1 = ['first_name', 'last_name', 'place_of_birth', 'student_mobile','optional_subjects','admission_year'];
        $replaceArr1 = ['First Name', 'Surname', get_string('birthplace','request'), get_string('studentmobile','request'),'Optional Subjects','Fees Year'];

        $array = [
            'tblstudent.enrollment_no as enrollment_no',
            'tblstudent_enrollment.roll_no as roll_no',
            'tblstudent.id as id',
            'standard.name as standard',
            'division.name as division',
        ];
        $header = [
            'enrollment_no' => get_string('grno', 'request'),
            'student_name' => 'Student Name',
            'standard' => get_string('standard', 'request'),
            'division' => get_string('division', 'request'),
        ];

        foreach ($dynamicFields as $field) {
            // if (!in_array($field, ["bloodgroup", "van", "optional_subjects", "roll_no","student_name","academic_year","religion_name","father_name","gender","mobile","email"])) {
            //     $array[] = $field;
            // }
            $seprateValue  = explode("/",$field);
            $fielValue = $seprateValue[0];
            $fieldId = $seprateValue[1];

          $customDetails = DB::table("tblcustom_fields")
            ->whereRaw("status=1 AND (common_to_all= 1 or sub_institute_id=$sub_institute_id) AND is_deleted != 'Y'")
            ->where('id',$fieldId)
            ->where('user_type','student')
            ->first();

            if(!empty($customDetails) && !in_array($fielValue,["student_name"])){
                $array[] = $customDetails->table_name.".".$fielValue." as ".str_ireplace(" ","_",$customDetails->field_label);
                $makeKey = strtolower(str_replace(" ","_",$customDetails->field_label));
                $header[$makeKey] = ucfirst(str_replace(['_'], [' '], str_replace($searchArr1, $replaceArr1, $customDetails->field_label)));
            }else if($fielValue=="academic_year"){
                $array[] = "academic_section.title as academic_year";
                $header[$fielValue] = ucfirst(str_replace(['_'], [' '], str_replace($searchArr1, $replaceArr1, $fielValue)));
            }
            
        }

        // Additional conditions for ordering
        $orderField = $searchFieldsMapping[$order_by] ?? $defaultOrderBy;
        $extra_order_by = $orderField ?? $defaultOrderBy;

        // Add additional fields to $array based on $sub_institute_id
        if ($sub_institute_id == 254) {
            $array[] = 'IF(tblstudent.admission_year = 2019, YEAR(tblstudent.admission_date), tblstudent.admission_year) AS fees_year';
        }

        // Concatenated student name
        $array[] = 'CONCAT_WS(" ", tblstudent.first_name, tblstudent.middle_name, tblstudent.last_name) AS student_name';
      
        // Query
        $student_data = DB::table('tblstudent')
            ->select(DB::raw(strtolower(implode(',', $array))))
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->leftJoin('religion', 'religion.id', '=', 'tblstudent.religion')
            ->leftJoin('house_master', 'house_master.id', '=', 'tblstudent_enrollment.house_id')
            ->leftJoin('student_quota', 'student_quota.id', '=', 'tblstudent_enrollment.student_quota')
            ->leftJoin('religion as r', 'r.id', '=', 'tblstudent.religion')
            ->leftJoin('caste', 'caste.id', '=', 'tblstudent.cast')
            ->leftJoin('blood_group', 'blood_group.id', '=', 'tblstudent.bloodgroup')
            ->leftJoin('batch', 'tblstudent.studentbatch', '=', 'batch.id')
            ->leftJoin('transport_map_student', 'transport_map_student.student_id', '=', 'tblstudent.id')
            ->leftJoin('admission_enquiry', 'tblstudent.mobile', '=', 'admission_enquiry.mobile')
            ->leftJoin('transport_vehicle', function($join) use ($sub_institute_id) {
                $join->on('transport_vehicle.id', '=', 'transport_map_student.from_bus_id')
                    ->where('transport_vehicle.sub_institute_id', '=', $sub_institute_id);
            })
            ->leftJoin('student_optional_subject', function($join){
                $join->on('student_optional_subject.student_id', '=', 'tblstudent.id')
                    ->where('student_optional_subject.syear', session()->get('syear'));
            })
            ->leftJoin('subject', 'student_optional_subject.subject_id', '=', 'subject.id')
            ->leftJoin('transport_school_shift', 'transport_vehicle.school_shift', '=', 'transport_school_shift.id')
            ->where($extraSearchArray)
            ->when($request->grade,function($q) use($request){
                $q->where('tblstudent_enrollment.grade_id',$request->grade);
            })
            ->when($request->standard,function($q) use($request){
                $q->where('tblstudent_enrollment.standard_id',$request->standard);
            })
            ->when($request->division,function($q) use($request){
                $q->where('tblstudent_enrollment.section_id',$request->division);
            })
            ->whereNull('tblstudent_enrollment.end_date')
            ->orderByRaw($extra_order_by)
            ->groupBy('tblstudent.id')
            ->get();

            // echo "<pre>";print_r($header);exit;
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
