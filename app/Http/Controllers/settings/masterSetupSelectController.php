<?php

namespace App\Http\Controllers\settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use DB;

class masterSetupSelectController extends Controller
{
    //
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
        }
        $res['masterData'] = DB::table('master_setup_select')->select('*',DB::raw('GROUP_CONCAT(fieldvalue) as allvalues'))->where('sub_institute_id',$sub_institute_id)->groupBy('type')->get()->toArray();
        return is_mobile($type, "settings/master_setup_select/index", $res, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
        }
        $res['seltypes'] = ['Qualification','Skills','Bank Transfer Type'];
        return is_mobile($type, "settings/master_setup_select/add", $res, "view");
    }

    public function store(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
        } 
        $seltype = $request->seltype;
        $fieldname = $request->fieldname;
        $selOptions = $request->selOptions;
        // // remove empty options
        // $newSel = [];
        // foreach ($selOptions as $key => $value) {
        //    if($value!=''){
        //     $newSel[]=$value;
        //    }
        // }
        // // convert array into string
        // $fieldvalue='';
        // if(isset($newSel) && !empty($newSel)){
        //     $fieldvalue = implode('||',$newSel);
        // }
        // // echo "<pre>";print_r($fieldvalue);exit;
        $inVal = ['sub_institute_id'=>$sub_institute_id,'type'=>$seltype];
        $check = DB::table('master_setup_select')->where($inVal)->first();

        if(empty($check)){
            $inVal['fieldname'] = $fieldname;
            $inVal['created_at'] = now();
            // $inVal['fieldvalue'] = $fieldvalue;

            // $insert = DB::table('master_setup_select')->insert($inVal);
            $insert = 0;
            foreach ($selOptions as $key => $value) {
                $inVal['fieldvalue'] = '';
                if($value!=''){
                    $insert++;
                    $inVal['fieldvalue'] = $value;
                    DB::table('master_setup_select')->insert($inVal);
                }
             }
            if($insert!=0){
                $res['status_code'] = 1;
                $res['message'] = "Master Setup Added Successfully!";
            }else{
                $res['status_code'] = 0;
                $res['message'] = "Master Setup Failed To Add!";
            }
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Master Setup Already Exists! for changes go to Edit";
        }
       
        return is_mobile($type, "master_setup.index", $res);
    }

    public function edit(Request $request,$id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
        }
        $res['editData'] = DB::table('master_setup_select')
        ->select('*', DB::raw('GROUP_CONCAT(fieldvalue SEPARATOR "||") as allvalues'))
        ->where('sub_institute_id', $sub_institute_id)
        ->where('type',$id)
        ->groupBy('type')
        ->first();
    
        // echo "<pre>";print_r($res);exit;
        return is_mobile($type, "settings/master_setup_select/edit", $res, "view");
    }

    public function update(Request $request,$id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
        } 
        $seltype = $request->seltype;
        $fieldname = $request->fieldname;
        $selOptions = $request->selOptions;
        $update = 0;
        $updateId=$insVal=[];

        foreach ($selOptions as $key => $value) {
            $check = DB::table('master_setup_select')->where('sub_institute_id',$sub_institute_id)->where('type',$id)->where('fieldvalue',$value)->first();
            if(!empty($check) && isset($check->id)){
                $update++;
                $updateVal = [
                    'type'=>$seltype,
                    'fieldname'=>$fieldname,
                    'fieldvalue'=>$value,
                    'sub_institute_id'=>$sub_institute_id,
                    'updated_at'=>now()
                ];
                // DB::table('master_setup_select')->where('type',$id)->delete();
                $update = DB::table('master_setup_select')->where('sub_institute_id',$sub_institute_id)->where('id',$check->id)->update($updateVal);

                $updateId[] = $check->id;
            }else{
                $update++;
                $insVal[]=$value;
            }
        }
        // deleted data which not required 
        if(!empty($updateId)){
            DB::table('master_setup_select')->where('sub_institute_id',$sub_institute_id)->where('type',$id)->whereRaw('id not in ('.implode($updateId).')')->delete();
        }

        foreach ($selOptions as $key => $value) {
            if($value!=''){
                $insertVal = [
                    'type'=>$seltype,
                    'fieldname'=>$fieldname,
                    'fieldvalue'=>$value,
                    'sub_institute_id'=>$sub_institute_id,
                    'updated_at'=>now()
                ];
                // DB::table('master_setup_select')->where('type',$id)->delete();
                $update = DB::table('master_setup_select')->insert($insertVal);
            }
        }
        if($update!=0){
            $res['status_code'] = 1;
            $res['message'] = "Master Setup Updated Successfully!";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Master Setup Failed To Update!";
        }

        return is_mobile($type, "master_setup.index", $res);
    }
}
