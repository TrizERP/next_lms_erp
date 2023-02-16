<?php

namespace App\Http\Controllers\front_desk\dicipline_report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class dicipline_reportController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }

//        $data['data'] = $this->getData();


        $data['data'] = array();
//        $data['data']['dd'] = $dd;
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "front_desk/dicipline_report/show", $data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
//        echo "<pre>";
//        print_r($_REQUEST);
//        exit;

        $extra_where = "";
        if (isset($_REQUEST['grade']) && $_REQUEST['grade'] != '') {
            $grade_val = $_REQUEST['grade'];
            $extra_where .= " AND se.grade_id =  '" . $_REQUEST['grade'] . "'";
        }
        if (isset($_REQUEST['standard']) && $_REQUEST['standard'] != '') {
            $extra_where .= " AND se.standard_id =  '" . $_REQUEST['standard'] . "'";
//            $responce_arr['standard'] = $_REQUEST['standard'];
        }
        if (isset($_REQUEST['division']) && $_REQUEST['division'] != '') {
            $extra_where .= " AND se.section_id ='" . $_REQUEST['division'] . "'";
//            $responce_arr['division'] = $_REQUEST['division'];
        }
        if (isset($_REQUEST['from_date']) && $_REQUEST['from_date'] != '') {
            $extra_where .= " and pc.date_ >='" . $_REQUEST['from_date'] . "'";
        }
        if (isset($_REQUEST['to_date']) && $_REQUEST['to_date'] != '') {
            $extra_where .= " and pc.date_ <='" . $_REQUEST['to_date'] . "'";
        }
        $sql = "SELECT s.*,se.syear,se.student_id,se.grade_id,
                se.standard_id,se.section_id,se.student_quota,se.start_date,
                se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,
                se.drop_remarks,se.term_id,se.remarks,se.admission_fees,
                se.house_id,se.lc_number,st.name standard_name,
                d.name as division_name,pc.id,pc.syear,pc.student_id,pc.message,
                pc.dicipline,pc.date_,pc.name
                FROM tblstudent s
                INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
                INNER JOIN academic_section g ON g.id = se.grade_id
                INNER JOIN standard st ON st.id = se.standard_id
                INNER JOIN division d ON  d.id = se.section_id
                INNER JOIN dicipline pc ON pc.student_id = s.id
                WHERE s.sub_institute_id = '" . session()->get('sub_institute_id') . "'
                AND se.syear = '" . session()->get('syear') . "'
                AND pc.syear = '" . session()->get('syear') . "'
               $extra_where
                ";
//        echo $sql;
        $sql = preg_replace('/\n+/', '', $sql);

        $result = DB::select($sql);

//        echo "<pre>";
//        print_r($result);
//        exit;
        $data['data'] = $result;
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "front_desk/dicipline_report/add", $data, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        //
    }

}
