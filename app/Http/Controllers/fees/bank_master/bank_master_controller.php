<?php

namespace App\Http\Controllers\fees\bank_master;

use App\Http\Controllers\Controller;
use App\Models\fees\bank_master\bankmasterModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use function App\Helpers\is_mobile;

class bank_master_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['bank_data'] = $data['bank_data'];

        return is_mobile($type, 'fees/bank_master/show_bankmaster', $res, "view");
    }

    public function getData($request)
    {
        $data['bank_data'] = bankmasterModel::get()->toArray();

        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $data = [];

        return is_mobile($type, 'fees/bank_master/add_bankmaster', $data, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {

        $content = [
            'bank_name' => $request->get('bank_name'),
        ];

        bankmasterModel::insert($content);

        $res = [
            "status_code" => 1,
            "message"     => "Bank Added Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "bank_master.index", $res, "redirect");
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

        $data = bankmasterModel::find($id)->toArray();

        return is_mobile($type, "fees/bank_master/add_bankmaster", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $content = [
            'bank_name' => $request->get('bank_name'),
        ];
        bankmasterModel::where(["id" => $id])->update($content);
        $res = [
            "status_code" => 1,
            "message"     => "Bank Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "bank_master.index", $res, "redirect");
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
        bankmasterModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Bank Deleted Successfully";

        return is_mobile($type, "bank_master.index", $res);
    }

}
