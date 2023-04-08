<?php
session_start();
error_reporting(1);
include('db.php');

$sub_institute_id = '';
if(isset($_REQUEST['sub_institute_id']) && $_REQUEST['sub_institute_id'] != '')
{
    $sub_institute_id = $_REQUEST['sub_institute_id'];
}

if (isset($_REQUEST['print'])) {
    if (isset($_REQUEST['otp'])) {

        $mobile = $_SESSION['mobile'];
		$token = $_SESSION['token_no'];
		
        $get_Sql = "SELECT otp from new_admission_inquiry_registration WHERE mobile = '".$mobile."' AND token = '".$token."'";
        $get_result = mysqli_query($cn,$get_Sql);

        while ($row = mysqli_fetch_array($get_result, MYSQLI_ASSOC)) {
            $otp = $row["otp"];
        }
        if ($otp == $_REQUEST['otp']) 
		{
            header("Location: print.php/?sub_institute_id=".$sub_institute_id);
        } else {
            $_SESSION['error'] = "Wrong OTP ..!";
        }
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

                    <label>OTP</label>
                    <div class="wrap-input100 validate-input">
                        <input id="mobile" class="input100" type="text" required name="otp" maxlength=5>	
                    </div>


                    <div class="container-contact100-form-btn">
                        <button type="submit" name="print" id="print" class="contact100-form-btn">Print</button>
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
