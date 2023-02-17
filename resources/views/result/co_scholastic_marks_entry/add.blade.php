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
                    
                    <form action="{{ route('co_scholastic_marks_entry.create') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("GET") }}
                        {{csrf_field()}}

                        <div class="row">
                            {{ App\Helpers\TermDD($data['term_id']) }}
                        
                            {{ App\Helpers\SearchChain('4','single','grade,std,div',$data['grade'],$data['standard'],$data['division']) }}
                        

                            <div class="col-md-4 form-group">
                                <label for="title">Select Co-Scholastic Parent:</label>
                                <select name="co_scholastic_parent" id="co_scholastic_parent" class="form-control">
                                    <option value="">Select</option>
                                    @php
                                    foreach ($data['co_scholastic_parent_dd'] as $id_dd=>$arr_dd){
                                    $selected = "";
                                    if($data['co_scholastic_parent'] == $id_dd){
                                    $selected = 'selected=selected';
                                    }
                                    echo "<option $selected value=$id_dd>$arr_dd</option>";
                                    }
                                    @endphp
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label for="title">Select Co-Scholastic:</label>
                                <select name="co_scholastic" id="co_scholastic" class="form-control">
                                    <option value="">Select</option>
                                    @php
                                    foreach ($data['co_scholastic_dd'] as $id_dd=>$arr_dd){
                                    $selected = "";
                                    if($data['co_scholastic'] == $id_dd){
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
                    <form action="{{ route('co_scholastic_marks_entry.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <div class="table-responsive">
                        <table class="table-bordered table" id="myTable">
                            <tr>
                                <th>No</th>
                                <th>Student Name</th>
                                @php
                                if($data['mark_type'] == 'GRADE'){
                                echo "<th>Grade</th>";
                                }
                                else{
                                echo "<th>Marks</th>";
                                }
                                @endphp
                            </tr>
                            @php

                            $arr = $data['stu_data'];
                            foreach ($arr as $id=>$col_arr){
                            @endphp
                            <tr>
                            <input type="hidden" name="values[{{ $col_arr['student_id'] }}][term_id]" value="{{$data['term_id']}}" />
                            <input type="hidden" name="values[{{ $col_arr['student_id'] }}][grade_id]" value="{{$data['grade']}}" />
                            <input type="hidden" name="values[{{ $col_arr['student_id'] }}][standard_id]" value="{{$data['standard']}}" />
                            <input type="hidden" name="values[{{ $col_arr['student_id'] }}][division_id]" value="{{$data['division']}}" />
                            <input type="hidden" name="values[{{ $col_arr['student_id'] }}][co_scholastic]" value="{{$data['co_scholastic']}}" />
                            <td>@php echo $id+1; @endphp</td>
                            <td>@php echo $col_arr['name']; @endphp</td>
                            @php
                            if($data['mark_type'] == 'GRADE'){
                            $name = "values[".$col_arr['student_id']."][grade]";
                            echo "<td>
                                <select name=$name id='grade' class='form-control'>
                                    <option value=''>Select</option>";
                                    foreach ($data["co_scholastic_grade_dd"] as $id_dd=>$arr_dd){
                                    $selected = "";
                                    if($col_arr["grade"] == $id_dd){
                                    $selected = "selected=selected";
                                    }
                                    echo "<option $selected value=$id_dd>$arr_dd</option>";
                                    }
                            echo '        
                                </select>
                            </td>';
                            }
                            else{
                            $name = "values[".$col_arr['student_id']."][points]";
                            $value = $col_arr['points'];
                            $max_mark = $col_arr['outof'];
                            echo "<td> <input type=text class=att name=$name style='width: 50px;' value=$value /> Out Of <lable>$max_mark</lable></td>";
                            }
                            @endphp

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
    $("#grade").prop('required', true);
    $("#standard").prop('required', true);
    $("#division").prop('required', true);
    $("#co_scholastic_parent").prop('required', true);
    $("#term").prop('required', true);
    $("#co_scholastic").prop('required', true);
    $('#term').change(function () {
        $("#grade").val("");
        $("#standard").empty();
        $("#standard").append('<option value="">Select</option>');
        $("#division").empty();
        $("#division").append('<option value="">Select</option>');
        $("#co_scholastic_parent").empty();
        $("#co_scholastic_parent").append('<option value="">Select</option>');
        $("#co_scholastic").empty();
        $("#co_scholastic").append('<option value="">Select</option>');
    });
    $('#grade').change(function () {
        $("#co_scholastic_parent").empty();
        $("#co_scholastic_parent").append('<option value="">Select</option>');
        $("#co_scholastic").empty();
        $("#co_scholastic").append('<option value="">Select</option>');
    });
    $('#standard').change(function () {
        $("#co_scholastic_parent").empty();
        $("#co_scholastic_parent").append('<option value="">Select</option>');
        $("#co_scholastic").empty();
        $("#co_scholastic").append('<option value="">Select</option>');
    });
    $('#division').on('change', function () {
        $("#co_scholastic").empty();
        $("#co_scholastic").append('<option value="">Select</option>');
        var standardID = $("#standard").val();
        if (standardID) {
            $.ajax({
                type: "GET",
                url: "/api/get-co-scholastic-parent-list?standard_id=" + standardID,
                success: function (res) {
                    if (res) {
                        $("#co_scholastic_parent").empty();
                        $("#co_scholastic_parent").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#co_scholastic_parent").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#co_scholastic_parent").empty();
                    }
                }
            });
        } else {
            $("#co_scholastic_parent").empty();
        }

    });
    $('#co_scholastic_parent').on('change', function () {
        var standardID = $("#standard").val();
        var co_scholastic_parentID = $("#co_scholastic_parent").val();
        var termID = $("#term").val();

        if (standardID && co_scholastic_parentID && termID) {
            $.ajax({
                type: "GET",
                url: "/api/get-co-scholastic-list?standard_id=" + standardID +
                        "&co_scholastic_parent_id=" + co_scholastic_parentID + "&term_id=" + termID,
                success: function (res) {
                    if (res) {
                        $("#co_scholastic").empty();
                        $("#co_scholastic").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#co_scholastic").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#co_scholastic").empty();
                    }
                }
            });
        } else {
            $("#co_scholastic").empty();
            $("#co_scholastic").append('<option value="">Select</option>');
            if (termID == "") {
                alert("Please Select Term.");
            }
        }

    });
</script>
@include('includes.footer')
