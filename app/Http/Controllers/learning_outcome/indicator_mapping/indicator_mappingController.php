<?php

namespace App\Http\Controllers\learning_outcome\indicator_mapping;

// namespace  App\Http\Controllers\learning_outcome\indicator_mapping\indicator_mapping_controller;


//use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use Illuminate\Http\Request;

class indicator_mappingController extends Controller
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

        $school_data['data'] = $this->get_all_dd();
        // $dataStore = $this->get_all_dd();
        // $school_data['data'] = array();


        // echo "<pre>";
        // print_r($school_data);
        // exit;
        // $school_data['data'] = DB::table('learning_outcome_pdf')->get();

        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, 'learning_outcome/indicator_mapping/show', $school_data, 'view');
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

        return \App\Helpers\is_mobile($type, 'learning_outcome/indicator_mapping/add', $dataStore, 'view');
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
        // echo ('<pre>');
        // print_r($_REQUEST);
        // dd(session()->all());
        // exit;

        for ($i=0; $i < count($request->get('question_title')) ; $i++) {
            $data = array(
                'DATE' => $request->session()->get('examdate'),
                'MEDIUM' => $request->session()->get('medium'),
                'STANDARD' => $request->session()->get('std'),
                'SUBJECT' => $request->session()->get('subject'),
                'QUESTION_TITLE' => $request->get('question_title')[$i],
                'QUESTION_OUT_OF' => $request->get('total_marks')[$i],
                'INDICATORE_ID' => $request->get('learning_outcome')[$i],
                'EXAM_CODE' => $request->get('exam_code'),
                'EXAM_TYPE' => $request->get('exam_type'),
                'SYEAR' => $request->session()->get('syear'),
            );
    
            DB::table('learning_outcome_question_master')->insert(
                $data
            );
        }

        
        $res = array(
            'status_code' => 1,
            'message' => 'Data Saved',
        );

        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, 'indicator_mapping.index', $res, 'redirect');

        // echo '<pre>';
        // print_r($request->Code);
        // exit;
    }
    public function get_indicator(Request $request)
    {
        // echo ('<pre>');print_r($_REQUEST);exit;
        $request->session()->put('examdate', $request->get('examdate'));
        $request->session()->put('medium', $request->get('medium'));
        $request->session()->put('std', $request->get('std'));
        $request->session()->put('subject', $request->get('subject'));
        $type = $request->input('type');

        $where_arr = array(
            "MEDIUM" => $request->get('medium'),
            "STANDARD" => $request->get('std'),
            "SUBJECT" => $request->get('subject'),
        );

        $data = DB::table('learning_outcome_indicator')
                ->where($where_arr)
                ->pluck('INDICATOR', 'ID');

        $exam_type_dd = DB::table('learning_outcome_exam_type_master')
                ->pluck('EXAM_TYPE', 'EXAM_TYPE');
        
        $where_arr = array(
                    "learning_outcome_question_master.MEDIUM" => $request->get('medium'),
                    "learning_outcome_question_master.STANDARD" => $request->get('std'),
                    "learning_outcome_question_master.SUBJECT" => $request->get('subject'),
                    "learning_outcome_question_master.DATE" => $request->session()->get('examdate')
                );
        
        $inserted_data = DB::table('learning_outcome_question_master')
                ->join('learning_outcome_indicator', 'learning_outcome_indicator.ID', '=', 'learning_outcome_question_master.INDICATORE_ID')
                ->where($where_arr)
                ->select('learning_outcome_question_master.ID', 'learning_outcome_question_master.DATE', 'learning_outcome_question_master.MEDIUM', 'learning_outcome_question_master.STANDARD', 'learning_outcome_question_master.SUBJECT', 'learning_outcome_question_master.QUESTION_TITLE', 'learning_outcome_question_master.QUESTION_OUT_OF', 'learning_outcome_indicator.INDICATOR','learning_outcome_question_master.EXAM_TYPE','learning_outcome_question_master.EXAM_CODE')
                ->get();
        // echo "asdsad";
        // echo ('<pre>');print_r($inserted_data);exit;

        $dataStore = array(
                    'lo_dd' => $data,
                    'exam_type_dd' => $exam_type_dd,
                     'data' => $inserted_data,
                    // 'div' => $div,
                );

        return \App\Helpers\is_mobile($type, 'learning_outcome/indicator_mapping/FinalAdd', $dataStore, 'view');
        // return \App\Helpers\is_mobile($type, 'indicator_mapping.index', $res, 'redirect');

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

        // $allData = indicator_mapping::
        //     where(['SubInstituteId' => $sub_institute_id])
        //     ->get()->toArray();

        $str = 'SELECT * FROM learning_outcome_indicator WHERE ID = '.$id;
        $allData = DB::select(DB::raw($str));

        // $allData = indicator_mapping::find($id)->toArray();
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
        return \App\Helpers\is_mobile($type, "learning_outcome/indicator_mapping/edit", $data, "view");
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

        return \App\Helpers\is_mobile($type, "indicator_mapping.index", $res, "redirect");
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

        DB::table('learning_outcome_question_master')
        ->where(["ID" => $id])
        ->delete();

        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return \App\Helpers\is_mobile($type, "indicator_mapping.index", $res, "redirect");
    }
}
