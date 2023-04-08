<?php
session_start();
error_reporting(1);
include('db.php');

$sub_institute_id = '';
if(isset($_REQUEST['sub_institute_id']) && $_REQUEST['sub_institute_id'] != '')
{
    $sub_institute_id = $_REQUEST['sub_institute_id'];
}

if (!isset($_SESSION['otp'])) {
    header("Location: index.php/?sub_institute_id=".$sub_institute_id);
}

//print_r($_REQUEST);
// echo '<pre>';
// print_r($_REQUEST);//die;

if (isset($_REQUEST['resend_otp'])) {
    $sql_sql = "select 
otp
from new_admission_inquiry_registration
where  mobile =  '$_SESSION[mobile]'";
    $ret_token = mysqli_query($cn,$sql_sql);
    $otp = "";
    while ($row = mysqli_fetch_array($ret_token, MYSQLI_ASSOC)) {
        $otp = $row["otp"];
    }
    $otp_text = "Dear Parent, Your OTP is ".$otp." -Shri Muljibhai Mehta School";
    $otp_text = urlencode($otp_text);
    // $final_otp = $otp_text . $otp;
    $mobile = $_SESSION['mobile'];
    $sms = "https://49.50.67.32/smsapi/httpapi.jsp?username=MMISVR1&password=abc@123&from=MMISVR&template_id=1207161762431502012&pe_id=1201159351534184675&to=$mobile&text=$otp_text";
    // $sms = "https://49.50.67.32/smsapi/httpapi.jsp?username=MMISVR1&password=abc@123&from=MMISVR&coding=0&to=$mobile&text=$final_otp";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $sms);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);
}
if (isset($_REQUEST['next'])) {

    $sql_sql = "select 
otp
from new_admission_inquiry_registration
where  mobile =  '$_SESSION[mobile]'";
    $ret_token = mysqli_query($cn,$sql_sql);
    $otp = "";
    while ($row = mysqli_fetch_array($ret_token, MYSQLI_ASSOC)) {
        $otp = $row["otp"];
    }

    if ($_REQUEST['otp'] == $_SESSION['otp_for_store']) 
	{
        $_SESSION['check'] = 1;

        header("Location: next.php/?sub_institute_id=".$sub_institute_id);
    } else {
        $_SESSION['error'] = "Wrong OTP";
    }
}

include('header.php');

?>

				
				
                <?php
                if (isset($_SESSION['error'])) {
                    echo "<br><center><label style='color:red;'>" . $_SESSION['error'] . "</label></center>";
                    UNSET($_SESSION['error']);
                }
                ?>

                <form class="contact100-form validate-form" method='post'>
                    <label>Enter OTP</label>
                    <div class="wrap-input100">
                        <input id="otp" class="input100" type="text" name="otp" required maxlength="5">	
                    </div>
                    <div class="container-contact100-form-btn">
                            <!--<input type="hidden" name='action' value="resend">-->
						<button type="submit" name="next" id="resend_otp" style="margin-bottom: 20px;" class="contact100-form-btn">Next</button>
                        <button type="submit" name="resend_otp" style="margin-bottom: 20px;" id="resend_otp" class="contact100-form-btn">Resend OTP</button>

                    </div>
                </form>
<?php include('footer.php'); ?>