<?php

namespace App\Http\Controllers\hostel_management;

use App\Http\Controllers\Controller;
use App\Models\hostel_management\hostel_visitor_masterModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;

class hostel_visitor_masterController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $visitor_data['message'] = $data_arr['message'];
            }
        }

        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data = hostel_visitor_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $visitor_data['status_code'] = 1;
        $visitor_data['data'] = $data;
        $type = $request->input('type');

        return is_mobile($type, "hostel_management/show_hostel_visitor", $visitor_data, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = hostel_visitor_masterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        return view('hostel_management/add_hostel_visitor_master', ['menu' => $data]);
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $visitor = new hostel_visitor_masterModel([
            'name'             => $request->get('name'),
            'contact'          => $request->get('contact'),
            'email'            => $request->get('email'),
            'coming_from'      => $request->get('coming_from'),
            'to_meet'          => $request->get('to_meet'),
            'relation'         => $request->get('relation'),
            'meet_date'        => $request->get('meet_date'),
            'in_time'          => $request->get('in_time'),
            'out_time'         => $request->get('out_time'),
            'sub_institute_id' => $sub_institute_id,
        ]);
        $visitor->save();

        $message['status_code'] = "1";
//        $message = [
//            "message" => "Visitor details Added Succesfully",
//        ];
        $message = hostel_visitor_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $type = $request->input('type');

        return is_mobile($type, "add_hostel_visitor_master.index", $message, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = hostel_visitor_masterModel::find($id);

        return is_mobile($type, "hostel_management/add_hostel_visitor_master", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $visitor = [
            'name'        => $request->get('name'),
            'contact'     => $request->get('contact'),
            'email'       => $request->get('email'),
            'coming_from' => $request->get('coming_from'),
            'to_meet'     => $request->get('to_meet'),
            'relation'    => $request->get('relation'),
            'meet_date'   => $request->get('meet_date'),
            'in_time'     => $request->get('in_time'),
            'out_time'    => $request->get('out_time'),
        ];

        hostel_visitor_masterModel::where(["id" => $id])->update($visitor);

        $message['status_code'] = "1";
        $message = [
            "message" => "Data Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_hostel_visitor_master.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        hostel_visitor_masterModel::where(["id" => $id])->delete();

        $message['status_code'] = "1";
        $message = [
            "message" => "Data Deleted successfully",
        ];

        return is_mobile($type, "add_hostel_visitor_master.index", $message, "redirect");
    }
}
