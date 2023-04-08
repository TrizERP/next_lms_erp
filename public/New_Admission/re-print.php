<?php
session_start();
error_reporting(1);
include('db.php');

$sub_institute_id = '';
if(isset($_REQUEST['sub_institute_id']) && $_REQUEST['sub_institute_id'] != '')
{
    $sub_institute_id = $_REQUEST['sub_institute_id'];
}

if (isset($_REQUEST['re_send'])) {
    $mobile = $_REQUEST['mobile'];
    $_SESSION['mobile'] = $mobile;

    $token_no = $_REQUEST['token_no'];
    $_SESSION['token_no'] = $token_no;

    $get_Sql = 'SELECT mobile from new_admission_inquiry_registration WHERE mobile = ' . $mobile . ' AND token = "' . $token_no . '"';
    $get_result = mysqli_query($cn,$get_Sql);

    $count_row = mysqli_num_rows($get_result);

    if ($count_row == 0) {
        $_SESSION['error'] = "Mobile Number Is Not Registered With Us.";
    } else {
        $otp = rand(10000, 99999);
        $otp_text = "Dear Parent, Your OTP is ".$otp." -Shri Muljibhai Mehta School";
        $otp_text = urlencode($otp_text);
        // $final_otp = $otp_text . $otp;
        $sms = "https://49.50.67.32/smsapi/httpapi.jsp?username=MMISVR1&password=abc@123&from=MMISVR&template_id=1207161762431502012&pe_id=1201159351534184675&to=$mobile&text=$otp_text";
        // $sms = "https://49.50.67.32/smsapi/httpapi.jsp?username=MMISVR1&password=abc@123&from=MMISVR&coding=0&to=$mobile&text=$final_otp";

        $update_Sql = "UPDATE new_admission_inquiry_registration SET 
                otp=$otp WHERE mobile = '$mobile' AND token = '" . $token_no . "' "; //die;
        mysqli_query($cn,$update_Sql);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $sms);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        header("Location: check_otp.php/?sub_institute_id=".$sub_institute_id);
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
                <form class="contact100-form validate-form" method="post">

                    <label>Token No.</label>
                    <div class="wrap-input100 validate-input">
                        <input id="token_no" class="input100" type="text" required name="token_no" maxlength="15">	
                    </div>

                    <label>Mobile</label>
                    <div class="wrap-input100 validate-input">
                        <input id="mobile" class="input100" type="text" required name="mobile" maxlength="10" onkeypress="return (event.charCode == 8 || event.charCode == 0 || event.charCode == 13) ? null : event.charCode >= 48 && event.charCode <= 57" />	
                    </div>


                    <div class="container-contact100-form-btn">
                        <button type="submit" name="re_send" id="re_send" class="contact100-form-btn">Continue</button>
                    </div>
                </form>
            </div>
        </div>



        <script src="vendor/jquery/jquery-3.2.1.min.js"></script>
        <script src="vendor/animsition/js/animsition.min.js"></script>
        <script src="vendor/bootstrap/js/popper.js"></script>
        <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
        <script src="vendor/select2/select2.min.js"></script>
        <!--<script src="jquery-ui-1.12.1.custom/jquery-ui.min.js"></script>-->
        <!--<script src="vendor/daterangepicker/moment.min.js"></script>-->
        <script src="vitalets-bootstrap-datepicker-c7af15b/js/bootstrap-datepicker.js"></script>
        <!--<script src="vendor/daterangepicker/daterangepicker.js"></script>-->
        <script src="vendor/countdowntime/countdowntime.js"></script>
        <script src="js/main.js"></script>

    </body>
</html>
