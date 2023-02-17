@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">       
            <div class="card">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    @php


                    @endphp
                    <form action="{{ route('marks_entry.create') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("GET") }}
                        {{csrf_field()}}

                        <div class="row">
                            {{ App\Helpers\TermDD($data['term_id']) }}
                        
                            {{ App\Helpers\SearchChain('4','single','grade,std,div',$data['grade'],$data['standard'],$data['division']) }}
                        

                            <div class="col-md-4 form-group">
                                <label for="title">Select Subject:</label>
                                <select name="subject" id="subject" class="form-control">
                                    <option value="">Select</option>
                                    @php
                                    foreach ($data['subject_dd'] as $id_dd=>$arr_dd){
                                    $selected = "";
                                    if($data['subject'] == $id_dd){
                                    $selected = 'selected=selected';
                                    }
                                    echo "<option $selected value=$id_dd>$arr_dd</option>";
                                    }
                                    @endphp
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label for="title">Select Exam:</label>
                                <select name="exam" id="exam" class="form-control">
                                    <option value="">Select</option>
                                    @php
                                    foreach ($data['exam_dd'] as $id_dd=>$arr_dd){
                                    $selected = "";
                                    if($data['exam'] == $id_dd){
                                    $selected = 'selected=selected';
                                    }
                                    echo "<option $selected value=$id_dd>$arr_dd</option>";
                                    }
                                    @endphp
                                </select>
                            </div>

                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
                                </center>
                            </div>
                        </div>

                    </form>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    @php
                    if(isset($data['stu_data'])){
                    @endphp
                    <form action="{{ route('marks_entry.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <div class="table-responsive">
                        <table class="table-bordered table" id="myTable">
                            <tr>
                              
                                <th>Roll No</th>
                                <th>Student Name</th>
                                <th>Marks</th>
                                <th style="display: none;">Percentage</th>
                                <th style="display: none;">Grade</th>
                                <th>Remark</th>
                            </tr>
                            @php

                            $arr = $data['stu_data'];
                            foreach ($arr as $id=>$col_arr){
                            @endphp
                            <tr>
                            <input type="hidden" class="total_days" value="{{ $col_arr['outof'] }}" />
                            <input type="hidden" name="values[{{ $col_arr['student_id'] }}][exam_id]" value="{{$data['exam']}}" />
                           
                            <td>@php echo $col_arr['roll_no']; @endphp</td>
                            <td>@php echo $col_arr['name']; @endphp</td>
                            <td> <input type="text" class="att" name="values[{{ $col_arr['student_id'] }}][points]" style="width: 100px;" value="{{ $col_arr['points'] }}" /> Out Of <lable>{{$col_arr['outof']}}</lable></td>
                            <td style="display: none;"><label class="at_per">{{ $col_arr['per'] }}%</label> <input type="hidden" class="at_per_val" name="values[{{ $col_arr['student_id'] }}][per]" readonly="readonly" style="width: 70px;"  value="{{ $col_arr['per'] }}%" /></td>

<!--<input type="text" class="at_per" name="values[{{ $col_arr['student_id'] }}][per]" readonly="readonly" style="width: 70px;"  value="{{ $col_arr['per'] }}%" />-->
                            <td style="display: none;"><label class="at_grd">{{ $col_arr['grade'] }}</label> <input type="hidden" class="at_grd_val" name="values[{{ $col_arr['student_id'] }}][grade]" readonly="readonly" style="width: 70px;"  value="{{ $col_arr['grade'] }}" /></td>
                            <td>
                                <textarea name="values[{{ $col_arr['student_id'] }}][comment]" rows="2" cols="20">{{ $col_arr['comment'] }}</textarea>
                            </td>
                            </tr>
                            @php
                            }
                            @endphp
                        </table>
                        </div>

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>

                    </form>
                    @php
                    }else{
                    echo "No Student Found.";
                    }
                    @endphp
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


