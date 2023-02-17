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
                    <table class="main-table" width="100%" style="page-break-after: always;">
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
                                                        echo date_format($date,"d-m-Y"); 
                                                        ?></label></td>
                                                <td></td>
                                                <td>G.R. No. : <label><?php echo $all_data['gr_no']; ?> </label></td>
                                            </tr>
                                        </tbody>
                                    </table><br><br>
                                    {{-- <table class="report-card" style="border-collapse:collapse;" width="100%" --}}
                                    <table border=1 style="border-collapse:collapse;" width="100%" cellspacing="0"
                                        cellpadding="0">
                                        <tbody>
                                            <tr>
                                                <th rowspan="4">
                                                    SUBJECTS
                                                </th>
                                                <th colspan="12" style="text-align:center">
                                                    THEORY EXAM
                                                </th>
<!--                                                <th rowspan="3" colspan="2" style="text-align:center">
                                                    PRACTICAL
                                                </th>
                                                <th rowspan="3" style="text-align:center">
                                                    GRAND TOTAL
                                                </th>
-->                                                
                                            </tr>

                                            <tr>
                                                <th colspan="3" style="text-align:center">
                                                    UNIT TEST
                                                </th>
                                                <th rowspan="2" colspan="3" style="text-align:center">
                                                    TERM I
                                                </th>
                                                <th rowspan="2" colspan="3" style="text-align:center">
                                                    TERM II
                                                </th>
                                                <th rowspan="2" colspan="3" style="text-align:center">
                                                    GRAND TOTAL
                                                </th>
                                            </tr>
                                            <tr>
                                                <th style="text-align:center">I</th>
                                                <th style="text-align:center">II</th>
                                                <th rowspan="2" style="text-align:center">Wtg. 5% OF I + II</th>
                                            </tr>
                                            <tr>
                                                <th style="text-align:center">OUT OF (25)</th>
                                                <th style="text-align:center">OUT OF (25)</th>
                                                <th style="text-align:center">OBT.</th>
                                                <th style="text-align:center">MM</th>
                                                <th style="text-align:center">Wtg. 20%</th>
                                                <th style="text-align:center">OBT.</th>
                                                <th style="text-align:center">MM</th>
                                                <th style="text-align:center">Wtg. 75%</th>
                                                <th style="text-align:center">100</th>
<!--                                                <th style="text-align:center">THEORY %</th>
                                                <th style="text-align:center">MM</th>
                                                <th style="text-align:center">OBT.</th>
                                                <th style="text-align:center">MM</th>
                                                <th style="text-align:center">(TH+PR) 100</th>
