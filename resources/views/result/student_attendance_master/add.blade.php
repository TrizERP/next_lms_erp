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
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('student_attendance_master.create') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("GET") }}
                        {{csrf_field()}}
                        <div class="row">                            
                            {{ App\Helpers\SearchChain('4','single','grade,std,div',$data['grade'],$data['standard'],$data['division']) }}
                            {{ App\Helpers\TermDD($data['term_id']) }}
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    @php
                    if(isset($data['stu_data'])){
                    @endphp
                    <form action="{{ route('student_attendance_master.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <div class="table-responsive">                            
                            <table class="table-striped table" id="myTable" >
                                <tr>
                                    <th>No</th>
                                    <th width="20%">Student Name</th>
                                    <th>Attendance</th>
                                    <th>Percentage</th>
                                    <th width="20%">Remark</th>
                                    <th width="40%">Teacher Remark</th>
                                </tr>
                                @php
                                $arr = $data['stu_data'];
                                foreach ($arr as $id=>$col_arr){
                                @endphp
                                <tr>
                                    <input type="hidden" class="total_days" value="{{$col_arr['att_out']}}" />
                                    <input type="hidden" name="values[{{ $col_arr['student_id'] }}][grade]" value="{{$data['grade']}}" />
                                    <input type="hidden" name="values[{{ $col_arr['student_id'] }}][standard]" value="{{$data['standard']}}" />
                                    <input type="hidden" name="values[{{ $col_arr['student_id'] }}][term_id]" value="{{$data['term_id']}}" />
                                    <td>@php echo $id+1; @endphp</td>
                                    <td>@php echo $col_arr['name']; @endphp</td>
                                    <td><input type="text" class="att" name="values[{{ $col_arr['student_id'] }}][attendance]" style="width: 50%;" value="{{ $col_arr['att'] }}" /> Out Of <lable>{{$col_arr['att_out']}}</lable></td>
                                    <td> <input type="text" class="at_per" name="values[{{ $col_arr['student_id'] }}][per]" readonly="readonly" style="width: 55%;"  value="{{ $col_arr['per'] }}%" /></td>
                                    <td>
                                        <select name="values[{{ $col_arr['student_id'] }}][remark_id]" class="form-control" onchange="set_comment(this, '{{ $col_arr['student_id'] }}');">
                                            <option value="">Select</option>
                                            @php
                                            foreach ($data['remark_data'] as $id_dd => $arr_dd) {
                                                $selected = ($col_arr['remark'] == $id_dd) ? 'selected="selected"' : '';
                                                echo "<option $selected value='$id_dd'>$arr_dd</option>";
                                            }
                                            @endphp
                                        </select>
                                    </td>
                                    <td>
                                        <textarea name="values[{{ $col_arr['student_id'] }}][teacher_remark]" id="ta_comment_{{ $col_arr['student_id'] }}" rows="2" cols="20" class="form-control">{{ $col_arr['teacher_remark'] }}</textarea>
                                    </td>
                                </tr>
                                @php
                                }
                                @endphp
                            </table>
                        </div>

                        <div class="col-md-12 mt-2 form-group">
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
    jQuery('.att').on('change', function () {
        var $row = jQuery(this).closest('tr');
        var $att = $row.find('.att').val();
        var $total_days = $row.find('.total_days').val();
        var $per = ($att * 100 / $total_days).toFixed(2);
        $row.find('.at_per').val($per + "%");
    });

</script>
<script>
    function set_comment(selectElement, studentId) {
        var dd_value = selectElement.value;
        var dd_text = selectElement.options[selectElement.selectedIndex].text;
        var ta_element = document.getElementById('ta_comment_' + studentId);
        ta_element.value = dd_text;
    }
</script>
@include('includes.footer')
