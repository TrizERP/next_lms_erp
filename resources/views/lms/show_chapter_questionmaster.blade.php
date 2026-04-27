@extends('lmslayout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Create Question Bank</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{ route('course_master.index') }}">LMS</a></li>
                        <li class="breadcrumb-item"><a
                                href="{{ route('chapter_master.index', ['standard_id' => $data['breadcrum_data']->standard_id, 'subject_id' => $data['breadcrum_data']->subject_id]) }}">{{ $data['breadcrum_data']->subject_name }}</a>
                        </li>
                        <li class="breadcrumb-item"><a
                                href="{{ route('topic_master.index', ['id' => $data['breadcrum_data']->chapter_id]) }}">{{ $data['breadcrum_data']->chapter_name }}</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Create Question Bank</li>
                    </ol>
                </nav>
            </div>
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4 text-md-right">
                <a href="{{ route('question_master.create', ['chapter_id' => $_REQUEST['chapter_id'],'standard_id'=>$_REQUEST['standard_id']]) }}"
                    class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Question</a>
                <br/>
                <a href="#" id="openAssessmentPreview" class="btn btn-success add-new">
                    <i class="fa fa-rocket"></i> AI</a>
            </div>
        </div>

        <!-- Display Academic Section, Standard, Subject, Chapter -->
        <div class="row mb-3">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3"
                                <strong>Standard:</strong> {{ $data['breadcrum_data']->standard_name ?? 'N/A' }}
                            </div>
                            <div class="col-md-3">
                                <strong>Subject:</strong> {{ $data['breadcrum_data']->subject_name ?? 'N/A' }}
                            </div>
                            <div class="col-md-6">
                                <strong>Chapter:</strong> {{ $data['breadcrum_data']->chapter_name ?? 'N/A' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="white-box">
                <div class="panel-body">
                    @if ($sessionData = Session::get('data'))
                        <div
                            class="@if ($sessionData['status_code'] == 1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{{ $sessionData['message'] }}</strong>
                        </div>
                    @endif
                    <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                <table id="example" class="table table-striped table-bordered" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th>Sr. No.</th>
                                            <th>Question</th>
                                            <th>Question Type</th>
                                            <th>Mapping Type</th>
                                            <th>Multiple Answer</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if (count($data['data']) > 0)
                                            @php $i = 1;@endphp
                                            @foreach ($data['data'] as $key => $quesdata)
                                            @php
                                                $map_type = explode('||', $quesdata->type_name);
                                                $j =1;
                                            @endphp
                                                <tr>
                                                    <td><input type="checkbox" name="select_que[]"
                                                            id="{{ $quesdata->id }}" value="{{ $quesdata->id }}">
                                                    </td>
                                                    <td>@php echo $i++;@endphp</td>
                                                    <td>{!! $quesdata->question_title !!}</td>
                                                    <td>{{ ucwords($quesdata->question_type) }}</td>
                                                    <td>
                                                        <ul>
                                                        @foreach($map_type as $map)
                                                        @if(!empty($map))
                                                           <li>{{ $j++.") ".$map }}</li>
                                                        @endif
                                                        @endforeach
                                                        </ul>
                                                    </td>
                                                    <td>
                                                        @if ($quesdata->multiple_answer == 1)
                                                            Yes
                                                        @else
                                                            No
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($quesdata->status == 1)
                                                            Show
                                                        @else
                                                            Hide
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($quesdata->attempt_question==0)
                                                        <div class="d-flex align-items-center justify-content-end">
                                                            <a class="btn btn-outline-success"
                                                               href="{{ route('question_master.edit', $quesdata->id) }}">
                                                                <i class="ti-pencil-alt"></i>
                                                            </a>
                                                            <form class="d-inline"
                                                                  action="{{ route('question_master.destroy', $quesdata->id) }}"
                                                                  method="post"
                                                                  onsubmit="return delete_question({{ $quesdata->id }});">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-outline-danger"><i
                                                                        class="ti-trash"></i></button>
                                                            </form>
                                                        </div>
                                                         @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="8">
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
    </div>
</div>

@include('includes.lmsfooterJs')
@include('lms.assessment_preview')

<!-- MathJax -->
<script src="//cdn.mathjax.org/mathjax/latest/MathJax.js">
    MathJax.Hub.Config({
        extensions: ["mml2jax.js"],
        jax: ["input/MathML", "output/HTML-CSS"]
    });
</script>

<!-- DataTable Script - Make sure this is after jQuery -->
<script>
$(document).ready(function() {
    // Check if DataTable is available
    if (typeof $.fn.DataTable !== 'undefined') {
        console.log('DataTable is available');
        
        var table = $('#example').DataTable({
            select: true,
            sort: false,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Question Lists',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Question Lists'},
                {extend: 'excel', text: ' EXCEL', title: 'Question Lists'},
                {extend: 'print', text: ' PRINT', title: 'Question Lists'},
                'pageLength'
            ],
            initComplete: function() {
                // Add search inputs after table is initialized
                this.api().columns().every(function() {
                    var column = this;
                    var header = $(column.header());
                    
                    // Skip adding search to first column (checkbox column)
                    // if (column.index() > 0) {
                    //     var title = header.text();
                    //     header.html(title + '<br/><input type="text" placeholder="Search" style="width:100%;" />');
                        
                    //     $('input', header).on('keyup change', function() {
                    //         if (column.search() !== this.value) {
                    //             column.search(this.value).draw();
                    //         }
                    //     });
                    // }
                });
            }
        });
    } else {
        console.error('DataTable is not loaded. Check your CDN links.');
        alert('DataTable library failed to load. Please refresh the page.');
    }
    
    // Standard change handler
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
    });

    // Multi delete
    $("#multiDelete").click(function() {
        var val = [];
        $(':checkbox:checked').each(function(i) {
            val[i] = $(this).val();
        });
        
        if (val.length === 0) {
            alert('Please select at least one question to delete');
            return;
        }
        
        if (confirm('Are you sure you want to delete selected questions?')) {
            $.ajax({
                url: "{{ route('multi_delete_questions') }}",
                data: {
                    'question_ids': val
                },
                dataType: 'json',
                success: function(result) {
                    if (result.status_code == 1) {
                        alert(result.message);
                        location.reload();
                    }
                },
                error: function(er) {
                    alert('Error: ' + er.responseText);
                }
            });
        }
    });
});

function delete_question(question_id) {
    if (confirm('Are you sure?')) {
        var error = 1;
        var path = "{{ route('ajax_questionDependencies') }}";
        $.ajax({
            url: path,
            data: "question_id=" + question_id,
            async: false,
            success: function(result) {
                if (result > 0) {
                    alert("You cannot delete Question. Question is having dependencies in Other Module");
                    error = 1;
                } else {
                    error = 0;
                }
            },
            error: function(er) {
                alert('Error: ' + er.responseText);
                error = 1;
            }
        });
    } else {
        error = 1;
    }

    return error !== 1;
}
</script>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.6/css/buttons.dataTables.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/1.5.6/js/dataTables.buttons.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/1.5.6/js/buttons.html5.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/1.5.6/js/buttons.print.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

@include('includes.footer')
@endsection