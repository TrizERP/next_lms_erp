<?php
namespace App\Http\Controllers\library;

use App\Http\Controllers\Controller;
use App\Models\school_setup\sub_std_mapModel;
use App\Models\LibraryBook;
use App\Models\LibraryBookCirculation;
use App\Models\LibraryItem;
use App\Models\student\tblstudentModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use Picqer\Barcode\BarcodeGeneratorPNG;
use PDF;

class LibraryReportController extends Controller
{
    use GetsJwtToken;

    public function index(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');

        $data['get_material_resource_type'] = DB::table('library_books')->select('id','material_resource_type')->where(['sub_institute_id' => $sub_institute_id])->groupBy('material_resource_type')->get()->toArray();

        $data['get_author_name'] = DB::table('library_books')->select('id','author_name')->where(['sub_institute_id' => $sub_institute_id])->groupBy('author_name')->get()->toArray();

        $data['get_publisher_name'] = DB::table('library_books')->select('id','publisher_name')->where(['sub_institute_id' => $sub_institute_id])->groupBy('publisher_name')->get()->toArray();

        $data['get_publish_place'] = DB::table('library_books')->select('id','publish_place')->where(['sub_institute_id' => $sub_institute_id])->groupBy('publish_place')->get()->toArray();

        $data['get_language'] = DB::table('library_books')->select('id','language')->where(['sub_institute_id' => $sub_institute_id])->groupBy('language')->get()->toArray();

        $data['get_subject'] = DB::table('library_books')->select('id','subject')->where(['sub_institute_id' => $sub_institute_id])->groupBy('subject')->get()->toArray();

        $type = $request->input('type');

        return is_mobile($type, "library/library_report", $data, "view");
    }

    public function show_library_report(Request $request)
    {
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $term_id = session()->get('term_id');
        $type = $request->input('type');
        $report_of = $request->input('report_of');
        $material_resource = $request->input('material_resource');
        $author = $request->input('author');
        $publisher_name = $request->input('publisher_name');
        $publishing_place = $request->input('publishing_place');
        $language = $request->input('language');
        $subject = $request->input('subject');

        if ($report_of == 'material_resource') 
        {
            $get_material_resources = DB::table("library_books")
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->when($request->material_resource,function($q) use($material_resource){
            $q->where("material_resource_type", "=", $material_resource);
            })
            ->get()->toArray();
            
            $data['material_resources'] = $get_material_resources;

            return is_mobile($type, "library/material_resource_show", $data, "view");
        }

        if ($report_of == 'author') 
        {
            $get_author_names = DB::table("library_books")
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->when($author,function($q) use($author){
                $q->where("author_name", "=", $author);
            })
            ->get()->toArray();
            
            $data['author_names'] = $get_author_names;

            return is_mobile($type, "library/author_show", $data, "view");
        }

        if ($report_of == 'publisher_name') 
        {
            $get_publisher_names = DB::table("library_books")
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->when($publisher_name,function($q) use($publisher_name){
                $q->where("publisher_name", "=", $publisher_name);
            })
            ->get()->toArray();
            
            $data['publisher_names'] = $get_publisher_names;

            return is_mobile($type, "library/publisher_name_show", $data, "view");
        }

        if ($report_of == 'publishing_place') 
        {
            $get_publishing_places = DB::table("library_books")
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->when($publishing_place,function($q) use($publishing_place){
                $q->where("publish_place", "=", $publishing_place);
            })
            ->get()->toArray();
            
            $data['publishing_places'] = $get_publishing_places;

            return is_mobile($type, "library/publishing_place_show", $data, "view");
        }

        if ($report_of == 'language') 
        {
            $get_language = DB::table("library_books")
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->when($language,function($q) use($language){
                $q->where("language", "=", $language);
            })
            ->get()->toArray();
            
            $data['language'] = $get_language;

            return is_mobile($type, "library/language_show", $data, "view");
        }

        if ($report_of == 'subject') 
        {
            $get_subject = DB::table("library_books")
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->when($subject,function($q) use($subject){
                $q->where("subject", "=", $subject);
            })
            ->get()->toArray();
            
            $data['subject'] = $get_subject;

            return is_mobile($type, "library/subject_show", $data, "view");
        }
    }


    //book issue report
    public function bookIssueDueReport(Request $request){
        $type=$request->type;
        $res =[];
        if (session()->has('data')) {
            $data_arr = session('data');
            if (isset($data_arr['message'])) {
                $res['status_code'] = $data_arr['status_code'];
                $res['message'] = $data_arr['message'];                
            }
        }
        return is_mobile($type, "library/reports/bookIssueDue", $res, "view");
    }

