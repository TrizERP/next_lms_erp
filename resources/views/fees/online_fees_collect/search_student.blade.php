<html>

<head>


    <meta charset="utf-8">
    <title>Online PAYMENT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <link rel="icon" type="image/png" href="images/icons/favicon.ico"> -->
    <!-- MATERIAL DESIGN ICONIC FONT -->
    <link rel="stylesheet"
        href="{{ asset("/online_payment/fonts/material-design-iconic-font/css/material-design-iconic-font.min.css") }}">

        <link href="{{ asset("/admin_dep/css/materialdesignicons.min.css") }}" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset("/admin_dep/css/materialdesignicons.min.css") }}"></noscript>
    <!-- STYLE CSS -->
    <!-- <link href="{{ asset("/admin_dep/bootstrap/dist/css/bootstrap.min.css") }}" rel="stylesheet"> -->
    <link href="{{ asset("/online_payment/css/style.css") }}" rel="stylesheet">
    <link href="{{ asset("/admin_dep/css/style.css") }}" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset("/admin_dep/css/style.css") }}"></noscript>

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

        .bottom-right-container {
            position: fixed;
            bottom: 20px; /* Adjust the distance from the bottom as needed */
            right: 20px; /* Adjust the distance from the right as needed */
            /* Add any additional styling you want for the bottom-right container */
        }
    </style>
    
</head>
<div class="wrapper container">
    <div class="inner" style="justify-content: center;">
        <!-- <div class="image-holder"> -->
        <!-- <img src="images/registration-form-6.jpg" alt=""> -->
        <!-- </div> -->
        <form method="POST" id="changeAction" action="{{route('icici_fees_collect')}}">
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

    <div class="bottom-right-container">
        <div class="help-guide">
            <div class="help-guide">
                <div class="help-head">
                    <div class="guide-title">Help Guide</div>
                    <div class="dropdown">
                        <button id="helpGuideButton" class="dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></button>
                    </div>
                    <div class="help-arraw">
                        <i class="mdi mdi-chevron-down"></i>
                    </div>
                </div>
                <div class="help-body" style="display:none;">
                    <div class="w-auto gutter-10 main-nav justify-content-center">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="help-box">
                                    <a id="pdf_link" target="_blank" class="nav-link pb-0">
                                        <span class="menu-main-icon"><i class="mdi mdi-file-pdf md-36"></i></span> PDF
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="help-box">
                                    <a id="youtube_link" target="_blank" class="nav-link pb-0">
                                        <span class="menu-main-icon"><i class="mdi mdi-youtube md-36"></i></span> Youtube
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset("/admin_dep/js/popper.min.js") }}"></script>
<script src="{{ asset("/admin_dep/js/custom.js") }}" ></script>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"
    integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
  
<script src="https://code.jquery.com/jquery-1.10.2.js"></script>
<script src="{{ asset("/admin_dep/js/jquery-ui.js") }}"></script>

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
                                    "/fees/hdfc/online_fees_collect";
                            }
                            if (value.bank_name == 'axis') {
                                document.forms.changeAction.action =
                                    "/fees/axis/online_fees_collect";
                                //console.log(key);
                            }
                            if (value.bank_name == 'aggre_pay') {
                                document.forms.changeAction.action =
                                    "/fees/aggre_pay/online_fees_collect";
                                // console.log(key);
                            }
                            if (value.bank_name == 'icici') {
                                document.forms.changeAction.action =
                                    "/fees/icici/online_fees_collect";
                                // console.log(key);
                            }
                            if (value.bank_name == 'razorpay') {
                                document.forms.changeAction.action =
                                    "/fees/razorpay/online_fees_collect";
                                // console.log(key);
                            }

                            if (value.bank_name == 'payphi') {
                                document.forms.changeAction.action =
                                    "/fees/payphi/online_fees_collect";
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
                if(amount == '' || amount == 0)
                {
                    document.getElementById("errorMessage").innerHTML = "You are not mapped with institute amount.";
                    submit_hide = 1;
                }
                else if(medium == '')
                {
                    document.getElementById("errorMessage").innerHTML = "You are not mapped with institute medium.";
                    submit_hide = 1;
                }
                else{
                    submit_hide = 0;  
                }
            }
        });

        $.each(arr, function (key, value) {
            if (value.id == selectedVal) {
                if (value.bank_name == 'hdfc') {
                    document.forms.changeAction.action = "/fees/hdfc/online_fees_collect";
                    // console.log(key);
                }
                if (value.bank_name == 'axis') {
                    document.forms.changeAction.action = "/fees/axis/online_fees_collect";
                    // console.log(key);
                }
                if (value.bank_name == 'aggre_pay') {
                    document.forms.changeAction.action = "/fees/aggre_pay/online_fees_collect";
                    // console.log(key);
                }
                 if (value.bank_name == 'icici') {
                    document.forms.changeAction.action = "/fees/icici/online_fees_collect";
                    // console.log(key);
                }
                if (value.bank_name == 'razorpay') {
                    document.forms.changeAction.action = "/fees/razorpay/online_fees_collect";
                    // console.log(key);
                }

                if (value.bank_name == 'payphi') {
                    document.forms.changeAction.action = "/fees/payphi/online_fees_collect";
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
<script>
     // Help Guide
		$('.help-body').hide(100);
		$('.guide-title').on('click', function(event) {
		    $('.help-guide').toggleClass('active', 100);
		    $('.help-body').slideToggle(100);
		});
</script>
<script>
    $(document).ready(function () {
        // Help Guide toggle
        $('#helpGuideButton').on('click', function () {
            $('.help-body').slideToggle(100);
        });
    });
</script>
<script>
    $(document).ready(function() {
       
    load_rightside_menu(localStorage.getItem('menu_id'), localStorage.getItem('main_menu_id'));

    function load_rightside_menu(menu_id, main_menu_id) {
      var path1 = "{{ route('ajax_load_helpguide') }}";

      $.ajax({
          url: path1,
          data: 'menu_id=' + menu_id,
          dataType: 'html',
          defer: false,
          success: function (links) {
              // console.log(links);
              if (links != "0") {
                  link_arr = links.split("####");
                  $("#youtube_link").attr("href", link_arr[0]);
                  $("#pdf_link").attr("href", "../../../storage/help_guide/" + link_arr[1]);
              }
          }
      });

      $("[aria-controls='menu-" + main_menu_id + "']").addClass('active');
      $("#menu-" + main_menu_id).addClass('active');

      //var tab_pane_id = $('.main-menu-block').find('.active').attr("aria-controls");
    }
});
</script>

</html>