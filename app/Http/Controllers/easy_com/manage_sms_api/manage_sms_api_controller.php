<?php

namespace App\Http\Controllers\easy_com\manage_sms_api;

use App\Http\Controllers\Controller;
use App\Models\easy_com\manage_sms_api\manage_sms_api;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use function App\Helpers\is_mobile;

class manage_sms_api_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }

        $data['data'] = $this->getData();

        $type = $request->input('type');

        return is_mobile($type, "easy_comm/manage_sms_api/show", $data, "view");
    }

    public function getData()
    {
        $sub_institute_id = session()->get('sub_institute_id');

        return manage_sms_api::where(['sub_institute_id' => $sub_institute_id])
            ->get();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create()
    {
        return view('easy_comm/manage_sms_api/add');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $school = new manage_sms_api([
            'url'              => $request->get('url'),
            'pram'             => $request->get('pram'),
            'mobile_var'       => $request->get('mobile_var'),
            'text_var'         => $request->get('text_var'),
            'last_var'         => $request->get('last_var'),
            'sub_institute_id' => session()->get('sub_institute_id'),
        ]);
        $school->save();

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "manage_sms_api.index", $res, "redirect");
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
        $data = manage_sms_api::find($id);

        return is_mobile($type, "easy_comm/manage_sms_api/edit", $data, "view");
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
        $data = array(
            [
                'url'              => $request->get('url'),
                'pram'             => $request->get('pram'),
                'mobile_var'       => $request->get('mobile_var'),
                'text_var'         => $request->get('text_var'),
                'last_var'         => $request->get('last_var'),
                'sub_institute_id' => session()->get('sub_institute_id'),
            ],
        );

        $data = $data[0];

        manage_sms_api::where(["id" => $id])->update($data);

        $res = array(
            "status_code" => 1,
            "message"     => "Data Saved",
        );
        $type = $request->input('type');

        return is_mobile($type, "manage_sms_api.index", $res, "redirect");
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
        manage_sms_api::where(["id" => $id])->delete();
        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "manage_sms_api.index", $res, "redirect");
    }

}
