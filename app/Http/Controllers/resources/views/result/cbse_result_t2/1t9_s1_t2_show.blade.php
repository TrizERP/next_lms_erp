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
                <div>
                    <?php
                    $header_data = $data['header_data'];
                    $footer_data = $data['footer_data'];
                    // echo "<pre>";print_r($data);exit;
                    $term_2_data = $data['term_2_data'];
                    $gradeScale = \App\Helpers\getGradeScale();
                    $grandTotal = 0;
                    //foreach ($data as $arr) {
                        foreach ($data['data'] as $stuent_id => $all_data) {
                            ?>
                            <div id="{{$stuent_id ?? 0}}">
                            <table class="main-table ml-5 mr-5 mb-5" width="100%" ><!--style="border:1px solid #f37a0d;"-->
                                <tbody>
                                    <tr>
                                        <td>
                                            <table class="report-card" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">
                                                <tbody>
                                                    <tr>
                                                         <!--<td style="width: 165px;text-align: center;" align="left">
                                                            <img style="width: 100px;height: 90px;margin: 0;" src="/storage/result/left_logo/{{$header_data['left_logo']}}" alt="SCHOOL LOGO">
                                                         </td>-->
                                                         <td style="text-align:center !important;" align="center"> 
                                                            <span class="sc-hd">{{$header_data['line1']}}</span><br>   
                                                            <span class="ma-hd">{{$header_data['line2']}}</span><br>  
                                                            <span class="rg-hd">{{$header_data['line3']}}</span><br> 
                                                            <span class="rg-hd">{{$header_data['line4']}}</span><br>                                                            
                                                         </td>
                                                         <!--<td style="width: 165px;text-align: center;" align="left">
                                                            <img style="width: 100px;height: 90px;margin: 0;" src="/storage/result/right_logo/{{$header_data['right_logo']}}" alt="SCHOOL LOGO">                                                            
                                                         </td>-->
                                                    </tr>
                                                    <tr>
                                                        <td colspan="4">
                                                            <br/><br/><br/><br/><br/><br/><hr></hr>
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
                                                            <table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" width="95%" cellspacing="0" cellpadding="0" border="1">
                                                                <tbody>
                                                                    <tr>
                                                                        <th align="left"><b>Scholastic Areas:</b></th>
                                                                        <!-- term -1  -->
                                                                        <?php if($all_data['class']!="IX"){ ?>
                                                                        <th  style="text-align: center;" colspan="<?php echo count($all_data['exam']) + 1; ?>" ><b><?php echo $all_data['term'] . " (" . $all_data['total_mark'] . " Marks)"; ?></b></th>
                                                                    <?php } ?>
                                                                    <!-- end term-1 -->
                                                                        <th  style="text-align: center;" colspan="<?php echo count(array_unique(array_column($term_2_data[$stuent_id]['exam'],'exam','exam_id'))) + 1; ?>"><b><?php echo "Academic Year (" . $term_2_data[$stuent_id]['total_mark'] . " Marks)"; ?></b></th>
                                                                    </tr>
                                                                    <tr>   
                                                                        <th align="left"><b>Subject Name</b></th>
                                                                        <!-- term - 1 -->
                                                                        <?php
                                                                            $failed = 0;
                                                                            $exams = array_column($all_data['exam'], 'exam');
                                                                            $uniqueExams = array_unique($exams, SORT_REGULAR);

                                                                            foreach ($all_data['mark'] as $subject => $subject_data) {
                                                                                if ($all_data['class'] != "IX") {
                                                                                    foreach ($uniqueExams as $temp_id => $exam_data) {
                                                                                        $mark = array_column($all_data['mark'], 'mark', 'exam');

                                                                                        // Check if the key exists in the $mark array before accessing it
                                                                                        $mark_value = isset($mark[$exam_data]) ? $mark[$exam_data] : 'N/A';
                                                                                        ?>
                                                                                        <th style="text-align: center;"><b><?php echo $exam_data; ?><br>(<?php echo $mark_value; ?>)</b></th>
                                                                                        <?php
                                                                                    }
                                                                                    ?>
                                                                                    <th style="text-align: center;"><b>Grade</b></th>
                                                                                    <?php
                                                                                }
                                                                        $exams = array_column($term_2_data[$stuent_id]['exam'], 'exam');
                                                                        $uniqueExams = array_unique($exams, SORT_REGULAR);
                                                                        // echo "<pre>";print_r($term_2_data[$stuent_id]['exam']);
                                                                      foreach ($uniqueExams as $temp_id => $exam_data) {

                                                                        $mark = array_column($term_2_data[$stuent_id]['exam'], 'mark','exam');
                                                                        ?>
                                                                        <th style="text-align: center;"><b><?php echo $exam_data; ?><br>(<?php echo $mark[$exam_data]; ?>)</b></th>
                                                                        <?php
                                                                        }
                                                                        break;
                                                                    }
                                                                        ?>
                                                                        <th style="text-align: center;"><b>Grade</b></th>
                                                                    </tr>
                                                                    <?php
                                                                    $term1_gain = $term2_gain = $term2_tot = 0;
                                                                    foreach ($all_data['mark'] as $subject => $subject_data) {
                                                                        // if($all_data['class']=="IX"){

                                                                        //     $total_marks[]=$term_2_data[$stuent_id]['mark'][$subject]['TOTAL_GAIN'];
                                                                        //     print_r($total_marks);
                                                                        // }
                                                                        // echo "<pre>";
                                                                        // print_r($all_data);
                                                                        $sub_gain = $sub_tot = 0;
                                                                        if($subject_data['TOTAL_GAIN']>=0){
                                                                            $term1_gain += $subject_data['TOTAL_GAIN']; 
                                                                            //RAJESH $term2_gain += $term_2_data[$stuent_id]['mark'][$subject]['TOTAL_GAIN'];
                                                                            $grandGainTotal = ((float)$subject_data['TOTAL_GAIN'] + (float) $term_2_data[$stuent_id]['mark'][$subject]['TOTAL_GAIN']) / 2;
                                                                        }else{
                                                                            $grandGainTotal = (float) $term_2_data[$stuent_id]['mark'][$subject]['TOTAL_GAIN'];
                                                                        }
                                                                        if($subject_data['TOTAL_MARKS'] >= 0){
                                                                            $grandSubTotal = ((float)$subject_data['TOTAL_MARKS'] + (float) $term_2_data[$stuent_id]['mark'][$subject]['TOTAL_MARKS']) / 2;
                                                                        }else{
                                                                            $grandSubTotal = (float) $term_2_data[$stuent_id]['mark'][$subject]['TOTAL_MARKS'];
                                                                        }
                                                                    ?>
                                                                    <tr>   
                                                                        <td><?php echo $subject; ?></td>  

                                                                            <?php
                                                                            foreach ($term_2_data[$stuent_id]['mark'][$subject] as $exam_name => $obtain_point) {

                                                                                $mark = array_column($term_2_data[$stuent_id]['exam'], 'mark','exam');

                                                                                //echo "<pre>";print_r($mark[$exam_name]);
                                                                                //echo "<pre>";print_r($exam_name);exit;
                                                                                if($exam_name != 'TOTAL_MARKS' && $exam_name != 'TOTAL_GAIN' && $exam_name != 'GRADE') { 
                                                                                    if($obtain_point == "N.A." || $obtain_point == "EX" ){
                                                                                        ?><td align="center"><?php echo $obtain_point; ?></td><?php
                                                                                    }
                                                                                    elseif($obtain_point == "AB"){
                                                                                        ?><td align="center"><?php echo $obtain_point; ?></td><?php
                                                                                        $obtain_point = 0;
                                                                                        $sub_gain += $obtain_point;
                                                                                        $sub_tot += $mark[$exam_name];
                                                                                        $term2_gain += $obtain_point;
                                                                                        $term2_tot += $mark[$exam_name];
                                                                                    }
                                                                                    else
                                                                                    {
                                                                                        ?><td align="center"><?php echo $obtain_point; ?></td><?php
                                                                                        $sub_gain += $obtain_point;
                                                                                        $sub_tot += $mark[$exam_name];
                                                                                        $term2_gain += $obtain_point;
                                                                                        $term2_tot += $mark[$exam_name];
                                                                                    }
                                                                                }
                                                                            } 
                                                                            $per = round(100*$sub_gain/$sub_tot,0);
                                                                            if($per < 33){
                                                                                $failed++;
                                                                            }?>
                                                                            <td align="center"><b><?php echo $sub_gain; //."=".$sub_tot."=".$per."->".$failed?></b></td>
                                                                            <td align="center"><b>{{ \App\Helpers\getGrade($gradeScale, $sub_tot, $sub_gain) }}</b></td>
                                                                                <?php
                                                                        $total_marks[] = $term_2_data[$stuent_id]['mark'][$subject]['TOTAL_GAIN'];
                                                                        ?>
                                                                    </tr>
                                                                        <?php
                                                                    }
                                                                    ?>
                                                                    <tr>
                                                                        <td colspan="<?php echo count(array_unique(array_column($term_2_data[$stuent_id]['exam'],'exam','exam_id'))); ?>" align="right"><b>Total</b></td>
                                                                        <td align="center"><b><?php echo $term2_gain; ?></b></td>
                                                                        <td align="center" rowspan="2"><b>{{ \App\Helpers\getGrade($gradeScale, $term2_tot, $term2_gain) }}</b></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td colspan="<?php echo count(array_unique(array_column($term_2_data[$stuent_id]['exam'],'exam','exam_id'))); ?>" align="right"><b>Percentage</b></td>
                                                                        <td align="center"><b><?php echo number_format(100 * $term2_gain / $term2_tot,2); ?></b></td>
                                                                    </tr>
                                                                     <?php
                                                                           $finalPer = number_format($term_2_data[$stuent_id]['per'], 2);
                                                                       
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
                                                                echo "<div style='display:flex;width:50%;margin-right:3%'>";
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
                                                                                <?php 
                                                                         if($all_data['class']!="IX"){ 
                                                                            ?>
                                                                        <th width="25%" style="text-align: center;">
                                                                            <b>{{ $all_data['term'] }}</b></th>
                                                                        <?php } ?>
                                                                        <th width="25%" style="text-align: center;">
                                                                            <b>Grade</b>
                                                                        </th>
                                                                    </tr>
                                                                    <?php
                                                                    foreach ($child_arr as $subject => $obtain_grade) {
                                                                    $term1grade = $term1arr[$subject] ?? '-';
                                                                    $term2grade = $term2arr[$subject] ?? '-';
                                                                    ?>
                                                                    <tr>
                                                                        <td><?php echo $subject; ?></td>
                                                                        <?php 
                                                                         if($all_data['class']!="IX"){ 
                                                                            ?>
                                                                        <td align="center"><?php echo $term1grade; ?></td>
                                                                    <?php } ?>
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
