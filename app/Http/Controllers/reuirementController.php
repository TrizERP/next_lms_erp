<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\requirementGathering;
use function App\Helpers\is_mobile;
use App\Models\tblmenumasterModel;
use Illuminate\Support\Facades\DB;

class reuirementController extends Controller
{
    //
    // requirement gathering index page only for triz
    public function index(Request $request){
        $type=$request->type;
        $res['TrizProcess'] = requirementGathering::where('sub_institute_id',0)->get()->toArray();
        return is_mobile($type, "requirements.show", $res, 'view');
    }
    // requirement gathering create page only for triz
    public function create(Request $request){
        $type=$request->type;
        $res['allMenu'] = tblmenumasterModel::where('menu_title','!=','')->whereNotNull('menu_title')->groupBy('menu_title')->orderBy('sort_order')->get()->toArray();
        return is_mobile($type, "requirements.add", $res, 'view');
    }
    // requirement gathering create page only onboarding and triz
    public function store(Request $request){
        // return $request->all();exit;
        $type=$request->type;
        $sub_institute_id = $request->sub_institute_id;
        $editorVal = $request->requirements;
        $user_id = session()->get('user_id');
        $user_name = session()->get('name');

        if($request->has('process')){
            $menuDetails = explode('/',$request->menuDetails);
            $menu_id = $menuDetails[0];
            $menu_name = $menuDetails[1];

            $message ="Process Added Successfully";
        }else{
            $menu_id = $request->menu_id;
            $menu_name = $request->menu_name;
            
            $message ="Requirement Added Successfully";
        }
        $dataArr = [
            'menu_id'=>$menu_id,
            'menu_name'=>$menu_name,
            'sub_institute_id'=>$sub_institute_id
        ];

        $checkData = requirementGathering::where($dataArr)->first();
        $i=0;
        if(!empty($checkData)){
            $dataArr['created_by_id']=$user_id;
            $dataArr['created_by_name']=$user_name;
            $dataArr['requirements']=$editorVal;
            $dataArr['updated_at']=now();

            $upadte = requirementGathering::where('id',$checkData->id)->update($dataArr);
            $i=1;
        }else{
            $dataArr['created_by_id']=$user_id;
            $dataArr['created_by_name']=$user_name;
            $dataArr['requirements']=$editorVal;
            $dataArr['created_at']=now();

            $insert =requirementGathering::insert($dataArr);
            $i=1;
        }
        if($i==1){
            $res['status_code']=1;
            $res['message']=$message;
        }else{
            $res['status_code']=0;
            $res['message']="Failed to Add";
        }
       return is_mobile($type, "requirements.index", $res);
    }

    public function edit(Request $request,$id){
        $type=$request->type;
        $res['allMenu'] = tblmenumasterModel::where('menu_title','!=','')->whereNotNull('menu_title')->groupBy('menu_title')->orderBy('sort_order')->get()->toArray();
        $res['editData'] = requirementGathering::where('id',$id)->first(); 
        return is_mobile($type, "requirements.edit", $res, 'view');
    }
    public function update(Request $request,$id){
        $type=$request->type;
        $sub_institute_id = $request->sub_institute_id;
        $editorVal = $request->requirements;
        $user_id = session()->get('user_id');
        $user_name = session()->get('name');

        $dataArr['created_by_id']=$user_id;
        $dataArr['created_by_name']=$user_name;
        $dataArr['requirements']=$editorVal;
        $dataArr['updated_at']=now();
        // echo "<pre>";print_r($id);exit;

        $upadte = requirementGathering::where('id',$id)->update($dataArr);
        
        if($upadte){
            $res['status_code']=1;
            $res['message']="Process Updated Successfully";
        }else{
            $res['status_code']=0;
            $res['message']="Failed to Update";
        }
       return is_mobile($type, "requirements.index", $res);
    }

    public function destroy(Request $request,$id){
        $type=$request->type;
        $delete = requirementGathering::where('id',$id)->delete();
        if($delete){
            $res['status_code']=1;
            $res['message']="Process Updated Successfully";
        }else{
            $res['status_code']=0;
            $res['message']="Failed to Update";
        }
       return is_mobile($type, "requirements.index", $res);
    }

    public function ReportData(Request $request){
        $type=$request->type;

        $res['allRequirement'] = requirementGathering::join('school_setup as ss','ss.Id','=','requirement_gathering.sub_institute_id')
        ->select('requirement_gathering.*','ss.SchoolName','ss.mobile','ss.email','ss.ContactPerson')
        ->where('sub_institute_id','!=',0)->get()->toArray();
        // echo "<pre>";print_r($res);exit;
        return is_mobile($type, "requirements.report", $res, 'view');
    }
}
