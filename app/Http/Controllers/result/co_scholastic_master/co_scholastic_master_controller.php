<?php

namespace App\Http\Controllers\result\co_scholastic_master;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\result\co_scholastic_master\co_scholastic_master;
use DB;

class co_scholastic_master_controller extends Controller {

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
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "result/co_scholastic_master/show", $data, "view");
    }

    public function getData() {
        $responce_arr = array();

        $data = DB::table('result_co_scholastic_parent as cs')
                     
                        ->select(
                                'cs.*'
                        )
                        ->where([
                            'cs.sub_institute_id' => session()->get('sub_institute_id'),
                                ]
                        )
                        ->get()->toArray();
//        $data = DB::table('co_scholastic_master as cs')
//                        ->join('academic_year', ['academic_year.term_id' => 'cs.term_id',
//                            'academic_year.sub_institute_id' => 'cs.sub_institute_id'
//                        ])
//                        ->leftjoin('co_scholastic_master as lcs', ['cs.parent_id' => 'lcs.id', 'cs.sub_institute_id' => 'lcs.sub_institute_id'])
//                        ->select(
//                                'cs.*', 'academic_year.title as term_name', 'lcs.title as parent_name'
//                        )
//                        ->where([
//                            'cs.sub_institute_id' => session()->get('sub_institute_id'),
//                                ]
//                        )
//                        ->get()->toArray();

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
        return \App\Helpers\is_mobile($type, 'result/co_scholastic_master/add', $dataStore, "view");
//        return view('result/co_scholastic_master/add');
    }

    public function maxSortOrder() {
        $sub_institute_id = session()->get('sub_institute_id');
        $maxSortOrder = co_scholastic_master::
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
     * phpsmarty     
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {

        $exam = new co_scholastic_master([
            'title' => $request->get('title'),
            'sort_order' => $request->get('sort_order'),
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

        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "co_scholastic_master.index", $res, "redirect");
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
        $data = co_scholastic_master::find($id)->toArray();
        $data['ddValue'] = $this->ddValue();
        return \App\Helpers\is_mobile($type, "result/co_scholastic_master/edit", $data, "view");
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
        $data = array([
                'title' => $request->get('title'),
                'sort_order' => $request->get('sort_order'),
                'sub_institute_id' => session()->get('sub_institute_id'),
        ]);
//        $data = array([
//                'term_id' => $request->get('term'),
//                'title' => $request->get('title'),
//                'sort_order' => $request->get('sort_order'),
//                'parent_id' => $request->get('parent_id'),
//                'sub_institute_id' => session()->get('sub_institute_id'),
//        ]);
        $data = $data[0];
//        print_r($data);
//        exit;

        co_scholastic_master::where(["id" => $id])->update($data);

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );
        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, "co_scholastic_master.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request,$id) {
        $type = $request->input('type');
        co_scholastic_master::where(["id" => $id])->delete();
        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return \App\Helpers\is_mobile($type, "co_scholastic_master.index", $res, "redirect");
    }

}
