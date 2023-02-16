<?php

namespace App\Http\Controllers\fees\fees_circular;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\DB;
use App\Models\fees\fees_circular\feesCircularMasterModel;

class feesCircularMasterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $data = feesCircularMasterModel::select('fees_circular_master.*','standard.name as standard_name',
                'academic_section.title as grade_name')
                ->join('standard', 'standard.id', '=', 'fees_circular_master.standard_id')
                ->join('academic_section', 'academic_section.id', '=', 'fees_circular_master.grade_id')
                ->where(['fees_circular_master.sub_institute_id' => $sub_institute_id,'fees_circular_master.syear' => $syear])
                ->get();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;

        return is_mobile($type, "fees/fees_circular/show_fees_circular_master", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('fees/fees_circular/add_fees_circular_master');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {        
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $data = $this->saveData($request);            
        $data = feesCircularMasterModel::where(['sub_institute_id' => $sub_institute_id,'syear' => $syear])->get();
    
        $res['status_code'] = "1";
        $res['message'] = "Fees Circular Master Added successfully";
        $res['data'] = $data;
        return is_mobile($type, "fees_circular_master.index", $res);
    }

    public function saveData(Request $request)
    {
        $newRequest = $request->all();
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');
        $created_on = date('Y-m-d h:i:s');
        $created_ip_address = $_SERVER['REMOTE_ADDR'];
        $finalArray['grade_id'] = $newRequest['grade'];
        $finalArray['standard_id'] = $newRequest['standard'];
        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArray['syear'] = $syear;
        $finalArray['created_by'] = $user_id;
        $finalArray['created_on'] = $created_on;
        $finalArray['created_ip_address'] = $created_ip_address;
        
        foreach($newRequest as $key => $value){
            if($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'grade' && $key != 'standard'){
                if(is_array($value)){
                    $value = implode(",",$value);
                }
                $finalArray[$key] = $value;
            }
        }
        feesCircularMasterModel::insert($finalArray);
        return  $id = DB::getPdo()->lastInsertId();
    }

    public function updateData(Request $request)
    {
        $newRequest = $request->all();
        $id = $newRequest['id'];
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        $updated_on = date('Y-m-d h:i:s');

        $finalArray['grade_id'] = $newRequest['grade'];
        $finalArray['standard_id'] = $newRequest['standard'];
        $finalArray['updated_by'] = $user_id;
        $finalArray['updated_on'] = $updated_on;

        foreach($newRequest as $key => $value){
            if($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'id' && $key != 'grade' && $key != 'standard'){
                if(is_array($value)){
                    $value = implode(",",$value);
                }
                $finalArray[$key] = $value;
            }
        }
        
        $data = feesCircularMasterModel::where(['id'=>$id])->update($finalArray);
        return $data;       
        
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
        $editData = feesCircularMasterModel::find($id)->toArray();
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return view('fees/fees_circular/edit_fees_circular_master',['data' => $editData]);
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
       
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $type = $request->input('type');  
        $request->request->add(['id' => $id]);
        $data = $this->updateData($request);
        
        $res['status_code'] = "1";
        $res['message'] = "Fees Circular Master Updated successfully";
        $res['data'] = $data;
        return is_mobile($type, "fees_circular_master.index", $res);
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
        feesCircularMasterModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Fees Circular Master deleted successfully";
        return is_mobile($type, "fees_circular_master.index", $res);
    }
}
