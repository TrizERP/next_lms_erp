@include('includes.headcss')

@include('includes.header')
@include('includes.sideNavigation')

<style>
    .filter-button {
        margin: 0;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Transfer Student</h4></div>
        </div>
        @php
        $grade_id = $standard_id = $division_id = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }
        @endphp
        <div class="row">
            <div class="white-box">
                <div class="panel-body">
                    @if ($sessionData = Session::get('data'))
                    @if($sessionData['status_code'] == 1)
                    <div class="alert alert-success alert-block">
                    @else
                    <div class="alert alert-danger alert-block">
                    @endif
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif
                    <form action="{{ route('show_student') }}" enctype="multipart/form-data" method="post">
                        @csrf
                        {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                        <div class="col-md-3 col-sm-offset-4 text-center form-group">
                            <input type="submit" name="submit" value="Search" class="btn btn-success triz-btn">
                        </div>
                    </form>
                    </div>


                            @if(isset($data['student_data']))
                                @php
                                    if(isset($data['student_data'])){
                                        $student_data = $data['student_data'];
                                    }
                                $j=1;
                                @endphp
                            <form method="POST" action="{{route('transfer_student')}}"  enctype="multipart/form-data">
                                @csrf
							<input type="hidden" name="hid_gradeid" value="{{$grade_id}}">
							<input type="hidden" name="hid_standardid" value="{{$standard_id}}">
							<input type="hidden" name="hid_divisionid" value="{{$division_id}}">
                                <div class="table-responsive">
                                    <table id="example" class="table table-striped table-bordered display">
                                        <thead>
                                        <tr>
                                            <th><input id="checkall" onclick="checkedAll();" name="checkall"
                                                       type="checkbox"></th>
                                            <th>{{App\Helpers\get_string('studentname','request')}}</th>
                                            <th>{{App\Helpers\get_string('grno','request')}}</th>
                                            <th>{{App\Helpers\get_string('standard','request')}}</th>
                                            <th>{{App\Helpers\get_string('division','request')}}</th>
                                            <th>Gender</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($student_data as $key => $value)
										<tr>
										<td><input type="checkbox" name="stud_ids[]" value="{{$value->student_id}}"></td>
                                        <td>{{$value->student_name}}</td>
                                        <td>{{$value->enrollment_no}}</td>
                                        <td>{{$value->standard_name}}</td>
                                        <td>{{$value->division_name}}</td>
                                        <td>{{$value->gender}}</td>
										</tr>
                                        @endforeach
                                        @if(count($student_data) == 0 )
										<tr><td colspan="7">No Records Found.</td></tr>
									@endif
                                </tbody>
                                </table>
                            </div>
							@if(count($student_data) > 0)
                            <div class="col-md-12 form-group">
                                <br><br>
								<center>
                                    <input type="submit" name="submit" onclick="return check_validation();" value="Transfer Student" class="btn btn-success" >
                                </center>
                            </div>
							@endif
                            </form>
                    </div>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>
var checked = false;
function checkedAll() {
    if (checked == false) {
        checked = true
    } else {
        checked = false
    }
    for (var i = 0; i < document.getElementsByName('stud_ids[]').length; i++) {
        document.getElementsByName('stud_ids[]')[i].checked = checked;
    }
}

function check_validation() {
    var total_student = $('input[name="stud_ids[]"]:checked').length;
    if (total_student == 0) {
        alert("Please select atleast one student to transfer");
        return false;
    } else {
        return true;
    }
}
</script>
@include('includes.footer')
