<?php

namespace App\Http\Controllers\fees\other_fees_title;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Schema;
use App\Models\fees\other_fees_title\other_fees_title;
use DB;

class other_fees_title_controller extends Controller {

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
        $data['data'] = $this->getData();                
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "fees/other_fees_title/show_other", $data, "view");
    }

    function getData() 
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $sql = "SELECT ift.*,if(ift.status = 1,'Active','Inactive') as status,if(ift.include_imprest = 'Y','Yes','No') as include_imprest
            FROM fees_other_head ift
            WHERE ift.sub_institute_id = '".$sub_institute_id."' AND ift.syear = '".$syear."'";

        $sql = preg_replace('/\n+/', '', $sql);
        $result = DB::select($sql);
        return $result;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $data = array();
        
        return \App\Helpers\is_mobile($type, 'fees/other_fees_title/add_other', $data, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) 
    {
        // dd($request);
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $created_by = session()->get('user_id');
        $created_ip = $_SERVER['REMOTE_ADDR'];

        $values = array(
            'display_name' =>  $request->input('display_name'),
            'amount' => $request->input('amount'),
            'include_imprest' => $request->input('include_imprest'),
            'status' => $request->input('status'),
            'sort_order' => $request->input('sort_order'),
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear,
            'created_on' => now(),
            'created_by' => $created_by,
            'created_ip' => $created_ip
        );
        // dd($values);
        DB::table('fees_other_head')->insert($values);

        $res = array(
            "status" => 1,
            "message" => "Other Fees Title Added Successfully.",
        );

        return \App\Helpers\is_mobile($type, "other_fees_title.index", $res, "redirect");
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
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $data = DB::select("SELECT * FROM fees_other_head WHERE id = '".$id."' AND sub_institute_id = '".$sub_institute_id."' AND syear = '".$syear."' ");     
        $data = $data[0];       
        return \App\Helpers\is_mobile($type, "fees/other_fees_title/add_other", $data, "view");
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
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $updated_by = session()->get('user_id');

        $finalArr = array(
            'display_name' =>  $request->input('display_name'),
            'amount' => $request->input('amount'),
            'include_imprest' => $request->input('include_imprest'),
            'status' => $request->input('status'),
            'sort_order' => $request->input('sort_order'),
            'updated_at' => now(),
            'updated_by' => $updated_by,          
        );
        DB::table("fees_other_head")->where(['id'=>$id])->update($finalArr);

        $res = array(
            "status" => 1,
            "message" => "Other Title Updated Successfully",
        );
        return \App\Helpers\is_mobile($type, "other_fees_title.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id) {
        $type = $request->input('type');
        DB::table('fees_other_head')->where(["id" => $id])->delete();
        $res = array(
            "status" => 1,
            "message" => "Other Title Deleted Successfully.",
        );

        return \App\Helpers\is_mobile($type, "other_fees_title.index", $res, "redirect");
    }  

}
