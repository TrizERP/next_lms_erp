<?php
session_start();
error_reporting(1);
//require("Warehouse.php");
$host = "150.129.172.214";
$user_name = "triz_surat";
$passwd = "Triz@2017$123";
$dbname = "mmiserp_cms";
// Create connection
$conn = mysql_connect($host, $user_name, $passwd);
// Check connection
if (!$conn) {
    die("Connection failed: " . mysql_error());
} else {
    mysql_select_db($dbname);
}

//
//echo '<pre>';
//print_r($_REQUEST); //die;
//exit;




if (isset($_REQUEST['submit'])) {

//    echo '<pre>';
//    print_r($_REQUEST);

    $standard = $_REQUEST['std'];
    $dob = $_REQUEST['dob'];
    $dob = date('Y-m-d', strtotime($dob));
//    $age = $_REQUEST['age'];
    $mobile = $_REQUEST['mobile'];
    $otp_text = "Dear Parents, Your OTP is ";
    $otp_text = urlencode($otp_text);
    $otp = rand(10000, 99999);
    $final_otp = $otp_text . $otp;
    $sms = "http://49.50.67.32/smsapi/httpapi.jsp?username=MMISVR&password=abc@123&from=MMISVR&coding=0&to=$mobile&text=$final_otp";
    $token = "";
    $_SESSION['mobile'] = $mobile;
	
    if ($standard != '') {
        $sql_sql = "select 
max(SUBSTRING(token, 3)) tk
from new_admission_inquiry_registration
where  admission_std =  '$standard'";
        $ret_token = mysql_query($sql_sql);

        while ($row = mysql_fetch_array($ret_token, MYSQL_ASSOC)) {
            $tk = $row["tk"];
        }
        $tk = $tk + 1;

        if ($tk < 10) {
            $tk = "0" . $tk;
        }
        if ($standard == 'Playgroup') {
            $before_tk = "PG";
        } else {
            $before_tk = "NR";
        }
        $token = $before_tk . $tk;
    }


    $query = "INSERT INTO `new_admission_inquiry_registration`
            (`admission_std`, `date_of_birth`, `mobile`, `otp`, `token`, `created_on`) 
VALUES('" . $standard . "', '" . $dob . "', " . $mobile . ", '" . $otp . "', '" . $token . "',Now())";
    $sql = mysql_query($query);
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $sms);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


    $output = curl_exec($ch);
//echo $output ;
//exit;
    curl_close($ch);
    header("Location: otp.php");
//    exit;
//    $address = $_REQUEST['address'];
//    $mother_mobile = $_REQUEST['mother_mobile'];
//    $aadhar_card = $_REQUEST['aadhar_card'];
    // if($_REQUEST['standard'] == 'Playgroup'){
    // $token = '';
    // }
    // or die(mysql_error());
}

//function pad(n) {
//    return (n < 10) ? ("0" + n) : n;
//}
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

                <div class="contact100-form-title" style="background-image: url(images/bg-02.jpg);">
                    <img src='MMIS_Logo.png' style="width: 100px;height: 95px;padding-right: 10px;" /><br>
                    <span>Muljibhai Mehta International School - MMIS<br>
                        Gokul Township, Agashi Road Bolinj, Virar (W)</span>
                </div>

                <form class="contact100-form validate-form" method="post">


                    <label>Date Of Birth</label>
                    <div class="wrap-input100 validate-input">
                        <input id="dob" class="datepicker" type="text" name="dob" onchange="showAge(this.value);">
                    </div>

                    <label>Admission Standard</label>
                    <div class="wrap-input100 validate-input">
                        <select name="standard" id="standard" class="input100" disabled="true">
                            <option >--Select Standard--</option>
                            <option value='Playgroup'>Playgroup</option>
                            <option value='Nursery'>Nursery</option>
                        </select>
                    </div>
                    <input type='hidden' id='std' value="" name="std"> 
                    <label>Mobile</label>
                    <div class="wrap-input100 validate-input">
                        <input id="mobile" class="input100" type="text" name="mobile" maxlength=10>	
                    </div>


                    <div class="container-contact100-form-btn">
                        <button type="submit" name="submit" id="submit" class="contact100-form-btn">Continue</button>
                        <button type="submit" name="submit" id="submit" class="contact100-form-btn">Re-Print Reciept</button>
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
        <script>
                            $('.datepicker').datepicker({format: 'dd-mm-yyyy'});
                            function showAge(age) {
                                var age = age.split("-").reverse().join("-");
                                var dob = new Date(age);
                                var today = new Date();
                                var Calage = ((today - dob) / (365.25 * 24 * 60 * 60 * 1000)).toFixed(2);
                                if (Calage >= 1.8 && Calage <= 2.59) {
                                    $("#standard").val("Playgroup");
                                    $("#std").val("Playgroup");
                                }
                                if (Calage >= 2.6 && Calage <= 3.6) {
                                    $("#standard").val("Nursery");
                                    $("#std").val("Nursery");
                                }
//                                alert(Calage);    
                            }
//
//                            function showAge(age) {
//                                var res = age.split("-");
//                                var dobYear = res[0];
//                                var dobMonth = res[1];
//                                var dobDay = res[2];
//                                var bthDate, curDate, days;
//                                var ageYears, ageMonths, ageDays;
//                                bthDate = new Date(dobYear, dobMonth - 1, dobDay);
//                                curDate = new Date();
//                                if (bthDate > curDate)
//                                    return;
//                                days = Math.floor((curDate - bthDate) / (1000 * 60 * 60 * 24));
//                                ageYears = Math.floor(days / 365);
//                                ageMonths = Math.floor((days % 365) / 31);
//                                ageDays = days - (ageYears * 365) - (ageMonths * 31);
//                                var FinalAge = ageYears + "." + ageMonths;
//                                document.getElementById("age_cal").value = FinalAge;
//                                return ageYears + " " + ageMonths + " " + ageDays;
//                            }



                            //console.log(showAge(2016,1,17));
        </script>
    </body>
</html>
