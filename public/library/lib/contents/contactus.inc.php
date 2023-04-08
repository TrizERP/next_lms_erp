<?php
require LIB_DIR.'member_logon.inc.php';
//require './sysconfig.inc.php';

if($_REQUEST['mail']=='set')
{    
    
        include("/var/www/html/phpMailer_v2.3/class.phpmailer.php");              
        $Mail_Server="mail.triz.co.in";
        $Mail_Username="development@triz.co.in";
        $Mail_Port="465";
        $Mail_Password="dev123";
        $form="info@triz.co.in";
        $html_text="<h1>Testing Mail</h1>";
        $to=$_SESSION[Email];
        $subject="Query Testing";
        
       $header1=$html_text;
       $mail = new PHPMailer();
       $mail->IsSMTP();
       $mail->SMTPAuth   = true;                  		     // enable SMTP authentication
       $mail->SMTPSecure = "ssl";                 		    // sets the prefix to the servier
       $mail->Host       = $Mail_Server;   // sets GMAIL as the SMTP server
       $mail->Port       = 465;                   		   // set the SMTP port for the GMAIL server                   
       $mail->Username   = $Mail_Username;              // GMAIL username
       $mail->Password   = $Mail_Password;           			   // GMAIL password
       $mail->SetFrom    = $form;
       $mail->FromName   = "Triz Testing";
       $mail->SMTPDebug  = 1;    
       $mail->Subject    = $subject;

       $mail->WordWrap   = 50; // set word wrap
       $mail->Body = $header1;
       $mail->MsgHTML($header1);

       $mail->AddAddress($to, "Triz Testing");  
       $mail->IsHTML(true); // send as HTML
              
       if($mail->Send())
           return true;
    
}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />

<script type="text/javascript">
function validateForm()
{       
  var g=document.forms["frm"]["strpost"].value;
  if(g==null || g=="")
  {
     alert("Your Query must be filled out");
     return false;
  }
  else
  {
      var r=confirm("Are you sure?");
    if (r==true)
    {       
        return true;
    }
    else
    {   
        return false;
    }
  }
 }
</script>

</head>

<body style="padding:3px; margin:0px;" bgcolor="#FFFFFF">
<table cellpadding="0" cellspacing="0" border="0" width="700" id="form" align=center class=content1>
   
    <tr><td style="height:30px" align=center><b>Ask Librarian</b></td></tr>
    <tr>
      <td colspan="2" style="text-align:justify; line-height:15px;" class="body">
       
      <form name="frm" method="POST" action="index.php?p=contactus&mail=set" enctype="multipart/form-data" >
      <table cellpadding="0" cellspacing="0" border="0" width="100%">          
        <tr>
		<tr>
                    <td valign="top" width="25%" class="body" align="right"><b> Your Query &nbsp;&nbsp; </b></td>
<td valign="top" width="3%" class="body"><b> : </b></td>
            <td width="74%"><textarea type="text" name="strpost" class="textfield" cols="50"></textarea></td>
        </tr>
        
        <tr>							
            
            <td colspan='3'><center>
            <input  type="submit" value="Send" name="submit" onclick="return validateForm();">
			<input type="reset" value="Reset" name="reset"></center></td>
			
        </tr>
       
      </table>   
     </form>

</td>
    </tr>
    <tr>
      <td colspan="2" align="center"> </td>
  </tr>
    </table>
</body>
</html>





