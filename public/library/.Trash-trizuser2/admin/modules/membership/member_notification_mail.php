<?php

//require_once SENAYAN_BASE_DIR .'sysconfig.inc.php';
require_once SENAYAN_BASE_DIR .'PHPMailer_v5.1/class.phpmailer.php';
if(!empty($insert) || !empty($update))
{
$select_membermail=$dbs->query("select * from member where member_email='".$_SESSION['membermail']."'");
$row=$select_membermail->fetch_assoc();
}
else
{
$select_membermail=$dbs->query("select * from member where member_id='".$itemID."'");
$row=$select_membermail->fetch_assoc();
$_SESSION['membermail'] = $row['member_email'];
}
//$row['mamber_id'];

try {
	$mail = new PHPMailer(true); //New instance, with exceptions enabled
	
	
	if(!empty($insert) || !empty($update))
	{
	//$body             = file_get_contents(SENAYAN_BASE_DIR .'PHPMailer_v5.1/test/contents.html');
	//$body             = preg_replace('/\\\\/','', $body); //Strip backslashes
	$body = "<table><tr><td bgcolor=lightgray>----Welcome to Library...Please Note down your member id and password to continue your operations in library----</td></tr><tr><td><u><b>User Information</b></u></td></tr>";
	$body.= "<tr><td>Member Id :- $row[member_id]</td></tr>";
	$body.= "<tr><td>Password :- $mpasswd1</td></tr>";
	//$body.= "<tr><td>Member Name :- $row[member_name]</td></tr>";
	//$body.= "<tr><td>Birth Date :- $row[birth_date]</td></tr>";
	//$body.= "<tr><td>Member Address :- $row[member_address]</td></tr>";
	//$body.= "<tr><td>Member Email :- $row[member_email]</td></tr>";
	//$body.= "<tr><td>Postal Code :- $row[postal_code]</td></tr>";
	//$body.= "<tr><td>Institute Name :- $row[inst_name]</td></tr>";
	//$body.= "<tr><td>Pin :- $row[pin]</td></tr>";
	//$body.= "<tr><td>Member Phone :- $row[member_phone]</td></tr>";
	//$body.= "<tr><td>Member Fax :- $row[member_fax]</td></tr>";
	$body.= "<tr><td>Member Since Date :- $row[member_since_date]</td></tr>";
	$body.= "<tr><td>Register Date :- $row[register_date]</td></tr>";
	$body.= "<tr><td>Expire Date :- $row[expire_date]</td></tr>";
	$body.="</table>";
	}
	else{
	$body = "<table><tr><td></td></tr><tr><td>Your Membership Has been deleted from Library</td></tr></table>";
	}
	$mail->IsSMTP();                           // tell the class to use SMTP
	$mail->SMTPAuth   = true;                  // enable SMTP authentication
	$mail->SMTPSecure = "ssl"; 
	/*$mail->Port       = 465;                    // set the SMTP server port
	$mail->Host       = "smtp.googlemail.com"; // SMTP server
	//$mail->Username   = "iresh.parmar@triz.co.in";     // SMTP server username
	//$mail->Password   = "iresh123";            // SMTP server password
	$mail->Username   =  "parth.kapadia@triz.co.in";	
	$mail->Password   = "parth123";

	//$mail->IsSendmail();  // tell the class to use Sendmail

	//$mail->AddReplyTo("iresh.parmar@triz.co.in","First Last");
	$mail->AddReplyTo("parth.kapadia@triz.co.in","First Last");

	//$mail->From       = "iresh.parmar@triz.co.in";
	$mail->From	    = "parth.kapadia@triz.co.in";	
	$mail->FromName   = "First Last";*/
	$mail->Port       = $port;                    // set the SMTP server port
	$mail->Host       = $host; // SMTP server
	$mail->Username   = $username;     // SMTP server username
	$mail->Password   = $password;            // SMTP server password

	//$mail->IsSendmail();  // tell the class to use Sendmail

	$mail->AddReplyTo($username);

	$mail->From       = $from;
	$mail->FromName   = $fromname;

	$to = $_SESSION['membermail'];

	$mail->AddAddress($to);

	$mail->Subject  = "Triz Library Management System Notification Mail";

	$mail->AltBody    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test
	$mail->WordWrap   = 80; // set word wrap

	$mail->MsgHTML($body);

	$mail->IsHTML(true); // send as HTML

	$mail->Send();
	echo 'Message has been sent.';
} catch (phpmailerException $e) {
	echo $e->errorMessage();
}
?> 
