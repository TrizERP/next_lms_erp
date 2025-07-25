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

        $type= $request->type;
        if(in_array($type,["API","JSON"])){
            $sub_institute_id = $request->sub_institute_id;
        }

        $tblcustoms = DB::table("tblcustom_fields")
        ->whereRaw("status=1 AND (common_to_all= 1 or sub_institute_id=$sub_institute_id) AND is_deleted != 'Y'")
        ->where('user_type','student')
        // ->when($sub_institute_id==257,function($q) use($sub_institute_id){
        //     $q->whereRaw('field_message NOT IN ('.$sub_institute_id.')');
        // })
        ->whereRaw('FIND_IN_SET('.$sub_institute_id.', field_message) = 0')
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

        if(in_array($type,["API","JSON"])){
            $sub_institute_id = $request->sub_institute_id;
                $syear = $request->syear;
        }

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
            'last_name' => 'tblstudent.last_name',
        ];
        $defaultOrderBy = 'tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name';

        // Map dynamic fields and headers
        $res['dynamicFields'] = $dynamicFields = $request->input('dynamicFields') ?? [];
        $searchArr1 = ['first_name', 'last_name', 'place_of_birth', 'student_mobile','optional_subjects','admission_year'];
        $replaceArr1 = ['First Name', 'Surname', get_string('birthplace','request',$sub_institute_id), get_string('studentmobile','request',$sub_institute_id),'Optional Subjects','Fees Year'];

        $array = [
            'tblstudent.enrollment_no as enrollment_no',
            'tblstudent_enrollment.roll_no as roll_no',
            'tblstudent.id as id',
            'standard.name as standard',
            'division.name as division',
        ];
        $header = [
            'enrollment_no' => get_string('grno', 'request',$sub_institute_id),
            'student_name' => 'Student Name',
            'standard' => get_string('standard', 'request',$sub_institute_id),
            'division' => get_string('division', 'request',$sub_institute_id),
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
            // ->when($sub_institute_id==257,function($q) use($sub_institute_id){
            //     $q->whereRaw('field_message NOT IN ('.$sub_institute_id.')');
            // })
            ->whereRaw('FIND_IN_SET('.$sub_institute_id.', field_message) = 0')
            ->first();

            if(!empty($customDetails) && !in_array($fielValue,["student_name","optional_subject"])){
                $array[] = $customDetails->table_name.".".$fielValue." as ".str_ireplace(" ","_",$customDetails->field_label);
                $makeKey = strtolower(str_replace(" ","_",$customDetails->field_label));
                $header[$makeKey] = ucfirst(str_replace(['_'], [' '], str_replace($searchArr1, $replaceArr1, $customDetails->field_label)));
            }else if($fielValue=="academic_year"){
                $array[] = "academic_section.title as academic_year";
                $header[$fielValue] = ucfirst(str_replace(['_'], [' '], str_replace($searchArr1, $replaceArr1, $fielValue)));
            }
            else if($fielValue=="optional_subject"){
                $array[] = "GROUP_CONCAT(DISTINCT subject.subject_name) as optional_subject";
                if($sub_institute_id==254){
                    $header['optional_subject4']="Optional Subject 4";
                    $header['optional_subject5']="Optional Subject 5";
                    $header['optional_subject6']="Optional Subject 6";
                }else{
                    $header[$fielValue] = ucfirst(str_replace(['_'], [' '], str_replace($searchArr1, $replaceArr1, $fielValue)));
                }
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
            ->leftJoin('batch', function($join) use ($syear) {
                $join->on('tblstudent.studentbatch', '=', 'batch.id')
                    ->where('batch.syear', '=', $syear);
            })
            ->leftJoin('transport_map_student', function($join) use ($syear) {
                $join->on('transport_map_student.student_id', '=', 'tblstudent.id')
                    ->where('transport_map_student.syear', '=', $syear);
            })
            ->leftJoin('admission_enquiry', 'tblstudent.mobile', '=', 'admission_enquiry.mobile')
            ->leftJoin('student_height_weight', function($join) use ($syear) {
                $join->on('tblstudent.id', '=', 'student_height_weight.student_id')
                    ->where('student_height_weight.syear', '=', $syear);
            })
            ->leftJoin('transport_vehicle', function($join) use ($sub_institute_id) {
                $join->on('transport_vehicle.id', '=', 'transport_map_student.from_bus_id')
                    ->where('transport_vehicle.sub_institute_id', '=', $sub_institute_id);
            })
            ->leftJoin('student_optional_subject', function($join) use($syear,$sub_institute_id){
                $join->on('student_optional_subject.student_id', '=', 'tblstudent.id')
                    ->where('student_optional_subject.syear', $syear)
                    ->where('student_optional_subject.sub_institute_id', $sub_institute_id); // added on 02-07-2025 by uma for conflict with hills and mmis
            })
            ->leftJoin('subject', 'student_optional_subject.subject_id', '=', 'subject.id')
            ->leftJoin('transport_school_shift', 'transport_vehicle.school_shift', '=', 'transport_school_shift.id')
            ->leftJoin('tblstudent_bank_detail', 'tblstudent_bank_detail.student_id', '=', 'tblstudent.id')
            ->leftJoin('tblstudent_tc_details', function($join) use($syear,$sub_institute_id){
                $join->on('tblstudent_tc_details.student_id', '=', 'tblstudent.id')
                    ->where('tblstudent_tc_details.syear', $syear)
                    ->where('tblstudent_tc_details.sub_institute_id', $sub_institute_id);
            })// adddd on 25-07-2025 by uma for conflict with tc details
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
            $student_dataArr = [];
            if($sub_institute_id==254){
                foreach ($student_data as $key => $value) {
                    // optional subject level wise 
                    if(isset($value->optional_subject)){
                        $explodeSub = explode(',',$value->optional_subject);
                        $value->optional_subject4 =  $value->optional_subject5= $value->optional_subject6 = [];
                        foreach ($explodeSub as $keys => $subName) {
                            $getLevel = DB::table('subject as s')
                                ->join('student_optional_subject as sos', 'sos.subject_id', '=', 's.id')
                                ->where('sos.syear',$syear)
                                ->where('sos.student_id', $value->id)
                                ->where('s.subject_name', $subName)
                                ->first();
                        
                            if ($getLevel) {
                                if ($getLevel->level == 4 || $getLevel->level==null || $getLevel->level=="") {
                                    $value->optional_subject4[] = $getLevel->subject_name;
                                } 
                                if ($getLevel->level == 5) {
                                    $value->optional_subject5[] = $getLevel->subject_name;
                                }
                                if ($getLevel->level == 6) {
                                    $value->optional_subject6[] = $getLevel->subject_name;
                                }
                            }
                        }
                        // convert into string 
                       $value->optional_subject4 = implode(',',$value->optional_subject4) ?? [];     
                       $value->optional_subject5 = implode(',',$value->optional_subject5) ?? [];     
                       $value->optional_subject6 = implode(',',$value->optional_subject6) ?? [];     
                    }
                    //  ends level

                 $student_dataArr[$key] = $value;
                }
            }else{
                $student_dataArr = $student_data;
            }

        $res['status_code'] = 1;
        $res['message'] = "Student List";
        $res['student_data'] = $student_dataArr;
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
