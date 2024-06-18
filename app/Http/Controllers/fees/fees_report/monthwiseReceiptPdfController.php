<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use App\Http\Controllers\AJAXController;
use ZipArchive;
use PDF;
use File;

class monthwiseReceiptPdfController extends Controller
{
    // monthwiseReceiptPdf
    public function index(Request $request){
        $type = $request->input('type');
        $res = session()->get('data');
        return is_mobile($type, "fees/fees_report/monthwiseReceiptPdf", $res, "view");
    }

    public function create(Request $request){
        $type = $request->input('type');
        $res['from_date'] = $from_date = $request->from_date;
        $res['to_date'] = $to_date = $request->to_date;

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        // for API 
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }

        // student fees details 
        $feesDetails = DB::table('fees_collect as fc')
                       ->join('tblstudent as s','s.id','=','fc.student_id')  
                       ->join('tblstudent_enrollment as se','se.student_id','=','fc.student_id')
                        ->select('fc.*',DB::raw('count(fc.id) as total_records'),DB::raw('concat_ws(" ",COALESCE(s.first_name,"-"),COALESCE(s.middle_name,"-"),COALESCE(s.last_name,"-")) as student_name'),DB::raw('(SELECT name FROM standard where id = fc.standard_id) as standard'),DB::raw('(SELECT name FROM division where id = se.section_id) as division'),)
                        ->whereBetween('fc.receiptdate',[$from_date,$to_date])
                        ->where(['fc.sub_institute_id'=>$sub_institute_id,'fc.syear'=>$syear,'fc.is_deleted'=>"N"])
                        ->whereNull('se.end_date')
                        ->groupBy('fc.receiptdate','fc.student_id')
                        ->get()->toArray();
        // echo "<pre>";print_r($feesDetails);exit;
        $res['fees_details'] = $feesDetails;
        return is_mobile($type, "fees/fees_report/monthwiseReceiptPdf", $res, "view");
    }

    public function store(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        // Define the directory and file paths
        $directoryPath = public_path('zip_pdf/'.$sub_institute_id);

        // Check if the directory exists
        if (File::exists($directoryPath)) {
            File::cleanDirectory($directoryPath);
        } else {
            File::makeDirectory($directoryPath, 0755, true);
        }

        $AJAXController = new AJAXController;
        $getFeesCss = $AJAXController->get_FeesCss('fees_receipt');
        $fees_receipt_css = "<style>" . $getFeesCss . "</style>";
        
        $files = [];

        foreach($request->studentData as $key => $value){
            $receipt_no = str_replace('/','_',$value['receipt_no']).'.pdf';
            $fees_html = $value['fees_html'];
            $pdfFilePath = $directoryPath . '/'.$receipt_no;
            $files[]=$pdfFilePath;

            $dom = '<!DOCTYPE html>
                    <html>
                        <head>
                        <title></title>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=erpice-width, initial-scale=1.0">';
            $dom .= $fees_receipt_css; // for css 
            $dom .='</head>
                        <body>'.$fees_html.'</body>
                </html>';

            set_time_limit(300);
            // Generate the PDF
            $pdf = PDF::loadHTML($dom);
            // echo "<pre>";print_r($dom);exit;
            
            // Save the PDF to the specified path
            $pdf->save($pdfFilePath);
        }
       
        $zipFileName = 'bulk_receipt.zip';

        $zip = new ZipArchive;

        if ($zip->open(public_path($zipFileName), ZipArchive::CREATE) === TRUE) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $zip->addFile($file, basename($file));
                }
            }
            $zip->close();

            return response()->download(public_path($zipFileName))->deleteFileAfterSend(true);
        } else {
            $res['status_code']=0;
            $res['message']='Failed to download files';

            return is_mobile($type, "monthly_receipt_pdf.index", $res);
        }

        return $request;
    }
}