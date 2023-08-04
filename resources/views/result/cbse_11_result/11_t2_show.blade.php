@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<link rel="stylesheet" href="{{ URL::asset('css/result.css') }}" />
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
                            /*border: 1px solid #ddd;*/
                            padding: 4px;
                        }
                    </style>
                <div>
                    <?php
                    $header_data = $data['header_data'];
                    $footer_data = $data['footer_data'];
                    //$term_2_data = $data['term_2_data'];
                    $gradeScale = \App\Helpers\getGradeScale();
                    $grandTotal = 0;
             //echo ('<pre>');print_r($data);exit;
    // foreach ($data as $arr) {
        foreach ($data['data'] as $stuent_id => $all_data) {
        	$failed = 0;
        	// echo ('<pre>');print_r($all_data['year']);exit;
        ?>
        <div id="{{$stuent_id}}">
    <table class="main-table ml-5 mr-5 mb-5" width="100%">
        <tbody>
            <tr>
            <td>
             <table class="report-card" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">
                <tbody>
                    <tr>
                        <td style="text-align:center !important;" align="center"> 
                            <span class="sc-hd">{{$header_data['line1']}}</span><br>   
                            <span class="ma-hd">{{$header_data['line2']}}</span><br>  
                            <span class="rg-hd">{{$header_data['line3']}}</span><br> 
                            <span class="rg-hd">{{$header_data['line4']}}</span><br>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <br/><br/><br/><br/><br/><br/><br/><hr></hr>
                        </td>
                    </tr>
                </tbody>
            </table>
            <table class="report-card ml-4" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">
               <tbody>
                <tr>
                    <td colspan="3" align="center">
                        <h3 style="font-size:16;color:black;font-weight: 600;">ACADEMIC SESSION : <?php echo $all_data['year']; ?> | REPORT CARD FOR CLASS - <?php echo $all_data['class']; ?></h3>
                        <!-- <h3 style="font-size:14">SESSION <?php echo $all_data['year']; ?></h3> -->
                    </td>
                </tr>
                <tr>
                    <td width="60%">Student's Name : <label><?php echo $all_data['name']; ?></label></td>
                    <td width="20%">Roll No. : <label><?php echo $all_data['roll_no']; ?></label></td>
                    <td width="20%" rowspan="4"><img height="100px" src="/storage/student/<?php echo $all_data['image']; ?>"></td>
                </tr>
                <tr>
                    <td>Father's Name : <label><?php echo $all_data['father_name']; ?></label></td>
                    <td>Class : <label><?php echo $all_data['class']; ?></label></td>
                </tr>
                <tr>
                    <td>Mother's Name : <label><?php echo $all_data['mother_name']; ?></label></td>
                    <td>Division : <label><?php echo $all_data['division']; ?></label></td>
                </tr>
                <tr>
                    <td>Date Of Birth : <label><?php echo $all_data['date_of_birth']; ?></label></td>
                    <td>G.R. No. : <label><?php echo $all_data['gr_no']; ?> </label></td>
                </tr>
            </tbody>
            </table>
            <table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" width="100%" cellspacing="0" cellpadding="0" border="1">
            <tbody>
                <tr>
                    <th rowspan="4"><b>SUBJECTS</b></th>
                    <th colspan="11" style="text-align:center"><b>ACADEMIC YEAR EXAM</b></th>
                </tr>
                <tr>
                    <th colspan="2" style="text-align:center"><b>UNIT TEST</b></th>
                    <th rowspan="2" colspan="2" style="text-align:center"><b>HALF YEARLY<br/>EXAM</b></th>
                    <th rowspan="2" colspan="2" style="text-align:center"><b>YEARLY<br/>PRACTICAL/ASL/PROJECT</b></th>
                    <th rowspan="2" colspan="2" style="text-align:center"><b>YEARLY<br/>EXAM</b></th>
                    <th rowspan="3" style="text-align:center"><b>TOTAL</b></th>
                    <th rowspan="3" style="text-align:center"><b>GRAND<br/>TOTAL</b></th>
                    <th rowspan="3" style="text-align:center"><b>AVERAGE</b></th>
                </tr>
                <tr>
                    <th style="text-align:center"><b>I</b></th>
                    <th style="text-align:center"><b>II</b></th>
                </tr>
                <tr>
                    <th style="text-align:center"><b>OUT OF (25)</b></th>
                    <th style="text-align:center"><b>OUT OF (25)</b></th>
                    <th style="text-align:center"><b>MAX. MARKS</b></th>
                    <th style="text-align:center"><b>OBT. MARKS</b></th>
                    <th style="text-align:center"><b>MAX. MARKS</b></th>
                    <th style="text-align:center"><b>OBT. MARKS</b></th>
                    <th style="text-align:center"><b>MAX. MARKS</b></th>
                    <th style="text-align:center"><b>OBT. MARKS</b></th>
                </tr>
            <?php 
            $term1 = $all_data['term-1'];
            $term2 = $all_data['term-2'];
            // echo "<pre>";
            // print_r($term1);
            // exit;
            $exams = array();
            if (isset($all_data['exam_subject_wise']) && is_array($all_data['exam_subject_wise'])) {
                foreach($all_data['exam_subject_wise'] as $subject => $exam_data){ 
                    foreach($exam_data as $term_id=>$exam_arr){ 
                        foreach($exam_arr as $id=>$arr){ 
                            $exams[$subject][$term_id][$arr['exam']] = $arr['mark'];
                        }
                    }
                }
            }
            $total_get_mark_all_subject = array();
            $continue = array();
            if (isset($all_data['mark']) && is_array($all_data['mark'])) {
            foreach ($all_data['mark'] as $subject => $subject_data) {
                // echo "<pre>";
                // print_r($all_data);
                // exit;
            ?> 

            <tr>
            <td style="text-align:left"><?php echo $subject; ?></td>
            <?php
            $total_avg = 0;
            $theory_total = $theory_gain = $grand_total = $grand_obt = $term2_total = $term2_obt = 0;
            foreach($subject_data as $stud_id => $stud_data){
           
            if(isset($stud_data['Half Yearly'])||isset($stud_data['Yearly'])||isset($stud_data['Practical/ASL/Project'])||isset($stud_data['UT1'])||isset($stud_data['UT2'])){

                $unit_1 = $stud_data['UT1'];
                $unit_2 = $stud_data['UT2'];
                $practical = $stud_data['Practical/ASL/Project'];
                $half = $stud_data['Half Yearly'];
                $year = $stud_data['Yearly'];

                $total_unit_1 = $subject_data['total_points'][$stud_id]['UT1'];
                $total_unit_2 = $subject_data['total_points'][$stud_id]['UT2'];
                $total_practical=$subject_data['total_points'][$stud_id]['Practical/ASL/Project'];
                $total_half=$subject_data['total_points'][$stud_id]['Half Yearly'];
                $total_year=$subject_data['total_points'][$stud_id]['Yearly'];
               
//START MATHEMATICS ONLY
if($subject == "MATHEMATICS"){
	$i = round(10 * (is_numeric($unit_1) ? $unit_1 : 0) / $total_unit_1,0);
	$j = round(10 * (is_numeric($unit_2) ? $unit_2 : 0) / $total_unit_2,0);
	$k = round(10 * (is_numeric($half) ? $half : 0) / $total_half,0);
	$marksArr = array(
		$i,$j,$k		
	);
$marksArrs = \App\Helpers\getBestOf($marksArr);
//$marksArrs = getBestOf($marksArr);
//echo "<pre>";
//print_r($marksArrs);
$math_tot = round((array_sum($marksArrs) / 2),0) + $practical;

	?>
	<td style="text-align:center">-</td>
    <td style="text-align:center">-</td>
    <td style="text-align:center">-</td>
    <td style="text-align:center">-</td>
    <td style="text-align:center">20</td>
    <td style="text-align:center"><?php echo $math_tot; ?></td>
    <td style="text-align:center"><?php echo $total_year; $theory_total += $total_year; ?></td>
    <td style="text-align:center"><?php echo $year; if(is_numeric($year)) { $theory_gain += $year; } ?></td>
<?php  
	
    if ($year == "N.A.") { // handle the case where $r is zero, for example:
	  	$grand_obt = $math_tot;
    	$grand_total = 20;
	}elseif($year == "EX"){
		$grand_obt = $math_tot;
    	$grand_total = 20;
	}else{
	  	$grand_obt = $math_tot + $theory_gain;
    	$grand_total = 20 + $theory_total;
	}
 	$total_avg = round(($grand_obt*100 / $grand_total), 0);

    if ($theory_gain != 0) {
        $total_per = round(($theory_gain * 100 / $theory_total), 0);
        if($total_per < 33)
        	$failed++;
    }/* else {
         $total_per = 0; // Set $total_per to zero if $theory_total is zero
    }*/

    $term2_obt = $grand_obt;
}else
{
//END MATHEMATICS ONLY
    if($subject == "APP MATHS"){
              ?>
                <td style="text-align:center">-</td>
                <td style="text-align:center">-</td>
                <td style="text-align:center">-</td>
                <td style="text-align:center">-</td>
                <td style="text-align:center"><?php echo $total_practical; ?></td>
                <td style="text-align:center"><?php echo $practical; if(is_numeric($practical)){$term2_obt += $practical;} ?></td>
                <td style="text-align:center"><?php echo $total_year; ?></td>
                <td style="text-align:center"><?php echo $year; if(is_numeric($year)){ $theory_gain += $year; $term2_obt += $year; $theory_total += $total_year; } ?></td>
        <?php }
            else{ ?>
                <td style="text-align:center"><?php echo $unit_1; if(is_numeric($unit_1)) { $theory_gain += $unit_1; $theory_total += $total_unit_1; } ?></td>
                <td style="text-align:center"><?php echo $unit_2; if(is_numeric($unit_2)) { $theory_gain += $unit_2; $theory_total += $total_unit_2; } ?></td>
                <td style="text-align:center"><?php echo $total_half; ?></td>
                <td style="text-align:center"><?php echo $half; if(is_numeric($half)) { $theory_gain += $half; $theory_total += $total_half; } ?></td>
                <td style="text-align:center"><?php echo $total_practical; ?></td>
                <td style="text-align:center"><?php echo $practical; if(is_numeric($practical)){$term2_obt += $practical;} ?></td>
                <td style="text-align:center"><?php echo $total_year; ?></td>
                <td style="text-align:center"><?php echo $year; if(is_numeric($year)){ $theory_gain += $year; $term2_obt += $year; $theory_total += $total_year; } ?></td>
        <?php }
                $total_avg=0;
                //$term2_obt = $subject_data['TOTAL_GAIN'];
                if($subject == "APP MATHS")
                    $grand_obt = $term2_obt;
                else
                    $grand_obt = $subject_data['TOTAL_GAIN'];
}
if($subject != "MATHEMATICS"){
                foreach ($subject_data['total_points'][$stud_id] as $key => $value) {
                    // code...
                    if($half=="N.A."|| $half == "EX"){
                        $sum = array($total_practical,$total_year,$total_unit_1,$total_unit_2);
                        //$theory_total = array($total_year,$total_unit_1,$total_unit_2);
                    }elseif($year=="N.A."|| $year == "EX"){
                        $sum = array($total_practical,$total_half,$total_unit_1,$total_unit_2);
                        //$theory_total = array($total_half,$total_unit_1,$total_unit_2);
                    }elseif($practical=="N.A."|| $practical == "EX"){
                        $sum = array($total_half,$total_year,$total_unit_1,$total_unit_2);
                    }elseif($unit_1=="N.A."|| $unit_1 == "EX"){
                        $sum = array($total_practical,$total_year,$total_half,$total_unit_2);
                        //$theory_total = array($total_half,$total_year,$total_unit_2);
                    }elseif($unit_2=="N.A."|| $unit_2 == "EX"){
                        $sum = array($total_practical,$total_year,$total_unit_1,$total_half);
                        //$theory_total = array($total_half,$total_year,$total_unit_1);
                    }else{
                        if($subject == "APP MATHS"){
                        $sum = array($total_practical,$total_year);
                        }
                        else{
                        $sum = array($total_practical,$total_half,$total_unit_1,$total_unit_2,$total_year);
                        }
                        //$theory_total = array($total_half,$total_unit_1,$total_unit_2,$total_year);
                    }

                    $r = array_sum($sum);
                    //$theorytotal = array_sum($theory_total);
                    $theorytotal = $theory_total;
                    $grand_total = $r;
                    
                    if ($r == 0) { // handle the case where $r is zero, for example:
					  $total_avg = 0;
					} else {
					  $total_avg = round(($grand_obt*100 / $r), 0);
					}
                     
                    if ($theorytotal == 0) {
                        $total_per = 0; // Set $total_per to zero if $theory_total is zero
                    } else {
                        
                        $total_per = round(($theory_gain * 100 / $theorytotal), 0);
                    }
                    if($total_per < 33)
                        $failed++;
                }
}                
                // $count = count($stud_data);
            }
        }  
                 ?>
                 <td style="text-align:center"><?php echo $term2_obt; ?></td>
                 <td style="text-align:center"><?php echo $grand_obt; ?></td>
                 <td style="text-align:center"><?php echo isset($total_avg) ? $total_avg : 'N/A'; ?></td> 
                 </tr>
                 <?php
                 	if($total_avg != "N.A." && $total_avg != "EX"){
                 		$total_get_mark_all_subject[]=$total_avg;
             		}
        }
    }               
            $total_mark = count($total_get_mark_all_subject) * 100;
            $total_get_mark = 0;
            //echo "<pre>";
            //print_r($total_get_mark_all_subject);
            foreach($total_get_mark_all_subject as $id=>$val){
                $total_get_mark = $total_get_mark + $val;
                if($total_get_mark_all_subject < 33){
                    $result="fail";
                }
            }
            
            if($total_get_mark != 0){
                $per = number_format(100*$total_get_mark/$total_mark,2);}else{
                $per = "-";
            }
            ?>

            <tr>
                <td align="right" colspan="11"><b>GRAND TOTAL</b></td>
                <td align="center"><b><?php echo $total_get_mark; ?></b></td>
            </tr>
            <tr>
                <td align="right" colspan="11"><b>PERCENTAGE</b></td>
                <td align="center"><b><?php echo $per."%"; ?></b></td>
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
                if(isset($all_data['co_scholastic_area']) || isset($term_2_data[$stuent_id]['co_scholastic_area']))
                 {
                   if (isset($all_data['co_scholastic_area'])) {
                      $co_scholastic_area = $all_data['co_scholastic_area'];
                    }else{
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
                        <table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
                            <tbody>
                            <tr>
                                <th width="70%" style="text-align: left;"><b><?php echo strtoupper($parent); ?></b></th>
                                <th width="30%" style="text-align: center;"><b>GRADE</b></th>
                            </tr>
                            <?php
                            foreach ($child_arr as $subject => $obtain_grade) {
                            ?>
                            <tr>
                                <td><?php echo $subject; ?></td>
                                <td align="center"><?php echo $obtain_grade[$term1]; ?></td>
                            </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    <?php
            echo "</div>";
            echo "<div style='display:flex;width:30%;margin-left:3%'>";
            ?>
                <table class="aca-year"
                       style="width: 100%;height:fit-content;margin-top:8%;border-collapse:collapse; border:1px solid #e68023;"
                       cellspacing="0" cellpadding="0" border="1">
                    <tbody>
                    <tr>
                        <th colspan="2" style="text-align: left;">
                            <b>Total Attendance</b></th>
                    </tr>
                    <tr>
                        <td width="75%">No. Of Working Days</td>
                        <td width="25%" align="center"><?php echo $all_data['total_working_day']; ?></td>
                    </tr>
                    <tr>
                        <td>Days Attended</td>
                        <td align="center"><?php echo $all_data['att']; ?></td>
                    </tr>                                                                    
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
        // }
        ?>
                <!--</div>-->
        </td>
    </tr>
<!-- co-scholastic end -->
    @if(count($footer_data) > 0)
    <tr>
        <td><br/><br/>
            <table style="border:hidden;" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td colspan="3" class="p-t-10">
                        <b>Class Teacher's Remarks : {{ \App\Helpers\getGradeComment($gradeScale, 100, $per) }}</b>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" class="p-t-10">
                        <b>Result : <?php if($failed){ echo "Failed"; }else{echo "Pass";}; ?></b>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" class="p-t-10">
                        <b>School Reopens on : </b> <?php echo date_format(new DateTime($footer_data['reopen_date']),"d-M-Y"); ?>
                    </td>
                </tr>
                <tr>
                   <td align="center" width="33%">
                        <br>
                        <img height="50px" width="100px" src="/storage/result/teacher_sign/{{$footer_data['teacher_sign']}}" />
                        <br>
                        <!--<hr>-->
                        Teacher's Sign
                    </td>
                    <td align="center" width="33%">
                        <br>
                        <img height="50px" width="100px" src="/storage/result/principle_sign/{{$footer_data['principal_sign']}}" />
                        <br>
                        <!--<hr>-->
                        Principal's Sign
                    </td>
                    <td align="center" width="33%">
                        <br>
                        <img height="50px" width="100px" src="/storage/result/teacher_sign/{{$footer_data['teacher_sign']}}" />
                        <br>
                        Parent's Sign
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td colspan="3" class="p-t-10">
            <b>NOTE: N.A. = Not Applicable, AB = Absent, EX = Exemption</b>
        </td>
    </tr>
    @endif
        </td>
    </tr>
    </tbody>
    </table>
    </div>
        <div style="page-break-after: always !important;"></div>

<?php } }?>
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