<?php

namespace App\Http\Controllers\hostel_management;


use App\Http\Controllers\Controller;
use App\Models\hostel_management\hostel_masterModel;
use App\Models\hostel_management\hosteltypemasterModel;
use App\Models\settings\tblcustomfieldsModel;
use App\Models\settings\tblfields_dataModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use function App\Helpers\is_mobile;
use function App\Helpers\ValidateInsertData;


class hostel_masterController extends Controller
{
    public function index(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $dataCustomFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "hostel_master"])
            ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1)')
            ->get();

        $fieldsData = tblfields_dataModel::get()->toArray();
        $i = 0;
        $finalfieldsData = [];

        foreach ($fieldsData as $key => $value) {
            $finalfieldsData[$value['field_id']][$i]['display_text'] = $value['display_text'];
            $finalfieldsData[$value['field_id']][$i]['display_value'] = $value['display_value'];
            $i++;
        }

        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $hostel_data['message'] = $data_arr['message'];
            }
        }

        $users = DB::table('hostel_master')
            ->join('hostel_type_master', 'hostel_master.hostel_type_id', '=', 'hostel_type_master.id')
            ->select('hostel_master.*', 'hostel_type_master.hostel_type as hostel_type_id')
            ->where('hostel_master.sub_institute_id', '=', $sub_institute_id)->get();

        $hostel_data['status_code'] = 1;
        $hostel_data['data'] = $users;

        if (count($finalfieldsData) > 0) {
            $inward_data['data_fields'] = $finalfieldsData;
        }
        $type = $request->input('type');

        return is_mobile($type, "hostel_management/show_hostel", $hostel_data, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = hosteltypemasterModel::where(['sub_institute_id' => $sub_institute_id])->get();

        $dataCustomFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "hostel_master"])
            ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1)')
            ->get();

        $fieldsData = tblfields_dataModel::get()->toArray();

        $i = 0;
        $finalfieldsData = [];
        foreach ($fieldsData as $key => $value) {
            $finalfieldsData[$value['field_id']][$i]['display_text'] = $value['display_text'];
            $finalfieldsData[$value['field_id']][$i]['display_value'] = $value['display_value'];
            $i++;
        }

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;
        $res['custom_fields'] = $dataCustomFields;
        if (count($finalfieldsData) > 0) {
            $res['data_fields'] = $finalfieldsData;
        }
        $res['menu'] = $data;

        $type = $request->input('type');

        return is_mobile($type, "hostel_management/add_hostel_master", $res, "view");
    }

    public function store(Request $request)
    {

        ValidateInsertData('hostel_master', $request);

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');

        $file_name = "";
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $originalname = $file->getClientOriginalName();
            $name = date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
            $path = $file->storeAs('public/hostel_master/', $file_name);
        }
        $request->request->add(['image' => $file_name]);

        $dataCustomFields = tblcustomfieldsModel::select('field_name')
            ->where([
                'status'     => "1",
                'table_name' => "hostel_master",
                'field_type' => "file",
            ])->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1)')
            ->get()->toArray();

        foreach ($dataCustomFields as $key => $value) {
            $file_name = '';

            if ($request->hasFile($value['field_name'])) {
                $file = $request->file($value['field_name']);
                $originalname = $file->getClientOriginalName();
                $name = $value['field_name']."_".$request->input('user_name').date('YmdHis');
                $ext = File::extension($originalname);
                $file_name = $name.'.'.$ext;
                $path = $file->storeAs('public/hostel_master/', $file_name);
                $request->files->remove($value['field_name']);
                $request->request->add([$value['field_name'] => $file_name]); //add request
            }

        }

        $hostel = new hostel_masterModel([
            'code'             => $request->get('code'),
            'name'             => $request->get('name'),
            'description'      => $request->get('description'),
            'warden'           => $request->get('warden'),
            'warden_contact'   => $request->get('warden_contact'),
            'hostel_type_id'   => $request->get('hostel_type_id'),
            'sub_institute_id' => $sub_institute_id,
        ]);
        $hostel->save();

        $res['status_code'] = 1;
        $res['message'] = "Hostel Details Added Succesfully.";
//        $res['data'] = $data;
        $res = hostel_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();

        $type = $request->input('type');

        return is_mobile($type, "hostel_management/add_hostel_master", $res, "view");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = hostel_masterModel::find($id);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $editdata = hosteltypemasterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        view()->share('menu', $editdata);

        return view('hostel_management/add_hostel_master', ['data' => $data]);
    }

    public function update(Request $request, $id)
    {
        ValidateInsertData('hostel_master', 'update');

        $data = [
            'code'           => $request->get('code'),
            'name'           => $request->get('name'),
            'description'    => $request->get('description'),
            'warden'         => $request->get('warden'),
            'warden_contact' => $request->get('warden_contact'),
            'hostel_type_id' => $request->get('hostel_type_id'),
        ];

        hostel_masterModel::where(["id" => $id])->update($data);

        $message['status_code'] = "1";
        $message = [
            "message" => "Data Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_hostel_master.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        hostel_masterModel::where(["id" => $id])->delete();

        $message['status_code'] = "1";
        $message = [
            "message" => "Data Deleted successfully",
        ];

        return is_mobile($type, "add_hostel_master.index", $message, "redirect");
    }

    public function hostelList(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $hostel_type_id = $request->input('hostel_type_id');

        $extraSearchArray = [];

        $extraSearchArray['sub_institute_id'] = $sub_institute_id;
        if ($hostel_type_id != '') {
            $extraSearchArray['hostel_type_id'] = $hostel_type_id;
        }

        return hostel_masterModel::select('id', 'name')->where($extraSearchArray)->get()->toArray();
    }
}
