<?php
session_start();
error_reporting(1);
//require("Warehouse.php");
$host = "150.129.172.214";
$username = "triz_erp";
$password = "Triz@2019$04";
$database = "triz_erp_2";

$cn = mysqli_connect($host, $username, $password) or die("Database Not Connected");
mysqli_select_db($cn, $database) or die("database");


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
        $otp_text = "Dear Parents, Your OTP is ";
        $otp_text = urlencode($otp_text);
        $otp = rand(10000, 99999);
        $final_otp = $otp_text . $otp;
        $sms = "http://49.50.67.32/smsapi/httpapi.jsp?username=MMISVR1&password=abc@123&from=MMISVR&coding=0&to=$mobile&text=$final_otp";

        $update_Sql = "UPDATE new_admission_inquiry_registration SET 
                otp=$otp WHERE mobile = '$mobile' AND token = '" . $token_no . "' "; //die;
        mysqli_query($cn,$update_Sql);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $sms);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        header("Location: check_otp.php");
    }
}
?>


<html lang="en">
    <head>
        <title>Admission Registration</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" type="image/png" href="images/icons/favicon.ico"/>
        <link rel="stylesheet" type="text/css" href="vendor/bootstrap/css/bootstrap.min.css">
        <link rel="stylesheet" type="text/css" href="fonts/font-awesome-4.7.0/css/font-awesome.min.css">
        <link rel="stylesheet" type="text/css" href="fonts/iconic/css/material-design-iconic-font.min.css">
        <link rel="stylesheet" type="text/css" href="fonts/Linearicons-Free-v1.0.0/icon-font.min.css">
        <link rel="stylesheet" type="text/css" href="vendor/animate/animate.css">
        <link rel="stylesheet" type="text/css" href="vendor/css-hamburgers/hamburgers.min.css">
        <link rel="stylesheet" type="text/css" href="vendor/animsition/css/animsition.min.css">
        <link rel="stylesheet" type="text/css" href="vendor/select2/select2.min.css">
        <!--<link rel="stylesheet" type="text/css" href="vendor/daterangepicker/daterangepicker.css">-->
        <link rel="stylesheet" type="text/css" href="css/util.css">
        <link rel="stylesheet" type="text/css" href="css/main.css">
        <!--        <link rel="stylesheet" type="text/css" href="jquery-ui-1.12.1.custom/jquery-ui.theme.css">
                <link rel="stylesheet" type="text/css" href="jquery-ui-1.12.1.custom/jquery-ui.min.css">
                <link rel="stylesheet" type="text/css" href="jquery-ui-1.12.1.custom/jquery-ui.structure.min.css">-->
        <link rel="stylesheet" type="text/css" href="vitalets-bootstrap-datepicker-c7af15b/css/datepicker.css">

        <!--<script src="//ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>-->
        <!--<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css"/>-->
        <!--<link rel="stylesheet" href="https://resources/demos/style.css"/>-->
        <!--<script src="https://code.jquery.com/jquery-1.12.4.js"></script>-->
        <!--<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>-->
    </head>
    <body>

        <div class="container-contact100">
            <div class="wrap-contact100">

                <!--<div class="contact100-form-title" style="background-image: url(images/bg-02.jpg);">
                    <img src='MMIS_Logo.png' style="width: 100px;height: 95px;padding-right: 10px;" /><br>
                    <span>Muljibhai Mehta International School - MMIS<br>
                        Gokul Township, Agashi Road Bolinj, Virar (W)</span>
                </div>-->

                <CENTER>
                    <div class="contact100-form-title" style="background-image: url(images/bg-02.jpg);">
                        <table width="100%">
                            <tr>
                                <td>
                                    <img src='MMIS_Logo.png' style="width: 100px;" /><br>
                                </td>
                                <td>
                            <center><span style="font-size: 20px;font-weight: bold;color:white;">Muljibhai Mehta International School</br></span>
                                <span style="color:white;">Gokul Township, Agashi Road, Bolinj, Virar (W)</br></span>
                                <span style="color:white;">(ONLINE ADMISSION FOR A.Y. 2021-2022)</span></center>
                            </td>
                            </tr>
                        </table>
                    </div>
                </CENTER>

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
