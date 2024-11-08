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
                <form method="POST" enctype="multipart/form-data"
                      action="{{ route('lmsAssignment_submission.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <table id="example" class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th>Sr. No.</th>
                                        <th>Subject</th>
                                        <th>Assignment Title</th>
                                        <th>Assigned On</th>
                                        <th>Submission Date</th>
                                        <th>Download File</th>
                                        <th>Submission File</th>
                                        <th>Teacher Remarks</th>
                                        <th>Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @php
                                        $j=1;
                                    @endphp
                                    @foreach($assignment_data as $key => $data)
                                        <tr>
                                            <td>{{$j}}</td>
                                            <td>{{$data['subject_name']}}</td>
                                            <td>{{$data['title']}}</td>
                                            <td>{{$data['created_date']}}</td>
                                            <td>{{$data['submission_date']}}</td>
                                            <td><a href="../storage/{{$data['exam_pdf']}}" download>Attachment</a></td>
                                            @if($data['student_submission_status'] == 'Y')
                                                <td>
                                                    <a href="../storage/lms_assignment_submission/{{$data['submission_image']}}"
                                                       target="_blank">Submitted File</a></td>
                                            @else
                                                <td><input type="file" id="image[{{$data['id']}}]"
                                                           name="image[{{$data['id']}}]" class="form-control"></td>
                                            @endif
                                            <td>{{$data['teacher_remarks']}}</td>
                                            @if($data['teacher_submission_status'] == 'Y')
                                                <td>
                                                    <a target="_blank"
                                                       href="{{ route('lmsAssignment_submission.show',['lmsAssignment_submission'=>$data['id'],'student_id'=>$data['student_id'],'question_paper_id'=>$data['exam_id']]) }}"
                                                       class="btn btn-info btn-outline btn m-r-5">View Assignment</a>
                                                </td>
                                            @else
                                                <td></td>
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
$(document).ready(function () {
    $('#example').DataTable();
});
</script>
@include('includes.footer')
@endsection
