@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<?php
use App\Http\Controllers\result\cbse_result\cbse_1t5_result_controller;
$grade_arr = cbse_1t5_result_controller::getGradeScale($_REQUEST['standard'],'web');
?>

<link rel="stylesheet" href="{{ URL::asset('css/result.css') }}" />
<style type="text/css">
.exam_title_class
{
    font-size: 18px;
    font-weight: bold;
    color: #FFF;
    background: #000;
    padding: 2px 10px;
    border-radius: 5px 5px 0px 0px;
}
.exam{
    width: 30%;
    float: left;
    font-size:17px;
    font-weight: bold
}
.co_ordinator{
    width: 40%;
    float: right;
    font-size:17px;
    font-weight: bold
}
.ribben-base-blue {
  width: 50px;
  padding: 2px 15px;
  background-color: #2012ff;
  margin-left: 5px;
  border: 1px solid #adadad;
  color: #fff;
  font-size: 12px;
}
.ribben-base-yellow {
  width: 50px;
  padding: 2px 15px;
  background-color: #fdeb10;
  margin-left: 5px;
  border: 1px solid #adadad;
  color: #000;
  font-size: 12px;
}
.ribben-base-white {
  width: 50px;
  padding: 2px 15px;
  background-color: #ffffff;
  margin-left: 5px;
  border: 1px solid #adadad;
  color: #000;
  font-size: 12px;
}
.ribben-base-red {
  width: 80px;
  padding: 2px 15px;
  background-color: #ff0707;
  margin-left: 5px;
  border: 1px solid #adadad;
  color: #fff;
  font-size: 12px;
}
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">WRT Progress Report</h4>
            </div>
        </div>
        <div class="row" style=" margin-top: 25px;">
            <div class="panel-body white-box">
                @if(!empty($data['message']))
                <div class="alert alert-{{ $data['class'] }} alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif

                <div>
                    <center> <input class="btn btn-warning mb-4" type="button" onclick="printDiv('printableArea');" value="Print & Generate Result" /></center>
                </div>

                <div id="printableArea">
                <div>
                    <?php

                    $header_data = $data['header_data'];

                    foreach ($data['all_student'] as $key => $all_data)
                    {
                        $student_id = $all_data['id'];
                        $student_name = $all_data['first_name'].' '.$all_data['middle_name'].' '.$all_data['last_name'];

                    ?>
                    <div id="{{$student_id}}">
                        <table class="main-table ml-5 mr-5 mb-5" width="100%"
                               style="border:1px solid #f37a0d;border-collapse: collapse;">
                            <tbody>
                            <tr>
                                <td>
                                    <table class="report-card" style="border-collapse:collapse;" width="100%"
                                           cellspacing="0" cellpadding="0">
                                        <tbody>
                                        <tr>
                                            <td style="width: 165px;text-align: center;" align="left">
                                                <img style="width: 100px;height: 90px;margin: 0;"
                                                     src="/storage/result/left_logo/{{$header_data['left_logo'] ?? ''}}"
                                                     alt="SCHOOL LOGO">
                                            </td>
                                            <td style="text-align:center !important;" align="center">
                                                <span class="sc-hd">{{$header_data['line1']}}</span><br>
                                                <span class="ma-hd">{{$header_data['line2']}}</span><br>
                                                <span class="rg-hd">{{$header_data['line3']}}</span><br>
                                                <span class="rg-hd">{{$header_data['line4']}}</span><br>
                                            </td>
                                            <td style="width: 165px;text-align: center;" align="left">
                                                <img style="width: 100px;height: 90px;margin: 0;"
                                                     src="/storage/result/right_logo/{{$header_data['right_logo']}}"
                                                     alt="SCHOOL LOGO">
                                            </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4">
                                                        <hr></hr>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <table class="report-card ml-4" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">
                                            <tbody>
                                                <tr>
                                                    <td colspan="3" align="center">
                                                        <h3 style="font-size:14">WRT REPORT</h3>
                                                        <h3 style="font-size:14">SESSION <?php echo $data['result_year']; ?></h3>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td width="60%">Student's Name : <label><?php echo $student_name; ?></label></td>
                                                    <td width="20%"></td>
                                                    <td width="20%">Roll No. : <label><?php echo $all_data['roll_no']; ?></label></td>
                                                </tr>
                                                <tr>
                                                    <!-- <td>Mother's Name : <label><?php //echo $all_data['mother_name']; ?></label></td>
                                                    <td></td> -->
                                                    <td>Class : <label><?php echo $all_data['standard_name']; ?></label></td>
                                                </tr>
                                                <tr>
                                                   <!--  <td>Father's Name : <label><?php //echo $all_data['father_name']; ?></label></td>
                                                    <td></td> -->
                                                    <td>Division : <label><?php echo $all_data['division_name']; ?></label></td>
                                                </tr>
                                                <tr>
                                                    <td>Date Of Birth : <label><?php echo $all_data['dob']; ?></label></td>
                                                    <td></td>
                                                    <td>G.R. No. : <label><?php echo $all_data['enrollment_no']; ?> </label></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        @php
                                            $sr = 1;
                                            $grand_total_points = 0;
                                            $grand_obtained_points = 0;
                                        @endphp
                                        @if(isset($data['WRT_data'][$student_id]) && count($data['WRT_data'][$student_id]) > 0)
                                        <table class="report-card ml-4 mr-4 mb-4" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">
                                            <tbody>
                                                <tr>
                                                    <td colspan="2">
                                                        <table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" width="100%" cellspacing="0" cellpadding="0" border="1">
                                                            <tr>
                                                                <th>Sr. No.</th>
                                                                <th>Test Name</th>
                                                                <th>Date</th>
                                                                <th>Day</th>
                                                                <th>Subject</th>
                                                                <th>Total Marks</th>
                                                                <th>Obt. Marks</th>
                                                                <th>Percentage (%)</th>
                                                                <th>Grade</th>
                                                            </tr>
                                                            @foreach($data['WRT_data'][$student_id] as $wkey => $wdata)
                                                            <tr>
                                                                <td>@php echo $sr++; @endphp</td>
                                                                <td>{{$wdata['ExamTitle']}}</td>
                                                                <td>{{$wdata['exam_date']}}</td>
                                                                <td>{{$wdata['exam_day']}}</td>
                                                                <td>{{$wdata['subject_name']}}</td>
                                                                <td>{{$wdata['total_points']}}</td>

                                                                @if($wdata['is_absent'] == 'AB')
                                                                    <td>{{$wdata['is_absent']}}</td>
                                                                @else
                                                                    <td>{{$wdata['obtained_points']}}</td>
                                                                @endif

                                                                <td>{{$wdata['percentage']}}%</td>
                                                                <td>{{$wdata['grade']}}</td>
                                                                @php
                                                                $grand_total_points += $wdata['total_points'];
                                                                $grand_obtained_points += $wdata['obtained_points'];
                                                                $grand_per = (($grand_obtained_points * 100) / $grand_total_points);
                                                                $grand_per = number_format($grand_per,2);
                                                                @endphp
                                                                <?php
                                                                    $grand_grade = cbse_1t5_result_controller::getGrade($grade_arr, $grand_total_points, $grand_obtained_points);

                                                                    $passing_ratio = 35;
                                                                    if ($grand_per >= $passing_ratio)
                                                                    {
                                                                        $result = 'Pass';
                                                                    } else {
                                                                        $result = 'Fail';
                                                                    }

                                                                    if ($grand_per >= 80) {
                                                                        $ribbenClass = 'ribben-base-red';
                                                                        $ribbenLabel = 'Red';
                                                                    } else if ($grand_per >= 60 && $grand_per < 80) {
                                                                        $ribbenClass = 'ribben-base-blue';
                                                                        $ribbenLabel = 'Blue';
                                                                    } else if ($grand_per >= 40 && $grand_per < 60) {
                                                                        $ribbenClass = 'ribben-base-yellow';
                                                                        $ribbenLabel = 'Yellow';
                                                                    } else {
                                                                        $ribbenClass = 'ribben-base-white';
                                                                        $ribbenLabel = 'White';
                                                                    }
                                                                    $rank = $wdata['rank'];
                                                                ?>
                                                            </tr>
                                                            @endforeach
                                                            <tr>
                                                                <td><b>Total : </b>{{$grand_obtained_points}}/{{$grand_total_points}}</td>
                                                                <td><b>Per.(%) : </b>{{$grand_per}}</td>
                                                                <td><b>Difference Per.(%) : </b>{{$grand_per}}</td>
                                                                <td><b>Grade : </b>{{$grand_grade}}</td>
                                                                <td><b>Result : </b>{{$result}}</td>
                                                                <td><b>Rank : </b>{{$rank}}/{{$data['WRT_data']['total_student']}}</td>
                                                                <td width="25%" align="center" colspan="3">
                                                                    <b>Ribben Base : </b>
                                                                    <span class={{$ribbenClass}}>{{$ribbenLabel}}</span>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                        <div style="page-break-after: always !important;"></div>
                        <?php
                    }
                    $student_id_arr = implode(",",array_keys($data['all_student']));
                    ?>
                </div>
                </div>



                @if (count($errors) > 0)
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> There were some problems with your input.<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<form name="savehtml" id="savehtml" action="{{ route('save_result_html') }}" method="POST">
{{method_field('POST')}}
@csrf
<input type="hidden" id="grade_id" name="grade_id" value="{{$data['grade_id']}}">
<input type="hidden" id="standard_id" name="standard_id" value="{{$data['standard_id']}}">
<input type="hidden" id="division_id" name="division_id" value="{{$data['division_id']}}">
<input type="hidden" id="term_id" name="term_id" value="{{$data['term_id']}}">
<input type="hidden" id="syear" name="syear" value="{{$data['syear']}}">
<input type="hidden" id="student_arr" name="student_arr" value="{{$student_id_arr}}">
</form>

@include('includes.footerJs')
<script type="text/javascript">
    function printDiv(divName) {

        var student_data = <?php echo json_encode($data['all_student']); ?>;
        // console.log(student_data);
        $.each(student_data, function (idx, obj) {
            var stu_id = idx;
            var ele_id = "html_"+stu_id;
            result_html = document.getElementById(stu_id).innerHTML;
            result_html = result_html.replaceAll("'","\"");
            $("#savehtml").append("<input type='hidden' name='"+ele_id+"' id='"+ele_id+"' value='"+result_html+"'>");
        });

        var form = $("#savehtml");
        var url = form.attr('action');

        $.ajax({
               type: "POST",
               url: url,
               data: form.serialize(), // serializes the form's elements.
               success: function(data)
               {
                   //alert(data); // show response from the php script.
               }
         });

        var divToPrint = document.getElementById(divName);
        var popupWin = window.open('', '_blank', 'width=300,height=300');
        popupWin.document.open();
        popupWin.document.write('<html>');
        popupWin.document.write('<link rel="stylesheet" href="/css/result.css" />');
        popupWin.document.write('<body onload="window.print()">' + divToPrint.innerHTML + '</html>');
        popupWin.document.close();
    }
</script>
@include('includes.footer')
