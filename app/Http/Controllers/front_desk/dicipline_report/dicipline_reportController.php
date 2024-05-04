<?php

namespace App\Http\Controllers\front_desk\dicipline_report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class dicipline_reportController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }

        $data['data'] = [];
        $type = $request->input('type');

        return is_mobile($type, "front_desk/dicipline_report/show", $data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function create(Request $request)
    {

        // $result = DB::table("tblstudent as s")
        //     ->join('tblstudent_enrollment as se', function ($join) {
        //         $join->whereRaw("se.student_id = s.id");
        //     })
        //     ->join('academic_section as g', function ($join) {
        //         $join->whereRaw("g.id = se.grade_id");
        //     })
        //     ->join('standard as st', function ($join) use($marking_period_id){
        //         $join->whereRaw("st.id = se.standard_id");
        //         // ->when($marking_period_id,function($query) use ($marking_period_id){
        //         //     $query->where('st.marking_period_id',$marking_period_id);
        //         // });
        //     })
        //     ->join('division as d', function ($join) {
        //         $join->whereRaw("d.id = se.section_id");
        //     })
        //     ->join('dicipline as pc', function ($join) {
        //         $join->whereRaw("pc.student_id = s.id");
        //     })
        //     ->selectRaw('s.*,se.syear,se.student_id,se.grade_id, se.standard_id,se.section_id,se.student_quota,se.start_date, 
        //                 se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks, se.drop_remarks,se.term_id,se.remarks,se.admission_fees,
        //                 se.house_id,se.lc_number,st.name standard_name,d.name as division_name,pc.id,pc.syear,pc.student_id,pc.message,
        //                 pc.dicipline,pc.date_,pc.name')
        //     ->where("s.sub_institute_id", "=", session()->get('sub_institute_id'))
        //     ->where("se.syear", "=", session()->get('syear'))
        //     ->where("pc.syear", "=", session()->get('syear'))
        //     ->where(function ($q) use ($requestData) {
        //         if (isset($requestData['grade']) && $requestData['grade'] != '') {
        //             $q->where('se.grade_id', $requestData['grade']);
        //         }

        //         if (isset($requestData['standard']) && $requestData['standard'] != '') {
        //             $q->where('se.standard_id', $requestData['standard']);
        //         }

        //         if (isset($requestData['division']) && $requestData['division'] != '') {
        //             $q->where('se.section_id', $requestData['division']);
        //         }

        //         if (isset($requestData['from_date']) && $requestData['from_date'] != '') {
        //             $q->where('pc.date_', '>=', $requestData['from_date']);
        //         }

        //         if (isset($requestData['to_date']) && $requestData['to_date'] != '') {
        //             $q->where('pc.date_', '<=', $requestData['to_date']);
        //         }
        //     })
        //     ->get()->toarray();

        // added on 02/05/24 by uma 
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $type = $request->input('type');
        $res['grade_id'] = $grade = $request->grade;
        $res['standard_id'] = $standard = $request->standard;
        $res['division_id'] = $division = $request->division;
        $res['stu_name'] = $stu_name = $request->stu_name;
        $res['uniqueid'] = $uniqueid = $request->uniqueid;
        $res['mobile'] = $mobile = $request->mobile;
        $res['grno'] = $grno = $request->grno;
        $res['from_date'] = $from_date = $request->from_date;
        $res['to_date'] = $to_date = $request->to_date;
        
        // serach student from helper  serachStudent function
        $studentLists = SearchStudent($grade, $standard, $division, "","","", $stu_name , $uniqueid, $mobile, $grno,"","");
       
        $studentArr = [];
        $newKey = 0;
        if(empty($studentLists)){
            $res['status_code'] = 0;
            $res['message'] = "No student Found";
        }else{
            // get decipline data 
            $student_ids = array_column($studentLists, 'id');
            $deciplineData = DB::table('dicipline')->selectRaw('*,id as des_id')->whereIn('student_id', $student_ids)->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->orderBy('date_', 'desc')->get()->toArray();
            $studentArr = [];

            foreach ($deciplineData as $key => $value) {
                $student_id = $value->student_id;
                $filteredData = array_values(array_filter($studentLists, function ($item) use ($student_id) {
                    return $item['id'] == $student_id;
                }));
                $studentData = isset($filteredData[0]) ? $filteredData[0] : '';
                $studentArr[] = array_merge((array)$value, $studentData);
            }
        }

        $res['data'] = $studentArr;
        // echo "<pre>";print_r($res['data']);exit;
        return is_mobile($type, "front_desk/dicipline_report/add", $res, "view");
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
