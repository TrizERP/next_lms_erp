<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class student_certificate_reportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id =  $request->session()->get('sub_institute_id');
        $syear =  $request->session()->get('syear');
        $res['report_types'] = DB::table('template_master')->where(['sub_institute_id'=>$sub_institute_id,'status'=>1])->select('id','module_name')->get()->toArray();

        return is_mobile($type, "student/student_certificate/show", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $res['grade_id'] = $grade_id = $request->input('grade');
        $res['standard_id'] = $standard_id = $request->input('standard');
        $res['division_id'] = $division_id = $request->input('division');
        $res['stu_name'] = $stu_name = $request->input('stu_name');
        $res['mobile'] = $mobile = $request->input('mobile');
        $res['uniqueid'] = $uniqueid = $request->input('uniqueid');
        $res['grno'] = $grno = $request->input('grno');
        $res['certificate_type'] = $certificate_type = $request->input('certificate_type');
        $res['from_date'] = $from_date = $request->input('from_date');
        $res['to_date'] = $to_date = $request->input('to_date');
        
        $res['report_types'] = DB::table('template_master')->where(['sub_institute_id'=>$sub_institute_id,'status'=>1])->select('id','module_name')->get()->toArray();

        // $result = DB::table('certificate_history as sr')
        //     ->join('tblstudent as ts', function ($join) {
        //         $join->whereRaw('sr.STUDENT_ID = ts.id');
        //     })->join('tblstudent_enrollment as se', function ($join) use($syear) {
        //         $join->whereRaw('se.student_id = ts.id')->where('se.syear',$syear);
        //     })->join('standard as s', function ($join) use ($marking_period_id) {
        //         $join->whereRaw('s.id = se.STANDARD_ID');
        //     })->join('division as d', function ($join) {
        //         $join->whereRaw('d.id = se.SECTION_ID');
        //     })->selectRaw("sr.*,ts.enrollment_no, CONCAT_WS(' ',ts.first_name,ts.last_name) AS student_name,
        //         s.name AS standard,d.name AS division,sr.certificate_type AS REQUEST")
        //     ->where('ts.sub_institute_id', $sub_institute_id)
        //     ->where('sr.SYEAR', $syear);

        // if ($from_date != '') {
        //     $result = $result->where('sr.CREATED_AT', '<>', $from_date);
        // }

        // if ($to_date != '') {
        //     $result = $result->where('sr.CREATED_AT', '<>', $to_date);
        // }

        // $result = $result->groupBy('sr.id')->get()->toArray();

        // 03-04-24 by uma 
        $studentLists = SearchStudent($grade_id, $standard_id, $division_id, $sub_institute_id,$syear,"", $stu_name , $uniqueid, $mobile, $grno,"","",1);
        $studentArr = [];
        if(empty($studentLists)){
            $res['status_code'] = 0;
            $res['message'] = "No Student Found";
        }else{
            $studentIds = array_column($studentLists,'id');
      
            $certificateLists = DB::table('certificate_history')
                                ->selectRaw('*,id as certi_id')
                                ->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])
                                ->whereRaw('student_id in ('.implode(',',$studentIds).')')
                                ->when($certificate_type,function($query) use($certificate_type){
                                    $query->where('certificate_type',$certificate_type);
                                });

                        if(isset($from_date) && isset($to_date)){
                            $certificateLists->whereBetween('created_at',[$from_date,$to_date]);
                        }else if(isset($from_date)){
                            $certificateLists->where('created_at', '<>', $from_date);
                        }else if (isset($to_date)){
                            $certificateLists->where('created_at', '<>', $to_date);
                        }

                $certificateLists = $certificateLists->get()->toArray();

            foreach ($certificateLists as $key => $value) {
                $student_id = $value->student_id;
                $filteredData = array_values(array_filter($studentLists, function ($item) use ($student_id) {
                    return $item['id'] == $student_id;
                }));
                $studentData = isset($filteredData[0]) ? $filteredData[0] : '';
                $studentArr[] = array_merge((array)$value, $studentData);
            }

            if(!empty($studentArr)){
                $res['status_code'] = 1;
                $res['message'] = "Success";
            }else{
                $res['status_code'] = 0;
                $res['message'] = "No Certificate Found";
            }
        }
        // echo "<pre>";print_r($studentLists);exit;
      
        $res['result_report'] = $studentArr;

        return is_mobile($type, "student/student_certificate/show", $res, "view");
    }

}
