<?php

namespace App\Http\Controllers\settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class smtpController extends Controller
{
    public function index(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $this->get_data();
        $type = $request->input('type');

        return is_mobile($type, "settings/smtp_setting/show", $res, "view");
    }

    public function create(Request $request)
    {
        return view('settings/smtp_setting/add');
    }

    public function get_data()
    {
        return DB::table('smtp_details')->where(['sub_institute_id' => session()->get('sub_institute_id')])->get();
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data = [
            'gmail'            => $request['email'],
            'password'         => $request['password'],
            'server_address'   => $request['server_address'],
            'port'             => $request['port'],
            'sub_institute_id' => $sub_institute_id,
        ];

        DB::table('smtp_details')->insert($data);

        $res['status_code'] = "1";
        $res['message'] = "SMTP added successfully";

        $type = $request->input('type');

        return is_mobile($type, "smtp_setting.index", $res);
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = DB::table('smtp_details')->find($id);


        return is_mobile($type, "settings/smtp_setting/edit", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = [
            'gmail'            => $request['email'],
            'password'         => $request['password'],
            'server_address'   => $request['server_address'],
            'port'             => $request['port'],
            'sub_institute_id' => $sub_institute_id,
        ];

        DB::table('smtp_details')->where(["id" => $id])->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "smtp_setting.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        DB::table('smtp_details')->where('id', $id)->delete();
        $res['status_code'] = "1";
        $res['message'] = "SMTP Setting deleted successfully";
        $type = "";

        return is_mobile($type, "smtp_setting.index", $res);
    }
}
