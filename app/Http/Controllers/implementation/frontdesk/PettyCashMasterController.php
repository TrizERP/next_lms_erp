<?php

namespace App\Http\Controllers\implementation\frontdesk;

use App\Http\Controllers\Controller;
use App\Models\frontdesk\PettyCashMasterModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use function App\Helpers\is_mobile;

class PettyCashMasterController extends Controller
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
        $res['data'] = $data;

        return is_mobile($type, 'frontdesk/show_pettycashmaster', $res, "view");
    }

    function getData(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return PettyCashMasterModel::select('*')
            ->where(['sub_institute_id' => $sub_institute_id])
            ->get();
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

        return is_mobile($type, 'frontdesk/add_pettycashmaster', $data, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $type = $request->input("type");
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $data = $request->except(['_method', '_token', 'submit']);

        $data['SUB_INSTITUTE_ID'] = $sub_institute_id;

        PettyCashMasterModel::insert($data);

        $res['status_code'] = "1";
        $res['message'] = "Added successfully";

        return is_mobile($type, "pettycashmaster.index", $res);
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
        $petty_data = PettyCashMasterModel::find($id);
        $data['petty_data'] = $petty_data;

        return is_mobile($type, "frontdesk/add_pettycashmaster", $data, "view");
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
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = $request->except(['_method', '_token', 'submit']);
        PettyCashMasterModel::where(["id" => $id])->update($data);
        $res = [
            "status_code" => 1,
            "message"     => "Petty Cash Master Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "pettycashmaster.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        PettyCashMasterModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Petty Cash Master Deleted Successfully";

        return is_mobile($type, "pettycashmaster.index", $res);
    }
}
