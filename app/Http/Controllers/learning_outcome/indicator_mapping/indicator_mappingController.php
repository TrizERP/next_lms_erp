<?php

namespace App\Http\Controllers\learning_outcome\indicator_mapping;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class indicator_mappingController extends Controller
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
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->get_all_dd();

        $type = $request->input('type');

        return is_mobile($type, 'learning_outcome/indicator_mapping/show', $school_data, 'view');
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
        $result = DB::table("learning_outcome_pdf")
            ->selectRaw('MEDIUM')
            ->groupBy('MEDIUM')
            ->get()->toArray();

        $medium = [];
        foreach ($result as $id => $arr) {
            $medium[$arr->MEDIUM] = $arr->MEDIUM;
        }

        $result = DB::table("learning_outcome_pdf")
            ->selectRaw('STANDARD')
            ->groupBy('STANDARD')
            ->get()->toArray();


        $std = [];
        foreach ($result as $id => $arr) {
            $std[$arr->STANDARD] = $arr->STANDARD;
        }

        return [
            'medium' => $medium,
            'std'    => $std,
            // 'div' => $div,
        ];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $dataStore = $this->get_all_dd();

        return is_mobile($type, 'learning_outcome/indicator_mapping/add', $dataStore, 'view');
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     *
     * @return Response
     */
    public function store(Request $request)
    {
        for ($i = 0; $i < count($request->get('question_title')); $i++) {
            $data = [
                'DATE'            => $request->session()->get('examdate'),
                'MEDIUM'          => $request->session()->get('medium'),
                'STANDARD'        => $request->session()->get('std'),
                'SUBJECT'         => $request->session()->get('subject'),
                'QUESTION_TITLE'  => $request->get('question_title')[$i],
                'QUESTION_OUT_OF' => $request->get('total_marks')[$i],
                'INDICATORE_ID'   => $request->get('learning_outcome')[$i],
                'EXAM_CODE'       => $request->get('exam_code'),
                'EXAM_TYPE'       => $request->get('exam_type'),
                'SYEAR'           => $request->session()->get('syear'),
            ];

            DB::table('learning_outcome_question_master')->insert(
                $data
            );
        }


        $res = [
            'status_code' => 1,
            'message'     => 'Data Saved',
        ];

        $type = $request->input('type');

        return is_mobile($type, 'indicator_mapping.index', $res, 'redirect');
    }

    public function get_indicator(Request $request)
    {
        $request->session()->put('examdate', $request->get('examdate'));
        $request->session()->put('medium', $request->get('medium'));
        $request->session()->put('std', $request->get('std'));
        $request->session()->put('subject', $request->get('subject'));
        $type = $request->input('type');

        $where_arr = [
            "MEDIUM"   => $request->get('medium'),
            "STANDARD" => $request->get('std'),
            "SUBJECT"  => $request->get('subject'),
        ];

        $data = DB::table('learning_outcome_indicator')
            ->where($where_arr)
            ->pluck('INDICATOR', 'ID');

        $exam_type_dd = DB::table('learning_outcome_exam_type_master')
            ->pluck('EXAM_TYPE', 'EXAM_TYPE');

        $where_arr = [
            "learning_outcome_question_master.MEDIUM"   => $request->get('medium'),
            "learning_outcome_question_master.STANDARD" => $request->get('std'),
            "learning_outcome_question_master.SUBJECT"  => $request->get('subject'),
            "learning_outcome_question_master.DATE"     => $request->session()->get('examdate'),
        ];

        $inserted_data = DB::table('learning_outcome_question_master')
            ->join('learning_outcome_indicator', 'learning_outcome_indicator.ID', '=',
                'learning_outcome_question_master.INDICATORE_ID')
            ->where($where_arr)
            ->select('learning_outcome_question_master.ID', 'learning_outcome_question_master.DATE',
                'learning_outcome_question_master.MEDIUM', 'learning_outcome_question_master.STANDARD',
                'learning_outcome_question_master.SUBJECT', 'learning_outcome_question_master.QUESTION_TITLE',
                'learning_outcome_question_master.QUESTION_OUT_OF', 'learning_outcome_indicator.INDICATOR',
                'learning_outcome_question_master.EXAM_TYPE', 'learning_outcome_question_master.EXAM_CODE')
            ->get();

        $dataStore = [
            'lo_dd'        => $data,
            'exam_type_dd' => $exam_type_dd,
            'data'         => $inserted_data,
        ];

        return is_mobile($type, 'learning_outcome/indicator_mapping/FinalAdd', $dataStore, 'view');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     *
     * @return void
     */
    public function show($id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     *
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $all_dd = $this->get_all_dd();

        $allData = DB::table("learning_outcome_indicator")
            ->where("ID", "=", $id)
            ->get()->toArray();

        $standard = $allData[0]->STANDARD;
        $medium = $allData[0]->MEDIUM;

        $where = [
            'learning_outcome_pdf.standard' => $standard,
            'learning_outcome_pdf.medium'   => $medium,
        ];

        $std_sub_map = DB::table('learning_outcome_pdf')
            ->where($where)
            ->pluck('learning_outcome_pdf.DISPLAY_SUBJECT', 'learning_outcome_pdf.SUBJECTS');

        $data = [
            'medium'           => $all_dd['medium'],
            'std'              => $all_dd['std'],
            'selected_medium'  => $allData[0]->MEDIUM,
            'selected_std'     => $allData[0]->STANDARD,
            'selected_subject' => $allData[0]->SUBJECT,
            'learning_outcome' => $allData[0]->INDICATOR,
            'subject'          => $std_sub_map,
            'id'               => $id,

        ];

        $type = $request->input('type');


        return is_mobile($type, "learning_outcome/indicator_mapping/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     *
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $data = [
            'MEDIUM'     => $request->get('medium'),
            'STANDARD'   => $request->get('std'),
            'SUBJECT'    => $request->get('subject'),
            'INDICATOR'  => $request->get('learning_outcome'),
            'UPDATED_AT' => now(),
            'UPDATED_BY' => $request->session()->get('user_id'),
        ];

        DB::table('learning_outcome_indicator')
            ->where(["ID" => $id])
            ->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];
        $type = $request->input('type');

        return is_mobile($type, "indicator_mapping.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        DB::table('learning_outcome_question_master')
            ->where(["ID" => $id])
            ->delete();

        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "indicator_mapping.index", $res, "redirect");
    }
}
