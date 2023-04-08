
<noscript><meta http-equiv="refresh" content="1;url=error.html"></noscript>
<?php
session_start();
error_reporting(1);


$host = "150.129.172.214";
$username = "triz_erp";
$password = "Triz@2019$04";
$database = "triz_erp_2";

$cn = mysqli_connect($host, $username, $password) or die("Database Not Connected");
mysqli_select_db($cn, $database) or die("database");

//die();
// echo '<pre>';
// print_r($_REQUEST);
// print_r($_SESSION);

$Sql = "SELECT * FROM new_admission_inquiry_registration WHERE mobile = '" . $_SESSION['mobile'] . "' "; //die;
$RET = mysqli_query($cn, $Sql);
while ($row = mysqli_fetch_array($RET, MYSQLI_ASSOC))
{
   $admission_for_child_twins_2nd_time = $row["admission_for_child_twins"];
}

if(isset($_SESSION['mobile']) && $_SESSION['mobile'] != '' && !isset($_REQUEST['submit']) && $admission_for_child_twins_2nd_time == 'Twins')
{
    $Sql = "SELECT * FROM new_admission_inquiry_registration WHERE mobile = '" . $_SESSION['mobile'] . "' "; //die;
    $RET = mysqli_query($cn, $Sql);
    while ($row = mysqli_fetch_array($RET, MYSQLI_ASSOC))
    {
       $display_mobile = $row["mobile"];
       $display_dob = $row["date_of_birth"];
       $display_std = $row["admission_std"];
       $display_age = $row["age"];
       $display_father_adhar = $row["father_adhar"];
       $display_admission_for = $row["admission_for_child_twins"];
    }
}

if (isset($_REQUEST['submit'])) {
//    session_destroy();
   // echo '<pre>';
   // print_r($_REQUEST);
   // die;
    $syear = 2021;
    $standard = $_REQUEST['std'];
    $father_adhar = $_REQUEST['father_adhar'];
    $admission_for_child_twins = $_REQUEST['admission_for_child_twins'];
    $dob = $_REQUEST['dob'];
    $dob = date('Y-m-d', strtotime($dob));
    $age = $_REQUEST['age'];
    $mobile = $_REQUEST['mobile'];
    $otp_text = "Dear Parents, Your OTP is ";
    $otp_text = urlencode($otp_text);
    $otp = rand(10000, 99999);
    $final_otp = $otp_text . $otp;
    $sms = "http://49.50.67.32/smsapi/httpapi.jsp?username=MMISVR1&password=abc@123&from=MMISVR&coding=0&to=$mobile&text=$final_otp";
    $token = "";
    
    $_SESSION['mobile'] = $mobile;
  
    $check_mobile_Sql = "SELECT * FROM new_admission_inquiry_registration WHERE father_adhar = '" . $_REQUEST['father_adhar'] . "' 
	AND date_of_birth = '" . $dob . "'"; //die;
    $mobile_ret = mysqli_query($cn, $check_mobile_Sql);

    $count_result = mysqli_num_rows($mobile_ret);

    if ($count_result != 0 && $admission_for_child_twins_2nd_time != 'Twins') {
        $_SESSION['error'] = "Date Of Birth And Father’s Aadhar No. Is Already Register.";
    } else {
       /* if ($standard != '') {

             $sql_sql = "select 
                    max(SUBSTRING_INDEX(token, '-',-1) * 1) tk
                    from new_admission_inquiry_registration_max
                    where syear='".$syear."' and admission_std =  '$standard'";
            $ret_token = mysqli_query($cn,$sql_sql);


            while ($row = mysqli_fetch_array($ret_token, MYSQLI_ASSOC)) {
                $tk = $row["tk"];
            }
            $tk = $tk + 1;

            if ($tk < 10) {
                $tk = "0" . $tk;
            }
            if ($standard == 'CBSE-PH') {
                $before_tk = "PG";
            } else {
                $before_tk = "NR";
            }
            $token = $before_tk ."MMIS".$syear."-".$tk;
            // echo $token;
        }
         $query = "INSERT INTO `new_admission_inquiry_registration`
             (`admission_std`,`syear`,`date_of_birth`,`age`, `mobile`, `otp`, `token`, `created_on`,`father_adhar`,`admission_for_child_twins`) 
		 VALUES('" . $standard . "','".$syear."', '" . $dob . "', '" . $age . "', " . $mobile . ", '" . $otp . "', '" . $token . "',Now(),'" . $father_adhar . "','".$admission_for_child_twins."')";

        $sql = mysqli_query($cn,$query);
        */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $sms);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);

        $_SESSION['token_no'] = $token;
        $_SESSION['standard'] = $standard;
        $_SESSION['father_adhar'] = $father_adhar;
        $_SESSION['admission_for_child_twins'] = $admission_for_child_twins;
        $_SESSION['dob'] = $dob;
        $_SESSION['age'] = $age;
        $_SESSION['otp_for_store'] = $otp;
        $_SESSION['syear'] = $syear;
        //echo $output ;
        //exit;
        curl_close($ch);
        $_SESSION['otp'] = 1;
        header("Location: otp.php");
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
<!--                            
<tr>
<td colspan="2"><center></br></br><span style="font-size: 40px;font-weight: bold;color:red;">Coming Soon..!!!</span></center></td>
</tr>
-->
                        </table>
                    </div>
                </CENTER>

