<?php
session_start();
error_reporting(1);
include('db.php');

$sub_institute_id = '';
if(isset($_REQUEST['sub_institute_id']) && $_REQUEST['sub_institute_id'] != '')
{
    $sub_institute_id = $_REQUEST['sub_institute_id'];
}

include('header.php');

?>


                <?php
                    echo '<center>';
                    echo "<br><label style='color:red;font-weight: bold;font-size: 20px;padding-bottom: 30px;'>Thank you for submitting the Admission Form.</label>";
                    echo "<br><center><label style='color:red;font-weight: bold;font-size: 20px;padding-bottom: 30px;'></label>";
                    echo '</center>';
                    exit;
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
