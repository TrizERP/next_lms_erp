<?php
session_start();
error_reporting(1);
include('db.php');

$sub_institute_id = '';
if(isset($_REQUEST['sub_institute_id']) && $_REQUEST['sub_institute_id'] != '')
{
    $sub_institute_id = $_REQUEST['sub_institute_id'];
}

if (isset($_REQUEST['other_submit']) && $_REQUEST['other_submit'] == 'Save & Next' && $_SESSION['token_no'] != '') 
{

    $token_no = $_SESSION['token_no'];

    $house_no = $_REQUEST['house_no'];
    $area = $_REQUEST['area'];
    $city = $_REQUEST['city'];
    $pin_code = $_REQUEST['pin_code'];
   

     $update_sql = "UPDATE `new_admission_inquiry_registration` SET house_no = '".$house_no."',area = '".$area."',city = '".$city."',pin_code = '".$pin_code."',updated_on = Now() WHERE token = '".$token_no."' ";
    // die;
    $ret = mysqli_query($cn,$update_sql);

    if (mysqli_affected_rows($cn) != 0)
    {
        header('Location: step3.php/?sub_institute_id='.$sub_institute_id);
    }else{
        echo "<br><center><label style='color:red;'>Please fill all data in proper format !!</label></center>";
    }
}

if (!isset($_REQUEST['other_submit'])) 
{

    $token_no = $_SESSION['token_no'];

    $Sql = "SELECT * FROM new_admission_inquiry_registration WHERE token = '" . $token_no . "' "; //die;
    $RET = mysqli_query($cn, $Sql);
    while ($row = mysqli_fetch_array($RET, MYSQLI_ASSOC))
    {
        $house_no = $row['house_no'];
        $area = $row['area'];
        $city = $row['city'];
        $pin_code = $row['pin_code'];
    }
}

include('header.php');

?>


                <?php
                if ($_SESSION['token_no'] == '') {
                    echo "<br><center><label style='color:red;font-weight: bold;font-size: 20px;padding-bottom: 30px;'>Please follow steps properly !!</label></center>";
                    exit;
                }
                ?>
                <?php 
                if ($_SESSION['token_no'] != '') 
                {
                ?>    
                <form class="contact100-form validate-form" action="step2.php/?sub_institute_id=<?php echo $sub_institute_id; ?>">
                    <input id="sub_institute_id" type="hidden" name="sub_institute_id" value="<?php echo $sub_institute_id; ?>">
                    
                    <div style="width: 100%;">
                        <center>
                            <h3><b>Residential Address</b></h3>
                        </center>
                        <br>
                        <label>House No. & Building Name<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input1">
                            <input id="house_no" class="input100" type="text" name="house_no" value="<?php if(isset($house_no)) echo $house_no; ?>" required> 
                        </div>
                        <label>Area<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input1">
                            <input id="area" class="input100" type="text" name="area" value="<?php if(isset($area)) echo $area; ?>" required> 
                        </div>
                        <label>City<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input1">
                            <input id="city" class="input100" type="text" name="city" value="<?php if(isset($city)) echo $city; ?>" required> 
                        </div>
                        <label>Pin Code<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input1">
                            <input id="pin_code" class="input100" type="text" name="pin_code" value="<?php if(isset($pin_code)) echo $pin_code; ?>" pattern="\d*" maxlength="6" required> 
                        </div>
                    </div> 
                    <div class="container-contact100-form-btn">
                        <button type="submit" name="other_submit" id="other_submit" value="Save & Next" class="contact100-form-btn">Save & Next</button>
                    </div>
                </form>
                <?php 
                }
                ?>
            </div>
        </div>
        <script src="vendor/jquery/jquery-3.2.1.min.js"></script>
        <script src="vendor/animsition/js/animsition.min.js"></script>
        <script src="vendor/bootstrap/js/popper.js"></script>
        <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
        <script src="vendor/select2/select2.min.js"></script>
        <script src="vitalets-bootstrap-datepicker-c7af15b/js/bootstrap-datepicker.js"></script>
        <script src="vendor/countdowntime/countdowntime.js"></script>
        <script src="js/main.js"></script>
    </body>
</html>
