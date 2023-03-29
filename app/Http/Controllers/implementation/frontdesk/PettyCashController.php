<?php

namespace App\Http\Controllers\implementation\frontdesk;

use App\Http\Controllers\Controller;
use App\Models\frontdesk\PettyCashMasterModel;
use App\Models\frontdesk\PettyCashModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class PettyCashController extends Controller
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

        return is_mobile($type, 'frontdesk/show_pettycash', $res, "view");
    }

    function getData(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return PettyCashModel::from("petty_cash as p")
            ->select('p.*', 'pm.title as title_name', db::raw('date_format(created_on,"%Y-%m-%d") as bill_date'))
            ->join('petty_cash_master as pm', 'pm.id', '=', 'p.title_id')
            ->where(['p.sub_institute_id' => $sub_institute_id])
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
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $title_arr = PettyCashMasterModel::select('*')
            ->where(['sub_institute_id' => $sub_institute_id])
            ->get();
        $data['Title_Arr'] = $title_arr;

        return is_mobile($type, 'frontdesk/add_pettycash', $data, "view");
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
        $user_id = $request->session()->get('user_id');
        $data = $request->except(['_method', '_token', 'submit', 'bill_image']);

        if ($request->hasFile('bill_image')) {
            $image = $request->file('bill_image');
            $data['bill_image'] = $user_id.'-'.time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/pettycash');
            $image->move($destinationPath, $data['bill_image']);
        }


        $data['SUB_INSTITUTE_ID'] = $sub_institute_id;
        $data['user_id'] = $user_id;

        PettyCashModel::insert($data);

        $res['status_code'] = "1";
        $res['message'] = "Added successfully";

        return is_mobile($type, "pettycash.index", $res);
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
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $petty_data = PettyCashModel::find($id);
        $data['petty_data'] = $petty_data;
        $title_arr = PettyCashMasterModel::select('*')
            ->where(['sub_institute_id' => $sub_institute_id])
            ->get();
        $data['Title_Arr'] = $title_arr;

        return is_mobile($type, "frontdesk/add_pettycash", $data, "view");
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
            "message"     => "Petty Cash Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "pettycash.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        PettyCashModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Petty Cash Deleted Successfully";

        return is_mobile($type, "pettycash.index", $res);
    }
}