-->
                                            </tr>
                                            <?php 
                                            $term1 = $all_data['term-1'];
                                            $term2 = $all_data['term-2'];
                                            $exams = array();
                                            $result = "pass";
                                            $pass = true;
                                            foreach($all_data['exam_subject_wise'] as $subject=>$exam_data){ 
                                            foreach($exam_data as $term_id=>$exam_arr){ 
                                                foreach($exam_arr as $id=>$arr){ 
                                                    $exams[$subject][$term_id][$arr['exam']] = $arr['mark'];
                                                }
                                            }
                                            }
                                            // echo ('<pre>');print_r($all_data['mark']);exit;
                                                $total_get_mark_all_subject = array();
                                                $continue = array();
                                            foreach($all_data['mark'] as $subject=>$exam_arr){ 
                                                ?>
                                            <tr>
                                                <?php
                                                if (is_numeric($exam_arr[$term1]['UNIT TEST']) ) {
                                                    if($exam_arr[$term1]['UNIT TEST'] == 0){
                                                        // echo ('<pre>');print_r($_REQUEST);exit;
                                                        $continue[] = $subject;
                                                        continue;
                                                    }
                                                }
                                                    // if($exam_arr[$term1]['UNIT TEST'] != "AB"){
                                                   
                                                    // }
                                                  ?>
                                                <td> <?php echo $subject; ?> </td>

                                                <td> <?php echo $exam_arr[$term1]['UNIT TEST']; ?> </td>
                                                <td> <?php echo $exam_arr[$term2]['UNIT TEST']; ?> </td>
                                                <?php 
                                                if ( !is_numeric($exam_arr[$term1]['UNIT TEST']) ) {
                                                    $exam_arr[$term1]['UNIT TEST'] = 0;
                                                        } 
                                                if ( !is_numeric($exam_arr[$term2]['UNIT TEST']) ) {
                                                    $exam_arr[$term2]['UNIT TEST'] = 0;
                                                        } 
                                                    $total_get_unit_test = $exam_arr[$term2]['UNIT TEST'] + $exam_arr[$term1]['UNIT TEST'];
                                                    $total_unit_test = $exams[$subject][$term1]['UNIT TEST'] + $exams[$subject][$term2]['UNIT TEST'];
                                                    
                                                    $conver5 =  number_format((5 * $total_get_unit_test / $total_unit_test),2);
                                                ?>
                                                <td> <?php echo $conver5; ?> </td>

                                                <td> <?php echo $exam_arr[$term1]['THEORY']; ?> </td>
                                                <td> <?php echo $exams[$subject][$term1]['THEORY']; ?> </td>
                                                <?php 
                                                if ( !is_numeric($exam_arr[$term1]['THEORY']) ) {
                                                    $exam_arr[$term1]['THEORY'] = 0;
                                                        } 
                                                $total_t1_get_theory = $exam_arr[$term1]['THEORY'];
                                                $total_t1_theory = $exams[$subject][$term1]['THEORY'];
                                                
                                                $t1_theory_convert =  number_format((20 * $total_t1_get_theory / $total_t1_theory),2);
                                            ?>
                                                <td> <?php echo $t1_theory_convert; ?> </td>

                                                <td> <?php echo $exam_arr[$term2]['THEORY']; ?> </td>
                                                <td> <?php echo $exams[$subject][$term2]['THEORY']; ?> </td>
                                                <?php 
                                                if ( !is_numeric($exam_arr[$term2]['THEORY']) ) {
                                                    $exam_arr[$term2]['THEORY'] = 0;
                                                        } 
                                                $total_t2_get_theory = $exam_arr[$term2]['THEORY'];
                                                $total_t2_theory = $exams[$subject][$term2]['THEORY'];
                                                
                                                $t2_theory_convert =  number_format((75 * $total_t2_get_theory / $total_t2_theory),2);
                                            ?>
                                                <td> <?php echo $t2_theory_convert; ?> </td>


                                                <?php 
                                                $out_of_100 = $conver5 + $t1_theory_convert + $t2_theory_convert;

                                                
                                               
                                                $convert_acording_exam_mm = number_format(($exams[$subject][$term2]['THEORY'] * $out_of_100 / 100),2);
                                                
                                                if($exams[$subject][$term2]['THEORY'] == 70){
                                                    if($convert_acording_exam_mm < 23){
                                                    $pass = false;
                                                }
                                                }else{
                                                    if($convert_acording_exam_mm < 25){
                                                    $pass = false;
                                                }
                                                }
                                                ?>

                                                <td> <?php echo $out_of_100; ?> </td>
                                            </tr>
                                            <?php 
                                                $total_get_mark_all_subject[] = $out_of_100;
                                                } 

                                           //  echo ('<pre>');print_r($total_get_mark_all_subject);exit;
                                            $total_mark = count($total_get_mark_all_subject) * 100;
                                            $total_get_mark = 0;
                                            
                                            foreach($total_get_mark_all_subject as $id=>$val){
                                                $total_get_mark = $total_get_mark + $val;
                                                if($total_get_mark_all_subject < 35){
                                                    $result="fail";
                                                }
                                            }
                                            // echo ('<pre>');// print_r($total_get_mark_all_subject);
                                            // echo ($total_get_mark)."asd<br>";
                                            // echo($total_mark)."asd<br>";
                                            
                                            // exit;
                                                if($total_get_mark != 0){
                                            $per = number_format(100*$total_get_mark/$total_mark,2);}else{
                                                $per = "-";
                                            }

                                            ?>
                                            <tr>
                                                <td>Grand Total (Out of <?php echo $total_mark; ?>)</td>
                                                <td colspan="10" align="right"><?php echo $total_get_mark; ?></td>
                                            </tr>
                                            <tr>
                                                <td>PERCENTAGE</td>
                                                <td colspan="10" align="right"><?php echo $per."%"; ?></td>
                                            </tr>

                                        </tbody>
                                    </table>
                                </td>
                            </tr>
<!--                            
                            <tr>
                                <td><br><br>
                                    <table border=1 style="border-collapse:collapse;" width="100%" cellspacing="0"
                                        cellpadding="0">
                                        <tr>
                                            <th>
                                                INTERNAL GRADE
                                            </th>
                                            <th>
                                                GRADE
                                            </th>
                                        </tr>

                                        <?php foreach($all_data['co_scholastic_area'] as $co_area=>$co_area_arr){ ?>
                                        <?php foreach($co_area_arr as $disipline=>$disipline_arr){ ?>
                                        <?php foreach($disipline_arr as $co_name=>$co_arr){ ?>
                                        <?php foreach($co_arr as $id=>$val){ ?>
                                        <tr>
                                            <td>
                                                <?php echo $co_name; ?>

                                            </td>
                                            <td>
                                                <?php echo $val; ?>
                                            </td>
                                        </tr>
                                        <?php 
                                                } 
                                                } 
                                                } 
                                                } 
                                                ?>

                                    </table>
                                </td>
                            </tr>
-->                            
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
                                                if($pass === true){
                                                    echo "Passed and Promoted to Grade XII ".$all_data['medium'];
                                                }else{
                                                    echo "Detained  in  Grade  XI ".$all_data['medium'];
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
                                                        echo date_format($date,"d-m-Y"); 
                                                         
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