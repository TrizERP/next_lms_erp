<?php
//date_default_timezone_set("Asia/Kolkata");
				
namespace App\Http\Controllers\visitor_management;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\visitor_management\visitor_masterModel;
use App\Models\visitor_management\visitor_typeModel;
use App\Models\user\tbluserModel;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;

class visitor_masterController extends Controller
{
	use GetsJwtToken;

    public function index(Request $request)
    {        	
		if (session()->has('data')) { // check if it exists
            $data_arr = session('data');// to retrieve value
            if (isset($data_arr['message'])) {
                $visitor_data['message'] = $data_arr['message'];
            }
        }
         
        $sub_institute_id = $request->session()->get('sub_institute_id');
		
        $data = visitor_masterModel::select('visitor_master.*',
		DB::raw('concat(u.first_name," ",u.middle_name," ",u.last_name) as staff_name'),
		DB::raw('if(out_time = "00:00:00","green","") as status'),		
		'vt.title as visitor_type_name'  )
		->join('tbluser as u','u.id','=','visitor_master.to_meet')
		->join('visitor_type as vt','vt.id','=','visitor_master.visitor_type')
		->where(['visitor_master.sub_institute_id'=>$sub_institute_id,'meet_date'=> date('Y-m-d')])
		->get();
		
        $visitor_data['status_code'] = 1;
        $visitor_data['data'] = $data;
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "visitor_management/show_visitor", $visitor_data, "view");
    }
	
	public function show_visitor_report(Request $request)
    {        	
		if (session()->has('data')) { // check if it exists
            $data_arr = session('data');// to retrieve value
            if (isset($data_arr['message'])) {
                $visitor_data['message'] = $data_arr['message'];
            }
        }
         		 
        $visitor_data['status_code'] = 1;      
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "visitor_management/show_visitor_report", $visitor_data, "view");
    }
	
	public function show_visitor_report_data(Request $request)
    {        	
		if (session()->has('data')) { // check if it exists
            $data_arr = session('data');// to retrieve value
            if (isset($data_arr['message'])) {
                $visitor_data['message'] = $data_arr['message'];
            }
        }
		
		$from_date = $request->get('from_date'); 
        $to_date = $request->get('to_date'); 		
		
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = visitor_masterModel::select('visitor_master.*',
		DB::raw('concat(u.first_name," ",u.middle_name," ",u.last_name) as staff_name'),'vt.title as visitor_type_name'  )
		->join('tbluser as u','u.id','=','visitor_master.to_meet')
		->join('visitor_type as vt','vt.id','=','visitor_master.visitor_type')
		->where(['visitor_master.sub_institute_id'=>$sub_institute_id])
		->whereBetween('meet_date', array($from_date, $to_date))
		->get();
		
        $visitor_data['status_code'] = 1;
        $visitor_data['data'] = $data;
		$visitor_data['to_date'] = $to_date;                 
        $visitor_data['from_date'] = $from_date;
        $type = $request->input('type');
        //return \App\Helpers\is_mobile($type, "visitor_management/show_visitor_report_chart", $visitor_data, "view");
        return \App\Helpers\is_mobile($type, "visitor_management/show_visitor_report", $visitor_data, "view");
    }
	
	
    
   public function create(Request $request) {
		$type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
		$data['visitor_type_data'] = visitor_typeModel::where(['sub_institute_id' => $sub_institute_id])->get();
		$data['to_meet_array'] = tbluserModel::select(
            'id',
            DB::raw('concat(first_name," ",middle_name," ",last_name) as staff_name')  
        )
		->where(['sub_institute_id' => $sub_institute_id])->get();
		//dd($data);
        //$data =  hostel_visitor_masterModel::where(['sub_institute_id'=>$sub_institute_id])->get()->toArray();
		return is_mobile($type,'visitor_management/add_visitor_master',$data,"view");      
    }
    
    public function store(Request $request) {            
		$type = $request->get('type');

     	if ($type != "API") {
			$sub_institute_id = $request->session()->get('sub_institute_id');
		} else {
			try {
				if (!$this->jwtToken()->validate()) {
					$response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
					return response()->json($response, 401);
				}
			} catch (\Exception $e) {
				$response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
				return response()->json($response, 401);
			}
								
			$sub_institute_id = $request->input('sub_institute_id');
			$appointment_type = $request->input('appointment_type');
			$visitor_type = $request->input('visitor_type');
			$name = $request->input('name');
			$contact = $request->input('contact');
			$meet_date = $request->input('meet_date');
			$in_time = $request->input('in_time');

			if($appointment_type == '' || $visitor_type == '' ||  $name == '' ||  $contact == '' ||  $meet_date == '' || $in_time == '' || 
			   $sub_institute_id == '')
			{
				$res['status_code'] = 0;
				$res['message'] = "Parameter Missing.";
				return is_mobile($type, "student_attendance.index", $res);
			}
		}

        		
		if($request->get('appointment_type') == "Direct")
		{
			$meet_date = date('Y-m-d');
			$in_time = date('h:i:s');
		}
		else{
			$meet_date = $request->get('meet_date');
			$in_time = $request->get('in_time');
		}		
		
		$newfilename = $size = $ext = "";
        if($request->hasFile('visitor_photo'))
        {
            $img = $request->file('visitor_photo');
            $filename = $img->getClientOriginalName();
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $newfilename = 'visitor_'.date('Y-m-d_h-i-s').'.'.$ext;           
            $file_folder = '/visitor_photo';          
            $img->storeAs('public/visitor_photo/',$newfilename);
        }
		
        $visitor = array(
            'appointment_type' => $request->get('appointment_type'),
            'visitor_type' => $request->get('visitor_type'),
            'name' => $request->get('name'),
            'contact' => $request->get('contact'),
            'email' => $request->get('email'),
            'coming_from' => $request->get('coming_from'),
            'to_meet' => $request->get('to_meet'),
            'relation' => $request->get('relation'),
            'purpose' => $request->get('purpose'),
            'visitor_idcard' => $request->get('visitor_idcard'),            
            'photo' => $newfilename,
            'file_size' => $size,            
            'file_type' => $ext,           
            'meet_date' => $meet_date,
            'in_time' => $in_time,           
            'sub_institute_id' => $sub_institute_id,
			'created_at' => now()
        );
				
        $visitor_id = visitor_masterModel::insertGetId($visitor);  
		
		$res = array(
			"status_code" => 1,
			"message" => "Visitor details Added Successfully",
		);
		
		//For Sending Welcome sms		
		$this->get_sms_setting($visitor_id,'Welcome');
		        
        return is_mobile($type, "add_visitor_master.index", $res, "redirect");      
    }
    
    public function edit(Request $request, $id)
    {         
        $type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
        $data = visitor_masterModel::find($id);
		
		$data['visitor_type_data'] = visitor_typeModel::where(['sub_institute_id' => $sub_institute_id])->get();
		$data['to_meet_array'] = tbluserModel::select(
            'id',
            DB::raw('concat(first_name," ",middle_name," ",last_name) as staff_name'))
		->where(['sub_institute_id' => $sub_institute_id])->get();
        
		return \App\Helpers\is_mobile($type, "visitor_management/add_visitor_master", $data, "view");
    }
      
    public function update(Request $request, $id)
    {  	
	  $sub_institute_id = $request->session()->get('sub_institute_id');

      $visitor = array(	  
			'appointment_type' => $request->get('appointment_type'),
            'visitor_type' => $request->get('visitor_type'),
            'name' => $request->get('name'),
            'contact' => $request->get('contact'),
            'email' => $request->get('email'),
            'coming_from' => $request->get('coming_from'),
            'to_meet' => $request->get('to_meet'),
            'relation' => $request->get('relation'),
            'purpose' => $request->get('purpose'),
            'visitor_idcard' => $request->get('visitor_idcard'),                                  
            'sub_institute_id' => $sub_institute_id,                       
			'exit_msg_sent' => 'Y',
			'updated_at' => now()    
      );
      //'out_time' => date('h:i:s'),

	    if($request->get('hid_out_time') == "00:00:00")
		{
			$visitor['out_time'] = date('h:i:s');
		}
	
	if($request->hasFile('visitor_photo'))
	{
		unlink('storage/visitor_photo'.$request->input('hid_photo'));   		
		$img = $request->file('visitor_photo');
		$filename = $img->getClientOriginalName();
		$ext = $img->getClientOriginalExtension();
		$size = $img->getSize();
		$newfilename = 'visitor_'.date('Y-m-d_h-i-s').'.'.$ext;           
		$file_folder = '/visitor_photo';          
		$img->storeAs('public/visitor_photo/',$newfilename);
		$visitor['photo'] = $newfilename;
	}	
       visitor_masterModel::where(["id" => $id])->update($visitor);
	
	if($request->get('hid_exit_msg_sent') == null)//Send sms on only first time update
	{
	   //Send Exit Sms
	   $this->get_sms_setting($id,'Exit');	
	}	
	   
       $message['status_code'] = "1";
       $message = array(
            "message" => "Data Updated Successfully",
        );
       $type = $request->input('type');

       return \App\Helpers\is_mobile($type, "add_visitor_master.index", $message, "redirect");
    }
    public function destroy(Request $request,$id)
    {  
     $type = $request->input('type');
     visitor_masterModel::where(["id" => $id])->delete();
     $message['status_code'] = "1";
        $message = array(
            "message" => "Data Deleted Successfully",
        );

        return \App\Helpers\is_mobile($type, "add_visitor_master.index", $message, "redirect");
     
    } 
	
	public function get_sms_setting($visitor_id,$type)
	{
		$data = DB::select("SELECT v.name AS visitor_name,v.contact AS visitor_contact,v.sub_institute_id,
				v.purpose,v.meet_date,v.in_time, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS staff_name,u.mobile AS staff_contact,s.*,vm.*
				FROM visitor_master v
				INNER JOIN tbluser u ON v.to_meet = u.id
				LEFT JOIN sms_api_details s ON s.sub_institute_id = v.sub_institute_id
				LEFT JOIN visitor_master_settings vm ON vm.sub_institute_id = v.sub_institute_id
				WHERE v.id = '".$visitor_id."'");
		//dd($data);
		$data = $data[0];
		if($data->url != "")//SMS API is set
		{
			$staff_name = ucfirst(strtolower($data->staff_name));
			$staff_contact = $data->staff_contact;
			
			$visitor_name = ucfirst($data->visitor_name);
			$visitor_contact = $data->visitor_contact;
			
			if($type == 'Welcome')//Send Welcome sms
			{
				$welcome_staff_msg = $data->welcome_staff_msg;
				$welcome_visitor_msg = $data->welcome_visitor_msg;
				
				$welcome_staff_msg = str_replace("<<staff_name>>",$staff_name,$welcome_staff_msg);
				$welcome_staff_msg = str_replace("<<visitor_name>>",$visitor_name,$welcome_staff_msg);
				$welcome_staff_msg = str_replace("<<purpose>>",$data->purpose,$welcome_staff_msg);
				$welcome_staff_msg = str_replace("<<date>>",$data->meet_date,$welcome_staff_msg);
				$welcome_staff_msg = str_replace("<<time>>",$data->in_time,$welcome_staff_msg);
							
				$welcome_visitor_msg = str_replace("<<visitor_name>>",$visitor_name,$welcome_visitor_msg);
				
				if($data->welcome_staff_msg_enable == 1)//Send Welcome msg to staff member if enable
				{
					$url = $data->url . $data->pram . $data->mobile_var . $staff_contact . $data->text_var . urlencode($welcome_staff_msg) . $data->last_var;	
					$this->send_sms($url);
				}
				
				if($data->welcome_visitor_msg_enable == 1)//Send Welcome msg to Visitor if enable
				{
					$url = $data->url . $data->pram . $data->mobile_var . $visitor_contact . $data->text_var . urlencode($welcome_visitor_msg) . $data->last_var;	
					$this->send_sms($url);
				}	
			}
			else if ($type == "Exit")//Send Exit sms
			{
				$exit_visitor_msg = $data->exit_visitor_msg;
				
				if($data->exit_visitor_msg_enable == 1)//Send Welcome msg to Visitor
				{
					$url = $data->url . $data->pram . $data->mobile_var . $visitor_contact . $data->text_var . urlencode($exit_visitor_msg) . $data->last_var;	
					$this->send_sms($url);
				}
			}
		}
	}
	
	public function send_sms($url)
	{		
		$ch = curl_init();
		//Ignore SSL certificate verification
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL,$url );
		//get response
		$output = curl_exec($ch);
		//Print error if any
		/*if (curl_errno($ch)) {
		   echo $errorMessage = curl_error($ch);
		}
		else{
			echo "SMS sent";
		}*/	
		curl_close($ch);
	}

	public function get_visitorTypeAPI(Request $request)
    {
        try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }

        $sub_institute_id = $request->get('sub_institute_id');
        
        $data['visitor_type_data'] = visitor_typeModel::where(['sub_institute_id' => $sub_institute_id])->get();

		$data['to_meet_array'] = tbluserModel::select('id',DB::raw('concat(first_name," ",middle_name," ",last_name) as staff_name'))
								->where(['sub_institute_id' => $sub_institute_id])->get();

        return json_encode($data);
        exit;
    }

	public function get_visitorAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $teacher_id = $request->input("teacher_id");
        $from_date = $request->get('from_date'); 
        $to_date = $request->get('to_date'); 	

        $response = array();
        $validator =  Validator::make($request->all(), [
            'teacher_id' => 'required|numeric',           
            'sub_institute_id' => 'required|numeric',                    
        ]);

        if ($validator->fails()) 
        {
            $response['response'] = $validator->messages();
        } 
        else 
        {
           $sql = "SELECT v.*,CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) as staff_name,vt.title as visitor_type_name,if(v.photo = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/visitor_photo/',v.photo)) as visitor_photo FROM visitor_master v
				INNER JOIN tbluser AS u ON u.id = v.to_meet AND u.sub_institute_id = v.sub_institute_id
				INNER JOIN visitor_type as vt ON vt.id = v.visitor_type AND vt.sub_institute_id = v.sub_institute_id
				WHERE v.sub_institute_id = '".$sub_institute_id."' AND v.to_meet = '".$teacher_id."' AND v.meet_date BETWEEN '".$from_date."' AND '".$to_date."'";

           $data = DB::select($sql);

			if(count($data) > 0)
            {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $data;
            }
            else
            {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }
        return json_encode($response);
    }   
}
