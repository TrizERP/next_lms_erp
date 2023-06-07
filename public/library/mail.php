<?php
 require_once 'Swift/lib/swift_required.php';

  //  $server = 'smtp.googlemail.com';
   // $port = '465';
   // $user = 'harshit.bhatt@triz.co.in';
   // $pass = 'harshit26';

    $server='mail.trizinnovation.com';
 $port='25';
 $user='dotproject@trizinnovation.com';
 $pass='dotproject';
  $transport = Swift_SmtpTransport::newInstance($server, $port)
      ->setUsername($user)
      ->setPassword($pass);
$con = mysqli_connect("localhost", "root", "triz");
if (!$con) {
    die('Could not connect: ' . mysqli_error());
}

mysqli_select_db("libb", $con);
echo $sql="SELECT l.member_id,m.member_email AS mail,m.member_name AS membername,i.biblio_id,b.title AS title,l.item_code,l.due_date from loan l
left join member m on m.member_id=l.member_id
left join item i on i.item_code=l.item_code
left join biblio b on b.biblio_id=i.biblio_id
where l.is_return=0 AND l.due_date=CURDATE()+1";
$sql = mysqli_query($sql);


    //Create a Swift transport and mailer


//  $html = "<p>Optionally, <b>send an HTML version of the message</b></p><pre>$body</pre>";
while ($row = mysqli_fetch_array($sql)) {


    $from = 'harshit.bhatt@triz.co.in';
    $to = $row['mail'];
    $subject = "DueDate Book Notice";
    $body = "Dear " . $row['membername'] . "

	   We would like to inform you that
	   Tommorow is last date to return this Book :-".$row['title']."

 Best Regards
 Library Management";
    //Create the message
  $mailer = Swift_Mailer::newInstance($transport);
    $message = Swift_Message::newInstance($subject)
        ->setFrom(array($from =>$from))
        ->setTo(array($to))
        ->setBody($body);
      //  ->addPart($html, 'text/html')

    //You can leave out the 'addPart' line if you only want to send a plain text email.

    if ($mailer->send($message)) {
        echo "Sent";
    } else {
        echo "Failed";
    }
}


?>
