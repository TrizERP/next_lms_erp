<?php

namespace App\Http\Controllers\fees\fees_reconciliation;

use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use App\Models\school_setup\sub_std_mapModel;
use App\Models\fees\fees_collect;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function PHPUnit\Framework\fileExists;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class fees_reconciliation_upload_sheet_controller extends Controller
{
    public function fees_reconciliation(Request $request)
    {
        $get_fees_reconciliations = DB::table('fees_reconciliation')->where(['sub_institute_id' => session()->get('sub_institute_id')])->get()->toArray();
      
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['get_fees_reconciliations'] = $get_fees_reconciliations;

        return is_mobile($type, 'fees/fees_reconciliation/fees_reconciliation', $res, "view");
    }
    
    public function store_fees_reconciliation_data(Request $request)
    {
        // Get the uploaded file
        $file = $request->file('attachment');
        
        // Check if a file was uploaded
        if (!$file) {
            return "No file uploaded.";
        }

        // Get the file extension
        $ext = $file->getClientOriginalExtension();

        // Check if the file is an Excel file
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            return "Invalid file format. Only Excel files (xlsx or xls) are allowed.";
        }

        // Generate a timestamp
        $timestamp = time();

        // Construct the final file name
        $fileName = 'fees_reconciliation_' . $timestamp . '.' . $ext;

        // Store the file in the 'public/bazar' directory
        $path = $file->storeAs('public/fees_reconciliation', $fileName);

        // Read data from the Excel file
        $spreadsheet = IOFactory::load(storage_path("app/$path"));
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $worksheet->toArray();
        
        // Assuming the Excel file has headers, skip the first row
        $headers = array_shift($data);

        // Get the current date and time
        $now = now();

        // Get the total number of rows
        $rowCount = count($data);

        $success = true; // Initialize success flag to true
        
        // Assuming you have a database model for 'sharebazar_position', you can use Eloquent to insert the data
        if($rowCount!=0) 
        {
            for ($i = 0; $i < $rowCount; $i++) 
            { // Exclude the last row
                $row = $data[$i];

                $check = DB::table('fees_reconciliation')->where(['reference_no' => $row[1], 'payer_opted_mode' => $row[18]])->get()->toArray();
                
                if(empty($check))
                {
                    $last_insert_ids = DB::table('fees_reconciliation')->insertGetId([
                        'icid' => $row[0],
                        'reference_no' => $row[1],
                        'subMerchant_id' => $row[2],
                        'PGAmount' => $row[3],
                        'student_name' => $row[4],
                        'mobile_no' => $row[5],
                        'email_iD' => $row[6],
                        'CN_student' => $row[7],
                        'sports_type' => $row[8],
                        'tenure' => $row[9],
                        'batch' => $row[10],
                        'UPIVPA' => $row[11],
                        'TRANID' => $row[12],
                        'merchant_opted_mode' => $row[13],
                        'tran_date' => $row[14],
                        'tran_amount' => $row[15],
                        'PROCESSINGFEE' => $row[16],
                        'SERVICETAX' => $row[17],
                        'payer_opted_mode' => $row[18],
                        'recon_date' => $row[19],
                        'settlement_date' => $row[20],
                        'transaction_process' => $row[21],
                        'UNIQUE_REF_NUMBER' => $row[22],
                        'VAN_NUMBER' => $row[23],
                        'student_id' => 'Null',
                        'term_id' => 'Null',
                        'receipt_no' => 'Null',
                        'standard_id' => 'Null',
                        'paymode' => 'Null',
                        'bank_detail' => 'Null',
                        'amount' => 'Null',
                        'sub_institute_id' => session()->get('sub_institute_id'),
                        'created_at' => $now,
                        'updated_at' => 'Null',
                    ]);
                
                    $check_fees = DB::table('fees_reconciliation')->where(['id' => $last_insert_ids, 'reference_no' => $row[1], 'payer_opted_mode' => $row[18], 'sub_institute_id' => session()->get('sub_institute_id')])->get()->toArray();
                } 
                else
                {
                    $check_fees = $check;
                }
                
                if(!empty($check_fees))
                {
                    // DB::enableQueryLog();
                    $get_fees_collect = fees_collect::select([
				        'student_id',
				        DB::raw('GROUP_CONCAT(term_id) AS term_id'),
				        'receipt_no',
				        'standard_id',
				        'payment_mode',
				        'cheque_bank_name',
				        DB::raw('SUM(amount) AS amount')
				    ])
                    ->where([
                    	'cheque_no' => $row[1],
                    	'sub_institute_id' => session()->get('sub_institute_id'),
                    	'syear' => session()->get('syear')
                    ])
                    ->get()
                    ->toArray();

                    if(!empty($get_fees_collect))
                    {
                        $student_id= $get_fees_collect[0]->student_id;
                        $term_id = $get_fees_collect[0]->term_id; 
                        $receipt_no = $get_fees_collect[0]->receipt_no;
                        $standard_id = $get_fees_collect[0]->standard_id;
                        $paymode = $get_fees_collect[0]->payment_mode;
                        $bank_detail = $get_fees_collect[0]->cheque_bank_name;
                        $amount = $get_fees_collect[0]->amount;
                        // dd(DB::getQueryLog($check_fees));
                        
                        DB::table('fees_reconciliation')->where('id', $check_fees[0]->id)->update([
                            'student_id' => $student_id,
                            'term_id' => $term_id,
                            'receipt_no' => $receipt_no,
                            'standard_id' => $standard_id,
                            'paymode' => $paymode,
                            'bank_detail' => $bank_detail,
                            'amount' => $amount,
                            'updated_at' => $now,
                        ]);

                        DB::table('fees_collect')->where(['student_id'=>$student_id,'cheque_no' => $row[1], 'sub_institute_id' => session()->get('sub_institute_id'), 'syear' => session()->get('syear')])->update([
                            'conciliation' => 1,
                        ]);
                    }
                }
            }
        } 
        else
        {
            $success=false;
        }
        
        // Check if data insertion was successful
        if ($success) 
        {
            // Provide a success response message
            $message = "Fees Reconciliation Uploaded Successfully";
        } 
        else 
        {
            // Provide an error response message if insertion failed
            $message = "Error occurred while inserting data";
        }

        $request->session()->flash('message', $message);
        $request->session()->flash('status_code', $success ? 1 : 0);

        $type = $request->input('type');

        return is_mobile($type, "fees_reconciliation_upload_sheet", [], "redirect");
    }
}
