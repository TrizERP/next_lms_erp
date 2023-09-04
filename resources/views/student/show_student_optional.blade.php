@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Search Student for Optional Subject</h4>
            </div>
        </div>
        @php
        $grade_id = $standard_id = $division_id = '';

        if(isset($data['grade_id'])){
        $grade_id = $data['grade_id'];
        $standard_id = $data['standard_id'];
        $division_id = $data['division_id'];
        }
        @endphp
        <div class="card">
            @if ($sessionData = Session::get('data'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <form action="{{ route('show_search_student_optional_subject') }}" enctype="multipart/form-data" method="post">
                @csrf
                <div class="row">
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label class="box-title after-none mb-0">Last Name</label>
                        <div class = "ui-widget">
                            <input type="text" name="last_name" id="last_name" value="@if(isset($data['last_name'])) {{$data['last_name']}} @endif" class="form-control" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="box-title after-none mb-0">First Name</label>
                        <div class = "ui-widget">
                            <input type="text" name="first_name" id="first_name" value="@if(isset($data['first_name'])) {{$data['first_name']}} @endif" class="form-control" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="box-title after-none mb-0">Mobile</label>
                        <input type="text" name="mobile" value="@if(isset($data['mobile'])) {{$data['mobile']}} @endif" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="box-title after-none mb-0">{{App\Helpers\get_string('grno','request')}}</label>
                        <input type="text" name="gr_no" value="@if(isset($data['gr_no'])) {{$data['gr_no']}} @endif" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="box-title after-none mb-0">{{App\Helpers\get_string('uniqueid','request')}}</label>
                        <input type="text" name="unique_id" value="@if(isset($data['unique_id'])) {{$data['unique_id']}} @endif"
                               class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>
                </div>
            </div>
        </form>
    </div>
    @if(isset($data['data']))
        @php
        if(isset($data['data'])){
        $student_data = $data['data'];
        }
        @endphp
        <div class="card">
            <form method="POST" action="{{ route('student_optional_subject.store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Optional Subjects</label>
                        <select class="form-control" name="subjects[]" required="required" multiple>
                            <option value="">Select Subjects</option>
                            @if(isset($data['optional_subject_data']))
                                @foreach ($data['optional_subject_data'] as $subjects)
                                    <option value="{{ $subjects['subject_id'] }}">{{ $subjects['subject_name'] }}</option>
                                @endforeach
                            @endif                                                                
                        </select>
                    </div>
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                <tr>
                                    <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>
                                    <th>{{App\Helpers\get_string('studentname','request')}}</th>
                                    <th>{{App\Helpers\get_string('grno','request')}}</th>
                                    <th>{{App\Helpers\get_string('uniqueid','request')}}</th>
                                    <th>Academic Section</th>
                                    <th>{{App\Helpers\get_string('standard','request')}}</th>
                                    <th>{{App\Helpers\get_string('division','request')}}</th>
                                    <th>Gender</th>
                                    <th>Mobile</th>
                                </tr>
                                </thead>
                                <tbody>
                                @php
                                $j=1;
                                @endphp
                                @foreach($student_data as $key => $data)
                                    <tr>
                                        <td><input id="{{$data['stu_id']}}" value="{{$data['stu_id']}}" name="students[]" type="checkbox"></td>
                                        <td>{{$data->first_name}} {{$data->middle_name}} {{$data->last_name}}</td>
                                        <td>{{$data->enrollment_no}}</td>
                                        <td>{{$data->uniqueid}}</td>
                                        <td>{{$data->grade}}</td>
                                        <td>{{$data->standard}}</td>
                                        <td>{{$data->division}}</td>
                                        <td>{{$data->gender}}</td>
                                        <td>{{$data->mobile}}</td>
                                    </tr>
                                @php
                                $j++;
                                @endphp
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="hidden" name="grade_id" @if(isset($data['grade_id'])) value="{{$data['grade_id']}}" @endif">
                            <input type="hidden" name="standard_id" @if(isset($data['standard_id'])) value="{{$data['standard_id']}}" @endif
                            ">
                            <input type="submit" name="submit" value="Submit" class="btn btn-success" onclick="check_validation()">
                        </center>
                    </div>
                </div>
            </form>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>
$(document).ready(function () {
    $("#first_name").autocomplete({
        source: function (request, response) {
            $.ajax({
                url: "{{route('search_student_by_firstname')}}",
                type: 'POST',
                data: {
                    'value': request.term
                },
                success: function(data){
                    response( $.map( data, function( item ) {
                        return {
                            label: item.first_name,
                            value: item.first_name
                        }
                    }));
                }
            });
        }
    });

    $("#last_name").autocomplete({
        source: function (request, response) {
            $.ajax({
                url: "{{route('search_student_by_lastname')}}",
                type: 'POST',
                data: {
                    'value': request.term
                },
                success: function(data){
                    response( $.map( data, function( item ) {
                        return {
                            label: item.last_name,
                            value: item.last_name
                        }
                    }));
                }
            });
        }
    });
});
</script>
<script>
function checkAll(ele) {
    var checkboxes = document.getElementsByTagName('input');
    
    if (ele.checked) {
        for (var i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].type == 'checkbox') {
                checkboxes[i].checked = true;
            }
        }
    } else {
        for (var i = 0; i < checkboxes.length; i++) {
            console.log(i)
            if (checkboxes[i].type == 'checkbox') {
                checkboxes[i].checked = false;
            }
        }
    }
}

function check_validation()
{    
    var checked_questions = err = 0;

    $("input[name='students[]']:checked").each(function ()
    {             
        checked_questions = checked_questions + 1;
    });

    if(checked_questions == 0)
    {
        alert("Please Select Atleast one student");
        err = 1;
        return false;
    }else{
        return true;
    }
}
</script>
@include('includes.footer')
