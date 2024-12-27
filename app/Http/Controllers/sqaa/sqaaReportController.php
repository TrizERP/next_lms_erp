<?php

namespace App\Http\Controllers\sqaa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\sqaa\sqaa_master;
use App\Models\sqaa\sqaa_mark;
use App\Models\sqaa\sqaa_document;
use Illuminate\Support\Facades\Storage;
use DB;

class sqaaReportController extends Controller
{
    //
     public function index(Request $request)
    {
        $type="";
        $res['level_1'] = sqaa_master::where(['level'=>1])->get()->toArray();
         return is_mobile($type, "sqaa/report", $res, "view");
    }

    public function create(Request $request)
    { 
        if($request->has('level_4_sel') && $request->input('level_4_sel')!=null){
            $menu_id = $request->input('level_4_sel');
        }else if($request->has('level_3_sel')  && $request->input('level_3_sel')!=null){
            $menu_id = $request->input('level_3_sel');
        }else if($request->has('level_2_sel') && $request->input('level_2_sel') !== null){
            $menu_id = $request->input('level_2_sel');
        }        
        else if($request->has('level_1') && $request->input('level_1') !== null){
            $menu_id = $request->input('level_1');
        }
        else{
            $menu_id = 0;
        }
        $type="";
        $sub_institute_id = session()->get('sub_institute_id');
        
        $res['level_1'] = sqaa_master::where(['level'=>1])->get()->toArray();
        $res['level_2_val']=sqaa_master::where(['level'=>2,'parent_id'=>$request->input('level_1')])->get()->toArray();
        $res['level_3_val']=sqaa_master::where(['level'=>3,'parent_id'=>$request->input('level_2_sel')])->get()->toArray();
        $res['level_4_val']=sqaa_master::where(['level'=>4,'parent_id'=>$request->input('level_3_sel')])->get()->toArray();
      
        $res['data']=sqaa_mark::from('sqaa_marks as s')
        ->join('sqaa_master as sm','s.menu_id','=','sm.id')
        ->leftjoin('sqaa_documents as sd','sd.menu_id','=','sm.id')
        ->selectRaw('s.*,sm.title as menu_title,sm.description,sm.level,sd.id as document_id,sd.title as document_title,sd.availability,sd.file')
        ->where(['s.sub_institute_id'=>$sub_institute_id,'s.menu_id'=>$menu_id])
        ->get()->toArray();

        if(empty($res['data'])){
            $res['status_code']=0;
            $res['message']='Data not found';
        }
        $res['selected_1']=$request->input('level_1');
        $res['selected_2']=$request->input('level_2_sel');
        $res['selected_3']=$request->input('level_3_sel');
        $res['selected_4']=$request->input('level_4_sel');            

        return is_mobile($type, "sqaa/report", $res, "view");
        
    }

    public function destroy(Request $request,$id){
        $documentId = $request->input('document_id');
        // DELETE document
        if ($documentId !== null) {
            $document = sqaa_document::find($documentId);
            $document->delete();
        }
         // DELETE Mark
        $mark = sqaa_mark::find($id);
        $mark->delete();
        $type=$request->input('type');
        $res['status_code']=1;
        $res['message']="Record deleted succefully";
        return is_mobile($type, "sqaa_report_master.index", $res, "redirect");
    }

    public function edit(Request $request,$id){
        $sub_institute_id = session()->get('sub_institute_id');
        $type=$request->input('type');
        $documentId = $request->input('document_id');
      
        $res['data']=sqaa_mark::from('sqaa_marks as s')
        ->join('sqaa_master as sm','s.menu_id','=','sm.id')
        ->leftjoin('sqaa_documents as sd','sd.menu_id','=','s.menu_id')
        ->selectRaw('s.*,sm.title as menu_title,sm.level,sm.description,sm.level,sd.id as document_id,sd.title as document_title,sd.availability,sd.file,GROUP_CONCAT(sm.parent_id) as parent_id')
        ->where(['s.sub_institute_id'=>$sub_institute_id,'s.id'=>$id])
        ->when($documentId !== null, function ($query) use ($documentId) {
            return $query->where('sd.id', $documentId);
        })
        ->first();
        
      if($res['data']->level == 4){
            $get_level_3 = sqaa_master::where(['level'=>3,'id'=>$res['data']->parent_id])->first();
            $get_level_2 = sqaa_master::where(['level'=>2,'id'=>$get_level_3->parent_id])->first();
            $get_level_1 = sqaa_master::where(['level'=>1,'id'=>$get_level_2->parent_id])->first();
            $res['sel_4'] = $res['data']->menu_id;            
            $res['sel_3'] = $get_level_3->id;
            $res['sel_2'] = $get_level_2->id;                        
            $res['sel_1'] = $get_level_1->id; 
       }else if($res['data']->level == 3){
            $get_level_2 = sqaa_master::where(['level'=>2,'id'=>$res['data']->parent_id])->first();
            $get_level_1 = sqaa_master::where(['level'=>1,'id'=>$get_level_2->parent_id])->first(); 
            $res['sel_3'] = $res['data']->menu_id;                        
            $res['sel_2'] = $get_level_2->id;            
            $res['sel_1'] = $get_level_1->id;    
                                    
        }else if($res['data']->level == 2){
            $get_level_1 = sqaa_master::where(['level'=>1,'id'=>$res['data']->parent_id])->first();  
            $res['sel_2'] = $res['data']->menu_id;                        
            $res['sel_1'] = $get_level_1->id;       
        }else if($res['data']->level == 1){
            $res['sel_1'] = $res['data']->menu_id;       
        }

        $res['level_1'] = sqaa_master::where(['level'=>1])->get()->toArray();
        $res['level_2_val']=sqaa_master::where(['level'=>2,'parent_id'=> $res['sel_1'] ?? 0])->get()->toArray();
        $res['level_3_val']=sqaa_master::where(['level'=>3,'parent_id'=> $res['sel_2'] ?? 0])->get()->toArray();
        $res['level_4_val']=sqaa_master::where(['level'=>4,'parent_id'=> $res['sel_3'] ?? 0])->get()->toArray();
       
        // echo "<pre>";print_r($res['data']);exit;
        return is_mobile($type, "sqaa/edit", $res, "view");        
    }

