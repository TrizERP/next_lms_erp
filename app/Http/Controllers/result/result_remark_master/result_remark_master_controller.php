<?php

namespace App\Http\Controllers\result\result_remark_master;

use App\Http\Controllers\Controller;
use App\Models\result\result_remark_mater\result_remark_master;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class result_remark_master_controller extends Controller
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

        return is_mobile($type, "result/result_remark_master/show_result_remark_master", $data, "view");
    }

    public function getData()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        return DB::table('result_remark_masters')
            ->join('academic_year', [
                'academic_year.term_id'          => 'result_remark_masters.marking_period_id',
                'academic_year.sub_institute_id' => 'result_remark_masters.sub_institute_id',
                'academic_year.syear' => 'result_remark_masters.syear',
            ])
//                        ->join('division', 'division.id', '=', 'result_master_confrigration.division_id')
            ->select('result_remark_masters.*', 'academic_year.title as term_name')
            ->where(['result_remark_masters.sub_institute_id' => $sub_institute_id,'result_remark_masters.syear' => $syear])
            ->get()->toArray();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create()
    {
        return view('result/result_remark_master/add_result_remark_master');
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

        $school = new result_remark_master([
            'syear'             => session()->get('syear'),
            'sub_institute_id'  => session()->get('sub_institute_id'),
            'marking_period_id' => $request->get('term'),
            'title'             => $request->get('title'),
            'remark_status'     => $request->get('result_status'),
            'sort_order'        => $request->get('sort_order'),
        ]);
        $school->save();

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "result_remark_master.index", $res, "redirect");
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
    public function edit($id, Request $request)
    {
        $type = $request->input('type');
        $data = result_remark_master::find($id);

        return is_mobile($type, "result/result_remark_master/edit_result_remark_master", $data, "view");
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
        $data = [
            'syear'             => session()->get('syear'),
            'sub_institute_id'  => session()->get('sub_institute_id'),
            'marking_period_id' => $request->get('term'),
            'title'             => $request->get('title'),
            'remark_status'     => $request->get('result_status'),
            'sort_order'        => $request->get('sort_order'),
        ];

        result_remark_master::where(["id" => $id])->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "result_remark_master.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id, Request $request)
    {
        $type = $request->input('type');
        result_remark_master::where(["id" => $id])->delete();
        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "result_remark_master.index", $res, "redirect");
    }

}
