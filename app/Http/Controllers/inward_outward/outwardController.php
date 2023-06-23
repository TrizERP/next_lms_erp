<?php

namespace App\Http\Controllers\inward_outward;

use App\Http\Controllers\Controller;
use App\Models\inward_outward\outwardModel;
use App\Models\inward_outward\physical_file_locationModel;
use App\Models\inward_outward\place_masterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use function App\Helpers\is_mobile;

class outwardController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $outward_data['message'] = $data_arr['message'];
            }
        }

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $outward = DB::table('outward')
            ->join('place_master', 'outward.place_id', '=', 'place_master.id')
            ->join('physical_file_location', 'outward.file_location_id', '=', 'physical_file_location.id')
            ->select('outward.*', 'place_master.title as place_id', 'physical_file_location.title as file_name',
                'physical_file_location.file_location as file_location_id')
            ->where(['outward.sub_institute_id' => $sub_institute_id, 'outward.syear' => $syear])
            ->get();

        $outward_data['status_code'] = 1;
        $outward_data['data'] = $outward;
        $type = $request->input('type');

        return is_mobile($type, "inward_outward/show_outward", $outward_data, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $data = place_masterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        $data1 = physical_file_locationModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $get_outward_no = DB::table("outward")
            ->selectRaw('(IFNULL(MAX(CAST(outward_number AS INT)),0) + 1) AS outward_no')
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->where("syear", "=", $syear)
            ->get()->toArray();

        view()->share('outward_no', $get_outward_no[0]->outward_no);

        return view('inward_outward/add_outward', ['menu' => $data], ['menu1' => $data1]);
    }

    public function store(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $file_name = $file_size = $ext = "";
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
//            $path = $file->storeAs('public/outward/', $file_name);
            $path = Storage::disk('digitalocean')->putFileAs('public/outward/', $file, $file_name, 'public');
        }

        $outward = new outwardModel([
            'place_id'         => $request->get('place_id'),
            'file_location_id' => $request->get('file_location_id'),
            'outward_number'   => $request->get('outward_number'),
            'title'            => $request->get('title'),
            'description'      => $request->get('description'),
            'attachment'       => $file_name,
            'attachment_size'  => $file_size,
            'attachment_type'  => $ext,
            'acedemic_year'    => $request->get('acedemic_year'),
            'outward_date'     => $request->get('outward_date'),
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
        ]);
        $outward->save();

        $message['status_code'] = "1";
//        $message = [
//            "message" => "Outward Added Succesfully",
//        ];
        $message = outwardModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $type = $request->input('type');

        return is_mobile($type, "add_outward.index", $message, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $syear = $request->session()->get('syear');

        $data = outwardModel::find($id);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $editdata = place_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $editdata1 = physical_file_locationModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $get_outward_no = DB::table("outward")
            ->selectRaw('(IFNULL(MAX(CAST(outward_number AS INT)),0) + 1) AS outward_no')
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->where("syear", "=", $syear)
            ->get()->toArray();

        view()->share('outward_no', $get_outward_no[0]->outward_no);
        view()->share('menu', $editdata);
        view()->share('menu1', $editdata1);

        return view('inward_outward/add_outward', ['data' => $data]);
    }

    public function update(Request $request, $id)
    {
        $data = [
            'place_id'         => $request->get('place_id'),
            'file_location_id' => $request->get('file_location_id'),
            'outward_number'   => $request->get('outward_number'),
            'title'            => $request->get('title'),
            'description'      => $request->get('description'),
            'acedemic_year'    => $request->get('acedemic_year'),
            'outward_date'     => $request->get('outward_date'),
        ];

        $file_name = $file_size = $ext = "";
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
            //$path = $file->storeAs('public/outward/', $file_name);
            $path = Storage::disk('digitalocean')->putFileAs('public/outward/', $file, $file_name, 'public');
        }
        if ($file_name != "") {
            $data['attachment'] = $file_name;
            $data['attachment_size'] = $file_size;
            $data['attachment_type'] = $ext;
        }

        outwardModel::where(["id" => $id])->update($data);
        $message['status_code'] = "1";
        $message = [
            "message" => "Outward Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_outward.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        outwardModel::where(["id" => $id])->delete();
        $message['status_code'] = "1";
        $message = [
            "message" => "Outward Deleted successfully",
        ];

        return is_mobile($type, "add_outward.index", $message, "redirect");

    }
}
