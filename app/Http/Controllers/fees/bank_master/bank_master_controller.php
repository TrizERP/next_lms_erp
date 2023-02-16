<?php

namespace App\Http\Controllers\fees\bank_master;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\fees\map_year\map_year;
use App\Models\fees\bank_master\bankmasterModel;
use DB;
use function App\Helpers\is_mobile;

class bank_master_controller extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {
        $data = $this->getData($request);      
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['bank_data'] = $data['bank_data'];        
        return is_mobile($type,'fees/bank_master/show_bankmaster',$res,"view");  
    }

    public function getData($request){
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data['bank_data'] = array();

        $data['bank_data'] = bankmasterModel::select('*')
        ->get()->toArray();

        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        $type = $request->input('type');        
        $data = array(); 

        return is_mobile($type,'fees/bank_master/add_bankmaster',$data,"view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {

        $content = array(
            'bank_name' => $request->get('bank_name'),            
        );
                        
        bankmasterModel::insert($content);
                    
        $res = array(
            "status_code" => 1,
            "message" => "Bank Added Successfully",
        );
        $type = $request->input('type');
        return is_mobile($type, "bank_master.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request,$id) {
        $type = $request->input('type');                
                        
        $data = bankmasterModel::find($id)->toArray();       
        
        return is_mobile($type, "fees/bank_master/add_bankmaster", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        $content = array(
            'bank_name' => $request->get('bank_name'),            
        );
        bankmasterModel::where(["id" => $id])->update($content);
        $res = array(
            "status_code" => 1,
            "message" => "Bank Updated Successfully",
        );
        $type = $request->input('type');
        return is_mobile($type, "bank_master.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request,$id) {
        $type = $request->input('type');
        bankmasterModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Bank Deleted Successfully";
        return is_mobile($type, "bank_master.index", $res);
    }

}
