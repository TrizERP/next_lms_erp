{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Assignment Submission</h4>
            </div>
        </div>

        @if(isset($data['assignment_data']))
        @php
            if(isset($data['assignment_data'])){
                $assignment_data = $data['assignment_data'];
            }
        @endphp
        <div class="card">
                <div class="row">
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th data-toggle="tooltip" title="Sr. No.">Sr. No.</th>
                                        <th data-toggle="tooltip" title="Standard">Standard</th>
                                        <th data-toggle="tooltip" title="Student Name">Student Name</th>
                                        <th data-toggle="tooltip" title="Subject">Subject</th>
                                        <th data-toggle="tooltip" title="Assigned Title">Assignment Title</th>
                                        <th data-toggle="tooltip" title="Assigned On">Assigned On</th>
                                        <th data-toggle="tooltip" title="Submission Date">Submission Date</th>
                                        <th data-toggle="tooltip" title="Download File">Download File</th>
                                        <th data-toggle="tooltip" title="Submission File">Submission File</th>
                                        <th data-toggle="tooltip" title="Action">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                    @foreach($assignment_data as $key => $data)
                                    <tr>
                                        <td>{{$j}}</td>
                                        <td>{{$data['standard_name']}}</td>
                                        <td>{{$data['student_name']}}</td>
                                        <td>{{$data['subject_name']}}</td>
                                        <td>{{$data['title']}}</td>
                                        <td>{{$data['created_date']}}</td>
                                        <td>{{$data['submission_date']}}</td>
                                        <td><a href="../storage/{{$data['exam_pdf']}}" download>Attachment</a></td>
                                        @if($data['student_submission_status'] == 'Y')
                                            <td><a href="../storage/lms_assignment_submission/{{$data['submission_image']}}" target="_blank">Submitted File</a></td>
                                            @if($data['teacher_submission_status'] == 'N')
                                            <td><a href="{{ route('lmsAnnotate_assignment.edit',['id'=>$data['id']])}}" target="_blank">Review Assignment</a></td>
                                            @else
                                            <td>Completed</td>
                                            @endif
                                        @else
                                            <td>-</td>
                                            <td>-</td>
                                        @endif
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
                            <input type="submit" name="submit" value="Submit" class="btn btn-success" >
                        </center>
                    </div>
                </div>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>
$(document).ready(function () {
    $('#example').DataTable();
});
</script>
@include('includes.footer')
@endsection
