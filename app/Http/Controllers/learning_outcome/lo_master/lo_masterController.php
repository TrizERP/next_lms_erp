<?php

namespace App\Http\Controllers\learning_outcome\lo_master;

// namespace  App\Http\Controllers\learning_outcome\lo_master\lo_master_controller;


//use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use Illuminate\Http\Request;

class lo_masterController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->getData();
        // $school_data['data'] = array();
        // echo "<pre>";
        // print_r($school_data);
        // exit;
        // $school_data['data'] = DB::table('learning_outcome_pdf')->get();
        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, 'learning_outcome/lo_master/show', $school_data, 'view');
    }

    public function getData()
    {
        $data = DB::table('learning_outcome_indicator')->get();
        $i = 1;
        foreach ($data as $key => $arr) {
            $arr->SrNo = $i;
            $i++;
        }
        return $data;
    }
    public function get_all_dd()
    {
        $str = 'SELECT MEDIUM FROM learning_outcome_pdf GROUP BY MEDIUM';
        $result = DB::select(DB::raw($str));

        $medium = array();
        foreach ($result as $id => $arr) {
            $medium[$arr->MEDIUM] = $arr->MEDIUM;
        }

        $str = 'SELECT STANDARD FROM learning_outcome_pdf GROUP BY STANDARD';
        $result = DB::select(DB::raw($str));

        $std = array();
        foreach ($result as $id => $arr) {
            $std[$arr->STANDARD] = $arr->STANDARD;
        }

        $dataStore = array(
            'medium' => $medium,
            'std' => $std,
            // 'div' => $div,
        );

        return $dataStore;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $dataStore = $this->get_all_dd();

        return \App\Helpers\is_mobile($type, 'learning_outcome/lo_master/add', $dataStore, 'view');
    }

  

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = array(
            'MEDIUM' => $request->get('medium'),
            'STANDARD' => $request->get('std'),
            'SUBJECT' => $request->get('subject'),
            'INDICATOR' => $request->get('learning_outcome'),
            'CREATED_AT' => now(),
            'UPDATED_AT' => now(),
            'CREATED_BY' =>  $request->session()->get('user_id'),
            'UPDATED_BY' => $request->session()->get('user_id'),
        );

        DB::table('learning_outcome_indicator')->insert(
            $data
        );
  
        $res = array(
            'status_code' => 1,
            'message' => 'Data Saved',
        );

        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, 'lo_master.index', $res, 'redirect');

        // echo '<pre>';
        // print_r($request->Code);
        // exit;
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $all_dd = $this->get_all_dd();

        // $allData = lo_master::
        //     where(['SubInstituteId' => $sub_institute_id])
        //     ->get()->toArray();

        $str = 'SELECT * FROM learning_outcome_indicator WHERE ID = '.$id;
        $allData = DB::select(DB::raw($str));

        // $allData = lo_master::find($id)->toArray();
        // echo('<pre>');
        // print_r($allData);
        // exit;

        $standard = $allData[0]->STANDARD;
        $medium = $allData[0]->MEDIUM;

        $where = array(
            'learning_outcome_pdf.standard' => $standard,
            'learning_outcome_pdf.medium' => $medium,
        );

        $std_sub_map = DB::table('learning_outcome_pdf')
            ->where($where)
            ->pluck('learning_outcome_pdf.DISPLAY_SUBJECT', 'learning_outcome_pdf.SUBJECTS');

        $data = array(
            'medium' => $all_dd['medium'],
            'std' => $all_dd['std'],
            'selected_medium' => $allData[0]->MEDIUM,
            'selected_std' => $allData[0]->STANDARD,
            'selected_subject' => $allData[0]->SUBJECT,
            'learning_outcome' => $allData[0]->INDICATOR,
            'subject' => $std_sub_map,
            'id' => $id,

        );
        // echo ('<pre>');print_r($data);exit;

        // $sub_institute_id = session()->get('sub_institute_id');
        $type = $request->input('type');

        // $data['ddValue'] = $ddvalue;
        return \App\Helpers\is_mobile($type, "learning_outcome/lo_master/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = array(
            'MEDIUM' => $request->get('medium'),
            'STANDARD' => $request->get('std'),
            'SUBJECT' => $request->get('subject'),
            'INDICATOR' => $request->get('learning_outcome'),
            'UPDATED_AT' => now(),
            'UPDATED_BY' => $request->session()->get('user_id'),
        );

        DB::table('learning_outcome_indicator')
        ->where(["ID" => $id])
        ->update($data);
        
        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );
        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, "lo_master.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        DB::table('learning_outcome_indicator')
        ->where(["ID" => $id])
        ->delete();

        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return \App\Helpers\is_mobile($type, "lo_master.index", $res, "redirect");
    }
}
