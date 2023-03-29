<html>

<head>


    <meta charset="utf-8">
    <title>Online PAYMENT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <link rel="icon" type="image/png" href="images/icons/favicon.ico"> -->
    <!-- MATERIAL DESIGN ICONIC FONT -->
    <link rel="stylesheet"
        href="{{ asset("/online_payment/fonts/material-design-iconic-font/css/material-design-iconic-font.min.css") }}">


    <!-- STYLE CSS -->
    <!-- <link href="{{ asset("/admin_dep/bootstrap/dist/css/bootstrap.min.css") }}" rel="stylesheet"> -->
    <link href="{{ asset("/online_payment/css/style.css") }}" rel="stylesheet">

    <!-- <link rel="stylesheet" href="css/style.css"> -->
    <style>
        #customers {
            font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;
            border-collapse: collapse;
            width: 100%;
            text-align: center;
        }

        #customers td,
        #customers th {
            border: 1px solid #ddd;
            padding: 8px;
        }

        /*#customers tr:nth-child(even){background-color: #f2f2f2;}*/

        #customers tr:hover {
            background-color: #ddd;
        }

        #customers th {
            padding-top: 12px;
            padding-bottom: 12px;
            text-align: center;
            background-color: #7fc6da;
            color: white;
        }
    </style>

</head>
<div class="wrapper">
    <div class="inner">
        <!-- <div class="image-holder"> -->
        <!-- <img src="images/registration-form-6.jpg" alt=""> -->
        <!-- </div> -->
        <form method="POST" id="changeAction" action="{{route('icici_fees_collect')}}" style="margin-left: 70px;">
            {{csrf_field()}}
            <p style="color:red;text-align:center;" id="errorMessage"></p>
            <h3>Make An Online Payment</h3>
            <div class="form-row">
                <input type="text" class="form-control" onchange="getStudents(this.value);" required="required"
                    placeholder="Enter Mobile Number.">
                <div class="form-holder">
                    <select name="student_id" id="student_id" onchange="return changePostmethod(this.value);"
                        class="form-control" required="required">
                        <option value="" disabled="" selected="">Choose Your Child</option>
                    </select>
                    <i class="zmdi zmdi-chevron-down"></i>
                </div>
            </div>
            <button type="submit" name="submit">Submit<i class="zmdi zmdi-long-arrow-right"></i></button>
        </form>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"
    integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script type="text/javascript">
    var arr;
    var submit_hide = 0;
    
    function getStudents(number) {
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                if (this.responseText != '' && this.responseText != '[]') {
                    console.log("asd" + this.responseText + "as");
                    temp = JSON.parse(this.responseText);
                    arr = temp;
                    var i = 1;
                    $('#student_id').find('option').remove().end().append('<option value="">Choose Your Child</option>').val('');
                    $.each(temp, function (key, value) {
                        console.log(key);
                        if (i == 1) {
                            if (value.bank_name == 'hdfc') {
                                document.forms.changeAction.action =
                                    "https://erp.triz.co.in/fees/hdfc/online_fees_collect";
                            }
                            if (value.bank_name == 'axis') {
                                document.forms.changeAction.action =
                                    "https://erp.triz.co.in/fees/axis/online_fees_collect";
                                //console.log(key);
                            }
                            if (value.bank_name == 'aggre_pay') {
                                document.forms.changeAction.action =
                                    "https://erp.triz.co.in/fees/aggre_pay/online_fees_collect";
                                // console.log(key);
                            }
                            if (value.bank_name == 'icici') {
                                document.forms.changeAction.action =
                                    "https://erp.triz.co.in/fees/icici/online_fees_collect";
                                // console.log(key);
                            }
                            if (value.bank_name == 'razorpay') {
                                document.forms.changeAction.action =
                                    "https://erp.triz.co.in/fees/razorpay/online_fees_collect";
                                // console.log(key);
                            }
                        }
                        $("#student_id").append('<option value=' + value.id + '>' + value.name +
                            '</option>');
                        i = i + 1;
                    });
                    //document.getElementById("student_id").innerHTML = this.responseText;
                    document.getElementById("errorMessage").innerHTML = "";
                } else {
                    document.getElementById("errorMessage").innerHTML = "Please enter valid mobile number.";
                }
            }
        };
        xhttp.open("GET", "get-student?" + "mobile_number=" + number, true);
        xhttp.send();
    }

    function changePostmethod(selectedVal) {
       
        var path = "{{ route('ajax_checkFeesBreakoff') }}";
        $.ajax({
            url:path,
            data:'student_id='+selectedVal,
            success:function(result){                
                var result_arr = result.split("####");
                var amount = result_arr[0];
                var medium = result_arr[1];
                if(amount == '' || amount == 0 || medium == '')
                {
                    document.getElementById("errorMessage").innerHTML = "Please enter fees breakoff first OR Student Medium.";
                    submit_hide = 1;
                }else{
                    submit_hide = 0;  
                }
            }
        });

        $.each(arr, function (key, value) {
            if (value.id == selectedVal) {
                if (value.bank_name == 'hdfc') {
                    document.forms.changeAction.action = "https://erp.triz.co.in/fees/hdfc/online_fees_collect";
                    // console.log(key);
                }
                if (value.bank_name == 'axis') {
                    document.forms.changeAction.action = "https://erp.triz.co.in/fees/axis/online_fees_collect";
                    // console.log(key);
                }
                if (value.bank_name == 'aggre_pay') {
                    document.forms.changeAction.action = "https://erp.triz.co.in/fees/aggre_pay/online_fees_collect";
                    // console.log(key);
                }
                 if (value.bank_name == 'icici') {
                    document.forms.changeAction.action = "https://erp.triz.co.in/fees/icici/online_fees_collect";
                    // console.log(key);
                }
                 if (value.bank_name == 'razorpay') {
                    document.forms.changeAction.action = "https://erp.triz.co.in/fees/razorpay/online_fees_collect";
                    // console.log(key);
                }
            }
            // console.log(key);
        });
    }

$(document).ready(function() {
    $("form").submit(function(e){
        if(submit_hide == 1)
        {
            return false;
        }
        else
        {
            return true;
        }
    });
});
</script>

</html>