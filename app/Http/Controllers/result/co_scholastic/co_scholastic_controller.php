<?php

namespace App\Http\Controllers\result\co_scholastic;

use App\Http\Controllers\Controller;
use App\Models\result\co_scholastic\co_scholastic;
use App\Models\result\co_scholastic\co_scholastic_grade;
use App\Models\result\co_scholastic_master\co_scholastic_master;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;
use DB;

class co_scholastic_controller extends Controller
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

        $data['data'] = $this->getData();
        $type = $request->input('type');

        return is_mobile($type, "result/co_scholastic/show", $data, "view");
    }

    public function getData()
    {
        $responce_arr = [];

        $join = [
            "csp.sub_institute_id" => "cs.sub_institute_id",
            "csp.id"               => "cs.parent_id",
        ];
        return co_scholastic::from('result_co_scholastic as cs')
        ->leftjoin('standard as s','s.id','=','cs.standard_id')
            ->join("result_co_scholastic_parent as csp", $join)
            ->join('academic_year', [
                'academic_year.term_id'          => 'cs.term_id',
                'academic_year.sub_institute_id' => 'cs.sub_institute_id',
            ])->select('cs.*', "csp.title as parent_name", 'academic_year.title as term_name','s.name as standard')
            ->where([
                'cs.sub_institute_id' => session()->get('sub_institute_id'),
            ])->orderBy('cs.sort_order')->groupBy('cs.title','cs.standard_id')->get();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $dataStore['standard'] = DB::table('standard')->where('sub_institute_id',session()->get('sub_institute_id'))->get()->toArray();
        $dataStore['SortOrder'] = $this->maxSortOrder();
        $dataStore['ddValue'] = $this->ddvalue();

        return is_mobile($type, 'result/co_scholastic/add', $dataStore, "view");
    }

    public function maxSortOrder()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $maxSortOrder = co_scholastic::
        where(['sub_institute_id' => $sub_institute_id])
            ->max('sort_order');

        return $maxSortOrder + 1;
    }

    public function ddValue()
    {
        $sub_institute_id = session()->get('sub_institute_id');

        return co_scholastic_master::
        where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function store(Request $request)
    {
        
        $max_id = co_scholastic_grade::max('map_id');
        if ($max_id == "") {
            $max_id = 1;
        } else {
            ++$max_id;
        }
        foreach ($_REQUEST['co_grade'] as $id => $arr) {
            if ($arr['title'] != "" && $arr['break_off'] != "") {
                $exam = new co_scholastic_grade([
                    "map_id"           => $max_id,
                    "title"            => $arr['title'],
                    "break_off"        => $arr['break_off'],
                    'sub_institute_id' => session()->get('sub_institute_id'),
                ]);
                $exam->save();
            }
        }
        if ($request->get('mark_type') == "MARK") {
            $max_id = "";
        }
        $sort =$request->get('sort_order');
        foreach ($request->standard as $key => $value) {
            $exam = new co_scholastic([    
                "term_id"          => $request->get('term'),
                "title"            => $request->get('title'),
                "sort_order"       => $sort++,
                "parent_id"        => $request->get('parent_id'),
                "mark_type"        => $request->get('mark_type'),
                "max_mark"         => $request->get('max_mark'),
                "co_grade"         => $max_id,
                'sub_institute_id' => session()->get('sub_institute_id'),
                "standard_id"         => $value,            
                
            ]);
            $exam->save();
            // echo "<pre>";print_r($exam);
        }
// exit;
        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "co_scholastic.index", $res, "redirect");
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
     * @param  Request  $request
     * @param  int  $id
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = co_scholastic::find($id)->toArray();

        $where = [
            'map_id'           => $data['co_grade'],
            'sub_institute_id' => session()->get('sub_institute_id'),
        ];

        $grd_data = co_scholastic_grade::where($where)->get()->toArray();

        $data['grd_data'] = $grd_data;

        $data['ddValue'] = $this->ddValue();
        $data['standard'] = DB::table('standard')->where('sub_institute_id',session()->get('sub_institute_id'))->get()->toArray();

        return is_mobile($type, "result/co_scholastic/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function update(Request $request, $id)
    {

        $data = co_scholastic::find($id)->toArray();

        co_scholastic_grade::where(["map_id" => $data['co_grade']])->delete();

        $max_id = "";

        if ($data['co_grade'] == "") {
            $max_id = co_scholastic_grade::max('map_id');
            if ($max_id == "") {
                $max_id = 1;
            } else {
                ++$max_id;
            }
        } else {
            $max_id = $data['co_grade'];
        }

        foreach ($_REQUEST['co_grade'] as $ids => $arr) {
            if ($arr['title'] != "" && $arr['break_off'] != "") {
                $exam = new co_scholastic_grade([
                    "map_id"           => $max_id,
                    "title"            => $arr['title'],
                    "break_off"        => $arr['break_off'],
                    'sub_institute_id' => session()->get('sub_institute_id'),
                ]);
                $exam->save();
            }
        }

        $data1 = [
            "term_id"    => $request->get('term'),
            "title"      => $request->get('title'),
            "sort_order" => $request->get('sort_order'),
            "parent_id"  => $request->get('parent_id'),
            "mark_type"  => $request->get('mark_type'),
            "max_mark"   => $request->get('max_mark'),
            "standard_id"   => $request->get('standard'),            
        ];
        if ($request->get('mark_type') == "GRADE") {
            $data1['co_grade'] = $max_id;
        }


        co_scholastic::where(["id" => $id])->update($data1);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];
        $type = $request->input('type');

        return is_mobile($type, "co_scholastic.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        co_scholastic::where(["id" => $id])->delete();
        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "co_scholastic.index", $res, "redirect");
    }

}
