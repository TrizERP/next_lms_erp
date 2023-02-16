<?php

namespace App\Http\Controllers\result\working_day_master;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\result\working_day_master\working_day_master;
use DB;

class working_day_master_controller extends Controller {

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

        $data['data'] = $this->getData();
//        $data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "result/working_day_master/show", $data, "view");
    }

    public function getData() {
        $responce_arr = array();

        $data = working_day_master::select(
                                'result_working_day_master.id', 'academic_year.title as term_name', 'academic_year.term_id', 'acs.title as grade_name', 'acs.id as grade_id', 's.name as standard_name', 's.id as standard_id', 'result_working_day_master.total_working_day'
                        )
                        ->join('academic_year', ['academic_year.term_id' => 'result_working_day_master.term_id', 'academic_year.sub_institute_id' => 'result_working_day_master.sub_institute_id'])
                        ->join('standard as s', 's.id', '=', 'result_working_day_master.standard')
                        ->join('academic_section as acs', 'acs.id', '=', 's.grade_id')
                        ->where(['result_working_day_master.sub_institute_id' => session()->get('sub_institute_id')])
                        ->get()->toArray();
//echo "<pre>";
//print_r($data);
//exit;

        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        return view('result/working_day_master/add');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        foreach ($_REQUEST['standard'] as $id => $arr) {
            $exam = new working_day_master([
                'standard' => $arr,
                'term_id' => $request->get('term'),
                'total_working_day' => $request->get('total_working_day'),
                'sub_institute_id' => session()->get('sub_institute_id'),
            ]);
            $exam->save();
        }
        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );

        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "working_day_master.index", $res, "redirect");
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
    public function edit(Request $request, $id) {
        $type = $request->input('type');
        $all_data = $this->getData();
        $data = array();
        foreach ($all_data as $all_data_id => $all_data_arr) {
            if ($all_data_arr['id'] == $id) {
                $data = $all_data_arr;
            }
        }
//        $data = co_scholastic_master::find($id)->toArray();
//        $data['ddValue'] = $this->ddValue();
        return \App\Helpers\is_mobile($type, "result/working_day_master/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {

//        foreach ($_REQUEST['standard'] as $id => $arr) {
//            working_day_master::where([
//                'sub_institute_id' => session()->get('sub_institute_id'),
//                'standard' => $arr
//            ])->delete();

        $data = array(
            'standard' => $request->get('standard'),
            'term_id' => $request->get('term'),
            'total_working_day' => $request->get('total_working_day'),
            'sub_institute_id' => session()->get('sub_institute_id'),
        );
        working_day_master::where(["id" => $id])->update($data);

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );
        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, "working_day_master.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id) {
        $type = $request->input('type');
        working_day_master::where([
            'id' => $id,
        ])->delete();
        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return \App\Helpers\is_mobile($type, "working_day_master.index", $res, "redirect");
    }

}
