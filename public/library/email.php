 <?php
/*
$con = mysqli_connect("localhost","root","triz");
if (!$con)
 {
	 die('Could not connect: ' . mysqli_error());
 }

mysqli_select_db("new", $con);
$sql = "INSERT INTO Orders(OrderNo) values('iresh')";
mysqli_query($sql);
*/
 $con = mysqli_connect("localhost", "root", "triz");
if (!$con) {
    die('Could not connect: ' . mysqli_error());
}

 mysqli_select_db("libb", $con);
 $sql = "SELECT l.member_id,m.member_email AS mail,m.member_name AS membername,i.biblio_id,b.title AS title,l.item_code,l.due_date from loan l
left join member m on m.member_id=l.member_id
left join item i on i.item_code=l.item_code
left join biblio b on b.biblio_id=i.biblio_id
where l.is_return=0 AND l.due_date=CURDATE()+1";
 $sql = mysqli_query($sql);
 while ($row = mysqli_fetch_assoc($sql)) {
     echo $to = $row['mail'];
     $subject = "DueDate Book Notice";
//$message ="Tommorow You Have Last Date TO Return This Book :-".$row['title'];
     echo $message = "Dear " . $row['membername'] . "

	   	We would like to inform you that
	        Tommorow is last date to return this Book :-" . $row['title'] . "

 Best Regards
 Library Management";
$from = "ireshparmar@gmail.com";
$headers = "From:" . $from;
mail($to,$subject,$message,$headers);
}

echo "<br>";
echo "Mail Sent.";

 ?>
<!--

<?php

function ae_send_mail($from, $to, $subject, $text, $headers="")
{
    if (strtolower(substr(PHP_OS, 0, 3)) === 'win')
        $mail_sep = "\r\n";
    else
        $mail_sep = "\n";

    function _rsc($s)
    {
        $s = str_replace("\n", '', $s);
        $s = str_replace("\r", '', $s);
        return $s;
    }

    $h = '';
    if (is_array($headers))
    {
        foreach($headers as $k=>$v)
            $h = _rsc($k).': '._rsc($v).$mail_sep;
        if ($h != '') {
            $h = substr($h, 0, strlen($h) - strlen($mail_sep));
            $h = $mail_sep.$h;
        }
    }

    $from = _rsc($from);
    $to = _rsc($to);
    $subject = _rsc($subject);
    mail($to, $subject, $text, 'From: '.$from.$h);
}



$site_admin = 'ireshparmar@gmail.com';

// function ae_send_mail (see code above) is pasted here

if (($_SERVER['REQUEST_METHOD'] == 'POST') &&
    isset($_POST['subject']) && isset($_POST['text']) &&
    isset($_POST['from1']) && isset($_POST['from2']))
    {
        $from = $_POST['from1'].' <'.$_POST['from2'].'>';
        // nice RFC 2822 From field

        ae_send_mail($from, $site_admin, $_POST['subject'], $_POST['text'],
        array('X-Mailer'=>'PHP script at '.$_SERVER['HTTP_HOST']));
        $mail_send = true;
    }
?>
<html><head><title>Send us mail</title>
</head><body>
<?php
if (isset($mail_send)) {
    echo '<h1>Form has been sent, thank you</h1>';
}
else {
?>
<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
Your Name: <input type="text" name="from1" size="30" /><br />
Your Email: <input type="text" name="from2" size="30" /><br />
Subject: <input type="text" name="subject" size="30" /><br />
Text: <br />
<textarea rows="5" cols="40" name="text"></textarea>
<input type="submit" value="send" />
</form>
<?php } ?>
</body></html>
-->
