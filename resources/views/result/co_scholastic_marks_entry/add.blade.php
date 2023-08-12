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

                  @php
                $users = ["Admin"];
                $message = "";
                if(isset($data['approve_status']) && $data['approve_status']->status ==1 && isset($data['approved_user'])){
                    $message = "Approved By ".$data['approved_user']->first_name." ".$data['approved_user']->last_name." ";   
                }
                @endphp
                @if(in_array(session()->get('user_profile_name'),$users))
                <form action="{{ route('co_scholastic_marks_entry_approve') }}" enctype="multipart/form-data" method="post" style="margin-top:40px">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <div class="row mb-2 mt-6"> 
                            <div class="col-md-6 text-right ">
                                <label for="approve">Approved</label> 
                                <input type="checkbox" name="approve" id="approve" value="1" @if(isset($data['approve_status']) && $data['co_scholastic']==$data['approve_status']->exam_id && $data['approve_status']->status ==1) checked @endif>
                            </div> 
                            <div class="col-md-6">
                                <input type="hidden" name="term_id" value="{{$data['term_id']}}">
                                <input type="hidden" name="standard_id" value="{{$data['standard']}}">
                                <input type="hidden" name="division_id" value="{{$data['division']}}">
                                <input type="hidden" name="subject_id" value="0">
                                <input type="hidden" name="exam_id" value="{{$data['co_scholastic']}}">

                                <input type="submit"  class="btn btn-outline-secondary" name="submit" id="submit" Value="Approved Marks">
                                <div id="passwordHelpBlock" class="form-text">
                                {{$message}}</div>
                            </div>
                            <!-- subject_id,standard_id,division_id,exam_id,term_id,status,sub_institute_id,created_by,module_name -->
                        </div> 
                    </form>
                        @endif

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
                            $disable = "";
                            
                            if($data['mark_type'] == 'GRADE'){
                           
                            $name = "values[".$col_arr['student_id']."][grade]";
                            if(isset($data['approve_status']) && $data['approve_status']->status ==1  && $data['co_scholastic']==$data['approve_status']->exam_id){
                                $disable="disabled";
                            @endphp
                                <input type="hidden" name="{{$name}}" value="{{$data['stu_data'][$id][$col_arr['student_id']]['grade_marks']}}">
                            @php
                            }
                            echo "<td>
                                <select name=$name id='grade' class='form-control' $disable>
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
                                if(isset($data['approve_status']) && $data['approve_status']->status ==1 && $data['co_scholastic']==$data['approve_status']->exam_id){
                                $disable="disabled";
                            }
                            $name = "values[".$col_arr['student_id']."][points]";
                            $value = $col_arr['points'];
                            $max_mark = $col_arr['outof'];
                            echo '<td> <input type="text" class="att" name="' . $name . '" style="width: 50px;" onchange="check_input(this, ' . $col_arr["outof"] . ')" ' . $disable . ' value="' . $value . '" /> Out Of <label>' . $max_mark . '</label></td>';
}
                            @endphp

                            </tr>
                            @php
                            }
                            @endphp
                        </table>
                        </div>

                        @if(isset($data['approve_status']->status) && $data['approve_status']->status ==1)
                        @else
                        <div class="col-md-12 form-group mt-4">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>
                        @endif
                       
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

    
    function check_input(inputElement,outof) {
    var inputValue = inputElement.value;
    var values = inputValue.trim().split(/\s+/); 
    var totalValue = 0;
    var isValidValue = 0;

    var isValidValue = false;

    for (var i = 0; i < values.length; i++) {
        var intValue = parseInt(values[i]);
        if (!isNaN(intValue)) {
            totalValue += intValue;
        } 
    }

    if (totalValue > outof) {
        alert("Total value cannot be greater than " + outof + ".");
        inputElement.value =0;    
        }

}

</script>
@include('includes.footer')
