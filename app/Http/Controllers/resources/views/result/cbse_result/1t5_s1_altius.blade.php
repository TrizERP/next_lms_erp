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
                @if(session()->get('user_profile_name') == 'Admin')
                <div class="row">
                    <div class="col-sm-5 text-right"><input class="btn btn-warning mb-4" type="button" onclick="printDiv('printableArea');" value="Print Paper" /></div>
                    <div class="col-sm-2 center"></div>
                    <div class="col-sm-5 text-left"><input class="btn btn-danger mb-4" type="button" onclick="printMob('printableArea');" value="Print Mobile" /></div>
                </div>
                @endif
                <!--<div class="col-lg-12 col-sm-12 col-xs-12">-->
                <div id="printableArea">
                    <style>
                        table td,
                        table th {
                            padding: 8px;
                        }
                    </style>
                <div>
                    <?php
                    $header_data = $data['header_data'];
                    $footer_data = $data['footer_data'];
                    // $term_2_data = $data['term_2_data'];
                    $gradeScale = \App\Helpers\getGradeScale();
                    $grandTotal = 0;
                    //foreach ($data as $arr) {
                        foreach ($data['data'] as $stuent_id => $all_data) {
                            ?>
                            <div id="{{$stuent_id}}">
                            <table class="main-table ml-5 mr-5 mb-5" width="100%" ><!--style="border:1px solid #f37a0d;"-->
                                <tbody>
                                    <tr>
                                        <td><br/>
                                            <table class="report-card" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">
                                                <tbody>
                                                    <tr>
                                                         <td style="text-align: center;" align="left" width="15%">
                                                            <img style="height: 90px;margin: 0;" src="/storage/result/left_logo/{{$header_data['left_logo']}}" alt="SCHOOL LOGO">
                                                         </td>
                                                         <td style="text-align:center !important;" align="center" colspan="2" width="70%"> 
                                                            <span class="sc-hd">{{$header_data['line1']}}</span><br>   
                                                            <span class="ma-hd">{{$header_data['line2']}}</span><br>  
                                                            <span class="rg-hd">{{$header_data['line3']}}</span><br> 
                                                            <span class="rg-hd" style="font-size:11px !important;color:#313e84 !important">{{$header_data['line4']}}</span><br>
                                                         </td>
                                                         <td style="text-align: center;" align="left" width="15%">
                                                            <img style="height: 90px;margin: 0;" src="/storage/result/right_logo/{{$header_data['right_logo']}}" alt="SCHOOL LOGO">
                                                         </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="2" width="50%">
                                                            <span class="rg-hd">Phone No. : +91 937 716 0777 | +91 942 793 7777</span>
                                                        </td>
                                                        <td colspan="2" align="right" width="50%">
                                                            <span class="rg-hd">Website : www.altiusfortius.in</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="4">
                                                            <hr>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <table class="report-card ml-4" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">
                                                <tbody>
                                                    <tr>
                                                        <td colspan="3" align="center">
                                                            <h3 style="font-size:16;color:black;font-weight: 600;">REPORT CARD FOR ACADEMIC SESSION : <?php echo $all_data['year']; ?></h3>
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
                                                        <td colspan="3"><br/>
                                                            <table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" width="100%" cellspacing="0" cellpadding="0" border="1">
                                                                <tbody>
                                                                    <tr>   
                                                                        <th align="left" rowspan="2"><b>Subject Name</b></th>
                                                                        <!--<th class="main-th" align="left">Part 1-A-Scholastic Areas:</th>   -->
                                                                        <th align="left" colspan="<?php echo count($all_data['exam']) + 1; ?>" ><b>Scholastic Area</b></th>
                                                                    </tr>
                                                                    <tr>
                                                                        <?php
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
                                                                        
                                                                    </tr>
                                                                    <?php
                                                                    $term1_tot = $term1_obt = 0;
                                                                    foreach ($all_data['mark'] as $subject => $subject_data) {
                                                                        ?>
                                                                        <tr>   
                                                                            <td><?php echo $subject; ?></td>   
                                                                            <?php 
                                                                            foreach ($subject_data as $exam_name => $obtain_point) {
if($exam_name == 'TOTAL_GAIN') {$term1_obt += $subject_data['TOTAL_GAIN'];}
if($exam_name == 'TOTAL_MARKS') {$term1_tot += $subject_data['TOTAL_MARKS'];}
                                                                                if($exam_name != 'TOTAL_MARKS') { ?>
                                                                                <td align="center"><?php echo $obtain_point; ?></td>   
                                                                            <?php }} ?>
                                                                            
                                                                            
                                                                        </tr>
                                                                        <?php
                                                                    }
                                                                    ?>
                                                                    <tr>
                                                                        <td align="right" colspan="<?php echo count($all_data['exam']) + 1; ?>"><b>Half Yearly Obtained</b></td>
                                                                        <td align="center"><b><?php echo $term1_obt."/".$term1_tot; ?></b></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td align="right" colspan="<?php echo count($all_data['exam']) + 1; ?>"><b>Half Yearly Percentage</b></td>
                                                                        <td align="center"><b><?php echo number_format($all_data['per'],2); ?>%</b></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td align="right" colspan="<?php echo count($all_data['exam']) + 1; ?>"><b>Half Yearly Grade</b></td>
                                                                        <td align="center"><b><b><?php echo $all_data['final_grade']; ?></b></td>
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
            if (isset($all_data['co_scholastic_area'])) {
                $co_scholastic_area = $all_data['co_scholastic_area'];
            }
            foreach ($co_scholastic_area as $co_area => $arr) {
                $term1co = $all_data['co_scholastic_area'][$co_area] ?? [];
                //$term2co = $term_2_data[$stuent_id]['co_scholastic_area'][$co_area] ?? [];
                foreach ($arr as $parent => $child_arr) {
                    $term1arr = $term1co[$parent] ?? [];
                    $term2arr = $term2co[$parent] ?? [];
                    $count = $count + 1;
                    if ($count % 2 == 0) {
                        $margin = "margin-left:2.5%;";
                    } else {
                        $margin = "margin-right:2.5%;";
                    }
                    echo "<div style='display:flex;width:45%;'>";//$margin
                    ?>
                    <table class="aca-year"
                           style="width: 100%;border-collapse:collapse; border:1px solid #e68023;"
                           cellspacing="0" cellpadding="0" border="1">
                        <tbody>
                            <!--<tr>
                                <th colspan="2" width="15%" style="text-align: left;">
                                    <b><?php echo $parent; ?></b></th>
                            </tr>-->
                            <tr>
                                <th width="50%" style="text-align: left;"><b><?php echo $parent; ?></b></th>
                                <th width="25%" style="text-align: center;"><b>Half Yearly</b></th>
                            </tr>
                            <?php
                            foreach ($child_arr as $subject => $obtain_grade) {
                                $term1grade = $term1arr[$subject] ?? '-';
                                $term2grade = $term2arr[$subject] ?? '-';
                                ?>
                            <tr>
                                <td><?php echo $subject; ?></td>
                                <td align="center"><?php echo $term1grade; ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    </div>
                <?php
                if ($count % 2 == 0) {
                    echo "</div>";
                    echo "<div class='p-t-10' style='display:flex;'>";
                    }
            }
                    ?>
                    <div style='display:flex;width:30%;margin-left:10%;'>
                    <table class="aca-year"
                           style="width: 100%;border-collapse:collapse; border:1px solid #e68023;"
                           cellspacing="0" cellpadding="0" border="1">
                        <tbody>
                            <tr>
                                <th width="50%" style="text-align: left;"><b>Student Details</b></th>
                                <th width="25%" style="text-align: center;"><b>Half Yearly</b></th>
                            </tr>
                            <tr>
                                <td>Attendance</td>
                                <td width="25%" style="text-align: center;"><b>{{ $all_data['att'] }}/{{ $all_data['total_working_day'] }}</b></td>
                            </tr>
                            <tr>
                                <td>Height (cm)</td>
                                <td width="25%" style="text-align: center;"><b>{{ $all_data['height'] }}</b></td>
                            </tr>
                            <tr>
                                <td>Weight (kg)</td>
                                <td width="25%" style="text-align: center;"><b>{{ $all_data['weight'] }}</b></td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    <?php
            echo "</div>";
        }
    }
    ?>

                                                        </td>
                                                    </tr>
                                                    @if(count($footer_data) > 0)
                                                    <tr>
                                                            <table style="border:hidden;" width="100%" cellspacing="0" cellpadding="0">
                                                                 <tr>
                                                                    <td colspan="3" class="p-t-10"><b>Half Yearly Remarks : {{ $all_data['teacher_remark'] }}</b></td>
                                                                </tr>
                                                                <!--<tr>
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
                                                                </tr>-->
                                                                <!-- <tr>
                                                                    <th><b>School Reopens on</b></th>
                                                                    <td>
                                                                    <?php
                                                                   // $date = date_create($footer_data['reopen_date']);
                                                                   // echo date_format($date,"d-m-Y");                                 
                                                                    ?>
                                                                    </td> 
                                                                </tr>-->
                                                                <tr>
<!--                                                                     <th><b>Signature</b></th>
                                                                    <td colspan=2>
                                                                        <table width="100%" border="0">
                                                                            <tr> -->
                                                                                <td align="center" width="33%">
                                                                                    <img height="50px" width="100px" src="/storage/result/teacher_sign/{{$footer_data['teacher_sign']}}" />
                                                                                    <br>
                                                                                    <!--<hr>-->
                                                                                    Teacher's Sign
                                                                                </td>
                                                                                <td align="center" width="33%">
                                                                                    <img height="50px" width="100px" src="/storage/result/principle_sign/{{$footer_data['principal_sign']}}" />
                                                                                    <br>
                                                                                    <!--<hr>-->
                                                                                    Principal's Sign
                                                                                </td>
                                                                                <td align="center" width="33%">
                                                                                    <br><br>
                                                                                    <!--<img height="50px" width="100px"/>
                                                                                    <hr>-->
                                                                                    Parent's Sign
                                                                                </td>
                                                                            <!-- </tr>
                                                                        </table>
                                                                    </td> -->
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
                                                                    <table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" width="100%" cellspacing="0" cellpadding="0" border="1">            
                                                                        <tbody>
                                                                            <?php                                                                        
                                                                            foreach ($arr as $heading => $grd_data) {
                                                                                ?>
                                                                                <tr>                
                                                                                    <th align="center" width="200px"><b><?php echo $heading; ?></b></th>
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
                                                                </tbody>
                                                            </table>
                                                        </td>
                                                    </tr>-->
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
    function printMob(divName) {

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
<script type="text/javascript">
    function printDiv(divName) {

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
