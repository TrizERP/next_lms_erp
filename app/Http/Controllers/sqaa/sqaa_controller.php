<?php

namespace App\Http\Controllers\sqaa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\sqaa\sqaa_master;
use App\Models\sqaa\sqaa_mark;
use App\Models\sqaa\sqaa_document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Response;
use DB;
use PDF;

class sqaa_controller extends Controller
{
    //
    public function index()
    {
        $type="";
        $res['level_1'] = sqaa_master::where(['parent_id'=>0,"level"=>1])->orderBy('sort_order')->get()->toArray();
        // echo "<pre>";print_r($level_1);exit;
        return is_mobile($type, "sqaa/show", $res, "view");
    }
    public function get_level(Request $request)
    {
        # code...
        if(isset($request->level_2)){
            $level_2 = sqaa_master::where(['parent_id'=>$request->level_2,"level"=>2])->orderBy('sort_order')->get()->toArray();
            return $level_2;
        }
       
        if(isset($request->level_3)){
            $level_3 = sqaa_master::where(['parent_id'=>$request->level_3,"level"=>3])->orderBy('sort_order')->get()->toArray();
            return $level_3;
        }
        if(isset($request->level_4)){
            $level_4 = sqaa_master::where(['parent_id'=>$request->level_4,"level"=>4])->orderBy('sort_order')->get()->toArray();
            return $level_4;
        }
    }

    public function store(Request $request){
        // return $request;exit;
        $type=$request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        
        if($request->lev_1 != '' && $request->lev_2 != '' && $request->lev_3 != '' && $request->lev_4 != ''){
            $menu_id = $request->lev_4;
        } else  if($request->lev_1 != '' && $request->lev_2 != '' && $request->lev_3 != ''){
            $menu_id = $request->lev_3;
        }else if($request->lev_1 != '' && $request->lev_2 != ''){
            $menu_id = $request->lev_2;

        } else if($request->lev_1 != ''){
            $menu_id = $request->lev_1;
        }else{
            $menu_id = 0;
        }
        $arr = [
            "menu_id"=>$menu_id,
            "mark"=>$request->mark,
            "created_by" => $user_id,
            "sub_institute_id" => $sub_institute_id,
        ];
        $check_data=$this->check_data($arr,"sqaa_marks");
        // echo "<pre>";print_r($check_data);exit;
        if(!$check_data){
        $data = new sqaa_mark();
        $data->menu_id=$menu_id;
        $data->mark=$request->mark ?? 0;
        $data->created_by = $user_id;
        $data->sub_institute_id = $sub_institute_id;
        $data->created_at = now();
        $data->save();
        }
            $res['status_code']=1;
            $res['message']="Data inserted";
         
        if (!empty($request->input('document'))) {

            for ($i = 0; $i < count($request->input('document')); $i++) {
                $documentData = [
                    'document' => $request->input('document')[$i],
                ];
                $document =$request->input('document')[$i];
                $reasons =$request->input('reasons')[$i];                
                $availability =$request->input('availability')[$i] ?? 'no';

                // Check if a file is present for this row
                if ($request->input('availability')[$i] =="yes" && $request->hasFile('files') && $request->file('files')[$i]->isValid()) {
                    $file = $request->file('files')[$i];
                    $filename = $file->getClientOriginalName();
                    $path = Storage::disk('digitalocean')->putFileAs('public/sqaa/', $file, $filename, 'public');

                }else{
                   $filename = "";        
                }
                $doc_arr=[
                    "menu_id"=>$menu_id,
                    "title"=>$document,
                    "availability"=>$availability,
                    "file"=>$filename,    
                    "created_by" => $user_id,
                    "sub_institute_id" => $sub_institute_id,                    
                ];
                $check_doc_data=$this->check_data($doc_arr,"sqaa_documents");
                if(!$check_doc_data){
                $data_doc = new sqaa_document();
                $data_doc->menu_id=$menu_id;
                $data_doc->title=$document;
                $data_doc->reasons=$reasons;                
                $data_doc->availability=$availability;
                $data_doc->file=$filename;        
                $data_doc->created_by = $user_id;
                $data_doc->sub_institute_id = $sub_institute_id;
                $data_doc->created_at = now();
                $data_doc->save();
                }
            }
           
        }else{
                $res['status_code']=0;
                $res['message']="Document not inserted";
        }
       
        return is_mobile($type, "sqaa_master.index", $res);
        // return $request;
    }

    function check_data($request,$table){
        $check_table_data = DB::table($table)->where($request)->get()->toArray();
        return $check_table_data;
    }

    public function edit_gen_pdf(Request $request) {
        $res='';
        $this->generatePdf($request);
        $type='';
        // return is_mobile($type, "sqaa/generatePdf", $res, "view");
        return redirect()->back();
    }

    public function generatePdf(Request $request) {
        $sub_institute_id = session()->get('sub_institute_id');
        $htmlContent = $request->input('html_content');
        $menu_id = $request->input('menu_id_pdf');
        $doc_id = $request->input('doc_id_pdf');
        
        $pdf = PDF::loadHTML($htmlContent);
        $filename = $sub_institute_id.'_pdf_menu'.$menu_id.'_doc'.$doc_id.'.pdf';
        $filePath= 'sqaa/' . $filename;
        $pdf->save(public_path('sqaa/' . $filename));
        
        $fileUrl = asset('sqaa/' . $filename);
        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
    
        // Return the PDF file as a response
        return response()->file($filePath, $headers);
        // return redirect()->route('gen-pdf', ['text' => $res['text'], 'path' => $res['path']]);
        // return $request;exit;
    }      
  public function unlink_file(Request $request){
    if (file_exists($request->file)) {
        if (unlink($request->file)) {
            echo 'File deleted successfully.';
        } else {
            echo 'Failed to delete the file.';
        }
    } else {
        echo 'File not found.';
    }
  }
}
