    </div>
</div>
<script src="vendor/jquery/jquery-3.2.1.min.js"></script>
<script src="vendor/animsition/js/animsition.min.js"></script>
<script src="vendor/bootstrap/js/popper.js"></script>
<script src="vendor/bootstrap/js/bootstrap.min.js"></script>
<script src="vendor/select2/select2.min.js"></script>
<script src="vendor/daterangepicker/moment.min.js"></script>
<script src="vitalets-bootstrap-datepicker-c7af15b/js/bootstrap-datepicker.js"></script>
<script src="vendor/countdowntime/countdowntime.js"></script>
<script src="js/main.js"></script>
<script>
    $('.datepicker').datepicker({format: 'dd-mm-yyyy', autoclose: true});

    function checkform()
    {
        if (document.getElementById("std").value == '') {
            alert("Please Enter Birthdate For Select Standard.");
            return false;
        }
        return true;
    }
    function showAge(age)
    {
        var age = age.split("-").reverse().join("/");
        var nur_from_date = new Date("10/01/2017"); 
        var nur_to_date = new Date("12/31/2018");
        var pg_from_date = new Date("01/01/2019"); 
        var pg_to_date = new Date("12/31/2019");
        var dob = new Date(age);
        var today = new Date("12/31/2021");//'12/31/2021'
        var cur_age = Math.floor((today-dob) / (365.25 * 24 * 60 * 60 * 1000));
        //var Calage = ((today - dob) / (365.25 * 24 * 60 * 60 * 1000)).toFixed(2);
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
    }
    </script>
</body>
</html>