<?php
include("auth/auth.php");

$host = "192.168.0.2";//"150.129.172.214";
$username = "root";//"triz_erp";
$password = "Tr!zLMS@123#";//"Triz@2019$04";
$database = "triz_erp_2";//"triz_erp_2";

$cn = mysqli_connect($host, $username, $password) or die("Database Not Connected");
mysqli_select_db($cn, $database) or die("database");

$type = $_REQUEST['type'];
$teacher_id = $_REQUEST['teacher_id'];
$sub_institute_id = $_REQUEST['sub_institute_id'];
$syear = $_REQUEST['syear'];
$action = $_REQUEST['action'];

/* START HRMS Database connection */
$sql = "select *,if(db_hrms is null,0,1) as rights,
			if(db_library is null,0,1) as library_rights
			from school_setup s
			inner join tblclient c on c.id = s.client_id
			where s.Id = ".$sub_institute_id; 
$client_data = mysqli_query($cn,$sql);
$client_data = mysqli_fetch_assoc($client_data);

$host1 = $client_data['db_host'];
$username1 = $client_data['db_user'];
$password1 = $client_data['db_password'];
$database1 = $client_data['db_hrms'];
 
$cn1 = mysqli_connect($host1, $username1, $password1) or die("Database Not Connected");	
mysqli_select_db($cn1, $database1) or die("database not found");
/* END HRMS Database connection */

