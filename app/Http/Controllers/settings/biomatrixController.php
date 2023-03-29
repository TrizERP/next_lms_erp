<?php

namespace App\Http\Controllers\settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class biomatrixController extends Controller
{
    public function index(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $this->get_data();
        $type = $request->input('type');

        return is_mobile($type, "settings/biomatrix/show", $res, "view");
    }

    public function create(Request $request)
    {
        return view('settings/biomatrix/add');
    }

    public function get_data()
    {
        return DB::table('biomatrix')->get();
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data = [
            'biomatrix_id'     => $request['biomatrix_id'],
            'sub_institute_id' => $sub_institute_id,
        ];

        DB::table('biomatrix')->insert($data);

        $res['status_code'] = "1";
        $res['message'] = "Biomatrix added successfully";

        $type = $request->input('type');

        return is_mobile($type, "biomatrix.index", $res);
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = DB::table('biomatrix')->find($id);

        return is_mobile($type, "settings/biomatrix/edit", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = [
            'biomatrix_id'     => $request['biomatrix_id'],
            'sub_institute_id' => $sub_institute_id,
        ];

        DB::table('biomatrix')->where(["id" => $id])->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];
        $type = $request->input('type');

        return is_mobile($type, "biomatrix.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        DB::table('biomatrix')->where('id', $id)->delete();
        $res['status_code'] = "1";
        $res['message'] = "Biomatrix Setting deleted successfully";
        $type = "";

        return is_mobile($type, "biomatrix.index", $res);
    }
}
