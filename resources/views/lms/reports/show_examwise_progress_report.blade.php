{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Examwise Progress Report</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item">Reports</li>
                        <li class="breadcrumb-item active" aria-current="page">Examwise Progress Report</li>
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
                    <form action="{{ route('lmsExamwise_progress_report.create') }}">
                        @csrf
                        <div class="row">
                            {{ App\Helpers\SearchChain('3','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                            <div class="col-md-3 form-group">
                                <label for="subject">Select Subject</label>
                                <select name="subject" id="subject" class="cust-select form-control mb-0">
                                    @if(empty($data['subject_data']))
                                        <option value="">Select Subject</option>
                                    @endif
                                    @if(!empty($data['subject_data']))
                                        @foreach($data['subject_data'] as $k1 => $v1)
                                            <option
                                                value="{{$v1['subject_id']}}" @if(isset($data['subject_id'])){{$data['subject_id'] == $v1['subject_id'] ? 'selected' : '' }} @endif>{{$v1['display_name']}} </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="exam">Select Exam</label>
                                <select class="cust-select form-control mb-0" name="exam_id[]" multiple="multiple"
                                        required="required">
                                    @if(!empty($data['exams_data']))
                                        @foreach($data['exams_data'] as $k => $v)
                                            <option
                                                value="{{$v->id}}" @if(isset($data->exam_id)){{$data->exam_id == $v->id ? 'selected=selected' : '' }} @endif>{{$v->paper_name}}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-2 form-group mt-4">
                                <br>
                                <input type="submit" name="submit" value="Search" class="btn btn-success">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @if(isset($data['student_data']))
        @php
        if(isset($data['student_data']))
        {
            $student_data = $data['student_data'];
        }
        if(isset($data['marks_data']))
        {
            $marks_data = $data['marks_data'];
        }
         if(isset($data['all_marks']))
        {
            $all_marks = $data['all_marks'];
        }
        if(isset($data['all_marks_col']))
        {
            $numColumns  = $data['all_marks_col'];
        }
        if(isset($data['grade_data']))
        {
            $grade_data = $data['grade_data'];
        }

        @endphp
            <div class="card">
                <div class="card-body">

                    <div class="row">
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <table id="example" class="table table-striped table-bordered">
                                    <thead>
                                    <tr>
                                        <th>Sr No.</th>
                                        <th>Student Name</th>
                                        <th>Enrollment Code</th>
                                        <!-- <th>All Marks</th> -->
                                        @if(isset($numColumns))
                                        @for ($i=1;$i<=$numColumns;$i++)
                                        <th>Test-{{$i}}</th>
                                        @endfor
                                        @endif
                                        <!-- <th>Standard</th> -->
                                        <!-- <th>Division</th> -->
                                                @php
                                                $total_marks = 0;
                                                @endphp
                                                @if(isset($data['exams_data']))
                                                @foreach($data['exams_data'] as $k => $exam_name)
                                                <th>{{$exam_name['paper_name']}}({{$exam_name['total_marks']}})</th>
                                                @php
                                                $total_marks = $total_marks + $exam_name['total_marks'];
                                                @endphp
                                                @endforeach
                                                @endif
                                                <th>Total</th>
                                                <th>Per(%)</th>
                                                <th>Grade</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $j=1;
                                                $grade = '';
                                            @endphp
                                            @foreach($student_data as $key => $data)
                                            <tr>
                                                <td>{{$j}}</td>
                                                <td>{{$data['first_name']}} {{$data['middle_name']}} {{$data['last_name']}}</td>
                                                <td>{{$data['enrollment_no']}}</td>
                                                <!-- al marks -->
                                                @if (isset($all_marks[$data['id']]))
                                                @foreach ($all_marks[$data['id']] as $question_paper_id => $all_markss)
                                                    @php
                                                        $m = explode(',', $all_markss);
                                                        $m = array_pad($m, $numColumns, '-');
                                                    @endphp
                                                    @foreach ($m as $am)
                                                        <td>{{ $am }}</td>
                                                    @endforeach
                                                @endforeach
                                                @endif

                                                <!-- best of 5 -->
                                                @php
                                                $total_obtain_marks = $obtain_per  = 0;
                                                @endphp
                                                @if(isset($marks_data[$data['id']]))
                                                @foreach($marks_data[$data['id']] as $question_paper_id => $obtain_marks)
                                                @php
                                                    if($obtain_marks != '-')
                                                    {
                                                        $total_obtain_marks = $total_obtain_marks + $obtain_marks;
                                                        $obtain_per = number_format(($total_obtain_marks/$total_marks) * 100,2);
                                                    }
                                                @endphp
                                                <td>{{$obtain_marks}}</td>
                                                    @endforeach
                                                @endif
                                                <td>{{$total_obtain_marks}}/{{$total_marks}}</td>
                                                <td>{{$obtain_per}}%</td>
                                                @php
                                                    foreach ($grade_data as $k1 => $v1)
                                                    {
                                                        if ($obtain_per >= $v1['breakoff'])
                                                        {
                                                            $grade = $v1['title'];
                                                            break;
                                                        }else{

                                                            $grade = "-";
                                                        }

                                                    }
                                                @endphp

                                                <td>{{$grade}}</td>
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
            </div>
        @endif
    </div>
</div>
@include('includes.lmsfooterJs')
<script>
    $("#standard").change(function () {
        var std_id = $("#standard").val();
        var path = "{{ route('ajax_LMS_StandardwiseSubject') }}";
        $('#subject').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
        $.ajax({
            url: path, data: 'std_id=' + std_id, success: function (result) {
                for (var i = 0; i < result.length; i++) {
                    $("#subject").append($("<option></option>").val(result[i]['subject_id']).html(result[i]['display_name']));
                }
            }
        });
    })

    $("#subject").change(function(){
        var std_id = $("#standard").val();
        var sub_id = $("#subject").val();
        var path = "{{ route('ajax_LMS_SubjectWiseExam') }}";
        $.ajax({
            url: path,
            data: 'std_id=' + std_id + '&sub_id=' + sub_id,
            success: function (result) {
                var e = $('select[name="exam_id[]"]');
                $(e).find('option').remove().end();
                for (var i = 0; i < result.length; i++) {
                    $(e).append($("<option></option>").val(result[i]['id']).html(result[i]['paper_name']));
                }
            }
        });
    })

    $(document).ready(function () {
        $('#grade').attr("required", true);
        $('#standard').attr("required", true);
        $('#subject').attr("required", true);
    });
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
                    title: 'Other Fees Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Other Fees Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Other Fees Report'},
                {extend: 'print', text: ' PRINT', title: 'Other Fees Report'},
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
                        .search( this.value )
                        .draw();
                }
            } );
        } );
    } );
</script>

@include('includes.footer')
@endsection
