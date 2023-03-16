<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use App\Models\school_setup\SchoolModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use function App\Helpers\is_mobile;
use function App\Helpers\ValidateInsertData;

class schoolController extends Controller
{

    public function index(Request $request)
    {

        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->getData();
        $type = $request->input('type');

        return is_mobile($type, "school_setup/show_school", $school_data, "view");
    }

    public function create(Request $request)
    {
        return view('school_setup/add_school');
    }

    public function getData()
    {
        return SchoolModel::orderBy('id')->get();
    }

    public function store(Request $request)
    {

        ValidateInsertData('school_setup', $request);

        $file_name = "";
        if ($request->hasFile('Logo')) {
            $file = $request->file('Logo');
            $originalname = $file->getClientOriginalName();
            $name = date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
            $path = $file->storeAs('public/school/', $file_name);
        }

        $school = new SchoolModel([
            'SchoolName'     => $request->get('SchoolName'),
            'ShortCode'      => $request->get('ShortCode'),
            'ContactPerson'  => $request->get('ContactPerson'),
            'Mobile'         => $request->get('Mobile'),
            'Email'          => $request->get('Email'),
            'ReceiptHeader'  => $request->get('ReceiptHeader'),
            'ReceiptAddress' => $request->get('ReceiptAddress'),
            'FeeEmail'       => $request->get('FeeEmail'),
            'ReceiptContact' => $request->get('ReceiptContact'),
            'SortOrder'      => $request->get('SortOrder'),
            'Logo'           => $file_name,
        ]);
        $school->save();

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "add_school.index", $res, "redirect");
    }

    public function edit(Request $request, $id)
    {

        $type = $request->input('type');
        $data = SchoolModel::find($id);

        return is_mobile($type, "school_setup/add_school", $data, "view");
    }

    public function update(Request $request, $id)
    {

        ValidateInsertData('school_setup', 'update');

        $data = [
            'SchoolName'     => $request->get('SchoolName'),
            'ShortCode'      => $request->get('ShortCode'),
            'ContactPerson'  => $request->get('ContactPerson'),
            'Mobile'         => $request->get('Mobile'),
            'Email'          => $request->get('Email'),
            'ReceiptHeader'  => $request->get('ReceiptHeader'),
            'ReceiptAddress' => $request->get('ReceiptAddress'),
            'FeeEmail'       => $request->get('FeeEmail'),
            'ReceiptContact' => $request->get('ReceiptContact'),
            'SortOrder'      => $request->get('SortOrder'),
        ];

        $file_name = "";
        if ($request->hasFile('Logo')) {
            $file = $request->file('Logo');
            $originalname = $file->getClientOriginalName();
            $name = date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
            $path = $file->storeAs('public/school/', $file_name);
        }
        if ($file_name != "") {
            $data['Logo'] = $file_name;
        }
        SchoolModel::where(["Id" => $id])->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_school.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        SchoolModel::where(["Id" => $id])->delete();
        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "add_school.index", $res, "redirect");
    }

}