    public function update(Request $request,$id){
        // return $request;exit;        
        if($request->has('level_4_sel') && $request->input('level_4_sel')!=null){
            $menu_id = $request->input('level_4_sel');
        }else if($request->has('level_3_sel')  && $request->input('level_3_sel')!=null){
            $menu_id = $request->input('level_3_sel');
        }else if($request->has('level_2_sel') && $request->input('level_2_sel') !== null){
            $menu_id = $request->input('level_2_sel');
        }        
        else if($request->has('level_1') && $request->input('level_1') !== null){
            $menu_id = $request->input('level_1');
        }
        else{
            $menu_id = 0;
        }

        $update_marks = sqaa_mark::where('id',$id)->update([
            "menu_id"=>$menu_id,
            "updated_at"=>now(),
        ]);
        if ($request->input('availability') =="yes" && $request->hasFile('files') && $request->file('files')->isValid()) {
            $file = $request->file('files');
            $filename = $file->getClientOriginalName();
            $path = Storage::disk('digitalocean')->putFileAs('public/sqaa/', $file, $filename, 'public');
            $update_document = sqaa_document::where('id', $request->document_id)->update([
                "menu_id"=>$menu_id,
                'title'=>$request->input('document'),
                'availability'=>$request->input('availability'),
                'file'=>$filename,
                "updated_at"=>now(),
            ]);
        }else{
            $update_document = sqaa_document::where('id', $request->document_id)->update([
                "menu_id"=>$menu_id,
                'title'=>$request->input('document'),
                'availability'=>$request->input('availability'),
                "updated_at"=>now(),
            ]);       
        }
        return redirect()->route('sqaa_report_master.edit', ['id' => $id, 'document_id' => $request->document_id]);
    }

    public function sqaaDocReport(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }

        if($request->has('level_4_sel') && $request->input('level_4_sel')!=null){
            $menu_id = $request->input('level_4_sel');
        }else if($request->has('level_3_sel')  && $request->input('level_3_sel')!=null){
            $menu_id = $request->input('level_3_sel');
        }else if($request->has('level_2_sel') && $request->input('level_2_sel') !== null){
            $menu_id = $request->input('level_2_sel');
        }        
        else if($request->has('level_1') && $request->input('level_1') !== null){
            $menu_id = $request->input('level_1');
        }
        else{
            $menu_id = 0;
        }
        $selAvail = $request->input('selAvail');
        if($selAvail==''){
            $selAvail = 'Yes';
        }

        $res['docData'] = DB::table('sqaa_documents as a')
            ->selectRaw('a.*,c.title as menuTitle')
            ->join('sqaa_documant_master as b','b.id','=','a.document_id')
            ->join('sqaa_master as c','c.id','=','b.menu_id')
            ->where('a.sub_institute_id',$sub_institute_id)
            ->when($selAvail!='all',function($q) use($selAvail){
                $q->where('availability',$selAvail);
            })
            ->when($menu_id!=0,function($q) use($menu_id){
                $q->where('a.menu_id',$menu_id);
            })
            ->orderBy('c.sort_order')
            ->get()->toArray();

        $res['level_1'] = sqaa_master::where(['level'=>1])->get()->toArray();
        $res['level_2_val']=sqaa_master::where(['level'=>2,'parent_id'=>$request->input('level_1')])->get()->toArray();
        $res['level_3_val']=sqaa_master::where(['level'=>3,'parent_id'=>$request->input('level_2_sel')])->get()->toArray();
        $res['level_4_val']=sqaa_master::where(['level'=>4,'parent_id'=>$request->input('level_3_sel')])->get()->toArray();

        $res['selected_1']=$request->input('level_1');
        $res['selected_2']=$request->input('level_2_sel');
        $res['selected_3']=$request->input('level_3_sel');
        $res['selected_4']=$request->input('level_4_sel');  
        $res['selAvail']=$request->input('selAvail');  
        return is_mobile($type, "sqaa/docReport", $res, "view"); 
    }
}