@include('includes.footerJs')
<script>
    $(document).ready(function () {
        var standardID = $("#standard").val();
        var divisionID = $("#division").val();
       
        if (standardID) {
            $.ajax({
                type: "GET",
                url: "/api/get-subject-list?standard_id=" + standardID + "&division_id="+ divisionID,
                success: function (res) {
                    if (res) {
                        $("#subject").empty();
                        $("#subject").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#subject").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#subject").empty();
                    }
                }
            });
        } else {
            $("#subject").empty();
        }
    });

    setTimeout(() => {
        $('#subject').val({{ $data['subject'] }});
    }, 1000);


    var myArray = <?php echo json_encode($data['grd_data']); ?>;

    jQuery('.att').on('change blur', function () {
        var $row = jQuery(this).closest('tr');
        var $att = $row.find('.att').val();
        var $total_days = $row.find('.total_days').val();
        if (isNaN($att)) {
            $row.find('.at_grd_val').val("-");
            $row.find(".at_grd").text("-");
        } else if ($att > 500) {
            alert("Marks Should Not Be Grater Then 500.");
            $row.find('.att').val(0);
            $row.find('.at_grd_val').val("-");
            $row.find(".at_grd").text("-");
        } else {
            var $per = ($att * 100 / $total_days).toFixed(2);
            $per = Math.round($per);
            $.each(myArray, function (idx, obj) {
                if (jQuery.inArray($per, obj) !== -1) {
                    $row.find('.at_grd_val').val(idx);
                    $row.find(".at_grd").text(idx);
                }
            });
        }
    });
//    console.log(jArray);
</script>
<script>
    jQuery('.att').on('change', function () {
        var $row = jQuery(this).closest('tr');
        var $att = $row.find('.att').val();
        var $total_days = $row.find('.total_days').val();
        if (isNaN($att)) {
            $row.find('.at_per_val').val("-");
            $row.find(".at_per").text("-");
        } else if ($att > 500) {
//            alert("Marks Should Not Be Grater Then 500.");
              $row.find('.at_per_val').val("-");
            $row.find(".at_per").text("-");
        } else {
//        console.log($att);
//        console.log($total_days);
            var $per = ($att * 100 / $total_days).toFixed(2);
            $row.find('.at_per_val').val($per + "%");
            $row.find(".at_per").text($per + "%");
        }
    });

</script>
<script>
    $("#grade").prop('required', true);
    $("#standard").prop('required', true);
    $("#division").prop('required', true);
    $("#subject").prop('required', true);
    $("#term").prop('required', true);
    $("#exam").prop('required', true);
    $('#term').change(function () {
        $("#grade").val("");
        $("#standard").empty();
        $("#standard").append('<option value="">Select</option>');
        $("#division").empty();
        $("#division").append('<option value="">Select</option>');
        $("#subject").empty();
        $("#subject").append('<option value="">Select</option>');
        $("#exam").empty();
        $("#exam").append('<option value="">Select</option>');
    });
    $('#grade').change(function () {
        $("#subject").empty();
        $("#subject").append('<option value="">Select</option>');
        $("#exam").empty();
        $("#exam").append('<option value="">Select</option>');
    });
    $('#standard').change(function () {
        $("#subject").empty();
        $("#subject").append('<option value="">Select</option>');
        $("#exam").empty();
        $("#exam").append('<option value="">Select</option>');
    });
    $('#division').on('change', function () {
        $("#exam").empty();
        $("#exam").append('<option value="">Select</option>');
        var standardID = $("#standard").val();
        var divisionID = $("#division").val();
        if (standardID) {
            $.ajax({
                type: "GET",
                url: "/api/get-subject-list?standard_id=" + standardID + "&division_id="+ divisionID,
                success: function (res) {
                    if (res) {
                        $("#subject").empty();
                        $("#subject").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#subject").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#subject").empty();
                    }
                }
            });
        } else {
            $("#subject").empty();
        }

    });
    $('#subject').on('change', function () {
        var standardID = $("#standard").val();
        var subjectID = $("#subject").val();
        var termID = $("#term").val();

        if (standardID && subjectID && termID) {
            $.ajax({
                type: "GET",
                url: "/api/get-exam-list?standard_id=" + standardID +
                        "&subject_id=" + subjectID + "&term_id=" + termID,
                success: function (res) {
                    if (res) {
                        $("#exam").empty();
                        $("#exam").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#exam").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#exam").empty();
                    }
                }
            });
        } else {
            $("#exam").empty();
            $("#exam").append('<option value="">Select</option>');
            if (termID == "") {
                alert("Please Select Term.");
            }
        }

    });
</script>
@include('includes.footer')
