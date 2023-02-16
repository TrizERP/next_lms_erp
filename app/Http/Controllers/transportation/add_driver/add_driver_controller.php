<?php

namespace App\Http\Controllers\transportation\add_driver;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\transportation\add_driver\add_driver;
use DB;

class add_driver_controller extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
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
        return \App\Helpers\is_mobile($type, "transportation/add_driver/show", $school_data, "view");
    }

    public function getData()
    {
        $data = add_driver::where(['sub_institute_id' => session()->get('sub_institute_id')])->get();
        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $dataStore = array();
        return \App\Helpers\is_mobile($type, 'transportation/add_driver/add', $dataStore, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) 
    {
        $sub_institute_id = session()->get('sub_institute_id');

        $file_name = "";
        if ($request->hasFile('icard_icon')) {
            $file = $request->file('icard_icon');
            $originalname = $file->getClientOriginalName();
            $name = date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = $name . '.' . $ext;
            $path = $file->storeAs('public/driver/', $file_name);
        }

        $add_driver = new add_driver([
            "first_name" => $request->get('first_name'),
            "last_name" => $request->get('last_name'),
            "mobile" => $request->get('mobile'),
            "type" => $request->get('type'),
            'icard_icon' => $file_name,
            'sub_institute_id' => $sub_institute_id
        ]);
        $add_driver->save();

        $res = array(
            "status_code" => 1,
            "message" => "Data Added Successfully."
        );

        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "add_driver.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = add_driver::find($id)->toArray();
        return \App\Helpers\is_mobile($type, "transportation/add_driver/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $sub_institute_id = session()->get('sub_institute_id');        
        $data1 = array([
                "first_name" => $request->get('first_name'),
                "last_name" => $request->get('last_name'),
                "mobile" => $request->get('mobile'),
                "type" => $request->get('type')
        ]);
        $data1 = $data1[0];

        $file_name = "";
        if ($request->hasFile('icard_icon')) {
            $file = $request->file('icard_icon');
            $originalname = $file->getClientOriginalName();
            $name = date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = $name . '.' . $ext;
            $path = $file->storeAs('public/driver/', $file_name);
        }
        if ($file_name != "") {
            $data1['icard_icon'] = $file_name;
        }

        add_driver::where(["id" => $id])->update($data1);

        $res = array(
            "status_code" => 1,
            "message" => "Data Updated Successfully."
        );
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "add_driver.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        add_driver::where(["id" => $id])->delete();
        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted Successfully.",
        );

        return \App\Helpers\is_mobile($type, "add_driver.index", $res, "redirect");
    }

}
