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


            <!--<div class="col-lg-12 col-sm-12 col-xs-12">-->
                <div>
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
                        // echo ('<pre>');print_r($all_data);exit;

                        $term1 = $all_data['term-1'];
                        $term2 = $all_data['term-2'];

                        ?>

                        <table class="main-table" width="100%">
                            <tbody>
                            <tr>
                                <td>
                                    <table class="report-card" style="border-collapse:collapse;" width="100%"
                                           cellspacing="0" cellpadding="0">
                                        <tbody>
                                        <tr class="b-b">
                                            <td align="left">
                                                <img alt="" src="" height="50px">

                                            </td>
                                            <td style="white-space:nowrap;" align="center">
                                                <h2 style="margin-top:5%"></h2>

                                                <br>

                                                <br>

                                            </td>
                                            <td align="right">
                                                <img alt="" src="" height="50px">
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <table class="report-card" style="border-collapse:collapse;" width="100%"
                                           cellspacing="0" cellpadding="0">

                                        <tbody>
                                        <tr>
                                            <td colspan="3" align="center">
                                                <h3 style="font-size:14">MARKSHEET CUM CERTIFICATE OF PERFORMANCE</h3>
                                                <h3 style="font-size:14">SESSION 2018 - 2019</h3>
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
                                            <td>Class : <label>1</label></td>
                                        </tr>
                                        <tr>
                                            <td>Father's Name :
                                                <label><?php echo $all_data['father_name']; ?></label></td>
                                            <td></td>
                                            <td>Division :
                                                <label><?php echo $all_data['division']; ?></label></td>
                                        </tr>
                                        <tr>
                                            <td>Date Of Birth :
                                                <label><?php $date = date_create($all_data['date_of_birth']);
                                                    echo date_format($date, "d-m-Y"); ?></label></td>
                                            <td></td>
                                            <td>G.R. No. :
                                                <label><?php echo $all_data['gr_no']; ?></label></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <table class="report-card" style="border-collapse:collapse;" width="100%"
                                           cellspacing="0" cellpadding="0">

                                        <tbody>
                                        <tr>
                                            <td colspan="3" class="p-t-10">
                                                <table class="aca-year"
                                                       style="border-collapse:collapse; border:1px solid #e68023;"
                                                       width="100%" cellspacing="0" cellpadding="0" border="1">
                                                    <thead>
                                                    <tr>
                                                        <th class="main-th" align="left">Part 1-A-Scholastic Areas:</th>
                                                        <th colspan="5" class="main-th" align="center">TERM-1 (50
                                                            marks)
                                                        </th>
                                                        <th colspan="5" class="main-th" align="center">TERM-2 (50
                                                            marks)
                                                        </th>
                                                        <th class="main-th" colspan="2">Total</th>
                                                    </tr>
                                                    <tr>
                                                        <th align="left">Sub Name</th>
                                                        <th align="center">PA1<br>(10)</th>
                                                        <th align="center">PA2<br>(10)</th>
                                                        <th align="center">SA1<br>(25)</th>
                                                        <th align="center">Notebook<br>(5)</th>
                                                        <th align="center">Marks Obtained<br>(50)</th>
                                                        <th align="center">PA3<br>(10)</th>
                                                        <th align="center">PA4<br>(10)</th>
                                                        <th align="center">SA2<br>(25)</th>
                                                        <th align="center">Notebook<br>(5)</th>
                                                        <th align="center">Marks Obtained<br>(50)</th>
                                                        <th align="center">Total Marks Obtained<br>(100)</th>
                                                        <th align="center">Grade</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
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
                                                    // print_r($all_data['mark']);
                                                    // exit;
                                                    // echo "<pre>";
                                                    // print_r($subject_data[$term1]);
                                                    // print_r($term2);

                                                    // exit;
                                                    ?>

                                                    <tr>
                                                        <td style="text-align:center"><?php echo $subject; ?></td>
                                                        <td><?php echo $subject_data[$term1]['TERM_GAIN'] ?></td>

                                                        <?php

                                                        // echo "<pre>";
                                                        // print_r($subject_data[$term1]);
                                                        foreach($subject_data as $stud_id => $stud_data){
                                                        //

                                                        // echo "<pre>";
                                                        // print_r($subject_data);

                                                        // $periodic_test = array();
                                                        // foreach($subject_data as $key => $value){
                                                        //     if($key != 'TOTAL_GAIN' && $key != 'GRADE'){
                                                        //         $periodic_test[] = $value["Periodic Test"];
                                                        //     }
                                                        // }
                                                        // echo "<pre>";
                                                        // print_r($periodic_test);
                                                        if(isset($stud_data['Half Yearly']) || isset($stud_data['Yearly']) || isset($stud_data['Practical/ASL/Project']) || isset($stud_data['UT1']) || isset($stud_data['UT2'])){


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

                                                        <td style="text-align:center"><?php echo $total_half; ?></td>
                                                        <td style="text-align:center"><?php echo $half; ?></td>
                                                        <td style="text-align:center"><?php echo $total_practical; ?></td>
                                                        <td style="text-align:center"><?php echo $practical; ?></td>
                                                        <td style="text-align:center"><?php echo $total_year ?></td>

                                                        <td style="text-align:center"><?php echo $year; ?></td>
                                                        <?php

                                                        // $count = count($stud_data);
                                                        }
                                                        }  ?>

                                                    </tr>
                                                    <?php
                                                    }
                                                    ?>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="p-t-10" width="45%" valign="top">
                                                <table class="aca-year"
                                                       style="border-collapse:collapse; border:1px solid #e68023;"
                                                       cellspacing="0" cellpadding="0" border="1">
                                                    <tbody>
                                                    <tr>
                                                        <th colspan="3" width="15%" align="center"><b>Part
                                                                1-B-Scholastic Areas:</b></th>
                                                    </tr>
                                                    <tr>
                                                        <th width="15%" align="center"><b>Subject</b></th>
                                                        <th width="15%" align="center"><b>TERM-1</b></th>
                                                        <th width="15%" align="center"><b>TERM-2</b></th>
                                                    </tr>
                                                    <tr>
                                                        <td>COMPUTER</td>
                                                        <td align="center">B</td>
                                                        <td align="center">D</td>
                                                    </tr>
                                                    <tr>
                                                        <td>GENERAL KNOWLEDGE</td>
                                                        <td align="center">A</td>
                                                        <td align="center">D</td>
                                                    </tr>
                                                    <tr>
                                                        <td>VALUE EDUCATION</td>
                                                        <td align="center">B</td>
                                                        <td align="center">D</td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </td>
                                            <td width="10%"></td>
                                            <td style="padding-left: 15px !important;" class="p-t-10" width="45%"
                                                valign="top">
                                                <div style='display:flex;width:100%'>
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
                                                    echo "<div style='display:flex;width:100%;" . $margin . "'>";
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
                                                            <th width="50%" style="text-align: left;"><b>Optional
                                                                    Subject</b></th>

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

                                            </td>
                                        </tr>

                                        <tr>
                                            <td colspan="3" class="p-t-10">
                                                <b>Attendance (Term I &amp; II): 75/85</b>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="p-t-10">
                                                <b>Class Teacher's Remarks : Very Good</b>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="p-t-10">
                                                <b>
                                                    <table width="100%" cellspacing="0" cellpadding="3" border="0">
                                                        <tbody>
                                                        <tr>
                                                            <td><b>Result : Passed &amp; Promoted to Class 2 - A</b>
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                        </tbody>
                                                    </table>
                                                </b>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="p-t-10">
                                                <b>School Reopens on : 3<sup>rd</sup> April, 2019</b>
                                            </td>
                                        </tr>
                                        <tr class="p-t b-b">
                                            <td colspan="3" style="padding-top:30px;">
                                                <table class="signature" width="100%" cellspacing="0" cellpadding="0">
                                                    <tbody>
                                                    <tr>
                                                        <td align="center"><b>Signature of Class Teacher</b></td>
                                                        <td align="center"><b>Signature of Principal</b></td>
                                                        <td align="center"><b>Signature of Parent</b></td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>

                                        <tr>
                                            <td colspan="3" align="center">
                                                <table class="aca-year"
                                                       style="border-collapse:collapse; border:1px solid #e68023;"
                                                       width="80%" cellspacing="0" cellpadding="0" border="1">
                                                    <tbody>
                                                    <tr>
                                                        <th align="center"><b>SCHOLASTIC MARKS RANGE</b></th>
                                                        <td align="center">90 - 100</td>
                                                        <td align="center">75 - 89</td>
                                                        <td align="center">56 - 74</td>
                                                        <td align="center">33 - 55</td>
                                                        <td align="center">32 &amp; Below</td>
                                                    </tr>
                                                    <tr>
                                                        <th align="center"><b>GRADE</b></th>
                                                        <td align="center">A+</td>
                                                        <td align="center">A</td>
                                                        <td align="center">B</td>
                                                        <td align="center">C</td>
                                                        <td align="center">D</td>
                                                    </tr>
                                                    <tr></tr>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>

                                        <tr>
                                            <td colspan="3" align="center">
                                                <table class="aca-year"
                                                       style="border-collapse:collapse; border:1px solid #e68023;"
                                                       width="80%" cellspacing="0" cellpadding="0" border="1">
                                                    <tbody>
                                                    <tr>
                                                        <th width="200px" align="center"><b>CO-SCHOLASTIC GRADE</b></th>
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
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                        </tbody>
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
