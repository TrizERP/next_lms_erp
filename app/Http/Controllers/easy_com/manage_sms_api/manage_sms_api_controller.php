<?php

namespace App\Http\Controllers\easy_com\manage_sms_api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\easy_com\manage_sms_api\manage_sms_api;
use DB;

class manage_sms_api_controller extends Controller {

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
//        echo "<pre>";
//        print_r($data['data']);
//        exit;
//        $data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "easy_comm/manage_sms_api/show", $data, "view");
    }

    public function getData() {
        $sub_institute_id = session()->get('sub_institute_id');
        $data = manage_sms_api::
                where(['sub_institute_id' => $sub_institute_id])
                ->get();

//        $data = DB::table('result_remark_masters')
//                        ->join('academic_year', ['academic_year.term_id' => 'result_remark_masters.marking_period_id',
//                            'academic_year.sub_institute_id' => 'result_remark_masters.sub_institute_id'])
////                        ->join('division', 'division.id', '=', 'result_master_confrigration.division_id')
//                        ->select('result_remark_masters.*', 'academic_year.title as term_name')
//                        ->where(['result_remark_masters.sub_institute_id' => $sub_institute_id])
//                        ->get()->toArray();
        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        return view('easy_comm/manage_sms_api/add');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $school = new manage_sms_api([
            'url' => $request->get('url'),
            'pram' => $request->get('pram'),
            'mobile_var' => $request->get('mobile_var'),
            'text_var' => $request->get('text_var'),
            'last_var' => $request->get('last_var'),
            'sub_institute_id' => session()->get('sub_institute_id'),
        ]);
        $school->save();

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );

        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "manage_sms_api.index", $res, "redirect");
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
        $data = manage_sms_api::find($id);

        return \App\Helpers\is_mobile($type, "easy_comm/manage_sms_api/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        $data = array([
                'url' => $request->get('url'),
                'pram' => $request->get('pram'),
                'mobile_var' => $request->get('mobile_var'),
                'text_var' => $request->get('text_var'),
                'last_var' => $request->get('last_var'),
                'sub_institute_id' => session()->get('sub_institute_id'),
        ]);

        $data = $data[0];
//        echo "<pre>";
//        print_r($data);
//        exit;

        manage_sms_api::where(["id" => $id])->update($data);

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );
        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, "manage_sms_api.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id) {
        $type = $request->input('type');
        manage_sms_api::where(["id" => $id])->delete();
        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return \App\Helpers\is_mobile($type, "manage_sms_api.index", $res, "redirect");
    }

}
