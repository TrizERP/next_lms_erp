{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Homework Submission</h4>
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
            <form action="{{ route('student_homework_submission.create') }}">
                @csrf
                <div class="row">
                    {{ App\Helpers\SearchChain('3','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                    <div class="col-md-3 form-group">
                        <label for="subject">Select Subject:</label>
                        <select name="subject" id="subject" class="form-control">
                            <option value="">Select Subject</option>
                        <!-- @foreach($data['subjects'] as $key => $value)
                            <option value="{{$value['id']}}" @if(isset($data['subject'])) @if($data['subject']==$value['id']) selected='selected' @endif @endif>{{$value['subject_name']}}</option>
                            @endforeach -->
                        </select>
                    </div>
                    <div class="col-sm-3 form-group">
                        <label>Submission Date</label>
                        <input type="text" name="submission_date" class="form-control mydatepicker" placeholder="Please select submission date." required="required" value="@if(isset($data['submission_date'])){{$data['submission_date']}}@endif"
                               autocomplete="off">
                    </div>
                    <div class="col-sm-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>
                </div>
            </form>
        </div>
        @if(isset($data['student_data']))
            @php
                if(isset($data['student_data'])){
                    $student_data = $data['student_data'];
                    $finalData = $data;
                }
            @endphp
            <div class="card">
                <form method="POST" enctype="multipart/form-data"
                      action="{{ route('student_homework_submission.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <table id="example" class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>
                                        <th>{{App\Helpers\get_string('grno','request')}}</th>
                                        <th>{{App\Helpers\get_string('studentname','request')}}</th>
                                        <th>{{App\Helpers\get_string('standard','request')}}</th>
                                        <th>{{App\Helpers\get_string('division','request')}}</th>
                                        <th>Mobile</th>
                                        <th>Homework Date</th>
                                        <th>Homework Title</th>
                                        <th>Homework Description</th>
                                        <th>Homework File</th>
                                        <th>Submission Date</th>
                                        <th>Remarks</th>
                                        <th>Submission File</th>
                                    </tr>
                                </thead>
                                <tbody>
                                        @php
                                        $j=1;
                                        @endphp
                                    @foreach($student_data as $key => $data)
                                    <tr>
                                        <td><input id="{{$data->CHECKBOX}}" value="{{$data->CHECKBOX}}" name="students[]" type="checkbox"></td>
                                        <td>{{$data->enrollment_no}}</td>
                                        <td>{{$data->student_name}}</td>
                                        <td>{{$data->standard}}</td>
                                        <td>{{$data->division}}</td>
                                        <td>{{$data->mobile}}</td>
                                        <td>{{$data->HOMEWORK_DATE}}</td>
                                        <td>{{$data->title}}</td>
                                        <td>{{$data->description}}</td>
                                        <td>{{$data->image}}</td>
                                        <td>{{$data->SUBMISSION_DATE}}</td>
                                        <td><textarea class="form-control" rows="2" name="submission_remarks[{{$data->CHECKBOX}}]">{{$data->submission_remarks}}</textarea></td>
                                        <td><input type="file" id="image[{{$data->CHECKBOX}}]" name="image[{{$data->CHECKBOX}}]" class="form-control"></td>
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
                                <input type="hidden" name="division_id"
                                       @if(isset($finalData['division_id'])) value="{{$finalData['division_id']}}" @endif
                                ">
                                <input type="hidden" name="standard_id"
                                       @if(isset($finalData['standard_id'])) value="{{$finalData['standard_id']}}" @endif
                                ">
                                <input type="hidden" name="subject_id"
                                       @if(isset($finalData['subject'])) value="{{$finalData['subject']}}" @endif">
                                <input type="submit" name="submit" value="Submit" class="btn btn-success">
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
    $(document).on('change', '#standard', function () {
        var standard_id = $(this).val();
        var path = "{{ route('ajax_getHomeworkSubjects') }}";
        $.ajax({
            url: path,
            data: 'standard_id=' + standard_id,
            success: function (result) {
                var e = $('select[name="subject"]');
                $(e).find('option').remove().end();
                $(e).append($("<option></option>").val("").html('Select Subject'));
                for (var i = 0; i < result.length; i++) {
                    $(e).append($("<option></option>").val(result[i]['subject_id']).html(result[i]['display_name']));
                }
            }
        });
    });
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
</script>
<script>
$(document).ready(function () {
    $('#example').DataTable();
});
</script>
@include('includes.footer')
@endsection
