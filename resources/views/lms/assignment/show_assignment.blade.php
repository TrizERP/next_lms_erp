{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Create Assignment</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item">Exam</li>
                        <li class="breadcrumb-item active" aria-current="page">Assignment</li>
                    </ol>
                </nav>
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
                <div class="card-body">
                    @if ($sessionData = Session::get('data'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif
                    <form action="{{ route('lmsAssignment.create') }}">
                        @csrf
                        <div class="row">
                            {{ App\Helpers\SearchChain('3','multiple','grade,std,div',$grade_id,$standard_id,$division_id) }}

                            <div class="col-md-3 form-group">
                                <label for="subject">Select Subject:</label>
                                <select name="subject" id="subject" class="form-control">
                                    <option value="">Select Subject</option>
                                    <!-- @foreach($data['subjects'] as $key => $value)
                                    <option value="{{$value['id']}}" @if(isset($data['subject'])) @if($data['subject']==$value['id']) selected='selected' @endif @endif>{{$value['subject_name']}}</option>
                                    @endforeach -->
                                </select>
                            </div>


                            <div class="col-md-2 form-group">
                                <br>
                                <input type="submit" name="submit" value="Search" class="btn btn-success">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @if(isset($data['student_data']))
        @php
        if(isset($data['student_data'])){
        $student_data = $data['student_data'];
        $finalData = $data;
        }
        @endphp
            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('lmsAssignment.store') }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label>Title</label>
                                <input type="text" id="title" name="title" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Description</label>
                                <input type="text" id="description" name="description" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Submission Date</label>
                                <input type="text" id="submission_date" name="submission_date" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Exam</label>
                                <select id="exam_pdf" name="exam_pdf" class="form-control" required>
                                    <option value="">Select</option>
                                    @if(count($data['exam_arr']) > 0)
                                        @foreach($data['exam_arr'] as $k => $v)
                                            <option value="{{$v['pdf_name']}}####{{$v['id']}}">{{$v['paper_name']}}</option>
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
                                                <th>Student Name</th>
                                                <th>Enrollment Code</th>
                                                <th>Standard</th>
                                                <th>Division</th>
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
                                                <td><input id="{{$data['id']}}" value="{{$data['id']}}" name="students[]" type="checkbox"></td>
                                                <td>{{$data['first_name']}} {{$data['middle_name']}} {{$data['last_name']}}</td>
                                                <td>{{$data['enrollment_no']}}</td>
                                                <td>{{$data['standard_name']}}</td>
                                                <td>{{$data['division_name']}}</td>
                                                <td>{{$data['gender']}}</td>
                                                <td>{{$data['mobile']}}</td>
                                            </tr>
                                            @php
                                            $j++;
                                            @endphp
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-12 form-group">
                                    <center>
                                        <?php
                                        if (isset($finalData['division_id'])) {
                                            foreach ($finalData['division_id'] as $id => $value) {
                                                echo '<input type="hidden" name="division_id[]" value="' . $value . '">';
                                            }
                                        }
                                        if (isset($finalData['standard_id'])) {
                                            foreach ($finalData['standard_id'] as $id => $value) {
                                                echo '<input type="hidden" name="standard_id[]" value="' . $value . '">';
                                            }
                                        }
                                        ?>

                                        <input type="hidden" name="subject_id" @if(isset($finalData['subject'])) value="{{$finalData['subject']}}" @endif>

                                        <input type="submit" name="submit" value="Submit"
                                               onclick="return validateData();" class="btn btn-success">
                                    </center>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@include('includes.lmsfooterJs')
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

    function validateData() {
        var selected_stud = $("input[name='students[]']:checked").length;
        if (selected_stud == 0) {
            alert("Please Select Atleast One Student");
            return false;
        } else {
            return true;
        }
    }

    $(document).ready(function () {
        $('#grade').attr("required", true);
        $('#standard').attr("required", true);
        $('#subject').attr("required", true);
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

@include('includes.footer')
@endsection
