<?php

namespace App\Http\Controllers\result\co_scholastic;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\result\co_scholastic_master\co_scholastic_master;
use App\Models\result\co_scholastic\co_scholastic;
use App\Models\result\co_scholastic\co_scholastic_grade;
use DB;

class co_scholastic_controller extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {
        // echo ('<pre>');print_r($_REQUEST);exit;
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }
        $data['data'] = $this->getData();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "result/co_scholastic/show", $data, "view");
    }

    public function getData() {
        $responce_arr = array();

        $join = array(
            "csp.sub_institute_id" => "cs.sub_institute_id",
            "csp.id" => "cs.parent_id",
        );

        $data = co_scholastic::from('result_co_scholastic as cs')
                ->join("result_co_scholastic_parent as csp", $join)
                ->join('academic_year', ['academic_year.term_id' => 'cs.term_id',
                    'academic_year.sub_institute_id' => 'cs.sub_institute_id'
                ])
                ->select(
                        'cs.*', "csp.title as parent_name", 'academic_year.title as term_name'
                )
                ->where([
                    'cs.sub_institute_id' => session()->get('sub_institute_id'),
                        ]
                )
                ->get();
        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        $type = $request->input('type');

        $dataStore['SortOrder'] = $this->maxSortOrder();
        $dataStore['ddValue'] = $this->ddvalue();
        return \App\Helpers\is_mobile($type, 'result/co_scholastic/add', $dataStore, "view");
//        return view('result/co_scholastic_master/add');
    }

    public function maxSortOrder() {
        $sub_institute_id = session()->get('sub_institute_id');
        $maxSortOrder = co_scholastic::
                where(['sub_institute_id' => $sub_institute_id])
                ->max('sort_order');
        $maxSortOrder = $maxSortOrder + 1;
        return $maxSortOrder;
    }

    public function ddValue() {
        $sub_institute_id = session()->get('sub_institute_id');
        $ddvalue = co_scholastic_master::
                where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        return $ddvalue;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
// echo ('<pre>');print_r($_REQUEST);exit;
        $max_id = co_scholastic_grade::max('map_id');
        if ($max_id == "") {
            $max_id = 1;
        } else {
            $max_id = $max_id + 1;
        }
        foreach ($_REQUEST['co_grade'] as $id => $arr) {
            if ($arr['title'] != "" && $arr['break_off'] != "") {
                $exam = new co_scholastic_grade([
                    "map_id" => $max_id,
                    "title" => $arr['title'],
                    "break_off" => $arr['break_off'],
                    'sub_institute_id' => session()->get('sub_institute_id'),
                ]);
                $exam->save();
            }
        }
        if ($request->get('mark_type') == "MARK") {
            $max_id = "";
        }
        $exam = new co_scholastic([
            "term_id" => $request->get('term'),
            "title" => $request->get('title'),
            "sort_order" => $request->get('sort_order'),
            "parent_id" => $request->get('parent_id'),
            "mark_type" => $request->get('mark_type'),
            "max_mark" => $request->get('max_mark'),
            "co_grade" => $max_id,
            'sub_institute_id' => session()->get('sub_institute_id'),
        ]);
//        $exam = new co_scholastic_master([
//            'term_id' => $request->get('term'),
//            'title' => $request->get('title'),
//            'sort_order' => $request->get('sort_order'),
//            'parent_id' => $request->get('parent_id'),
//            'sub_institute_id' => session()->get('sub_institute_id'),
//        ]);
        $exam->save();

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );
// echo ('<pre>');print_r($_REQUEST);exit;
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "co_scholastic.index", $res, "redirect");
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
        $data = co_scholastic::find($id)->toArray();
// echo ('<pre>');print_r($_REQUEST);exit;
        $where = array(
            'map_id' => $data['co_grade'],
            'sub_institute_id' => session()->get('sub_institute_id'),
        );
        $grd_data = co_scholastic_grade::
                        where($where)
                        ->get()->toArray();

        $data['grd_data'] = $grd_data;

        $data['ddValue'] = $this->ddValue();
        return \App\Helpers\is_mobile($type, "result/co_scholastic/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
//        echo "<pre>";
//        dd($request);
        $data = co_scholastic::find($id)->toArray();
        co_scholastic_grade::where(["map_id" => $data['co_grade']])->delete();
        $max_id = "";
        if ($data['co_grade'] == "") {
            $max_id = co_scholastic_grade::max('map_id');
            if ($max_id == "") {
                $max_id = 1;
            } else {
                $max_id = $max_id + 1;
            }
        } else {
            $max_id = $data['co_grade'];
        }
        foreach ($_REQUEST['co_grade'] as $ids => $arr) {
            if ($arr['title'] != "" && $arr['break_off'] != "") {
                $exam = new co_scholastic_grade([
                    "map_id" => $max_id,
                    "title" => $arr['title'],
                    "break_off" => $arr['break_off'],
                    'sub_institute_id' => session()->get('sub_institute_id'),
                ]);
                $exam->save();
            }
        }

        $data1 = array([
                "term_id" => $request->get('term'),
                "title" => $request->get('title'),
                "sort_order" => $request->get('sort_order'),
                "parent_id" => $request->get('parent_id'),
                "mark_type" => $request->get('mark_type'),
                "max_mark" => $request->get('max_mark'),
        ]);
        if($request->get('mark_type') == "GRADE"){
            $data1[0]['co_grade'] = $max_id;
        }
//echo "<pre>";
//print_r($data1);
//echo $id;
//exit;

        $data1 = $data1[0];

        co_scholastic::where(["id" => $id])->update($data1);

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );
        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, "co_scholastic.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id) {
        $type = $request->input('type');
        co_scholastic::where(["id" => $id])->delete();
        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return \App\Helpers\is_mobile($type, "co_scholastic.index", $res, "redirect");
    }

}
