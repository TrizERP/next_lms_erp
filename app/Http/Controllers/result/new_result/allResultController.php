<?php

namespace App\Http\Controllers\result\new_result;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use DB;

class allResultController extends Controller
{
    //
    public function index(Request $request){
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id') ?? $request->sub_institute_id;
        $student_id = session()->get('user_id') ?? $request->user_id;
       
        $res['result_data'] = DB::table('result_html as rh')->join('standard as std','std.id','=','rh.standard_id')->join('academic_year as ay',function($join){
            $join->on('ay.term_id','=','rh.term_id')
            ->on('ay.sub_institute_id','=','rh.sub_institute_id')
            ->on('ay.syear','=','rh.syear');
        })->selectRaw('rh.id,rh.standard_id,rh.term_id,std.name,ay.title,rh.syear')->where(['rh.sub_institute_id'=>$sub_institute_id,'student_id'=>$student_id])->orderBy('rh.syear','DESC')->groupBy('rh.syear')->groupBy('rh.term_id')->get()->toArray();
        // echo "<pre>";print_r($res);exit;

        return is_mobile($type, "result/new_result/student_results/all_result", $res, "view");        
    }

    public function create(Request $request){
        $type = $request->type;
        $res['result_data'] = DB::table('result_html as rh')->where(['rh.id'=>$request->id])->orderBy('rh.syear','DESC')->first();
        // $res['html'] = $res['result_data']->html;
        $html =$res['result_data']->html;
        $css_name = "http://".$_SERVER['SERVER_NAME'];       
        $result_css = '<link rel="stylesheet" href="'.$css_name.'/css/result.css" />';         
        $dom = '<!DOCTYPE html>
            <html>
                <head>
                   <title></title>
                   <meta charset="UTF-8">
                   <meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $dom .= "<style>

                </style>";       
        $dom .= $result_css;       
        $dom .= '</head>
                <body>
                    <div>
                       '.$html.'
                    </div>
                </body>
            </html>';

        if($type=='API'){
        $path = 'src="http://' . $_SERVER['HTTP_HOST'];
        $html = str_replace('src="', $path, $html);
        $html = str_replace('display:flex;', 'display: -webkit-box; -webkit-box-pack: center;', $html);
        $html = str_replace('##HTML_SEC##', $html, $dom);                


        //Start For Empty folder before creating new PDF
        $folder_path = $_SERVER['DOCUMENT_ROOT'] . '/storage/result_pdf/*';
        $files = glob($folder_path); // get all file names
        foreach($files as $file){ // iterate files
          if(is_file($file)) {
            unlink($file); // delete file
          }
        }
        //END For Empty folder before creating new PDF
        
        $save_path = $_SERVER['DOCUMENT_ROOT'] . '/storage/result_pdf';

        $CUR_TIME = date('YmdHis');
        $html_filename = $request->get('student_id').'_'.$CUR_TIME . ".html";
        $pdf_filename = $request->get('student_id').'_'.$CUR_TIME . ".pdf";
        
        $html_file_path = $save_path . '/' . $html_filename;
        $pdf_file_path = $save_path . '/' . $pdf_filename;
        file_put_contents($html_file_path, $html);
        htmlToPDF($html_file_path, $pdf_file_path); 
        unlink($html_file_path);

        $res['pdf_link'] = "http://".$_SERVER['SERVER_NAME']."/storage/result_pdf/".$pdf_filename;
    }else{
        $res['html'] =$dom;    
        $res['standard_id'] = $res['result_data']->standard_id;
        $res['grade_id'] = $res['result_data']->grade_id;
        $res['division_id'] = $res['result_data']->division_id;
        $res['term_id'] = $res['result_data']->term_id;
        $res['syear'] = $res['result_data']->syear;
        $res['all_stud_html'][$request->id] = $res['result_data']->html;
        $res['students_ids'][] = $res['result_data']->student_id;
    }
        return is_mobile($type, "result/new_result/student_results/result_view", $res, "view");
    }
}
