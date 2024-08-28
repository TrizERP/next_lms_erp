{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<style>
    br {
        display: block !important;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Create Exam</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{ route('course_master.index') }}">LMS</a></li>
                        <li class="breadcrumb-item">Exam</li>
                        <li class="breadcrumb-item active" aria-current="page">Create Exam</li>
                    </ol>
                </nav>
            </div>
            @php
                $user_profile = Session::get('user_profile_name');
                $show_block = 'NO';
                if (strtoupper($user_profile) == 'LMS TEACHER' || strtoupper($user_profile) == 'TEACHER') {
                    $show_block = 'YES';
                }
            @endphp
            @if ($show_block == 'YES')
                <div class="col-md-3 mb-4 text-md-right">
                    <a href="{{ route('question_paper.create') }}" class="btn btn-info add-new"><i
                            class="fa fa-plus"></i>Add Exam</a>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-body">
                @if ($sessionData = Session::get('data'))
                    <div
                        class="@if ($sessionData['status_code'] == 1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                @endif
                <div class="row">
                    <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                        <div class="table-responsive">
                            <table id="subject_list" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Sr. No.</th>
                                        <th>Exam/Paper Name</th>
                                        <th>Exam/Paper Description</th>
                                        <!-- <th>Academic Section</th> -->
                                        <th>Exam Type</th>
                                        <th>Standard</th>
                                        <th>Subject</th>
                                        <th>Total Questions</th>
                                        <th>Total Marks</th>
                                        <th>Attempt Allowed</th>
                                        <th>Open Date</th>
                                        <th>Close Date</th>
                                        <th>Status</th>
                                        <th>Show Right Answer In Result</th>
                                        <th>View</th>
                                        @if ($show_block == 'YES')
                                            <th>Action</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @if (count($data['data']) > 0)
                                        @php $i = 1;@endphp
                                        @foreach ($data['data'] as $key => $quespaper)
                                            <tr>
                                                <td>@php echo $i++;@endphp</td>
                                                <td>{{ $quespaper->paper_name }} </td>
                                                <td>{{ $quespaper->paper_desc }}</td>
                                                <!-- <td>{{ $quespaper->grade_name }}</td> -->
                                                <td>{{ $quespaper->exam_type }}</td>
                                                <td>{{ $quespaper->standard_name }}</td>
                                                <td>{{ $quespaper->subject_name }}</td>
                                                <td>{{ $quespaper->total_ques }}</td>
                                                <td>{{ $quespaper->total_marks }}</td>
                                                <td>{{ $quespaper->attempt_allowed }}</td>
                                                <td>{{ $quespaper->open_date }}</td>
                                                <td>{{ $quespaper->close_date }}</td>
                                                <td>
                                                    @if ($quespaper->show_hide == 1)
                                                        Show
                                                    @else
                                                        Hide
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($quespaper->result_show_ans == 1)
                                                        Show Right Answer
                                                    @else
                                                        Hide Right Answer
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($show_block == 'YES')
                                                        <a target="_blank"
                                                           href="{{ route('question_paper.show', ['question_paper' => $quespaper->id]) }}"
                                                           class="btn btn-info btn-outline btn m-r-5">View</a>
                                                    @endif

                                                    @if (strtoupper($user_profile) == 'STUDENT')
                                                        <div class="text-nowrap">
                                                            @if ($quespaper->total_attempt > 0)
                                                                <a href="{{ route('online_exam_attempt', ['questionpaper_id' => $quespaper->id, 'student_id' => session()->get('user_id')]) }}"
                                                                   class="btn btn-info btn-outline btn m-r-5">View
                                                                    Attempted Exam</a>
                                                            @endif

                                                            @php
                                                                $show_attempt_exam = 'no';
                                                                $attempt_allowed = 'no';

                                                                if ($quespaper->attempt_allowed == 0) {
                                                                    $attempt_allowed = 'yes';
                                                                } elseif ($quespaper->attempt_allowed != 0 && $quespaper->attempt_allowed > $quespaper->total_attempt) {
                                                                    $attempt_allowed = 'yes';
                                                                }

                                                                if ($quespaper->open_date != '' && $quespaper->close_date != '') {
                                                                    if ($quespaper->active_exam == 'yes') {
                                                                        $show_attempt_exam = 'yes';
                                                                    } else {
                                                                        $show_attempt_exam = 'no';
                                                                    }
                                                                }

                                                            @endphp

                                                            @if ($show_attempt_exam == 'yes' && $attempt_allowed == 'yes')
                                                                <a target="_blank"
                                                                    href="{{ route('online_exam.index', ['questionpaper_id' => $quespaper->id]) }}"
                                                                    class="btn btn-info btn-outline btn m-r-5">Attempt Exam</a>
                                                            @elseif ($show_attempt_exam == 'no')
                                                                <div class="btn btn-danger m-r-5" style="pointer-events: none;">Closed Exam</div>
                                                            @else
                                                                <div class="btn btn-warning m-r-5" style="pointer-events: none;">No more attempts</div>
                                                            @endif
                                                        </div>
                                                    @endif

                                                </td>
                                                @if ($show_block == 'YES')
                                                    <td>
                                                        <div class="d-flex align-items-center justify-content-end">
                                                            <a class="btn btn-outline-success"
                                                               href="{{ route('question_paper.edit', ['question_paper' => $quespaper->id]) }}"
                                                               onclick="return edit_questionpaper({{ $quespaper->id }},'{{ $quespaper->exam_type }}');">
                                                                <i class="ti-pencil-alt"></i>
                                                            </a>
                                                            <form class="d-inline"
                                                                  action="{{ route('question_paper.destroy', $quespaper->id) }}"
                                                                  method="post" class="btn btn-outline-danger btn-sm"
                                                                  onsubmit="return delete_questionpaper({{ $quespaper->id }},'{{ $quespaper->exam_type }}');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-outline-danger">
                                                                    <i class="ti-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="20">
                                                <center>No records</center>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@include('includes.lmsfooterJs')
<script>
    $(document).ready(function () {
        var table = $('#subject_list').DataTable({
            ordering: false,
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Exam List',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Exam List'},
                {extend: 'excel', text: ' EXCEL', title: 'Exam List'},
                {extend: 'print', text: ' PRINT', title: 'Exam List'},
                'pageLength'
            ],
        });
        //table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');

        $('#subject_list thead tr').clone(true).appendTo('#subject_list thead');
        $('#subject_list thead tr:eq(1) th').each(function (i) {
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
<script>
    $(document).ready(function() {
        $("#standard").change(function() {
            var std_id = $("#standard").val();
            var path = "{{ route('ajax_StandardwiseSubject') }}";
            $('#subject').find('option').remove().end().append(
                '<option value="">Select Subject</option>').val('');
            $.ajax({
                url: path,
                data: 'std_id=' + std_id,
                success: function(result) {
                    for (var i = 0; i < result.length; i++) {
                        $("#subject").append($("<option></option>").val(result[i][
                            'subject_id'
                        ]).html(result[i]['display_name']));
                    }
                }
            });
        })
    });

    function delete_questionpaper(id, exam_type) {
        if (confirm('Are you sure?')) {
            var error = 1;
            var path = "{{ route('ajax_questionpaperDependencies') }}";
            $.ajax({
                url: path,
                data: "id=" + id + '&exam_type=' + exam_type,
                async: false,
                success: function(result) {

                    if (result != 0) {
                        alert("You cannot delete Exam.Exam is having dependencies in Other Module");
                        error = 1;
                    } else {
                        error = 0;
                    }
                },
                failure: function(er) {
                    alert('error' + er);
                    error = 1;
                }
            });
        } else {
            error = 1;
        }

        if (error == 1) {
            return false;
        } else {
            return true;
        }
    }

    function edit_questionpaper(id, exam_type) {

        var error = 1;
        var path = "{{ route('ajax_questionpaperDependencies') }}";
        $.ajax({
            url: path,
            data: "id=" + id + '&exam_type=' + exam_type,
            async: false,
            success: function(result) {

                if (result != 0) {
                    alert("You cannot edit Exam as Exam is already attempted");
                    error = 1;
                } else {
                    error = 0;
                }
            },
            failure: function(er) {
                alert('error' + er);
                error = 1;
            }
        });

        if (error == 1) {
            return false;
        } else {
            return true;
        }
    }
</script>
@include('includes.footer')
@endsection