//Teacher Attendance API
if($action == "teacher_attendance_chart")
{
		$sql = "SELECT 'present' as att_status,concat_ws('_',e.emp_firstname,e.emp_lastname) as employee_name 
					FROM hs_hr_attendance a 
					INNER JOIN hs_hr_employee e on e.employee_id = a.employee_id
					WHERE date_format(punchin_time,'%Y-%m-%d') = '2021-12-06'
					UNION ALL
					SELECT 'absent' as att_status,concat_ws('_',e.emp_firstname,e.emp_lastname) as employee_name 
					FROM hs_hr_employee e where e.employee_id NOT IN (227,1,224)"; 
		$user = mysqli_query($cn1,$sql);
		if(mysqli_num_rows($user) > 0)
		{
			$chart_data = "[{id: '0.0',parent: '',name: 'Teacher Attendance'}, {id: '1.1',parent: '0.0',name: 'Present'}, {id: '1.2',parent: '0.0',name: 'Absent'},";

			$i = 1;
			while($user_data = mysqli_fetch_assoc($user))
			{
				 if($user_data['att_status'] == "present")
				 {
				 		$parent = "1.1";
				 }
				 else
				 {
				 		$parent = "1.2";
				 }
				 $chart_data .= "{";
                            $chart_data .= "id: "."'2." . $i."',";
                            $chart_data .= "parent: '".$parent."',";
                            $chart_data .= "name: "."'".trim($user_data['employee_name'])."',";
                            $chart_data .= "value: 1";
                            $chart_data .= "},";				
         $i++;
			}
			$chart_data .= "];";
			$res['status_code'] = 1;
			$res['message'] = "Success";
			$res['data'] = $chart_data;
		}
		else{
			$res['status_code'] = 0;
			$res['message'] = "Not Found";			
		}
}
else
{
		if($syear != "" && $sub_institute_id != "" && $teacher_id != "" && $action != "") 
		{
			$username_sql = "select user_name from tbluser where sub_institute_id = '".$sub_institute_id."' and id = '".$teacher_id."'"; 
			$username_data = mysqli_query($cn,$username_sql);
			$username_data = mysqli_fetch_assoc($username_data);						
				
			//Teacher Attendance List API
			if($action == "add_attendance")
			{
				$address = $_REQUEST["address"];

				$sql = "SELECT * FROM hs_hr_users u	WHERE u.user_name='".$username_data['user_name']."'"; 
				$user = mysqli_query($cn1,$sql);
				if(mysqli_num_rows($user) > 0)
				{
					$user_details = mysqli_fetch_assoc($user);			
					$employee_id = $user_details['emp_number'];		

					$hrChkSql = "SELECT attendance_id FROM hs_hr_attendance where employee_id='" . $employee_id . "' and DATE(punchin_time) = DATE(CURDATE())";
					$hrChkSqlRes = mysqli_query($cn1,$hrChkSql);
					if (mysqli_num_rows($hrChkSqlRes) > 0) //punch - out
					{
							//Update Code For Punch-Out
			        $upSql = "UPDATE hs_hr_attendance SET employee_id = '" . $employee_id . "',punchout_time = now(),out_note='Day End',
							status='1',ipaddress_out = '" . $address . "' where employee_id = '" . $employee_id . "' and DATE(punchin_time) = DATE(CURDATE())";
			        $res_update = mysqli_query($cn1,$upSql);			
			        if ($res_update) {
			            $res["message"] = "success";
			            $res["btn_text"] = "Gone";	           
			        } else {
			            $res["message"] = "No";
			            $res["btn_text"] = "Punch Out";	            
			        }
					}
					else // punch - in
					{
						 	//Insert Code For Punch-Out
							$sql = " INSERT INTO hs_hr_attendance(employee_id,punchin_time,in_note,ipaddress_in,status) "
			                . " VALUES('" . $employee_id . "',now(),if(CURTIME() > '09:30:00', 'Half Day','Day Start'),'" . $address . "','1')";

			        $res_insert = mysqli_query($cn1,$sql);
			        if ($res_insert) {
			            $res["message"] = "success";
			            $res["btn_text"] = "Punch Out";	            
			        } else {
			            $res["message"] = "No";
			            $res["btn_text"] = "Punch In";	            
			        }
					}			
				}
				else
				{
					$res['status_code'] = 0;
					$res['message'] = "Not Found";			
				}
			}
			
			//Add Teacher Attendance API
			if($action == "attendance_list")
			{
				$sql = "SELECT a.punchin_time,a.punchout_time FROM hs_hr_users u
						INNER JOIN hs_hr_attendance a on a.employee_id = u.emp_number
						WHERE u.user_name='".$username_data['user_name']."' and year(punchin_time)= '".$_REQUEST['syear']."'"; 
				$user = mysqli_query($cn1,$sql);
				if(mysqli_num_rows($user) > 0)
				{
					while($user_attendance = mysqli_fetch_assoc($user))
					{
						$data[] = $user_attendance;			
					}
					$res['status_code'] = 1;
					$res['message'] = "Success";
					$res['data'] = $data;
				}
				else{
					$res['status_code'] = 0;
					$res['message'] = "Not Found";			
				}
			}

			//Teacher Leave Request API
			if($action == "leave_request")
			{
				$sql = "SELECT leave_date,l.leave_length_hours,leave_length_days,leave_comments,leave_status,t.leave_type_name,
						l.HR_Comments,l.PRINCIPAL_Comments
						FROM hs_hr_leave l 
						INNER JOIN hs_hr_leavetype t on t.leave_type_id = l.leave_type_id
						INNER JOIN hs_hr_users u on u.emp_number = l.employee_id
						WHERE u.user_name='".$username_data['user_name']."' and year(leave_date) = '".$_REQUEST['syear']."'
						ORDER BY leave_date"; 
				$user = mysqli_query($cn1,$sql);
				if(mysqli_num_rows($user) > 0)
				{
					while($user_leave_request = mysqli_fetch_assoc($user))
					{
						$data[] = $user_leave_request;			
					}
					$res['status_code'] = 1;
					$res['message'] = "Success";
					$res['data'] = $data;
				}
				else{
					$res['status_code'] = 0;
					$res['message'] = "Not Found";			
				}
			}
			
			//Teacher Payslip API
			if($action == "payslip")
			{
				$sql = "SELECT eattach_desc,eattach_filename,eattach_type FROM hs_hr_emp_attachment a
						INNER JOIN hs_hr_users u on u.emp_number = a.emp_number
						WHERE u.user_name = '".$username_data['user_name']."'
						AND type_id = (select id from hs_hr_emp_attachment_type where type='Payslip')"; 
				$user = mysqli_query($cn1,$sql);
				if(mysqli_num_rows($user) > 0)
				{
					while($user_payslip = mysqli_fetch_assoc($user))
					{
						$data[] = $user_payslip;			
					}
					$res['status_code'] = 1;
					$res['message'] = "Success";
					$res['data'] = $data;
				}
				else{
					$res['status_code'] = 0;
					$res['message'] = "Not Found";			
				}
			}	
			
			//Teacher Leave Apply
			if($action == "leave_apply")
			{
				$start_date = $_REQUEST['start_date'];
				$end_date = $_REQUEST['end_date'];
				$leave_type = $_REQUEST['leave_type'];
				$reason = $_REQUEST['reason'];
				
				if($start_date != "" && $end_date != "" && $leave_type != "" && $reason != ""){
					
					$sql = "SELECT emp_number FROM hs_hr_users 
							WHERE user_name = '".$username_data['user_name']."'
							"; 
					$user = mysqli_query($cn1,$sql);
					if(mysqli_num_rows($user) > 0)
					{
						$user_id = mysqli_fetch_assoc($user);
						$user_id = $user_id['emp_number'];
						
						$leave_sql = "SELECT leave_type_id,leave_type_name FROM hs_hr_leavetype WHERE short_code = '".$leave_type."'";
						$leave = mysqli_query($cn1,$leave_sql);
						$leave = mysqli_fetch_assoc($leave);
						$leave_type_id = $leave['leave_type_id'];
						$leave_type_name = $leave['leave_type_name'];
						
						$lr_sql = "SELECT (max(leave_request_id)+1) as lr_id FROM hs_hr_leave_requests";
						$lr = mysqli_fetch_assoc(mysqli_query($cn1,$lr_sql));
						$leave_request_id = $lr['lr_id'];
						
						$ins_sql = "INSERT INTO hs_hr_leave_requests(leave_request_id,leave_type_id,leave_type_name,date_applied,employee_id) 
						VALUES('".$leave_request_id."','".$leave_type_id."','".$leave_type_name."',now(),'".$user_id."')";
						mysqli_query($cn1,$ins_sql);
						
						$dates = displayDates($start_date,$end_date);				
						foreach($dates as $key => $val)
						{					
							$l_sql = "SELECT (max(leave_id)+1) as l_id FROM hs_hr_leave";
							$l = mysqli_fetch_assoc(mysqli_query($cn1,$l_sql));
							$leave_id = $l['l_id'];
							
							$ins_sql = "INSERT INTO hs_hr_leave(leave_id,leave_date,leave_length_hours,leave_length_days,leave_status,
							leave_comments,leave_request_id,leave_type_id,employee_id) 
							VALUES('".$leave_id."','".$val."','8','1','3','".$reason."','".$leave_request_id."','".$leave_type_id."','".$user_id."')";
							mysqli_query($cn1,$ins_sql);					
						}
						
						$res['status_code'] = 1;
						$res['message'] = "Successfully Applied Leave";				
					}
					else{
						$res['status_code'] = 0;
						$res['message'] = "User Not Found";			
					}			
				}
				else{
					$res['status_code'] = 0;
					$res['message'] = "Parameter Missing";	
				}
			}
		}
		else
		{
			$res['status_code'] = 0;
			$res['message'] = "Parameter Missing";	
		}
}

function displayDates($date1, $date2, $format = 'Y-m-d' ) {
  $dates = array();
  $current = strtotime($date1);
  $date2 = strtotime($date2);
  $stepVal = '+1 day';
  while( $current <= $date2 ) {
	 $dates[] = date($format, $current);
	 $current = strtotime($stepVal, $current);
  }
  return $dates;
}

print_r(json_encode($res));
?>