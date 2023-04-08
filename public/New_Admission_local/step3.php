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


if (isset($_REQUEST['other_submit']) && $_REQUEST['other_submit'] == 'Save & Next' && $_SESSION['token_no'] != '') 
{

    $token_no = $_SESSION['token_no'];


    $blood_group = $_REQUEST['blood_group'];
    $height = $_REQUEST['height'];
    $weight = $_REQUEST['weight'];
    $vaccination = $_REQUEST['vaccination'];
    $diabetes = $_REQUEST['diabetes'];
    $blood_pressure = $_REQUEST['blood_pressure'];
    $child_admitted = $_REQUEST['child_admitted'];
    $if_yes_then_reason = $_REQUEST['if_yes_then_reason'];
    $how_long = $_REQUEST['how_long'];
    $child_allergies = $_REQUEST['child_allergies'];
    $habit_of_bed_wetting = $_REQUEST['habit_of_bed_wetting'];
    $habit_of_thumb_sucking = $_REQUEST['habit_of_thumb_sucking'];
    $habit_of_anti_acid_activity = $_REQUEST['habit_of_anti_acid_activity'];
    $habit_of_drug_allergy = $_REQUEST['habit_of_drug_allergy'];
    $child_dependent = $_REQUEST['child_dependent'];
    $behavioral_problem = $_REQUEST['behavioral_problem'];
    $child_taking_milk = $_REQUEST['child_taking_milk'];
    $child_taking_curd = $_REQUEST['child_taking_curd'];
    $child_taking_vegetables = $_REQUEST['child_taking_vegetables'];

    $update_sql = "UPDATE `new_admission_inquiry_registration` SET  blood_group = '".$blood_group."',height = '".$height."',weight = '".$weight."',vaccination = '".$vaccination."',diabetes = '".$diabetes."',blood_pressure = '".$blood_pressure."',child_admitted = '".$child_admitted."',if_yes_then_reason='".$if_yes_then_reason."',how_long = '".$how_long."',child_allergies = '".$child_allergies."',habit_of_bed_wetting = '".$habit_of_bed_wetting."',habit_of_thumb_sucking = '".$habit_of_thumb_sucking."',habit_of_anti_acid_activity = '".$habit_of_anti_acid_activity."',habit_of_drug_allergy = '".$habit_of_drug_allergy."',child_dependent = '".$child_dependent."',behavioral_problem = '".$behavioral_problem."',child_taking_milk = '".$child_taking_milk."',child_taking_curd = '".$child_taking_curd."',child_taking_vegetables = '".$child_taking_vegetables."' WHERE token = '".$token_no."' ";
    // die;
    $ret = mysqli_query($cn,$update_sql);

    if (mysqli_affected_rows($cn) != 0)
    {
        header('Location: step4.php');
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
        $blood_group = $row['blood_group'];
        $height = $row['height'];
        $weight = $row['weight'];
        $vaccination = $row['vaccination'];
        $diabetes = $row['diabetes'];
        $blood_pressure = $row['blood_pressure'];
        $child_admitted = $row['child_admitted'];
        $if_yes_then_reason = $row['if_yes_then_reason'];
        $how_long = $row['how_long'];
        $child_allergies = $row['child_allergies'];
        $habit_of_bed_wetting = $row['habit_of_bed_wetting'];
        $habit_of_thumb_sucking = $row['habit_of_thumb_sucking'];
        $habit_of_anti_acid_activity = $row['habit_of_anti_acid_activity'];
        $habit_of_drug_allergy = $row['habit_of_drug_allergy'];
        $child_dependent = $row['child_dependent'];
        $behavioral_problem = $row['behavioral_problem'];
        $child_taking_milk = $row['child_taking_milk'];
        $child_taking_curd = $row['child_taking_curd'];
        $child_taking_vegetables = $row['child_taking_vegetables'];
    }
}

?>


