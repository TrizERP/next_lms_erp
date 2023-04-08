<?php
session_start();
error_reporting(1);
//require("Warehouse.php");
/*$host = "150.129.172.214";
$user_name = "triz_surat";
$passwd = "Triz@2017$123";
$dbname = "mmiserp_cms";
*/
$host = "150.129.172.214";
$username = "triz_erp";
$password = "Triz@2019$04";
$database = "triz_erp_2";

$cn = mysqli_connect($host, $username, $password) or die("Database Not Connected");
mysqli_select_db($cn, $database) or die("database");


if (!isset($_SESSION['otp'])) {
    header("Location: index.php");
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
    $otp_text = "Dear Parents, Your OTP is ";
    $otp_text = urlencode($otp_text);
    $final_otp = $otp_text . $otp;
    $mobile = $_SESSION['mobile'];
    $sms = "http://49.50.67.32/smsapi/httpapi.jsp?username=MMISVR1&password=abc@123&from=MMISVR&coding=0&to=$mobile&text=$final_otp";
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

    if ($_REQUEST['otp'] == $otp) 
	{
        $_SESSION['check'] = 1;
        header("Location: next.php");
    } else {
        $_SESSION['error'] = "Wrong OTP";
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
        <link rel="stylesheet" type="text/css" href="vendor/daterangepicker/daterangepicker.css">
        <link rel="stylesheet" type="text/css" href="css/util.css">
        <link rel="stylesheet" type="text/css" href="css/main.css">
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

                <form class="contact100-form validate-form" method='post'>
                    <label>Enter OTP</label>
                    <div class="wrap-input100">
                        <input id="otp" class="input100" type="text" name="otp" required maxlength="5">	
                    </div>
                    <div class="container-contact100-form-btn">
                            <!--<input type="hidden" name='action' value="resend">-->
						<button type="submit" name="next" id="resend_otp" style="margin-bottom: 20px;" class="contact100-form-btn">Next</button>
                        <button type="submit" name="resend_otp" style="margin-bottom: 20px;" id="resend_otp" class="contact100-form-btn">Resend OTP</button>

                            <!--<input type="hidden" name='action' value="next">-->

                        <!--<a href="next.php" class="contact100-form-btn">Next</a>-->
                    </div>
                </form>

            </div>
        </div>



        <script src="vendor/jquery/jquery-3.2.1.min.js"></script>
        <script src="vendor/animsition/js/animsition.min.js"></script>
        <script src="vendor/bootstrap/js/popper.js"></script>
        <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
        <script src="vendor/select2/select2.min.js"></script>
        <script src="vendor/daterangepicker/moment.min.js"></script>
        <script src="vendor/daterangepicker/daterangepicker.js"></script>
        <script src="vendor/countdowntime/countdowntime.js"></script>
        <script src="js/main.js"></script>
    </body>
</html>
