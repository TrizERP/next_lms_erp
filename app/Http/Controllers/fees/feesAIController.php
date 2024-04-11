<?php

namespace App\Http\Controllers\fees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\FeeMonthId;
use function App\Helpers\getStudents;
use App\Models\student\tblstudentEnrollmentModel;
use App\Models\student\tblstudentModel;
use DB;
use Log;

class feesAIController extends Controller
{
    //
    public function index(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $res['message']='no data';
        $file_response = shell_exec('python3 /home/fees_analysis_10_04.py ' . escapeshellarg($sub_institute_id));
        // Decode the JSON string
        $json_data = json_decode($file_response, true);
        $standard_data = [];

        // Check if decoding was successful
        if ($json_data === null) {
            // Handle error if JSON decoding failed Student_Id
            $res['status_code']=0;
            $res['message']= "Error: Unable to decode JSON data. Error: " . json_last_error_msg();
        }else{
            // [0] => Array
            // (
            //     [Prediction] => 297.24761904762
            //     [True Label] => 332.8
            //     [Standard_Id] => 2223
            //     [Term_Id] => 42024
            //     [Year] => 2024
            // )
        // Extract the 'analysis' data
        $analysis_data = $json_data['analysis'];
        
        if(isset($analysis_data)){
            foreach ($analysis_data as $key => $value) {
                $standard=DB::table('standard')->where('id',$value['Standard_Id'])->first();
                $month = [
                    "1".$value['Year']=>'Jan-'.$value['Year'],
                    "2".$value['Year']=>'Feb-'.$value['Year'],
                    "3".$value['Year']=>'Mar-'.$value['Year'],
                    "4".$value['Year']=>'Apr-'.$value['Year'],
                    "5".$value['Year']=>'May-'.$value['Year'],
                    "6".$value['Year']=>'Jun-'.$value['Year'],
                    "7".$value['Year']=>'Jul-'.$value['Year'],
                    "8".$value['Year']=>'Aug-'.$value['Year'],
                    "9".$value['Year']=>'Sep-'.$value['Year'],
                    "10".$value['Year']=>'Oct-'.$value['Year'],
                    "11".$value['Year']=>'Nov-'.$value['Year'],
                    "12".$value['Year']=>'Dec-'.$value['Year'],
                    "1".($value['Year'] + 1)=>'Jan-'.($value['Year'] + 1),
                    "2".($value['Year'] + 1)=>'Feb-'.($value['Year'] + 1),
                    "3".($value['Year'] + 1)=>'Mar-'.($value['Year'] + 1),
                    "4".($value['Year'] + 1)=>'Apr-'.($value['Year'] + 1),
                ];
                if(!empty($standard) && isset($standard->name)){
                    $value['standard_name'] = $standard->name;
                    $value['month_name'] = isset($month[$value['Term_Id']]) ? $month[$value['Term_Id']] : '';
                   // add standard and month name in array
                    $standard_data[$value['standard_name']]['all_data'][]=$value;
                }
            }
            foreach ($standard_data as $standard_name => &$standard) {
                $total_prediction = array_sum(array_column($standard_data[$standard_name]["all_data"], "Prediction"));
                // Add total prediction to the standard_data
                $standard_data[$standard_name]["total_prediction"] = $total_prediction;

                usort($standard['all_data'], function($a, $b) {
                    $last4A = substr($a['Term_Id'], -4);
                    $last4B = substr($b['Term_Id'], -4);
                    return $last4A <=> $last4B;
                });
            }
        }
    }
        $res['standard_data'] = $standard_data;
        // echo "<pre>";print_r($standard_data);exit;
        return is_mobile($type, "fees/feesAI", $res, "view");        
    }
}
