<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\documentTypeModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class missingDocumentReportController extends Controller
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

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "student/missing_document_report", $res, "view");
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
        $grade_id = $request->input("grade");
        $standard_id = $request->input("standard");
        $division_id = $request->input("division");
        $user_type = $request->user_type;

        if($user_type=="student"){
            $result = DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('s.id = se.student_id');
                })->join('standard as st', function ($join) {
                    $join->whereRaw('st.id = se.standard_id');
                })->join('division as d', function ($join) {
                    $join->whereRaw('d.id = se.section_id');
                })->leftJoin('tblstudent_document as sd', function ($join) {
                    $join->whereRaw('sd.student_id = se.student_id');
                })->selectRaw("s.enrollment_no, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,
                    st.name as standard_name,d.name as division_name,GROUP_CONCAT(sd.document_type_id) as document_list")
                ->where('se.syear', $syear)
                ->where('s.sub_institute_id', $sub_institute_id);

            if ($grade_id != '') {
                $result = $result->where('se.grade_id', $grade_id);
            }
            if ($standard_id != '') {
                $result = $result->where('se.standard_id', $standard_id);
            }
            if ($division_id != '') {
                $result = $result->where('se.section_id', $division_id);
            }
            $result = $result->groupBy('se.student_id')->get()->toArray();
        }else if($user_type=="staff"){
            $result = DB::table('tbluser as s')
          ->leftJoin('staff_document as sd', function ($join) {
                $join->whereRaw('sd.user_id = s.id');
            })->selectRaw("CONCAT_WS(' ',s.first_name,s.last_name) AS student_name,GROUP_CONCAT(sd.document_type_id) as document_list")
            ->where('s.sub_institute_id', $sub_institute_id)
            ->groupBy('s.id')->get()->toArray();
        }else{
            $res['status_code']=0;
            $res['message'] = "User Type Not Selected";
        }

        $docment_type_data = documentTypeModel::select('student_document_type.*')->when($standard_id!='',function($query) use($standard_id){
            $query->join('tblstudent_doc_std_mapping','student_document_type.id','=','tblstudent_doc_std_mapping.document_type_id')->where('tblstudent_doc_std_mapping.standard_id',$standard_id);
        })
        ->where('student_document_type.user_type',$user_type)
        ->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['result_report'] = $result;
        $res['docment_type_data'] = $docment_type_data;
        $res['grade_id'] = $grade_id;
        $res['standard_id'] = $standard_id;
        $res['division_id'] = $division_id;
        $res['user_type'] = $user_type ;

        return is_mobile($type, "student/missing_document_report", $res, "view");
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
