<?php
$host = "150.129.172.214";
$username = "triz_erp";
$password = "Triz@2019$04";
$database = "triz_erp_2";

$cn = mysqli_connect($host, $username, $password) or die("Database Not Connected");
mysqli_select_db($cn, $database) or die("database");

$SEND_SMS = 0;
$SEND_MAIL = 0;
// $execute_ARR = array(
// "1" => "Only on the first save",
// "2" => "Until the first time the condition is true",
// "3" => "Every time the record is saved ",
// "4" => "Every time a record is modified ",
// "5" => "Schedule",
// );

$SMS_ARR = $MAIL_ARR = $UPDATE_QUERY = array();

$sql = "SELECT *,m.id as id FROM wk_main m
		LEFT JOIN wk_execute_schedule e ON e.main_id = m.id
		where m.status = 1 and m.id NOT IN (SELECT main_id FROM wk_log)";
$RET = mysqli_query($cn, $sql);
while( $row = mysqli_fetch_assoc($RET) )
{
	$at_time = !empty($row['at_time']) ? $row['at_time'] : '';
	if($row['week_days'] != "")
	{
		$week_days_arr = explode(",",$row['week_days']);
	}
	if($row['month_days'] != "")
	{
		$month_days_arr = explode(",",$row['month_days']);
	}
	
	//CHECK for Only on the First Save
	if($row['execute_id'] == '1')
	{
		$result = getcondition($row['id']);		
		if($result == "1")
		{
			$SMS_ARR[] = getsms($row['id']);			
			$MAIL_ARR[] = getmail($row['id']);						
			$UPDATE_QUERY[] = getupdatequery($row['id']);	
			insertintolog($row['id']);			
		}			
	}
	
	//CHECK for Until the first time condition is true
	if($row['execute_id'] == '2')
	{
		$result = getcondition($row['id']);		
		if($result == "1")
		{
			$SMS_ARR[] = getsms($row['id']);			
			$MAIL_ARR[] = getmail($row['id']);						
			$UPDATE_QUERY[] = getupdatequery($row['id']);	
			insertintolog($row['id']);			
		}			
	}

	//CHECK for daily workflow
	if($row['run_workflow'] == 'Daily' && $at_time == date('h:i:s') ) 
	{
		$result = getcondition($row['id']);
		if($result == "1")
		{
			$SMS_ARR[] = getsms($row['id']);		
			$MAIL_ARR[] = getmail($row['id']);									
			$UPDATE_QUERY[] = getupdatequery($row['id']);							
		}			
	}
	
	//CHECK for weekly workflow
	if($row['run_workflow'] == 'Weekly' && $at_time == date('h:i:s') && in_array(date('l'),$week_days_arr))
	{
		$result = getcondition($row['id']);
		if($result == "1")
		{
			$SMS_ARR[] = getsms($row['id']);			
			$MAIL_ARR[] = getmail($row['id']);			
			$UPDATE_QUERY[] = getupdatequery($row['id']);	
		}
	}
	
	//CHECK for Specific date workflow
	if($row['run_workflow'] == 'SpecificDate' && $at_time == date('h:i:s') && date('Y-m-d') == $row['at_date'])
	{
		$result = getcondition($row['id']);
		if($result == "1")
		{
			$SMS_ARR[] = getsms($row['id']);			
			$MAIL_ARR[] = getmail($row['id']);			
			$UPDATE_QUERY[] = getupdatequery($row['id']);	
		}
	}
	
	//CHECK for Monthly Date workflow
	if($row['run_workflow'] == 'MonthlyDate' && $at_time == date('h:i:s') && in_array(date('d'),$month_days_arr))
	{
		$result = getcondition($row['id']);
		if($result == "1")
		{
			$SMS_ARR[] = getsms($row['id']);			
			$MAIL_ARR[] = getmail($row['id']);			
			$UPDATE_QUERY[] = getupdatequery($row['id']);				
		}
	}
}