    public function bookIssueDueReportCreate(Request $request){
        $type=$request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $res['grade_id']=$grade_id = $request->grade;
        $res['standard_id']=$standard_id = $request->standard;
        $res['division_id']=$division_id = $request->division;        
        $res['from_date']=$from_date= $request->from_date;
        $res['to_date']=$to_date = $request->to_date;
        $res['stu_name']= $name = $request->stu_name;
        $res['grno']=$grno = $request->grno;
        $res['mobile']=$mobile = $request->mobile;
        $res['report_type']=$report_type = $request->report_type;
      
        $student_data = LibraryBookCirculation::join('tblstudent as s','s.id','=','library_book_circulations.student_id')
        ->join('tblstudent_enrollment as se','se.student_id','=','s.id')
        ->join('standard as std','std.id','=','se.standard_id')
        ->join('division as d','d.id','=','se.section_id')        
        ->join('library_items as li','li.book_id','=','library_book_circulations.book_id')
        ->join('library_books as lb','lb.id','=','library_book_circulations.book_id')        
        ->selectRaw('s.id as student_id,s.enrollment_no,concat_ws(" ",s.first_name,s.last_name,s.middle_name) as student_name,s.mobile,library_book_circulations.book_id,std.name as standard,d.name as division,li.item_code,lb.title as book_title,lb.sub_title as book_sub_title,lb.publisher_name,lb.author_name,library_book_circulations.issued_date,library_book_circulations.due_date,library_book_circulations.return_date')
        ->when($name,function($q) use ($name) {
            $q->where('s.first_name',$name)->oRwhere('s.last_name',$name)->oRwhere('s.middle_name',$name);
        })
        ->when($grno,function($q) use ($grno) {
            $q->where('s.enrollment_no',$grno);
        })
        ->when($mobile,function($q) use ($mobile) {
            $q->where('s.mobile',$mobile);
        })
        ->where('library_book_circulations.sub_institute_id',$sub_institute_id);
        if($report_type=="overdue"){
            $student_data->where('library_book_circulations.issued_date','>=',$from_date)->Where('library_book_circulations.due_date','<=',$to_date)->whereNull('library_book_circulations.return_date');
        }else{
            $student_data->where('library_book_circulations.issued_date','>=',$from_date)->Where('library_book_circulations.due_date','<=',$to_date);
        }
        $issue_overdue_data=$student_data->get()->toArray();
        // echo "<pre>";print_r($student_data);exit;   
        $res['details'] =  $issue_overdue_data;   
        return is_mobile($type, "library/reports/bookIssueDue", $res, "view");
        // return is_mobile($type, "book_issue_report.index", $res);
    }

    public function PrintBarcode(Request $request){
        $type=$request->type;
        $res=[];
        return is_mobile($type, "library/reports/print_barcode", $res, "view");        
    }

    public function PrintBarcodeCreate(Request $request){
        $type=$request->type;
        $sub_institute_id=session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $res['print_type']= $print_type = $request->print_type;
        $res['search_by']= $search_by = $request->search_by;
        // echo "<pre>";print_r($res);exit;
        if($print_type=="member"){
            $data = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se','s.id','=','se.student_id')
            ->join('standard as std','std.id','=','se.standard_id')
            ->join('division as d','d.id','=','se.section_id')            
            ->selectRaw('s.id as student_id,s.enrollment_no,s.roll_no,concat_ws(" ",first_name,middle_name,last_name) as student_name,std.name as standard,d.name as division')
            ->when($search_by,function ($q) use($search_by){
                $q->where('s.enrollment_no',$search_by);
            })
            ->where('s.sub_institute_id',$sub_institute_id)
            ->where('se.syear',$syear)       
            ->whereNull('se.end_date');
        }
        else{
            $data = DB::table('library_items as li')
            ->join('library_books as lb','lb.id','=','li.book_id')
            ->selectRaw('li.id,li.item_code,lb.title as book_title,lb.classification')
            ->when($search_by,function ($q) use($search_by) {
                $q->where('li.item_code',$search_by);
            })
            ->where('li.sub_institute_id',$sub_institute_id);
        }
        $data = $data->get()->toArray();
        $res['details'] = $data;
        return is_mobile($type, "library/reports/print_barcode", $res, "view");        
    }

    public function generateBarcodePdf(Request $request){
       
        $barcodes = [];

        foreach ($request->check_id as $key => $value) {
            $barcodeGenerator = new BarcodeGeneratorPNG();
            // $barcode = $barcodeGenerator->getBarcode($value, $barcodeGenerator::TYPE_CODE_128);
            $barcode = $barcodeGenerator->getBarcode($value, $barcodeGenerator::TYPE_CODE_128,2,60);
            
            $image = imagecreatefromstring($barcode);
            $text = $value;
            $fontSize = 16;
            $fontColor = imagecolorallocate($image, 0, 0, 0); // Black color
            $bgColor = imagecolorallocate($image, 255, 255, 255);
            $imageWidth = imagesx($image);
            $imageHeight = imagesy($image);
                        
            $textWidth = strlen($text) * $fontSize * 0.6;
            if ($request->print_type == "member") {
            $textWidth = strlen($text) * $fontSize * 0.3;                
            }
            $textX = ($imageWidth - $textWidth) / 2;
            $textY = $imageHeight  - 10; 
            $font = 1; 

            imagefilledrectangle($image, $textX - 43, $textY - $fontSize + 14, $textX + $textWidth + 43, $textY + $fontSize - 0, $bgColor);
            $textX = ($imageWidth - $textWidth) / 2;
            $textY = $imageHeight - 10; //

            imagestring($image, $font, $textX, $textY, $text, $fontColor);

            ob_start();
            imagepng($image);
            $barcodeWithText = ob_get_clean();
            imagedestroy($image);

            if ($request->print_type == "member") {
                $barcodes[] = ['code' => $value, 'image' => $barcodeWithText, 'title' => $request->print_text[$key],'other'=>$request->print_type];
            } else {
                $barcodes[] = ['code' => $value, 'image' => $barcodeWithText, 'title' => $request->print_text[$key], 'other' => $request->print_code[$key]];
            }
        }

        // Generate PDF
        $pdf = PDF::loadView('library.reports.barcodes', ['barcodes' => $barcodes]);
        // Download the PDF
        return $pdf->download('barcodes.pdf');
    }
}
