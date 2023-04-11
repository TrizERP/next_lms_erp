@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<link rel="stylesheet" href="{{ URL::asset('css/result.css') }}" />

<?php
// $css = File::get('css/result.css');
?>
{{-- <style>
    table td,
    table th {
        border: 1px solid #ddd;
        padding: 8px;
    }
</style> --}}
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
                    <center> <input class="btn btn-warning" type="button" onclick="printDiv('printableArea');" value="Print Result" /></center>
                </div>
                <!--<div class="col-lg-12 col-sm-12 col-xs-12">-->
                <div id="printableArea">
                    <style>
                        table td,
                        table th {
                            border: 1px solid #ddd;
                            padding: 8px;
                        }
                    </style>

                    <?php
                // echo ('<pre>');print_r($data);exit;
                    foreach ($data as $arr) {
                        foreach ($arr as $stuent_id => $all_data) {
                            ?>
                    <table class="main-table m-2" width="100%" style="page-break-after: always;">
                        <tbody>
                            <tr>
                                <td>

                                    <table class="report-card" style="border-collapse:collapse;" width="100%"
                                        cellspacing="0" cellpadding="0">
                                        <tbody>
                                            <tr>
                                                <td colspan="3" align="center">
                                                    <h3 style="font-size:18">
                                                        <?php echo $all_data['headings']['line1']; ?>
                                                    </h3>
                                                    <h3 style="font-size:14">
                                                        <?php echo $all_data['headings']['line2']; ?>
                                                    </h3>
                                                    <h3 style="font-size:14">
                                                        <?php echo $all_data['headings']['line3']; ?>
                                                    </h3>
                                                    <h3 style="font-size:16">
                                                        <?php echo $all_data['headings']['line4']; ?>
                                                    </h3>
                                                    <h3 style="font-size:16">
                                                        SESSION <?php echo $all_data['year']; ?>
                                                    </h3>
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
                                                    <label><?php
                                                        $date = date_create($all_data['date_of_birth']);
                                                        echo date_format($date, "d-m-Y");
                                                        ?></label></td>
                                                <td></td>
                                                <td>G.R. No. : <label><?php echo $all_data['gr_no']; ?> </label></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <br><br>

                                    <table border=1 style="border-collapse:collapse;" width="100%" cellspacing="0"
                                        cellpadding="0">
                                        <tbody>
                                            <tr>
                                                <th rowspan="4">
                                                    SUBJECTS
                                                </th>
                                                <th colspan="14" style="text-align:center">
                                                    ACADEMIC YEAR EXAM
                                                </th>
                                            </tr>

                                            <tr>
                                                <th colspan="2" style="text-align:center">
                                                    UNIT TEST
                                                </th>
                                                <th rowspan="2" colspan="2" style="text-align:center">
                                                    HALF YEARLY EXAM
                                                </th>
                                                <th rowspan="2" colspan="2" style="text-align:center">
                                                     YEARLY PRACTICAL/ASL/PROJECT
                                                </th>
                                                <th rowspan="2" colspan="2" style="text-align:center">
                                                    YEARLY EXAM
                                                </th>
                                                <th rowspan="3" colspan="3" style="text-align:center">
                                                    TOTAL
                                                </th>
                                                <th rowspan="3" colspan="3" style="text-align:center">
                                                    AVERAGE
                                                </th>
                                            </tr>
                                            <tr>
                                                <th style="text-align:center">I</th>
                                                <th style="text-align:center">II</th>
                                            </tr>
                                            <tr>
                                                <th style="text-align:center">OUT OF (25)</th>
                                                <th style="text-align:center">OUT OF (25)</th>

                                                <th style="text-align:center">MAX. MARKS</th>
                                                <th style="text-align:center">OBT. MARKS</th>

                                                <th style="text-align:center">MAX. MARKS</th>
                                                <th style="text-align:center">OBT. MARKS</th>

                                                <th style="text-align:center">MAX. MARKS</th>
                                                <th style="text-align:center">OBT. MARKS</th>
                                            </tr>
                                            <?php
                                            $term1 = $all_data['term-1'];
                                            $term2 = $all_data['term-2'];
                                            // echo "<pre>";
                                            // print_r($term1);
                                            // exit;
                                            $exams = array();
                                            $result = "pass";

                                            $pass = true;
                                            foreach ($all_data['exam_subject_wise'] as $subject => $exam_data) {

                                                foreach ($exam_data as $term_id => $exam_arr) {
                                                    foreach ($exam_arr as $id => $arr) {
                                                        $exams[$subject][$term_id][$arr['exam']] = $arr['mark'];
                                                    }
                                                }

                                            }
                                            $total_get_mark_all_subject = array();
                                            $continue = array();
                                            foreach ($all_data['mark'] as $subject => $subject_data) {
                                            // echo "<pre>";
                                            // print_r($all_data);
                                            // exit;
                                            ?>

                                            <tr>
                                            <td style="text-align:center"><?php echo $subject; ?></td>
                                            <?php

                                            foreach($subject_data as $stud_id => $stud_data){

                                            if(isset($stud_data['Half Yearly'])||isset($stud_data['Yearly'])||isset($stud_data['Practical/ASL/Project'])||isset($stud_data['UT1'])||isset($stud_data['UT2'])){

                                                $unit_1 = $stud_data['UT1'];
                                                $unit_2 = $stud_data['UT2'];
                                                $practical = $stud_data['Practical/ASL/Project'];
                                                $half = $stud_data['Half Yearly'];
                                                $year = $stud_data['Yearly'];

                                               ?>
                                                <td style="text-align:center"><?php echo $unit_1; ?></td>
                                                <td style="text-align:center"><?php echo $unit_2; ?></td>
                                                <?php
                                                // foreach($stud_id as $keys => $total_points){
                                                // echo "<pre>";
                                                // print_r($all_data['mark'][$subject]['total_points'][$stud_id]);
                                                // exit;
                                                // }
                                                ?>

                                                <td style="text-align:center"><?php echo $subject_data['total_points'][$stud_id]['Half Yearly'] ?></td>
                                                <td style="text-align:center"><?php echo $half; ?></td>
                                                <td style="text-align:center"><?php echo $subject_data['total_points'][$stud_id]['Practical/ASL/Project'] ?></td>
                                                <td style="text-align:center"><?php echo $practical; ?></td>
                                               <td style="text-align:center"><?php echo $subject_data['total_points'][$stud_id]['Yearly'] ?></td>

                                                <td style="text-align:center"><?php echo $year; ?></td>
                                                <?php
                                                $total_avg = 0;
                                                $out_of_100 = $subject_data['TOTAL_GAIN'];
                                                if ($out_of_100 > 0) {
                                                    foreach ($subject_data['total_points'][$stud_id] as $key => $value) {
                                                        // code...
                                                        $r = array_sum($subject_data['total_points'][$stud_id]);
                                                        $total_avg = round(floatval($out_of_100) * 100 / $r);

                                                    }
                                                }
                                                // $count = count($stud_data);
                                                }
                                                }
                                                ?>
                                                 <td style="text-align:center" colspan="2"><?php echo  $out_of_100?? 0; ?></td>
                                                 <td style="text-align:center" colspan="4"><?php echo $total_avg ?? 0; ?></td>
                                            </tr>
                                            <?php
                                            $total_get_mark_all_subject[] = $total_avg;
                                            }
                                            $total_mark = count($total_get_mark_all_subject) * 100;
                                            $total_get_mark = 0;

                                            foreach ($total_get_mark_all_subject as $id => $val) {
                                                $total_get_mark = $total_get_mark + $val;
                                                if ($total_get_mark_all_subject < 35) {
                                                    $result = "fail";
                                                }
                                            }

                                            if ($total_get_mark != 0) {
                                                $per = number_format(100 * floatval($total_get_mark) / $total_mark, 2);
                                            } else {
                                                $per = "-";
                                            }
                                            ?>

                                            <tr>
                                                <td>Grand Total</td>
                                                <td colspan="14" align="right"><?php echo $total_get_mark; ?></td>
                                            </tr>
                                            <tr>
                                                <td>PERCENTAGE</td>
                                                <td colspan="14" align="right"><?php echo $per."%"; ?></td>
                                            </tr>

                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <!-- co-scholastic start -->
                            <tr>
                                <td class="p-t-10" width="100%" valign="top">
                                                            <div style='display:flex;'>
                                                                <?php
                                                                $count = 0;
                                                                if(isset($all_data['co_scholastic_area'])
                                                                || isset($term_2_data[$stuent_id]['co_scholastic_area']))
                                                                {
                                                                if (isset($all_data['co_scholastic_area'])) {
                                                                    $co_scholastic_area = $all_data['co_scholastic_area'];
                                                                } else {
                                                                    $co_scholastic_area = $term_2_data[$stuent_id]['co_scholastic_area'];
                                                                }
                                                                foreach ($co_scholastic_area as $co_area => $arr) {
                                                                $term1co = $all_data['co_scholastic_area'][$co_area] ?? [];
                                                                $term2co = $term_2_data[$stuent_id]['co_scholastic_area'][$co_area] ?? [];

                                                                foreach ($arr as $parent => $child_arr) {
                                                                $term1arr = $term1co[$parent] ?? [];
                                                                $term2arr = $term2co[$parent] ?? [];

                                                                $count = $count + 1;
                                                                if ($count % 2 == 0) {
                                                                    $margin = "margin-left:2.5%;";
                                                                } else {
                                                                    $margin = "margin-right:2.5%;";
                                                                }
                                                                echo "<div style='display:flex;width:50%;".$margin."'>";
                                                                ?>
                                                                <table class="aca-year"
                                                                       style="width: 100%;border-collapse:collapse; border:1px solid #e68023;"
                                                                       cellspacing="0" cellpadding="0" border="1">
                                                                    <tbody>
                                                                    <tr>
                                                                        <th colspan="3" width="15%" style="text-align: left;">
                                                                            <b><?php echo $parent; ?></b></th>
                                                                    </tr>
                                                                    <tr>
                                                                        <th width="50%" style="text-align: left;"><b>Optional Subject</b></th>

                                                                        <th width="25%" style="text-align: center;">
                                                                            <b>Term 1</b></th>
                                                                        <th width="25%" style="text-align: center;">
                                                                            <b>Term 2</b>
                                                                        </th>
                                                                    </tr>
                                                                    <?php

                                                                    foreach ($child_arr as $subject => $obtain_grade) {
                                                                    ?>
                                                                    <tr>
                                                                        <td><?php echo $subject; ?></td>
                                                                        <td align="center">-</td>
                                                                        <td><?php echo $obtain_grade[$term1]; ?></td>

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
                            <!-- co-scholastic end -->
                            <tr>

                                <td><br><br>
                                    <table border=1 style="border-collapse:collapse;" width="100%" cellspacing="0"
                                           cellpadding="0">
                                        <tr>
                                            <th>
                                                Attendance (Term I+II)
                                            </th>
                                            <td>
                                                :
                                            </td>
                                            <td>
                                                <?php echo $all_data['att']; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
                                                Result
                                            </th>
                                            <td>
                                                :
                                            </td>
                                            <td>
                                                <?php
                                                if ($pass === true) {
                                                    echo "Passed and Promoted to Grade XII " . $all_data['medium'];
                                                } else {
                                                    echo "Detained  in  Grade  XI " . $all_data['medium'];
                                                }
                                                // if($result == "pass"){
                                                //     echo "Passed and Prometed to Grade XII Science";
                                                // }else{
                                                //     echo "Prometed to Grade XII Science";
                                                // }
                                                     ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
                                                School Reopens on
                                            </th>
                                            <td>
                                                :
                                            </td>
                                            <td>
                                                <?php
                                                $date = date_create($all_data['exam_master_settig']['reopen_date']);
                                                echo date_format($date, "d-m-Y");

                                                // echo $all_data['exam_master_settig']['reopen_date'];
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
                                                Signature
                                            </th>
                                            <td colspan=2>
                                                <table width="100%" border="0">
                                                    <tr>
                                                        <td style="text-align: center;">
                                                            <br><br>
                                                            <?php $img = "/storage/result/teacher_sign/".$all_data['exam_master_settig']['teacher_sign']; ?>
                                                            <hr>
                                                            Teacher's Sign
                                                        </td>
                                                        <td style="text-align: center;">
                                                            <br><br>
                                                            <?php $img1 = "/storage/result/principle_sign/".$all_data['exam_master_settig']['principal_sign']; ?>
                                                            <hr>
                                                            Principal's Sign
                                                        </td>
                                                        <td style="text-align: center;">
                                                            <br><br>
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
                        </tbody>
                    </table>
                    <?php
                            echo "<br><br>";
                        }
                    }
                    ?>
                </div>

            </div>
        </div>
    </div>
</div>


@include('includes.footerJs')
<script>
    function printDiv(divName) {
        var divToPrint = document.getElementById(divName);
        var popupWin = window.open('', '_blank', 'width=300,height=300');
        popupWin.document.open();
        popupWin.document.write('<html>');

        popupWin.document.write('<body onload="window.print()">' + divToPrint.innerHTML + '</html>');
        popupWin.document.close();
    }
</script>
@include('includes.footer')