function getcondition($main_id,$returnquery = null)
{
	global $cn;		
	
	$sql = "SELECT * FROM wk_condition c
			INNER JOIN wk_module m ON m.id = c.module_id
			WHERE main_id = '".$main_id."'";
	$RET = mysqli_query($cn, $sql);
	
	$i = 0;	
	while( $row = mysqli_fetch_assoc($RET) )
	{
		$tablename = $row['tablename'];
		$condition_type = $row['condition_type'];
		$fieldname = $row['fieldname'];
		$compare_value = $row['compare_value'];
		$condition = $row['condition'];
		
		if($i == 0)
		{
			$startquery = "SELECT * FROM ".$tablename." WHERE 1=1"; 
		}
		$i++;
		
		//CHECK different conditions
		if($condition == "is")
		{
			$middlequery .= " $condition_type $fieldname = '".$compare_value."'"; 
		}
		else if($condition == "contains")
		{
			$middlequery .= " $condition_type $fieldname LIKE '%".$compare_value."%'"; 
		}
		else if($condition == "doesnotcontains")
		{
			$middlequery .= " $condition_type $fieldname NOT LIKE '%".$compare_value."%'"; 
		}
		else if($condition == "startswith")
		{
			$middlequery .= " $condition_type $fieldname LIKE '".$compare_value."%'"; 
		}
		else if($condition == "endswith")
		{
			$middlequery .= " $condition_type $fieldname LIKE '".$compare_value."%'"; 
		}
		else if($condition == "isempty")
		{
			$middlequery .= " $condition_type $fieldname IS NULL"; 
		}
		else if($condition == "isnotempty")
		{
			$middlequery .= " $condition_type $fieldname IS NOT NULL"; 
		}
	}
	
	$finalquery = $startquery.$middlequery;	
	//echo $finalquery;die;
	$result = mysqli_num_rows(mysqli_query($cn, $finalquery));
	
	
		if(mysqli_num_rows($RET) == 0)// no condition found
		{	
			return "1";
		}
		else{
			if($returnquery == 1) // return condition query
			{
				return $finalquery; 
			}		
			else
			{
				if($result > 0) // all conditions are true
				{
					return "1";
				}
				else // conditions are false
				{
					return "0";
				}
			}
		}
}

function getsms($main_id)
{
	global $cn;
	
	$sql = "SELECT * FROM wk_sms WHERE main_id = '".$main_id."'";
	$smsres = mysqli_query($cn, $sql);
	$smsRET = mysqli_fetch_assoc($smsres);	
	if(mysqli_num_rows($smsres) > 0)
	{
		return $smsRET;
	}else{
		return "0";
	}
}

function getmail($main_id)
{
	global $cn;
	
	$sql = "SELECT * FROM wk_mail WHERE main_id = '".$main_id."'";
	$mailres = mysqli_query($cn, $sql);
	$mailRET = mysqli_fetch_assoc($mailres);	
	if(mysqli_num_rows($mailres) > 0)
	{
		return $mailRET;
	}else{
		return "0";
	}
}

function getupdatequery($main_id)
{
	global $cn;
	$getquery = 1;
	
	$sql = "SELECT * FROM wk_updatequery u
			INNER JOIN wk_module m ON m.id = u.module_id
			WHERE main_id = '".$main_id."'";			
	$updRET = mysqli_query($cn, $sql);		
	if(mysqli_num_rows($updRET) > 0)// if Update query record found
	{
		$finalquery = getcondition($main_id,$getquery);
		if($finalquery != "0")//No Query found
		{					
			$finalRET = mysqli_fetch_assoc(mysqli_query($cn, $finalquery));			
			$finalid = $finalRET['id'];								
			
			$i = 0;		
			while($row = mysqli_fetch_assoc($updRET) )
			{
				$tablename = $row['tablename'];
				$fieldname = $row['fieldname'];
				$fieldvalue = $row['fieldvalue'];
				
				if($i == 0)//Only for first time
				{
					$upd_start = "UPDATE $tablename ";
				}
				$i++;
				
				$upd_middle .= " SET $fieldname = '".$fieldvalue."' ,";			
			}
			$upd_middle = rtrim($upd_middle,",");
			$upd_end .= " WHERE id = '".$finalid."'";
			$update_query = $upd_start.$upd_middle.$upd_end;			
			mysqli_query($cn, $update_query);			
			return $update_query;
		}
		else{
			return "0";
		}
	}	
}

function insertintolog($main_id)
{
	global $cn;
						
	$ins_sql = "INSERT INTO wk_log (main_id) VALUES ('".$main_id."')";
	mysqli_query($cn, $ins_sql);	
}


