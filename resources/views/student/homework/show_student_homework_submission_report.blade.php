{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Homework Submission Report</h4>
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
            <form action="{{ route('student_homework_submission_report') }}" method="POST">
                @csrf
                <div class="row">
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                    <div class="col-md-4 form-group">
                        <label for="subject">Select Subject:</label>
                        <select name="subject" id="subject" class="form-control">
                            <option value="">Select Subject</option>
                            @foreach($data['subjects'] as $key => $value)
                                <option value="{{$value['id']}}"
                                        @if(isset($data['subject']))
                                        @if($data['subject'] == $value['id'])
                                        selected='selected'
                                    @endif
                                @endif
                                >{{$value['subject_name']}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>From Date</label>
                        <input type="text" id="from_date" @if(isset($data['from_date'])) value="{{$data['from_date']}}"  @endif name="from_date" class="form-control mydatepicker" required="required" autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>To Date</label>
                        <input type="text" id="to_date" @if(isset($data['to_date'])) value="{{$data['to_date']}}"  @endif name="to_date" class="form-control mydatepicker" required="required" autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Homework Submission Status</label>
                        <select id='status' name="status" class="form-control" required>
                            <option>--Select Status--</option>
                            <option value="Y" @if(isset($data['submission_status'])) @if($data['submission_status'] == 'Y') selected @endif @endif>Yes</option>
                            <option value="N" @if(isset($data['submission_status'])) @if($data['submission_status'] == 'N') selected @endif @endif>
                                No
                            </option>
                        </select>
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>
                </div>
            </form>
        </div>
        @if(isset($data['report_data']))
        @php
            if(isset($data['report_data'])){
                $report_data = $data['report_data'];
                $finalData = $data;
            }
        @endphp
            <div class="card">
                <div class="row">
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            {!! App\Helpers\get_school_details("$grade_id","$standard_id","$division_id") !!}
                            <table id="example" class="table table-striped">
                                <thead>
                                <tr>
                                    <th>Sr.No.</th>
                                    <th>{{App\Helpers\get_string('grno','request')}}</th>
                                    <th>{{App\Helpers\get_string('studentname','request')}}</th>
                                    <th>{{App\Helpers\get_string('std/div','request')}}</th>
                                    <th>SMS No.</th>
                                    <th>Homework Date</th>
                                    <th>Homework Title</th>
                                    <th>Description</th>
                                    <th>Homework Image</th>
                                    <th>Submission Date</th>
                                    <th>Submission Remark</th>
                                    <th>Submission Taken By</th>
                                </tr>
                            </thead>
                            <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                @foreach($report_data as $key => $data)
                                <tr>
                                    <td>{{$j}}</td>
                                    <td>{{$data['enrollment_no']}}</td>
                                    <td>{{$data['student_name']}}</td>
                                    <td>{{$data['std_div']}}</td>
                                    <td>{{$data['mobile']}}</td>
                                    <td>{{$data['HOMEWORK_DATE']}}</td>
                                    <td>{{$data['title']}}</td>
                                    <td>{{$data['description']}}</td>
                                    <td><a target="blank" href="/storage/student/{{$data['image']}}">view</a> </td>
                                    <td>{{$data['SUBMISSION_DATE']}}</td>
                                    <td>{{$data['submission_remarks']}}</td>
                                    <td>{{$data['submission_taken_by']}}</td>
                                </tr>
                                    @php
                                    $j++;
                                    @endphp
                                @endforeach
                            </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@include('includes.footerJs')

<script>
    $(document).ready(function () {
        var table = $('#example').DataTable({
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Student Homework Submission Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Student Homework Submission Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Student Homework Submission Report'},
                {
                    extend: 'print',
                    text: ' PRINT',
                    title: 'Student Homework Submission Report',
                    customize: function (win) {
                        $(win.document.body).prepend(`{!! App\Helpers\get_school_details("$grade_id", "$standard_id", "$division_id") !!}`);
                    }
                },
                'pageLength'
            ],
        });
        $('#example thead tr').clone(true).appendTo('#example thead');
        $('#example thead tr:eq(1) th').each(function (i) {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function () {
                if (table.column(i).search() !== this.value) {
                    table
                        .column(i)
                        .search(this.value)
                        .draw();
                }
            } );
        } );
    } );
</script>

@include('includes.footer')
@endsection
