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
                    <center> <input class="btn btn-warning mb-4" type="button" onclick="printDiv('printableArea');" value="Print & Generate Result" /></center>
                </div>

                <!--<div class="col-lg-12 col-sm-12 col-xs-12">-->
                <div id="printableArea">
                    <style>
                        table td,
                        table th {
                            /*border: 1px solid #ddd;*/
                            padding: 8px;
                        }
                    </style>
                <div>
                    <?php
                    $header_data = $data['header_data'];
                    $footer_data = $data['footer_data'];
                    $term_2_data = $data['term_2_data'];
                    $gradeScale = \App\Helpers\getGradeScale();
                    $grandTotal = 0;
                    //foreach ($data as $arr) {
                        foreach ($data['data'] as $stuent_id => $all_data) {
                            ?>
                            <div id="{{$stuent_id}}">
                            <table class="main-table ml-5 mr-5 mb-5" width="100%" ><!--style="border:1px solid #f37a0d;"-->
                                <tbody>
                                    <tr>
                                        <td>
                                            <table class="report-card" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">
                                                <tbody>
                                                <tr>
                                                    <td style="text-align:center !important;" align="center">
                                                        @if(isset($header_data['line1']))<span class="sc-hd">{{$header_data['line1']}}</span><br>@endif
                                                        @if(isset($header_data['line2']))<span class="ma-hd">{{$header_data['line2']}}</span><br>@endif
                                                        @if(isset($header_data['line3']))<span class="rg-hd">{{$header_data['line3']}}</span><br>@endif
                                                        @if(isset($header_data['line4']))<span class="rg-hd">{{$header_data['line4']}}</span><br>@endif
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
                                                            <!--<h3 style="font-size:14">SESSION <?php echo $all_data['year']; ?></h3>-->
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
                                            <table class="report-card ml-4 mr-4 mb-4" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">
                                                <tbody>
                                                    <tr>
                                                        <td colspan="3">
                                                            <?php 
                                                            if($all_data['class']!='IX'){ ?>
                                                            <table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" width="100%" cellspacing="0" cellpadding="0" border="1">
                                                            <?php } 
                                                            else{ ?>
                                                                    <table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" width="95%" cellspacing="0" cellpadding="0" border="1">
                                                                    <?php } ?>
                                                                <tbody>
                                                                    <tr>
                                                                        <th align="left"><b>Scholastic Areas:</b></th>
                                                                         <!-- term -1  -->
                                                                        <?php if($all_data['class']!="IX"){ ?>
                                                                        <th  style="text-align: center;" colspan="<?php echo count($all_data['exam']) + 1; ?>" ><b><?php echo $all_data['term'] . " (" . $all_data['total_mark'] . " Marks)"; ?></b></th>
                                                                    <?php } ?>
                                                                    <!-- end term-1 -->
                                                                        <th  style="text-align: center;" colspan="<?php echo count($term_2_data[$stuent_id]['exam']) + 1; ?>"><b><?php echo $term_2_data[$stuent_id]['term'] . " (" . $term_2_data[$stuent_id]['total_mark'] . " Marks)"; ?></b></th>
                                                                    </tr>
                                                                    <tr>   
                                                                        <th align="left"><b>Subject Name</b></th>
                                                                        <!-- term - 1 -->
                                                                        <?php
                                                                         if($all_data['class']!="IX"){ 
                                                                        foreach ($all_data['exam'] as $temp_id => $exam_data) {
                                                                            if(strtolower($exam_data['exam']) != 'marks obtained') {
                                                                                $grandTotal += (float)$exam_data['mark'];
                                                                            }
                                                                            ?>
                                                                            <th style="text-align: center;"><b><?php echo $exam_data['exam']; ?><br>(<?php echo $exam_data['mark']; ?>)</b></th>   
                                                                            <?php
                                                                        }
                                                                        ?>
                                                                        <th style="text-align: center;"><b>Grade</b></th>
                                                                        <?php
                                                                    }
                                                                    // end term - 1
                                                                        foreach ($term_2_data[$stuent_id]['exam'] as $temp_id => $exam_data) {
                                                                            if(strtolower($exam_data['exam']) != 'marks obtained') {
                                                                                $grandTotal += (float)$exam_data['mark'];
                                                                            }
                                                                        ?>
                                                                        <th style="text-align: center;"><b><?php echo $exam_data['exam']; ?><br>(<?php echo $exam_data['mark']; ?>)</b></th>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                        <th style="text-align: center;"><b>Grade</b></th>
                                                                    </tr>
                                                                    <?php
                                                                    $term1_gain = $term2_gain = $failed = 0;
                                                                    foreach ($all_data['mark'] as $subject => $subject_data) {
                                                                    	//echo $subject_data['TOTAL_GAIN'] ."=".$subject_data['TOTAL_MARKS'];
                                                                       //if(is_numeric($subject_data['TOTAL_GAIN']))
                                                                        {
                                                                            if ($subject_data['TOTAL_MARKS'] != 0) {
                                                                                $per_one = round((100 * $subject_data['TOTAL_GAIN'] / $subject_data['TOTAL_MARKS']), 0);
                                                                                if($per_one < 33){
                                                                                	$failed++;
                                                                            	}
                                                                            } else {
                                                                                //$per_one = 0; RAJESH 27_03_2023
                                                                            }

                                                                            $term1_gain += $subject_data['TOTAL_GAIN'];
                                                                        }
                                                                       //if(is_numeric($term_2_data[$stuent_id]['mark'][$subject]['TOTAL_GAIN']))
                                                                        {

                                                                            if ($term_2_data[$stuent_id]['mark'][$subject]['TOTAL_MARKS'] == 0) {
                                                                                $per_two = 0;
                                                                            } else {
                                                                                $per_two = round((100*$term_2_data[$stuent_id]['mark'][$subject]['TOTAL_GAIN'] / $term_2_data[$stuent_id]['mark'][$subject]['TOTAL_MARKS']),0) ;
                                                                            }

                                                                           if($per_two < 33){
                                                                                $failed++;
                                                                            }
                                                                            $term2_gain += $term_2_data[$stuent_id]['mark'][$subject]['TOTAL_GAIN'];
                                                                        }

                                                                        /*if($subject_data['TOTAL_MARKS'] > 0){
                                                                            $grandSubTotal = ((float)$subject_data['TOTAL_MARKS'] + (float) $term_2_data[$stuent_id]['mark'][$subject]['TOTAL_MARKS']) / 2;
                                                                        }else{
                                                                            $grandSubTotal = (float) $term_2_data[$stuent_id]['mark'][$subject]['TOTAL_MARKS'];
                                                                        }*/
                                                                    ?>
                                                                    <tr>   
                                                                        <td><?php echo $subject; ?></td>   
                                                                             <?php
                                                                            // term 1 
                                                                         if($all_data['class']!="IX"){ 

                                                                            foreach ($subject_data as $exam_name => $obtain_point) {
                                                                            //echo $term_2_data[$stuent_id]['mark'][$subject]['TOTAL_GAIN']."/".$term_2_data[$stuent_id]['mark'][$subject]['TOTAL_MARKS']."<br/>"; 
                                                                                if($exam_name != 'TOTAL_MARKS') { 
                                                                                	if($exam_name == 'TOTAL_GAIN' && $obtain_point == 0){
																					?>	
																						<td align="center">-</td>
																					<?php 
																					}else{
																						?>
																						<td align="center"><?php echo $obtain_point; ?></td>
																					<?php 
																					} } } } ?>
                                                                            <!-- term 1 -->
                                                                            <?php foreach ($term_2_data[$stuent_id]['mark'][$subject] as $exam_name => $obtain_point) { 
                                                                                if($exam_name != 'TOTAL_MARKS') { ?>
                                                                            <td align="center"><?php echo $obtain_point; ?></td>
                                                                            <?php }} ?>
                                                                    </tr>
                                                                        <?php
                                                                    }
                                                                    ?>
                                                                    <tr>
                                                                        <td colspan="<?php echo count($all_data['exam']); ?>" align="right"><b>Total</b></td>
                                                                        <!-- term-1 -->
                                                                        <?php 
                                                                         if($all_data['class']!="IX"){ 
                                                                            ?>
                                                                        <td align="center"><b><?php echo ($term1_gain!=0) ? $term1_gain : '-'; ?></b></td>
                                                                        <td align="center" rowspan="2"><b><?php echo ($all_data['per'] > 0) ? $all_data['final_grade'] : '-'; ?></b></td>
                                                                        <td colspan="<?php echo count($term_2_data[$stuent_id]['exam']) - 1; ?>" align="right"><b>Total</b></td>
                                                                   
                                                                        <td align="center"><b><?php echo $term2_gain; ?></b></td>
                                                                         <?php }else{
                                                                                ?>

                                                                        <td align="center"><b><?php echo $term2_gain; ?></b></td>

                                                                         <?php } ?>
                                                                    <!-- term -1 -->
                                                                        <td align="center" rowspan="2"><b><?php echo $term_2_data[$stuent_id]['final_grade']; ?></b></td>
                                                                    </tr>

                                                                    <tr>
                                                                       <td colspan="<?php echo count($all_data['exam']); ?>" align="right"><b>Percentage</b></td>
                                                                        <!-- term -1  -->
                                                                        <?php 
                                                                         if($all_data['class']!="IX"){ 
                                                                            ?>
                                                                        <td align="center"><b><?php echo ($all_data['per']!=0) ? number_format($all_data['per'],2).'%' : '-'; ?></b></td>

                                                                        <td colspan="<?php echo count($term_2_data[$stuent_id]['exam']) - 1; ?>" align="right"><b>Percentage</b></td>
                                                                        <td align="center"><b><?php echo number_format($term_2_data[$stuent_id]['per'],2); ?>%</b></td>
                                                                        <?php }else{
                                                                            $r = count($term_2_data[$stuent_id]['mark']);
                                                                            $t = $term2_gain/$r;
                                                                            ?>
                                                                            <td align="center"><b><?php echo substr($t,0,5)."%"; ?></b></td>
                                                                        <?php } ?>
                                                                        <!-- end term-1 -->
                                                                          </tr>
                                                                 <?php
                                                                       if($all_data['per'] > 0){
                                                                            $finalPer = number_format(($term_2_data[$stuent_id]['per'] + $all_data['per']) / 2, 2);
                                                                        }else{
                                                                            $finalPer = number_format($term_2_data[$stuent_id]['per'], 2);
                                                                        }
                                                                        // term - 1
                                                                       
                                                                    if($all_data['class']!='IX'){ 
                                                                    ?>
                                                                   
                                                                    <tr>
                                                                        <td colspan="<?php echo count($all_data['exam']); ?>" align="right"><b>Overall Percentage</b></td>
                                                                        <td align="center" colspan="2">
                                                                            <b>{{ $finalPer }}%</b>
                                                                        </td>
                                                                        <td colspan="<?php echo count($term_2_data[$stuent_id]['exam']) - 1; ?>" align="right"><b>Overall Grade</b></td>
                                                                        <td align="center" colspan="2"><b>
                                                                                {{ \App\Helpers\getGrade($gradeScale, 100, $finalPer) }}
                                                                            </b></td>
                                                                    </tr>                                                      <?php } ?> 
                                                                    <!-- term -1 end-->           
                                                                </tbody>
                                                            </table>
                                                        </td>
                                                    </tr>
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
                                                                echo "<div style='display:flex;width:60%;margin-right:3%'>";
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
                                                                        <?php 
                                                                         if($all_data['class']!="IX"){ 
                                                                            ?>
                                                                        <th width="25%" style="text-align: center;">
                                                                            <b>{{ $all_data['term'] }}</b></th>
                                                                        <?php } ?>
                                                                        <!-- end term-1 -->
                                                                        <th width="25%" style="text-align: center;">
                                                                            <b>{{ $term_2_data[$stuent_id]['term'] }}</b>
                                                                        </th>
                                                                    </tr>
                                                                    <?php
                                                                    foreach ($child_arr as $subject => $obtain_grade) {
                                                                    $term1grade = $term1arr[$subject] ?? '-';
                                                                    $term2grade = $term2arr[$subject] ?? '-';
                                                                    ?>
                                                                    <tr>
                                                                        <td><?php echo $subject; ?></td>
                                                                        <!-- term - 1 -->
                                                                    <?php 
                                                                         if($all_data['class']!="IX"){ 
                                                                            ?>
                                                                        <td align="center"><?php echo $term1grade; ?></td>
                                                                    <?php } ?>
                                                                    <!-- end term-1 -->
                                                                        <td align="center"><?php echo $term2grade; ?></td>
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
                                                            }
                                                            ?>
                                                                <!--</div>-->
                                                        </td>
                                                    </tr>

                                                    @if(count($footer_data) > 0)
                                                    <tr>
                                                        <td><br/><br/>
                                                            <table style="border:hidden;" width="100%" cellspacing="0" cellpadding="0">
                                                                <tr>
                                                                    <td colspan="3" class="p-t-10">
                                                                        <b>Class Teacher's Remarks : {{ \App\Helpers\getGradeComment($gradeScale, 100, $finalPer) }}</b>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td colspan="3" class="p-t-10">
                                                                        <b>Result : <?php if($failed){ echo "Promoted"; }else{echo "Pass";}; ?></b>
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
                                                    @endif
                                                    <tr>
                                                        <td colspan="3" class="p-t-10">
                                                            <b>NOTE: N.A. = Not Applicable, AB = Absent, EX = Exemption</b>
                                                        </td>
                                                    </tr>
                                           </tbody></table>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                            <div style="page-break-after: always !important;"></div>    
                            <?php
                        }
                    //}
                    
                    $student_id_arr = implode(",",array_keys($data['data']));
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
