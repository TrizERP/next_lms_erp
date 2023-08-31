<?php

namespace App\Http\Controllers\result\working_day_master;

use App\Http\Controllers\Controller;
use App\Models\result\working_day_master\working_day_master;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class working_day_master_controller extends Controller
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

        return is_mobile($type, "result/working_day_master/show", $data, "view");
    }

    public function getData()
    {
        $responce_arr = [];

        return working_day_master::select('result_working_day_master.id', 'academic_year.title as term_name',
            'academic_year.term_id',
            'acs.title as grade_name', 'acs.id as grade_id', 's.name as standard_name', 's.id as standard_id',
            'result_working_day_master.total_working_day')
            ->join('academic_year', [
                'academic_year.term_id'          => 'result_working_day_master.term_id',
                'academic_year.sub_institute_id' => 'result_working_day_master.sub_institute_id',
                'academic_year.syear' => 'result_working_day_master.syear',
            ])
            ->join('standard as s', 's.id', '=', 'result_working_day_master.standard')
            ->join('academic_section as acs', 'acs.id', '=', 's.grade_id')
            ->where(['result_working_day_master.sub_institute_id' => session()->get('sub_institute_id'), 'result_working_day_master.syear' => session()->get('syear')])
            ->get()->toArray();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create()
    {
        return view('result/working_day_master/add');
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
            $exam = new working_day_master([
                'standard'          => $arr,
                'term_id'           => $request->get('term'),
                'total_working_day' => $request->get('total_working_day'),
                'syear'             => session()->get('syear'),
                'sub_institute_id'  => session()->get('sub_institute_id'),
            ]);
            $exam->save();
        }
        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "working_day_master.index", $res, "redirect");
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

        return is_mobile($type, "result/working_day_master/edit", $data, "view");
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

//        foreach ($_REQUEST['standard'] as $id => $arr) {
//            working_day_master::where([
//                'sub_institute_id' => session()->get('sub_institute_id'),
//                'standard' => $arr
//            ])->delete();

        $data = [
            'standard'          => $request->get('standard'),
            'term_id'           => $request->get('term'),
            'total_working_day' => $request->get('total_working_day'),
            'sub_institute_id'  => session()->get('sub_institute_id'),
            'syear'             => session()->get('syear'),
        ];

        working_day_master::where(["id" => $id])->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];
        $type = $request->input('type');

        return is_mobile($type, "working_day_master.index", $res, "redirect");
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
        working_day_master::where([
            'id' => $id,
        ])->delete();
        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "working_day_master.index", $res, "redirect");
    }

}
