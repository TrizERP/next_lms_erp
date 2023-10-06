<?php

namespace App\Http\Controllers\bazar;

use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use App\Models\school_setup\sub_std_mapModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;

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
        $date = $request->get('date');
        $filename = $request->get('filename');
        
        if (!empty($filename))
        {
           
            // Load the Excel file
            $spreadsheet = IOFactory::load($filename);
        
            // Select the worksheet you want to read data from (e.g., the first sheet)
            $worksheet = $spreadsheet->getActiveSheet();
        
            $fileHeader = $worksheet->toArray()[0];
            
            $fileDetails = []; // Initialize the $fileDetails array
        
            $headerSkipped = false;
        
            while (!feof($filename)) 
            {
                $rowData = fgetcsv($filename, 0, ',');
                if (!$headerSkipped) 
                {
                    $headerSkipped = true; // Skip the first row (header)
                    continue;
                }
                $fileDetails[] = $rowData;
            }
            fclose($filename);
        
            if (!empty($fileDetails) && empty(end($fileDetails))) {
                array_pop($fileDetails);
            }
        
            if (count($fileDetails) > 0) 
            {
                $csv_datas = $fileDetails[0];
        
                foreach($csv_datas as $csv_data)
                {
                    echo("<pre>");
                    print_r($csv_data);
                    echo("</pre>");
                }
                die;
                /* $csv_data_id = DB::table('sharebazar_position')->insertGetId([
                    'csv_filename' => $request->file('filename')->getClientOriginalName(),
                    'csv_header' => $request->has('header'),
                    'csv_data' => json_encode($fileDetails),
                ]); */
            }
            else 
            {
                $type = $request->input('type');
        
                return is_mobile($type, "bulk_upload_sheet.index", "view");
            }
        } 
        else 
        {
            echo "The specified Excel file does not exist.";
        }
        
        /* $res = [
            "status_code" => 1,
            "message"     => "Position Inserted Successfully",
        ];

        $type = $request->input('type');

        return is_mobile($type, "bulk_upload_sheet.index", $res, "redirect"); */
    }
}