<?php //die();exit();
if (isset($_SESSION['error'])) {
    echo "<br><center><label style='color:red;'>" . $_SESSION['error'] . "</label></center>";
    UNSET($_SESSION['error']);
}
?>

                <form class="contact100-form validate-form" method="post">

                    <?php 
                    if(isset($_SESSION['mobile']) && $_SESSION['mobile'] != '' && !isset($_REQUEST['submit']))
                    {

                    ?>
                    <label>Date Of Birth<font color="red">*</font></label>
                    <div class="wrap-input100 validate-input">
                        <input id="dob" class="datepicker" required type="text" name="dob" autocomplete="off" onchange="showAge(this.value);" value="<?php if(isset($display_dob)) echo $display_dob; ?>" <?php if(isset($display_dob)) echo 'readonly="readonly"' ?> >
                    </div>

                    <label>Admission Standard<font color="red">*</font></label>
                    <div class="wrap-input100">
                        <select name="standard" id="standard" class="input100" disabled="true" <?php if(isset($display_std)) echo 'readonly'; ?> >
                            <option value=''>--Select Standard--</option>
                            <option value='CBSE-PH' <?php if($display_std == 'CBSE-PH') echo 'selected'; ?>>CBSE-PH</option>
                            <option value='CBSE-NR' <?php if($display_std == 'CBSE-NR') echo 'selected'; ?>>CBSE-NR</option>
                        </select>
                    </div>
                    <input type='hidden' id='std' value="<?php echo $display_std ?>" name="std"> 
                    <input type='hidden' id='age' value="<?php echo $display_age ?>" name="age"> 


                    <label>Mobile No.<font color="red">*</font><span style="font-size:small">&nbsp;&nbsp;&nbsp;(10 digit)</span></label>
                    <div class="wrap-input100 validate-input">
                        <input id="mobile" class="input100" required name="mobile" type="text" maxlength="10" pattern="\d{10}" value="<?php if(isset($display_mobile)) echo $display_mobile; ?>" <?php if(isset($display_mobile)) echo 'readonly="readonly"' ?> />
                      
                    </div>

                    <label>Father’s Aadhar No.<font color="red">*</font><span style="font-size:small">&nbsp;&nbsp;&nbsp;(12 digit)</span></label>
                    <div class="wrap-input100 validate-input">
                        <input id="father_adhar" class="input100" required name="father_adhar" type="text" maxlength="12" pattern="\d{12}" value="<?php if(isset($display_father_adhar)) echo $display_father_adhar; ?>" <?php if(isset($display_father_adhar)) echo 'readonly="readonly"' ?> />
                         
                    </div>

                    <label>Admission for One Child / Twins<font color="red">*</font></label>
                    <div class="wrap-input100">
                        <select name="admission_for_child_twins" id="admission_for_child_twins" class="input100" <?php if(isset($display_admission_for)) echo 'disabled' ?> <?php if(!isset($display_admission_for)) echo 'required' ?>>
                            <option value=''>--Select Admission For--</option>
                            <option value='One Child'>One Child</option>
                            <option value='Twins'>Twins</option>
                        </select>
                    </div>
                    <?php 
                    }else{
                    ?>

                    <label>Date Of Birth<font color="red">*</font></label>
                    <div class="wrap-input100 validate-input">
                        <input id="dob" class="datepicker" required type="text" name="dob" onchange="showAge(this.value);" autocomplete="off" >
                    </div>

                    <label>Admission Standard<font color="red">*</font></label>
                    <div class="wrap-input100">
                        <select name="standard" id="standard" class="input100" disabled="true" >
                            <option value=''>--Select Standard--</option>
                            <option value='CBSE-PH'>CBSE-PH</option>
                            <option value='CBSE-NR'>CBSE-NR</option>
                        </select>
                    </div>
                    <input type='hidden' id='std' value="" name="std"> 
                    <input type='hidden' id='age' value="" name="age"> 

                    <label>Mobile No.<font color="red">*</font><span style="font-size:small">&nbsp;&nbsp;&nbsp;(10 digit)</span></label>
                    <div class="wrap-input100 validate-input">
                        <input id="mobile" class="input100" required name="mobile" type="text" maxlength="10" pattern="\d{10}" />
                        <!-- value="<?php if(isset($display_mobile)) echo $display_mobile; ?>" <?php if(isset($display_mobile)) echo 'readonly="readonly"' ?>    -->
                    </div>

                    <label>Father’s Aadhar No.<font color="red">*</font><span style="font-size:small">&nbsp;&nbsp;&nbsp;(12 digit)</span></label>
                    <div class="wrap-input100 validate-input">
                        <input id="father_adhar" class="input100" required name="father_adhar" type="text" maxlength="12" pattern="\d{12}" />
                         <!-- value="<?php if(isset($display_father_adhar)) echo $display_father_adhar; ?>" <?php if(isset($display_father_adhar)) echo 'readonly="readonly"' ?>     -->
                    </div>

                    <label>Admission for One Child / Twins<font color="red">*</font></label>
                    <div class="wrap-input100">
                        <select name="admission_for_child_twins" id="admission_for_child_twins" class="input100">
                            <!-- <?php if(isset($display_admission_for)) echo 'disabled' ?> <?php if(!isset($display_admission_for)) echo 'required' ?> -->
                            <option value=''>--Select Admission For--</option>
                            <option value='One Child'>One Child</option>
                            <option value='Twins'>Twins</option>
                        </select>
                    </div>
                    <?php 
                    }
                    ?>
                    <div class="container-contact100-form-btn">
                        <a href='re-print.php' class="contact100-form-btn">Re-Print Reciept</a>
                        <button type="submit" name="submit" id="submit" class="contact100-form-btn" onclick="return checkform();">Continue</button>
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
        <script src="vendor/daterangepicker/moment.min.js"></script>
        <script src="vitalets-bootstrap-datepicker-c7af15b/js/bootstrap-datepicker.js"></script>
        <!--<script src="vendor/daterangepicker/daterangepicker.js"></script>-->
        <script src="vendor/countdowntime/countdowntime.js"></script>
        <script src="js/main.js"></script>
        <script>
                            $('.datepicker').datepicker({format: 'dd-mm-yyyy', autoclose: true});

                            function checkform() {
                                if (document.getElementById("std").value == '') {
                                    alert("Please Enter Birthdate For Select Standard.");
                                    return false;
                                }
                                return true;
                            }
                            function showAge(age) {
                                var age = age.split("-").reverse().join("/");

                                var nur_from_date = new Date("01/01/2018"); 
                                var nur_to_date = new Date("12/31/2018");
                                var pg_from_date = new Date("01/01/2019"); 
                                var pg_to_date = new Date("12/31/2019");

                                var dob = new Date(age);
                                var today = new Date();
                                var cur_age = Math.floor((today-dob) / (365.25 * 24 * 60 * 60 * 1000));
                                 
                                // alert(dob);
                                // alert(cur_age);
//                                var Calage = ((today - dob) / (365.25 * 24 * 60 * 60 * 1000)).toFixed(2);
                                $("#age").val(cur_age);    
                                var i = 0;
                                if (dob >= nur_from_date && dob <= nur_to_date) {
                                    $("#standard").val("CBSE-NR");
                                    $("#std").val("CBSE-NR");
                                    i = 1;
                                }
                                if (dob >= pg_from_date && dob <= pg_to_date) {
                                    $("#standard").val("CBSE-PH");
                                    $("#std").val("CBSE-PH");
                                    i = 1;
                                }

                                if (i == 0) {
                                    $("#standard").val("");
                                    $("#std").val("");
                                    alert("Your Child Is Not Eligible For Admission.");
                                }

//                                if (Calage >= 1.8 && Calage <= 2.59) {
//                                    $("#standard").val("Playgroup");
//                                    $("#std").val("Playgroup");
//                                }
//                                if (Calage >= 2.6 && Calage <= 3.6) {
//                                    $("#standard").val("Nursery");
//                                    $("#std").val("Nursery");
//                                }
//                                alert(Calage);  

                            }
                            // function checkform() {
                            // document.getElementById("standard").value = '';
// //                                alert(Calage);    
                            // }
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
