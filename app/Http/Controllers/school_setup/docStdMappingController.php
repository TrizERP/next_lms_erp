<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\school_setup\academic_sectionModel;
use App\Models\school_setup\standardModel;
use App\Models\student\documentTypeModel;
use Validator;
use DB;

class docStdMappingController extends Controller
{
    //
    public function index(Request $request){
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        if($type=="API"){
            $sub_institute_id=$request->sub_institute_id;
        }
       
        $res['mappedData']= DB::table('tblstudent_doc_std_mapping as dsm')
        ->join('student_document_type as sdt', 'sdt.id', '=', 'dsm.document_type_id')
        ->join('standard as s', 's.id', '=', 'dsm.standard_id')
        ->join('academic_section as g','g.id','=','s.grade_id')
        ->where('dsm.sub_institute_id', $sub_institute_id)
        ->select(
            'dsm.*',
            'sdt.document_type',
            DB::raw('GROUP_CONCAT(DISTINCT s.name ORDER BY s.sort_order) as standard'),
            DB::raw('GROUP_CONCAT(DISTINCT g.title ORDER BY g.sort_order) as grade_name'),
            DB::raw('GROUP_CONCAT(DISTINCT dsm.standard_id) as all_std')
        )
        ->orderBy('dsm.id','DESC')
        ->groupBy('dsm.document_type_id')
        ->get()
        ->toArray();
    
        // echo "<pre>";print_r($res['mappedData']);exit;
        return is_mobile($type, 'school_setup/doc_std_map/index', $res, 'view');
    }

    public function create(Request $request){
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        if($type=="API"){
            $sub_institute_id=$request->sub_institute_id;
        }
        $res['documentTypeList']= documentTypeModel::where('user_type','student')->where('status',1)->pluck('document_type','id')->toArray();

        return is_mobile($type, 'school_setup/doc_std_map/create', $res, 'view');
    }

    public function store(Request $request){
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        if($type=="API"){
            $sub_institute_id=$request->sub_institute_id;
        }

        $validate =  Validator::make($request->all(), [
            'standard' => 'required',
            'document_type' => 'required',
        ]);

        $standards = $request->standard;
        $document_type = $request->document_type;
        $i=0;
        foreach ($standards as $key => $stdId) {
            $inData = ['sub_institute_id'=>$sub_institute_id,'document_type_id'=>$document_type,'standard_id'=>$stdId];

            $check =  DB::table('tblstudent_doc_std_mapping')->where($inData)->first();
            if(empty($check)){
                $inData['created_at'] = now();
                $insert = DB::table('tblstudent_doc_std_mapping')->insert($inData); 
                $i++;
            }
        }
        if($i==0){
            $res['status_code']=0;
            $res['message']="Failed to Add!!";
        }else{
            $res['status_code']=1;
            $res['message']="Added Successfully !!";
        }
       
        return is_mobile($type, 'document_standard_mapping.index', $res);
    }

    public function edit(Request $request,$id){
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        if($type=="API"){
            $sub_institute_id=$request->sub_institute_id;
        }
        $res['editData'] = DB::table('tblstudent_doc_std_mapping as dsm')
        ->join('standard as s', 's.id', '=', 'dsm.standard_id')
        ->where('dsm.document_type_id', $id)
        ->select(
            'dsm.*',
            DB::raw('GROUP_CONCAT(DISTINCT dsm.standard_id) as all_std'),
            DB::raw('GROUP_CONCAT(DISTINCT s.grade_id) as all_grade')
        )
        ->groupBy('dsm.document_type_id')
        ->first();
        // echo "<pre>";print_r($res['editData']);exit;
        $res['documentTypeList']= documentTypeModel::where('user_type','student')->where('status',1)->pluck('document_type','id')->toArray();

        return is_mobile($type, 'school_setup/doc_std_map/edit', $res, 'view');
    }

    public function update(Request $request,$id){
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        if($type=="API"){
            $sub_institute_id=$request->sub_institute_id;
        }

        $validate =  Validator::make($request->all(), [
            'standard' => 'required',
            'document_type' => 'required',
        ]);

        $standards = $request->standard;
        $document_type = $request->document_type;
        $checkSeletedStd = DB::table('tblstudent_doc_std_mapping')->where('document_type_id',$id)->whereIn('standard_id',$standards)->delete();

        $i=0;
        foreach ($standards as $key => $stdId) {
            $inData = ['sub_institute_id'=>$sub_institute_id,'document_type_id'=>$document_type,'standard_id'=>$stdId];

            $check =  DB::table('tblstudent_doc_std_mapping')->where($inData)->first();
            if(empty($check)){
                $inData['updated_at'] = now();
                $insert = DB::table('tblstudent_doc_std_mapping')->insert($inData); 
                $i++;
            }
        }
      
        if($i>0){
            $res['status_code']=1;
            $res['message']="Updated Successfully !!";
        }else{
            $res['status_code']=0;
            $res['message']="Failed to Update!!";
        }
       
        return is_mobile($type, 'document_standard_mapping.index', $res);
    }

    public function destroy(Request $request,$id){
        $type = $request->input('type');
        
        $delete =  DB::table('tblstudent_doc_std_mapping')->where('document_type_id',$id)->delete();
        if($delete){
            $res['status_code']=1;
            $res['message']="Updated Successfully !!";
        }else{
            $res['status_code']=0;
            $res['message']="Failed to Update!!";
        }
       
        return is_mobile($type, 'document_standard_mapping.index', $res);
    }
}
