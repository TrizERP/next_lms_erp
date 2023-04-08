<?php
require_once SENAYAN_BASE_DIR .'sysconfig.inc.php';
require_once SENAYAN_BASE_DIR .'PHPMailer_v5.1/class.phpmailer.php';

//$select_membermail=$dbs->query("select member_email from member where member_id='".$_SESSION['receipt_record']['memberID']."'");
//$row=$select_membermail->fetch_assoc();


try {
	$mail = new PHPMailer(true); //New instance, with exceptions enabled

	
	//$body             = file_get_contents(SENAYAN_BASE_DIR .'PHPMailer_v5.1/test/contents.php');
	//$body             = preg_replace('/\\\\/','', $body); //Strip backslashes

	$body='';
	$body.= "<table border=0 bgcolor=lightgray><tr><td>Itemcode</td><td>Title</td><td>Loandate</td><td>Duedate</td></tr>";
	/*foreach($_SESSION['receipt_record']['loan'] as $loan)
        {*/
	   $body .="<tr><td>".$string_mail."</td></tr>";
	
       /*}*/
	$body.="</table>";
	$mail->IsSMTP();                           // tell the class to use SMTP
	$mail->SMTPAuth   = true;                  // enable SMTP authentication
        $mail->SMTPSecure = "ssl"; 
	$mail->Port       = $port;                    // set the SMTP server port
	$mail->Host       = $host; // SMTP server
	$mail->Username   = $username;     // SMTP server username
	$mail->Password   = $password;            // SMTP server password

	//$mail->IsSendmail();  // tell the class to use Sendmail

	$mail->AddReplyTo($username);

	$mail->From       = $from;
	$mail->FromName   = $fromname;

	$to = $email_member;

	$mail->AddAddress($to);

	$mail->Subject  = "Your Today's Transaction";

	$mail->AltBody    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test
	$mail->WordWrap   = 80; // set word wrap

	
	$mail->MsgHTML($body);

	$mail->IsHTML(true); // send as HTML

	$mail->Send();
	
	//echo 'Book Issue Notification Mail Has Been Sent.';
} catch (phpmailerException $e) {
	echo $e->errorMessage();
}



?> 

