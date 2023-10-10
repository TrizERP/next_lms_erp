<?php

namespace App\Http\Controllers\bazar;

use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use App\Models\school_setup\sub_std_mapModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function PHPUnit\Framework\fileExists;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class bulkUploadSheetController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";

        return is_mobile($type, 'bazar/bulk_upload_sheet', $res, "view");
    }

    public function bulk_position_data(Request $request)
    {
        $type = $request->input('type');
        
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";

        return is_mobile($type, 'bazar/bulk_position_data', $res, "view");
    }

    public function store_position_data(Request $request)
    {
        // Get the uploaded file
        $file = $request->file('attachment');
        $upload_date = \Carbon\Carbon::createFromFormat('d-m-Y', $request->input('upload_date'))->format('Y-m-d');
        
        // Check if a file was uploaded
        if (!$file) {
            return "No file uploaded.";
        }

        // Get the file extension
        $ext = $file->getClientOriginalExtension();

        // Check if the file is an Excel file
        if (!in_array($ext, ['xlsx', 'xls'])) {
            return "Invalid file format. Only Excel files (xlsx or xls) are allowed.";
        }

        // Generate a timestamp
        $timestamp = time();

        // Construct the final file name
        $fileName = 'bazar_position_' . $timestamp . '.' . $ext;

        // Store the file in the 'public/bazar' directory
        $path = $file->storeAs('public/bazar', $fileName);

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
        try 
        {
            for ($i = 0; $i < $rowCount - 1; $i++) 
            { // Exclude the last row
                $row = $data[$i];
                DB::table('sharebazar_position')->insert([
                    'date' => $row[0],
                    'client_id' => $row[1],
                    'exchange' => $row[2],
                    'ScriptName' => $row[3],
                    'b_f_qty' => $row[4],
                    'b_f_rate' => $row[5],
                    'b_f_value' => $row[6],
                    'buy_qty' => $row[7],
                    'buy_rate' => $row[8],
                    'buy_amount' => $row[9],
                    'sale_qty' => $row[10],
                    'sale_rate' => $row[11],
                    'sale_amount' => $row[12],
                    'net_qty' => $row[13],
                    'net_rate' => $row[14],
                    'net_amount' => $row[15],
                    'closing_price' => $row[16],
                    'booked' => $row[17],
                    'notional' => $row[18],
                    'total' => $row[19],
                    'created_at' => $now,
                    'upload_date' => $upload_date,
                ]);
            }
        } 
        catch (\Exception $e) {
            // If an exception occurs during data insertion, set success to false
            $success = false;
        }

        // Check if data insertion was successful
        if ($success) 
        {
            // Provide a success response message
            $message = "Position Uploaded Successfully";
        } 
        else 
        {
            // Provide an error response message if insertion failed
            $message = "Error occurred while inserting data";
        }

        $request->session()->flash('message', $message);
        $request->session()->flash('status_code', $success ? 1 : 0);

        $type = $request->input('type');

        return is_mobile($type, "bulk_upload_sheet.index", [], "redirect");
    }

    public function bulk_margin_data(Request $request)
    {
        $type = $request->input('type');
        
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";

        return is_mobile($type, 'bazar/bulk_margin_data', $res, "view");
    }

    public function store_margin_data(Request $request)
    {
        // Get the uploaded file
        $file = $request->file('attachment');
        $upload_date = \Carbon\Carbon::createFromFormat('d-m-Y', $request->input('upload_date'))->format('Y-m-d');

        // Check if a file was uploaded
        if (!$file) {
            return "No file uploaded.";
        }

        // Get the file extension
        $ext = $file->getClientOriginalExtension();

        // Check if the file is an Excel file
        if (!in_array($ext, ['xlsx', 'xls'])) {
            return "Invalid file format. Only Excel files (xlsx or xls) are allowed.";
        }

        // Generate a timestamp
        $timestamp = time();
        
        // Construct the final file name
        $fileName = 'bazar_margin_' . $timestamp . '.' . $ext;

        // Store the file in the 'public/bazar' directory
        $path = $file->storeAs('public/bazar', $fileName);

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
        try 
        {
            for ($i = 0; $i < $rowCount - 1; $i++) 
            { // Exclude the last row
                $row = $data[$i];
                DB::table('sharebazar_margin')->insert([
                    'Code' => $row[0],
                    'exchange' => $row[1],
                    'script' => $row[2],
                    'qty' => $row[3],
                    'span' => $row[4],
                    'exposure' => $row[5],
                    'delivery_margin' => $row[6],
                    'additional_margin' => $row[7],
                    'ex_%' => $row[8],
                    'total' => $row[9],
                    'created_at' => $now,
                    'upload_date' => $upload_date,
                ]);
            }
        } 
        catch (\Exception $e) {
            // If an exception occurs during data insertion, set success to false
            $success = false;
        }

        // Check if data insertion was successful
        if ($success) 
        {
            // Provide a success response message
            $message = "Margin Uploaded Successfully";
        } 
        else 
        {
            // Provide an error response message if insertion failed
            $message = "Error occurred while inserting data";
        }

        $request->session()->flash('message', $message);
        $request->session()->flash('status_code', $success ? 1 : 0);

        $type = $request->input('type');

        return is_mobile($type, "bulk_upload_sheet.index", [], "redirect");
    }

    public function bulk_pnl_data(Request $request)
    {
        $type = $request->input('type');
        
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";

        return is_mobile($type, 'bazar/bulk_pnl_data', $res, "view");
    }

    public function store_pnl_data(Request $request)
    {
        // Get the uploaded file
        $file = $request->file('attachment');
        $upload_date = \Carbon\Carbon::createFromFormat('d-m-Y', $request->input('upload_date'))->format('Y-m-d');

        // Check if a file was uploaded
        if (!$file) {
            return "No file uploaded.";
        }

        // Get the file extension
        $ext = $file->getClientOriginalExtension();

        // Check if the file is an Excel file
        if (!in_array($ext, ['xlsx', 'xls'])) {
            return "Invalid file format. Only Excel files (xlsx or xls) are allowed.";
        }

        // Generate a timestamp
        $timestamp = time();
        
        // Construct the final file name
        $fileName = 'bazar_PNL_' . $timestamp . '.' . $ext;

        // Store the file in the 'public/bazar' directory
        $path = $file->storeAs('public/bazar', $fileName);

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
        try 
        {
            for ($i = 0; $i < $rowCount - 1; $i++) 
            { // Exclude the last row
                $row = $data[$i];
                DB::table('sharebazar_pnl')->insert([
                    'code' => $row[0],
                    'name' => $row[1],
                    'gross' => $row[2],
                    'exp' => $row[3],
                    'other_exp' => $row[4],
                    'gross_total' => $row[5],
                    'intrest' => $row[6],
                    'net_total' => $row[7],
                    'created_at' => $now,
                    'upload_date' => $upload_date,
                ]);
            }
        } 
        catch (\Exception $e) {
            // If an exception occurs during data insertion, set success to false
            $success = false;
        }

        // Check if data insertion was successful
        if ($success) 
        {
            // Provide a success response message
            $message = "PNL Inserted Successfully";
        } 
        else 
        {
            // Provide an error response message if insertion failed
            $message = "Error occurred while inserting data";
        }

        $request->session()->flash('message', $message);
        $request->session()->flash('status_code', $success ? 1 : 0);

        $type = $request->input('type');

        return is_mobile($type, "bulk_upload_sheet.index", [], "redirect");
    }
}
