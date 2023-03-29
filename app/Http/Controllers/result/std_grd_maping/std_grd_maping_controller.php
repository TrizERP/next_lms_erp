<?php

namespace App\Http\Controllers\result\std_grd_maping;

use App\Http\Controllers\Controller;
use App\Models\result\GradeMaster\GradeMaster;
use App\Models\result\std_grd_mapping\std_grd_maping;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class std_grd_maping_controller extends Controller
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

        return is_mobile($type, "result/std_grd_maping/show", $data, "view");
    }

    public function getData()
    {
        $responce_arr = [];

        $data = std_grd_maping::select('result_std_grd_maping.id',
            DB::raw('group_concat(acs.title) as grade_name'),
            DB::raw('group_concat(acs.id) as grade_id'), DB::raw('group_concat(s.id) as standard_id'),
            DB::raw('group_concat(s.name) as standard_name'), 'gmm.grade_name as scale_name',
            'result_std_grd_maping.grade_scale')
            ->join('grade_master as gmm', 'gmm.id', '=', 'result_std_grd_maping.grade_scale')
            ->join('standard as s', 's.id', '=', 'result_std_grd_maping.standard')
            ->join('academic_section as acs', 'acs.id', '=', 's.grade_id')
            ->where(['result_std_grd_maping.sub_institute_id' => session()->get('sub_institute_id')])
            ->groupBy('result_std_grd_maping.grade_scale')
            ->get()->toArray();

        $responce_arr = [];
        foreach ($data as $id => $arr) {
            $grad_id = array_unique(explode(',', $arr['grade_id']));
            $standard_name = array_unique(explode(',', $arr['standard_name']));
            $grad_name = array_unique(explode(',', $arr['grade_name']));
            $standard_id = array_unique(explode(',', $arr['standard_id']));

            $responce_arr[$id]['id'] = $arr['id'];
            $responce_arr[$id]['scale_name'] = $arr['scale_name'];
            $responce_arr[$id]['grade_name'] = implode(',', $grad_name);
            $responce_arr[$id]['standard_name'] = implode(',', $standard_name);
            $responce_arr[$id]['standard_id'] = $standard_id;
            $responce_arr[$id]['grade_id'] = $grad_id;
            $responce_arr[$id]['grade_scale'] = $arr['grade_scale'];
        }

        return $responce_arr;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $dataStore['ddValue'] = $this->ddvalue();

        return is_mobile($type, 'result/std_grd_maping/add', $dataStore, "view");
    }

    public function ddValue()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $notin = std_grd_maping::where([
            'sub_institute_id' => $sub_institute_id,
        ])->get()->toArray();
        $not_in_array = [];

        foreach ($notin as $id => $arr) {
            $not_in_array[] = $arr['grade_scale'];
        }

        return GradeMaster::where([
            'sub_institute_id' => $sub_institute_id,
        ])
            ->whereNotIn('id', $not_in_array)
            ->get()
            ->toArray();
    }

    public function ddValueEdit()
    {
        $sub_institute_id = session()->get('sub_institute_id');

        return GradeMaster::where([
            'sub_institute_id' => $sub_institute_id,
        ])->get()->toArray();
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

        foreach ($_REQUEST['standard'] as $id => $arr) {
            $exam = new std_grd_maping([
                'standard'         => $arr,
                'grade_scale'      => $request->get('grade_scale'),
                'sub_institute_id' => session()->get('sub_institute_id'),
            ]);
            $exam->save();
        }
        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "std_grd_maping.index", $res, "redirect");
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
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $all_data = $this->getData();

        $data = [];
        foreach ($all_data as $all_data_id => $all_data_arr) {
            if ($all_data_arr['id'] == $id) {
                $data = $all_data_arr;
            }
        }

//        $data = co_scholastic_master::find($id)->toArray();
        $data['ddValue'] = $this->ddValueEdit();

        return is_mobile($type, "result/std_grd_maping/edit", $data, "view");
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

        $all_data = $this->getData();
        $data = [];
        foreach ($all_data as $all_data_id => $all_data_arr) {
            if ($all_data_arr['id'] == $id) {
                $data = $all_data_arr;
            }
        }
        std_grd_maping::where([
            'sub_institute_id' => session()->get('sub_institute_id'),
            'grade_scale'      => $data['grade_scale'],
        ])->delete();

        foreach ($_REQUEST['standard'] as $arr) {
            $exam = new std_grd_maping([
                'standard'         => $arr,
                'grade_scale'      => $request->get('grade_scale'),
                'sub_institute_id' => session()->get('sub_institute_id'),
            ]);
            $exam->save();
        }

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];
        $type = $request->input('type');

        return is_mobile($type, "std_grd_maping.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        $all_data = $this->getData();
        $data = [];
        foreach ($all_data as $all_data_id => $all_data_arr) {
            if ($all_data_arr['id'] == $id) {
                $data = $all_data_arr;
            }
        }
        std_grd_maping::where([
            'sub_institute_id' => session()->get('sub_institute_id'),
            'grade_scale'      => $data['grade_scale'],
        ])->delete();
        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "std_grd_maping.index", $res, "redirect");
    }

}
