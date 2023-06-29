<?php

namespace App\Http\Controllers\fees;

use App\Http\Controllers\Controller;
use App\Models\fees\tblfeesConfigModel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use function App\Helpers\is_mobile;

class tblfeesConfigController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $data = tblfeesConfigModel::where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;

        return is_mobile($type, "fees/show_fees_config", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create()
    {
        return view('fees/add_fees_config');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');

        $auto_head_value = $request->get('auto_head_counting');
        $auto_head_value = $auto_head_value ?? '';
        $request->request->add(['auto_head_counting' => $auto_head_value]);

        $show_month = $request->get('show_month');
        $show_month = $show_month ?? 0;
        $request->request->add(['show_month' => $show_month]);
        $file_name = "";
        if ($request->hasFile('fees_bank_logo')) {
            $file = $request->file('fees_bank_logo');
            $originalname = $file->getClientOriginalName();
            $name = $request->get('fees_bank_logo').date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = "bank_logo_".$name.'.'.$ext;
            $path = $file->storeAs('public/fees/', $file_name);
        }

        $request->request->add(['bank_logo' => $file_name]); //add request
        $data = $this->saveData($request);

        $data = tblfeesConfigModel::where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get();

        $res['status_code'] = "1";
        $res['message'] = "Fees Config Added successfully";
        $res['data'] = $data;

        return is_mobile($type, "fees_config_master.index", $res);
    }

    public function saveData(Request $request)
    {
        $newRequest = $request->all();
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArray['syear'] = $syear;
        $finalArray['created_by'] = $user_id;
        unset($newRequest['fees_bank_logo']);
        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'submit') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArray[$key] = $value;
            }
        }

        tblfeesConfigModel::insert($finalArray);

        return DB::getPdo()->lastInsertId();
    }

    public function updateData(Request $request)
    {
        $newRequest = $request->all();
        $id = $newRequest['id'];
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $finalArray['sub_institute_id'] = $sub_institute_id;
        unset($newRequest['fees_bank_logo']);
        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'id') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArray[$key] = $value;
            }
        }

        return tblfeesConfigModel::where(['id' => $id])->update($finalArray);
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
     * @return Application|Factory|View
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $editData = tblfeesConfigModel::find($id)->toArray();
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return view('fees/edit_fees_config', ['data' => $editData]);
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
        $type = $request->input('type');
        $auto_head_value = $request->get('auto_head_counting');
        $auto_head_value = $auto_head_value ?? '';
        $request->request->add(['auto_head_counting' => $auto_head_value]);

        $show_month = $request->get('show_month');
        $show_month = $show_month ?? 0;
        $request->request->add(['show_month' => $show_month]);

        $file_name = "";
        if ($request->hasFile('fees_bank_logo')) {
            $file = $request->file('fees_bank_logo');
            $originalname = $file->getClientOriginalName();
            $name = $request->get('fees_bank_logo').date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = "bank_logo_".$name.'.'.$ext;
            $path = $file->storeAs('public/fees/', $file_name);
        }
        if ($file_name != "") {
            $request->request->add(['bank_logo' => $file_name]); //add request
        }

        $request->request->add(['id' => $id]); //add request

        $data = $this->updateData($request);

        $res['status_code'] = "1";
        $res['message'] = "Fees Config Updated successfully";
        $res['data'] = $data;

        return is_mobile($type, "fees_config_master.index", $res);
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
        tblfeesConfigModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Fees Config deleted successfully";

        return is_mobile($type, "fees_config_master.index", $res);
    }
}