ob_start();

echo "Current Time : ".date('h:i:s');
echo "<br><br>";

echo '<pre>';
echo "SEND SMS<br><br>";
$SMS_ARR = array_filter($SMS_ARR);
print_r($SMS_ARR);
echo "<br><br>";

echo "SEND MAIL<br><br>";
$MAIL_ARR = array_filter($MAIL_ARR);
print_r($MAIL_ARR);
echo "<br><br>";

echo "UPDATED QUERY<br><br>";
print_r($UPDATE_QUERY);
echo "<br><br>";

echo "<br><br>Results are:<br><br>";

//SEND SMS
if($SEND_SMS == 1)
{
	foreach($SMS_ARR as $key => $val)
	{	
		$smstext = getDynamicContent($val['main_id'],$val['smstext']);
		
		$sql = "SELECT * FROM sms_api_details WHERE sub_institute_id = '2'";
		$smsapi = mysqli_query($cn, $sql);
		$smsRET = mysqli_fetch_assoc($smsapi);	
		if(mysqli_num_rows($smsapi) > 0)
		{
			$url = $smsRET['url'] . $smsRET['pram'] . $smsRET['mobile_var'] . $val['recepients'] . $smsRET['text_var'] . urlencode($smstext) . $smsRET['last_var'];
			
			$ch = curl_init();
			//Ignore SSL certificate verification
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_URL,$url );
			//get response
			$output = curl_exec($ch);
			//Print error if any
			if (curl_errno($ch)) {
			   echo $errorMessage = curl_error($ch);
			}
			else{
				echo "SMS sent for Workflow ".$main_id."<br>";
			}
			
			curl_close($ch);
		}
		else
		{
			echo "Please check SMS settings"."<br>";
		}
	}
}

function getDynamicContent($main_id,$smstext)
{
	global $cn;
	$getquery = 1;
	
	$finalquery = getcondition($main_id,$getquery);	
	if($finalquery != "0")//No Query found
	{					
		$finalRET = mysqli_fetch_assoc(mysqli_query($cn, $finalquery));					
		foreach($finalRET as $k => $v)
		{
			$fieldname = strtoupper($k);
			$smstext = str_replace('['.$fieldname.']',$v,$smstext);
		}		
	}	
	return $smstext;
}

//SEND MAIL
require_once('mailer/class.phpmailer.php');
if($SEND_MAIL == 1)
{
	foreach($MAIL_ARR as $key => $val)
	{						
		$sql = "SELECT * FROM smtp_details WHERE sub_institute_id = '46'";
		$mail = mysqli_query($cn, $sql);
		$smtp_details = mysqli_fetch_assoc($mail);	
		if(mysqli_num_rows($mail) > 0)
		{
			$from = $smtp_details['gmail'];
            $from_pass = $smtp_details['password'];
			$subject = $val['subject'];			
			$send_to = $val['send_to'];
			$message = getDynamicContent($val['main_id'],$val['content']);
			
			$mail = new PHPMailer();
			$mail->IsSMTP();			
			$mail->isHTML(true);
			$mail->SMTPDebug = 0;
			$mail->SMTPAuth = true;
			$mail->SMTPSecure = "ssl";
			// $mail->Host = "smtp.gmail.com";
			$mail->Host = $smtp_details['server_address'];
			// $mail->Port = 465;
			$mail->Port = $smtp_details['port'];
			/*foreach ($to_arr as $id => $val) {
				$mail->AddAddress($val);
			}*/
			
			$mail->AddAddress($send_to);
			$mail->Username = $from;
			$mail->Password = $from_pass;
			$mail->SetFrom($from, $from);
			$mail->AddReplyTo($from, $from);
			//$mail->addAttachment($attechment);
			$mail->Subject = $subject;
			$mail->Body = $message;
			$mail->AltBody = $message;
			$mail->Send();
			
			echo "Mail Sent for Workflow ID ->".$val['main_id']."<br>";
		}
		else{
			echo "Please check mail settings"."<br>";
		}
	}
}

ob_end_flush();

//START INSERT into wk_cron_log
$result = ob_get_contents();
$ins_sql = "INSERT INTO wk_cron_log (result) VALUES ('".$result."')";
mysqli_query($cn, $ins_sql);	
//END INSERT into wk_cron_log

?>