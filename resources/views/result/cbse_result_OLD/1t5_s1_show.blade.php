@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<link rel="stylesheet" href="{{ URL::asset('css/result.css') }}"/>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row" style=" margin-top: 25px;">
            <div class="panel-body white-box">
                @if(!empty($data['message']))
                    <div class="alert alert-{{ $data['class'] }} alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $data['message'] }}</strong>
                    </div>
                @endif

                <div>
                    <center><input class="btn btn-warning mb-4" type="button" onclick="printDiv('printableArea');"
                                   value="Print & Generate Result"/></center>
                </div>

                <!--<div class="col-lg-12 col-sm-12 col-xs-12">-->
                <div id="printableArea">
                    <div>
                        <?php
                        $header_data = $data['header_data'];
                        $footer_data = $data['footer_data'];

                        //foreach ($data as $arr) {
                        foreach ($data['data'] as $stuent_id => $all_data) {
                        ?>
                        <div id="{{$stuent_id}}">
                            <table class="main-table ml-5 mr-5 mb-5" width="100%" style="border:1px solid #f37a0d;">
                                <tbody>
                                <tr>
                                    <td>
                                        <table class="report-card" style="border-collapse:collapse;" width="100%"
                                               cellspacing="0" cellpadding="0">
                                            <tbody>
                                            <tr>
                                                <td style="width: 165px;text-align: center;" align="left">
                                                    <img style="width: 100px;height: 90px;margin: 0;"
                                                         src="/storage/result/left_logo/{{$header_data['left_logo']}}"
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
                                        <table class="report-card ml-4" style="border-collapse:collapse;" width="100%"
                                               cellspacing="0" cellpadding="0">
                                            <tbody>
                                            <tr>
                                                <td colspan="3" align="center">
                                                    <h3 style="font-size:14">MARKSHEET CUM CERTIFICATE OF
                                                        PERFORMANCE</h3>
                                                    <h3 style="font-size:14">
                                                        SESSION <?php echo $all_data['year']; ?></h3>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td width="60%">Student's Name :
                                                    <label><?php echo $all_data['name']; ?></label></td>
                                                <td width="20%"></td>
                                                <td width="20%">Roll No. :
                                                    <label><?php echo $all_data['roll_no']; ?></label></td>
                                            </tr>
                                            <tr>
                                                <td>Mother's Name :
                                                    <label><?php echo $all_data['mother_name']; ?></label></td>
                                                <td></td>
                                                <td>Class : <label><?php echo $all_data['class']; ?></label></td>
                                            </tr>
                                            <tr>
                                                <td>Father's Name :
                                                    <label><?php echo $all_data['father_name']; ?></label></td>
                                                <td></td>
                                                <td>Division : <label><?php echo $all_data['division']; ?></label></td>
                                            </tr>
                                            <tr>
                                                <td>Date Of Birth :
                                                    <label><?php echo $all_data['date_of_birth']; ?></label></td>
                                                <td></td>
                                                <td>G.R. No. : <label><?php echo $all_data['gr_no']; ?> </label></td>
                                            </tr>
                                            </tbody>
                                        </table>
                                        <table class="report-card ml-4 mr-4 mb-4" style="border-collapse:collapse;"
                                               width="100%" cellspacing="0" cellpadding="0">
                                            <tbody>
                                            <tr>
                                                <td colspan="3">
                                                    <table class="aca-year"
                                                           style="border-collapse:collapse; border:1px solid #e68023;"
                                                           width="100%" cellspacing="0" cellpadding="0" border="1">
                                                        <tbody>
                                                        <tr>
                                                            <th class="main-th" align="left">Part 1-A-Scholastic
                                                                Areas:
                                                            </th>
                                                            <th style="text-align: center;"
                                                                colspan="<?php echo count($all_data['exam']) + 1; ?>"
                                                                class="main-th"><?php echo $all_data['term'] . " (" . $all_data['total_mark'] . " Marks)"; ?></th>
                                                        </tr>
                                                        <tr>
                                                            <th align="left">Sub Name</th>
                                                            <?php
                                                            foreach ($all_data['exam'] as $temp_id => $exam_data) {
                                                            ?>
                                                            <th style="text-align: center;"><?php echo $exam_data['exam']; ?>
                                                                <br>(<?php echo $exam_data['mark']; ?>)
                                                            </th>
                                                            <?php
                                                            }
                                                            ?>
                                                            <th style="text-align: center;">Grade</th>
                                                        </tr>
                                                        <?php
                                                        foreach ($all_data['mark'] as $subject => $subject_data) {
                                                        ?>
                                                        <tr>
                                                            <td><?php echo $subject; ?></td>
                                                            <?php foreach ($subject_data as $exam_name => $obtain_point) { ?>
                                                            <td align="center"><?php echo $obtain_point; ?></td>
                                                            <?php } ?>
                                                        </tr>
                                                        <?php
                                                        }
                                                        ?>

                                                        <tr>
                                                            <td colspan="<?php echo count($all_data['exam']); ?>"><b>Percentage</b>
                                                            </td>
                                                            <td align="center">
                                                                <b><?php echo round($all_data['per'], 2); ?>%</b></td>
                                                            <td align="center">
                                                                <b><?php echo $all_data['final_grade']; ?></b></td>
                                                        </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="p-t-10" width="100%" valign="top">
                                                    <div style='display:flex;'>
                                                        <?php
                                                        $count = 0;
                                                        if(isset($all_data['co_scholastic_area']))
                                                        {
                                                        foreach ($all_data['co_scholastic_area'] as $co_area => $arr) {
                                                        foreach ($arr as $parent => $child_arr) {
                                                        $count = $count + 1;
                                                        if ($count % 2 == 0) {
                                                            $margin = "margin-left:2.5%;";
                                                        } else {
                                                            $margin = "margin-right:2.5%;";
                                                        }
                                                        echo "<div style='display:flex;width:50%;$margin'>";
                                                        ?>
                                                        <table class="aca-year"
                                                               style="width: 100%;border-collapse:collapse; border:1px solid #e68023;"
                                                               cellspacing="0" cellpadding="0" border="1">
                                                            <tbody>
                                                            <tr>
                                                                <th colspan="2" width="15%" align="center">
                                                                    <b><?php echo $parent; ?></b></th>
                                                            </tr>
                                                            <tr>
                                                                <th width="15%" align="center"><b>Optional Subject</b>
                                                                </th>
                                                                <th width="15%" align="center"><b>Grade</b></th>
                                                            </tr>
                                                            <?php
                                                            foreach ($child_arr as $subject => $obtain_grade) {
                                                            ?>
                                                            <tr>
                                                                <td><?php echo $subject; ?></td>
                                                                <td align="center"><?php echo $obtain_grade; ?></td>
                                                            </tr>

                                                            <?php } ?>
                                                            </tbody>
                                                        </table>
                                                    <?php
                                                    echo "</div>";

                                                    if ($count % 2 == 0) {
                                                        echo "</div>";
                                                        echo "<div class='p-t-10' style='display:flex;'>";
                                                    }
                                                    }
                                                    echo "</div>";
                                                    }
                                                    }
                                                    ?>
                                                    <!--</div>-->
                                                </td>
                                            </tr>

                                            @if(count($footer_data) > 0)
                                                <tr>
                                                    <td>
                                                        <table class="aca-year"
                                                               style="border-collapse:collapse; border:1px solid #e68023;"
                                                               width="100%" cellspacing="0" cellpadding="0" border="1">
                                                        <!--    <tr>
                                                                    <th><b>Attendance (Term I+II)</b></th>
                                                                    <td><?php echo $all_data['att']; ?></td>
                                                                </tr> -->
                                                            <tr>
                                                                <th><b>Result</b></th>

                                                                <td>
                                                                    <?php
                                                                    // if($pass === true){
                                                                    //     echo "Passed and Promoted to Grade XII ".$all_data['medium'];
                                                                    // }else{
                                                                    //     echo "Detained  in  Grade  XI ".$all_data['medium'];
                                                                    // }
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                        <!-- <tr>
                                                                    <th><b>School Reopens on</b></th>
                                                                    <td>
                                                                    <?php
                                                        // $date = date_create($footer_data['reopen_date']);
                                                        // echo date_format($date,"d-m-Y");
                                                        ?>
                                                            </td> -->
                                                            </tr>
                                                            <tr>
                                                                <th><b>Signature</b></th>
                                                                <td colspan=2>
                                                                    <table width="100%" border="0">
                                                                        <tr>
                                                                            <td style="text-align: center;">
                                                                                <br><br>
                                                                                <img height="50px" width="100px"
                                                                                     src="/storage/result/teacher_sign/{{$footer_data['teacher_sign']}}"/>
                                                                                <hr>
                                                                                Teacher's Sign
                                                                            </td>
                                                                            <td style="text-align: center;">
                                                                                <br><br>
                                                                                <img height="50px" width="100px"
                                                                                     src="/storage/result/principle_sign/{{$footer_data['principal_sign']}}"/>
                                                                                <hr>
                                                                                Principal's Sign
                                                                            </td>
                                                                            <td style="text-align: center;">
                                                                                <br><br>
                                                                                <img height="50px" width="100px"/>
                                                                                <hr>
                                                                                Parent's Sign
                                                                            </td>
                                                                        </tr>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            @endif




                                            <!-- <tr>
                                                        <td colspan="3" class="p-t-10">
                                                            <b>Attendance: <?php echo $all_data['att']; ?></b>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="3" class="p-t-10">
                                                            <b>Class Teacher's Remarks : Excellent</b>
                                                        </td>
                                                    </tr>
                                                    <tr class="p-t b-b">
                                                        <td colspan="3">
                                                            <table class="signature" width="100%" cellspacing="0" cellpadding="0">
                                                                <tbody><tr>
                                                                        <td align="center"><b>Signature of Class Teacher</b></td>
                                                                        <td align="center"><b>Signature of Principal</b></td>
                                                                        <td align="center"><b>Signature of Parent</b></td>
                                                                    </tr>
                                                                </tbody></table>
                                                        </td>
                                                    </tr>
                                                     -->
                                            <tr>
                                                <td colspan="3" align="center" class="p-t-10" valign="top">
                                                    <?php
                                                    if(count($all_data['grade_range']) > 0)
                                                    {
                                                    foreach ($all_data['grade_range'] as $mark_range => $arr) {
                                                    ?>
                                                    <table class="aca-year"
                                                           style="border-collapse:collapse; border:1px solid #e68023;"
                                                           width="100%" cellspacing="0" cellpadding="0" border="1">
                                                        <tbody>
                                                        <?php
                                                        foreach ($arr as $heading => $grd_data) {
                                                        ?>
                                                        <tr>
                                                            <th align="center" width="200px">
                                                                <b><?php echo $heading; ?></b></th>
                                                            <?php foreach ($grd_data as $id => $range) { ?>
                                                            <td align="center"><?php echo $range; ?></td>
                                                            <?php } ?>

                                                        </tr>
                                                        <?php
                                                        }
                                                        ?>
                                                        </tbody>
                                                    </table>
                                                    <?php
                                                    }
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <!-- <tr>
                                                <td colspan="3" align="center" class="p-t-10" valign="top">
                                                    <table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" width="100%" cellspacing="0" cellpadding="0" border="1">
                                                        <tbody>
                                                            <tr>
                                                                <th width="200px" align="center"><b>CO-SCHOLASTIC GRADE1</b></th>
                                                                <td align="center">4.1 - 5.0</td>
                                                                <td align="center">3.1 - 4.0</td>
                                                                <td align="center">2.1 - 3.0</td>
                                                            </tr>
                                                            <tr>
                                                                <th width="200px" align="center"><b>GRADE POINTS</b></th>
                                                                <td align="center">A</td>
                                                                <td align="center">B</td>
                                                                <td align="center">C</td>
                                                            </tr>
                                                            <tr>
                                                                <th width="200px" align="center"><b>REMARKS</b></th>
                                                                <td align="center">Very Good</td>
                                                                <td align="center">Good</td>
                                                                <td align="center">Fair</td>
                                                            </tr>
                                                        </tbody>-->
                                        </table>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                            </td>
                            </tr>
                            </tbody>
                            </table>
                        </div>
                        <div style="page-break-after: always !important;"></div>
                        <?php
                        }
                        //}

                        $student_id_arr = implode(",", array_keys($data['data']));
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

        var student_data = <?php echo json_encode($data['data']); ?>;
        $.each(student_data, function (idx, obj) {
            var stu_id = idx;
            var ele_id = "html_" + stu_id;
            result_html = document.getElementById(stu_id).innerHTML;
            result_html = result_html.replaceAll("'", "\"");
            $("#savehtml").append("<input type='hidden' name='" + ele_id + "' id='" + ele_id + "' value='" + result_html + "'>");
        });

        var form = $("#savehtml");
        var url = form.attr('action');

        $.ajax({
            type: "POST",
            url: url,
            data: form.serialize(), // serializes the form's elements.
            success: function (data) {
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