<html lang="en">
    <head>
        <title>Further Admission Registration</title>
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
        <link rel="stylesheet" type="text/css" href="css/util.css">
        <link rel="stylesheet" type="text/css" href="css/main.css">
        <link rel="stylesheet" type="text/css" href="vitalets-bootstrap-datepicker-c7af15b/css/datepicker.css">
    </head>
    <body>

        <div class="container-contact100">
            <div class="newwrap-contact100">
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
                if ($_SESSION['token_no'] == '') {
                    echo "<br><center><label style='color:red;font-weight: bold;font-size: 20px;padding-bottom: 30px;'>Please follow steps properly !!</label></center>";
                    exit;
                }
                ?>
    
                <?php 
                if ($_SESSION['token_no'] != '') 
                {
                ?>
                <form class="contact100-form validate-form" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" >
                    <div style="width: 100%;">
                        <center>
                            <h3><b>Medical History</b></h3>
                            <h4><b>(Upload related documents in the last page)</b></h4>
                        </center>
                        <br>
                        <label>Blood Group<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="blood_group" id="blood_group" class="input100">
                                <option value='A+' <?php if($blood_group == 'A+') echo 'selected'; ?>>A+</option>
                                <option value='A-' <?php if($blood_group == 'A-') echo 'selected'; ?>>A-</option>
                                <option value='B+' <?php if($blood_group == 'B+') echo 'selected'; ?>>B+</option>
                                <option value='B-' <?php if($blood_group == 'B-') echo 'selected'; ?>>B-</option>
                                <option value='O+' <?php if($blood_group == 'O+') echo 'selected'; ?>>O+</option>
                                <option value='O-' <?php if($blood_group == 'O-') echo 'selected'; ?>>O-</option>
                                <option value='AB+' <?php if($blood_group == 'AB+') echo 'selected'; ?>>AB+</option>
                                <option value='AB-' <?php if($blood_group == 'AB-') echo 'selected'; ?>>AB-</option>                           
                            </select>                            
                        </div>
                        <label>Height In Cms<font color="red">*</font></label>
                        <div class="wrap-input100">
                            <input id="height" class="input100" name="height" type="number" pattern="[0-9]+([,\.][0-9]+)?" step="0.01" value="<?php if(isset($height)) echo $height; ?>" > 
                        </div>
                        <label>Weight In Kgs<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="weight" class="input100" type="number" pattern="[0-9]+([,\.][0-9]+)?" step="0.01" name="weight" value="<?php if(isset($weight)) echo $weight; ?>" >
                        </div>
                        <label>Has the child given all the vaccination?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="vaccination" id="vaccination" class="input100">
                                <option value='Yes' <?php if($vaccination == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($vaccination == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <br>
                        <span style="font-weight: bold;">A) Family History Of illness :</span>
                        <label>Diabetes<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="diabetes" id="diabetes" class="input100">
                                <option value='Affected to Father/Mother' <?php if($diabetes == 'Affected to Father/Mother' ) echo 'selected'; ?>>Affected to Father/Mother</option>
                                <option value='Affected to Brother/Sister' <?php if($diabetes == 'Affected to Brother/Sister' ) echo 'selected'; ?>>Affected to Brother/Sister</option>
                                <option value='None of them are affected' <?php if($diabetes == 'None of them are affected' ) echo 'selected'; ?>>None of them are affected</option>
                                <option value='Affected to Both Mother & Father' <?php if($diabetes == 'Affected to Both Mother & Father' ) echo 'selected'; ?>>Affected to Both Mother & Father</option>
                            </select>
                        </div>
                        <label>Blood Pressure<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="blood_pressure" id="blood_pressure" class="input100">
                                <option value='Affected to Father/Mother' <?php if($blood_pressure == 'Affected to Father/Mother' ) echo 'selected'; ?>>Affected to Father/Mother</option>
                                <option value='Affected to Brother/Sister' <?php if($blood_pressure == 'Affected to Brother/Sister' ) echo 'selected'; ?>>Affected to Brother/Sister</option>
                                <option value='None of them are affected' <?php if($blood_pressure == 'Affected to Brother/Sister' ) echo 'selected'; ?>>None of them are affected</option>
                                <option value='Affected to Both Mother & Father' <?php if($blood_pressure == 'Affected to Both Mother & Father' ) echo 'selected'; ?>>Affected to Both Mother & Father</option>
                            </select>
                        </div>
                        <br>
                        <span style="font-weight: bold;">B) Past History of Major illness :</span>
                        <label>Was the child admitted to hospital at any time?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="child_admitted" id="child_admitted" class="input100" onchange="showothers(value);">
                                <option value='No' <?php if($child_admitted == 'No' ) echo 'selected'; ?>>No</option>
                                <option value='Yes' <?php if($child_admitted == 'Yes' ) echo 'selected'; ?>>Yes</option>
                            </select>
                        </div>
                        <label style="display: none;" id="labelforYes">If YES then reason</label>
                        <div class="wrap-input100" style="display: none;" id="inputsforYes">
                            <input id="if_yes_then_reason" class="input100" type="text" name="if_yes_then_reason" value="<?php if(isset($if_yes_then_reason)) echo $if_yes_then_reason; ?>" > 
                        </div>
                        <label style="display: none;" id="labelforlong">How long</label>
                        <div class="wrap-input100" style="display: none;" id="inputsforlong">
                            <input id="how_long" class="input100" type="text" name="how_long" value="<?php if(isset($how_long)) echo $how_long; ?>" > 
                        </div>
                        <label>Does the child have identified allergies if so, give details<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="child_allergies" class="input100" type="text" name="child_allergies" value="<?php if(isset($child_allergies)) echo $child_allergies; ?>" > 
                        </div>
                        <label>Habit Of Bed Wetting?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="habit_of_bed_wetting" id="habit_of_bed_wetting" class="input100">
                                <option value='Yes' <?php if($habit_of_bed_wetting == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($habit_of_bed_wetting == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <label>Habit Of Thumb Sucking?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="habit_of_thumb_sucking" id="habit_of_thumb_sucking" class="input100">
                                <option value='Yes' <?php if($habit_of_thumb_sucking == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($habit_of_thumb_sucking == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <label>Habit Of Anti Acid Activity?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="habit_of_anti_acid_activity" id="habit_of_anti_acid_activity" class="input100">
                                <option value='Yes' <?php if($habit_of_anti_acid_activity == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($habit_of_anti_acid_activity == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <label>Habit Of Drug Allergy?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="habit_of_drug_allergy" id="habit_of_drug_allergy" class="input100">
                                <option value='Yes' <?php if($habit_of_drug_allergy == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($habit_of_drug_allergy == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <br>
                        <span style="font-weight: bold;">C) History Of Psychiatric Problem :</span>
                        <label>Is the child too much dependent on parents?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="child_dependent" id="child_dependent" class="input100">
                                <option value='Yes' <?php if($child_dependent == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($child_dependent == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <label>Mention behavioral problems if any<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="behavioral_problem" class="input100" type="text" name="behavioral_problem" value="<?php if(isset($behavioral_problem)) echo $behavioral_problem; ?>" > 
                        </div>
                        <br>
                        <span style="font-weight: bold;">D) Food Habits :</span>
                        <label>Is the child taking plain milk regularly?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="child_taking_milk" id="child_taking_milk" class="input100">
                                <option value='Yes' <?php if($child_taking_milk == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($child_taking_milk == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <label>Does the child take curd?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="child_taking_curd" id="child_taking_curd" class="input100">
                                <option value='Yes' <?php if($child_taking_curd == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($child_taking_curd == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <label>Is the child taking all vegetables?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="child_taking_vegetables" id="child_taking_vegetables" class="input100">
                                <option value='Yes' <?php if($child_taking_vegetables == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($child_taking_vegetables == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
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
        <script type="text/javascript">
            function showothers(other)
            {
                if(other === 'Yes')
                {   
                    // alert('yes');
                    document.getElementById("labelforYes").style.display = "block";
                    document.getElementById("inputsforYes").style.display = "block";
                    document.getElementById("labelforlong").style.display = "block";
                    document.getElementById("inputsforlong").style.display = "block";
                }else{
                    // alert('no');
                    document.getElementById("labelforYes").style.display = "none";
                    document.getElementById("inputsforYes").style.display = "none";
                    document.getElementById("labelforlong").style.display = "none";
                    document.getElementById("inputsforlong").style.display = "none";
                }   
            }
        </script>
    </body>
</html>
