<?php

namespace App\Http\Controllers\fees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\getStudents;
use App\Models\student\tblstudentEnrollmentModel;
use App\Models\student\tblstudentModel;
use Log;

class feesAIController extends Controller
{
    //
    public function index(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $res['message']='no data';
        $file_response = shell_exec('python3 /home/fees_analysis_1.py ' . escapeshellarg($sub_institute_id));
        // Decode the JSON string
        $json_data = json_decode($file_response, true);
        $student_details = [];

        // Check if decoding was successful
        if ($json_data === null) {
            // Handle error if JSON decoding failed Student_Id
            $res['status_code']=0;
            $res['message']= "Error: Unable to decode JSON data. Error: " . json_last_error_msg();
        }else{
      
        // Extract the 'analysis' data
        $analysis_data = $json_data['analysis'];
        if(isset($analysis_data)){
            foreach ($analysis_data as $key => $value) {
                # code...
                $student_id=array($value['Student_Id']);
                $data = getStudents($student_id);
                if(!empty($data)){
                    $data[$value['Student_Id']]['prediction'] = $value['Prediction'];
                    $data[$value['Student_Id']]['true_label'] = $value['True Label'];                    
                    $student_details[]=$data;
                }
            }
        }
    }
        $res['student_details'] = $student_details;
        
        return is_mobile($type, "fees/feesAI", $res, "view");        
    }
}
