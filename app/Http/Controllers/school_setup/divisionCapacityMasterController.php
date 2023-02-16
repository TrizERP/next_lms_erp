<?php

namespace App\Http\Controllers\school_setup;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\divisionModel;
use App\Models\school_setup\classteacherModel;
use App\Models\school_setup\divisionCapacityMasterModel;
use App\Models\user\tbluserModel;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\DB;


class divisionCapacityMasterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {        
        $data = $this->getData($request);               
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;
        $res['grade_id'] = '';        
        $res['standard_id'] = '';        
        $res['division_id'] = '';
        $res['button'] = "Add";       
        return is_mobile($type,'school_setup/show_division_capacity_master',$res,"view");  
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');  
        $syear = $request->session()->get('syear');                
        $data = divisionCapacityMasterModel::from("division_capacity_master as dc")
        ->select('dc.*','a.title as academic_section_name','s.name as standard_name','d.name as division_name')
        ->join('academic_section as a', 'a.id', '=', 'dc.grade_id')
        ->join('standard as s', 's.id', '=', 'dc.standard_id')
        ->join('division as d', 'd.id', '=', 'dc.division_id')               
        ->where(['dc.sub_institute_id'=>$sub_institute_id,'dc.syear'=>$syear])
        ->get();       
        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {        
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id'); 
        $syear = $request->session()->get('syear');
        $created_by = $request->session()->get('user_id');

        $division_capacity = new divisionCapacityMasterModel([
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear,
            'grade_id' => $request->get('grade'),
            'standard_id' => $request->get('standard'),
            'division_id' => $request->get('division'),
            'capacity' => $request->get('capacity'),
            'created_on' => date('Y-m-d H:i:s'),
            'created_by' => $created_by,
            'created_ip' => $_SERVER['REMOTE_ADDR']
        ]);
        // dd($division_capacity);
        $division_capacity->save();

        $res = array(
                "status_code" => 1,
                "message" => "Divison Capacity Added Successfully",
                "class" => "alert-success",
            );
        return is_mobile($type, "division_capacity_master.index", $res, "redirect");
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
    public function edit(Request $request,$id)
    {
        $type = $request->input('type');
        $res = divisionCapacityMasterModel::find($id)->toArray();
        $data['id'] = $res['id'];      
        $data['grade_id'] = $res['grade_id'];      
        $data['standard_id'] = $res['standard_id'];              
        $data['division_id'] = $res['division_id']; 
        $data['capacity'] = $res['capacity']; 
        $data['data'] = $this->getData($request);
        $data['button'] = "Update";
        return is_mobile($type,'school_setup/show_division_capacity_master',$data,"view");  
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
        $updated_by = $request->session()->get('user_id');

        $capacity_data = array(
            'grade_id' => $request->get('grade'),
            'standard_id' => $request->get('standard'),
            'division_id' => $request->get('division'),
            'capacity' => $request->get('capacity'),
            'updated_on' => date('Y-m-d H:i:s'),
            'updated_by' => $updated_by
        );
        divisionCapacityMasterModel::where(["id" => $id])->update($capacity_data);

        $data = array(
                    "status_code" => 1,
                    "message" => "Division Capacity Updated Successfully",
                    "class" => "alert-success"           
                );
                      
        $type = $request->input('type');
        return is_mobile($type, "division_capacity_master.index", $data, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request,$id)
    {
        $type = $request->input('type');
        divisionCapacityMasterModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Division Capacity Deleted Successfully";
        return is_mobile($type, "division_capacity_master.index", $res);
    }
}
