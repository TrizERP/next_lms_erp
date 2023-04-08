<?php

//require_once SENAYAN_BASE_DIR .'sysconfig.inc.php';
require_once SENAYAN_BASE_DIR .'PHPMailer_v5.1/class.phpmailer.php';

/*else
{
$select_membermail=$dbs->query("select * from member where member_id='".$itemID."'");
$row=$select_membermail->fetch_assoc();
$_SESSION['membermail'] = $row['member_email'];
}*/
//$row['mamber_id'];

try {
	//New instance, with exceptions enabled
$select_member=$dbs->query("select * from member");
while($row_mail=$select_member->fetch_assoc())
{
//utility::jsAlert($row_mail['member_email']);
	if(!empty($insert) || !empty($update))
	{
	if(!empty($insert))
	{
	$select_membermail=$dbs->query("select * from biblio where biblio_id='".$last_biblio_id."'");
	}
	else
	{
	$select_membermail=$dbs->query("select * from biblio where biblio_id='".$updateRecordID."'");
	}
	$row=$select_membermail->fetch_assoc();
	//utility::jsAlert($row['material_sub_id']);
	//$body             = file_get_contents(SENAYAN_BASE_DIR .'PHPMailer_v5.1/test/contents.html');
	//$body             = preg_replace('/\\\\/','', $body); //Strip backslashes
	$body = "<table><tr><td bgcolor=lightgray>----New Information Regarding Material Updated----</td></tr><tr><td><u><b>Material Information</b></u></td></tr>";
//fetch material type	
	$select_material_type=$dbs->query("select material_sub_name from mst_material_sub_type where material_sub_id = '".$row['material_sub_id']."'");
while($row_material_type=$select_material_type->fetch_assoc())
{
$body.= "<tr><td>Material type :- $row_material_type[material_sub_name]</td></tr>";
}
	$body.= "<tr><td>Title :- $row[title]</td></tr>";
	$body.= "<tr><td>Sub Title :- $row[sub_title]</td></tr>";
	$body.= "<tr><td>Edition :- $row[edition]</td></tr>";
	$body.= "<tr><td>ISBN/ISSN :- $row[isbn_issn]</td></tr>";
	$body.= "<tr><td>Classification :- $row[classification]</td></tr>";
//fetch author name
if(!empty($insert))
	{
	$select_author_type=$dbs->query("select author_id from biblio_author where biblio_id = '".$last_biblio_id."'");
	}
else
	{
	$select_author_type=$dbs->query("select author_id from biblio_author where biblio_id = '".$updateRecordID."'");
	}
while($row_author_type=$select_author_type->fetch_assoc())
{
	$select_author_type_name=$dbs->query("select author_name from mst_author where author_id = '".$row_author_type['author_id']."'");
	while($row_author_type_name=$select_author_type_name->fetch_assoc())
	{
	
$body.= "<tr><td>Author :- $row_author_type_name[author_name]</td></tr>";
	}
}
	

	$body.="</table>";
	}
	/*else{
	$body = "<table><tr><td></td></tr><tr><td>Your Membership Has been deleted from Library</td></tr></table>";
	}*/
if(!empty($row_mail['member_email']))
	{
	$mail = new PHPMailer(true); 
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

	$to = $row_mail['member_email'];
	$mail->AddAddress($to);

	$mail->Subject  = "Triz Library Management System Notification Mail";

	$mail->AltBody    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test
	$mail->WordWrap   = 80; // set word wrap

	$mail->MsgHTML($body);

	$mail->IsHTML(true); // send as HTML

	$mail->Send();
	}
}
	echo 'Message has been sent.';
} catch (phpmailerException $e) {
	echo $e->errorMessage();
}
?> 
