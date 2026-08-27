{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
{{--
@include('includes.sideNavigation')
--}}
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Create Question Bank</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        @if($data['breadcrum_data'])
                        <li class="breadcrumb-item"><a
                                href="{{ route('chapter_master.index',['standard_id'=>$data['breadcrum_data']->standard_id,'subject_id'=>$data['breadcrum_data']->subject_id]) }}">{{$data['breadcrum_data']->subject_name}}</a>
                        </li>
                        <li class="breadcrumb-item"><a
                                href="{{ route('topic_master.index',['id'=>$data['breadcrum_data']->chapter_id]) }}">{{$data['breadcrum_data']->chapter_name}}</a>
                        </li>
                        <li class="breadcrumb-item"><a
                                href="{{ route('topic_master.index',['id'=>$data['breadcrum_data']->chapter_id]) }}">{{$data['breadcrum_data']->topic_name ?? ''}}</a>
                        </li>
                        @endif
                        <li class="breadcrumb-item active" aria-current="page">Create Question Bank</li>
                    </ol>
                </nav>
            </div>
            <div class="col-md-3 mb-4 text-md-right">
                <a href="{{ route('question_master.create',['chapter_id' => $_REQUEST['chapter_id'],'topic_id' => $_REQUEST['topic_id'],'standard_id'=>$_REQUEST['standard_id']]) }}"
                   class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Question</a>
                   <br/>
                <a href="#" id="openAssessmentPreview" class="btn btn-info add-new">
                    <i class="fa fa-rocket"></i> AI</a>
            </div>
        </div>

        <div class="row">
            <div class="white-box">
                <div class="panel-body">
                    @if ($sessionData = Session::get('data'))
                        <div
                            class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{{ $sessionData['message'] }}</strong>
                        </div>
                    @endif
                    @if(count($data['data']) > 0)
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f5f7fa; max-width: 600px; margin: 20px auto; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); font-family: Arial, sans-serif;">
                                <div style="flex: 3; padding: 10px;">
                                    <span style="font-weight: bold; color: #555;">Academic Section:</span>
                                    <span style="color: #333;">{{ $data['data'][0]->grade_name }}</span>
                                </div>
                                <div style="flex: 1; padding: 10px;">
                                    <span style="font-weight: bold; color: #555;">Standard:</span>
                                    <span style="color: #333;">{{ $data['data'][0]->standard_name }}</span>
                                </div>
                                <div style="flex: 2; padding: 10px;">
                                    <span style="font-weight: bold; color: #555;">Subject:</span>
                                    <span style="color: #333;">{{ $data['data'][0]->subject_name }}</span>
                                </div>
                            </div>
                        @endif
                    <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                        <div class="card">
                            <div class="card-body">
                                <table id="subject_list" class="table table-striped table-bordered" style="width:100%">
                                    <thead>
                                    <tr>
                                        <th>Sr. No.</th>
                                       {{-- <th>Academic Section</th>
                                        <th>Standard</th>
                                        <th>Subject</th>--}}
                                        <th>Chapter</th>
                                        <th>Question</th>
                                        <th>Question Type</th>
                                        <th>Mapping Type</th>
                                        <th>Multiple Answer</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(count($data['data']) > 0)
                                        @php $i = 1;@endphp
                                        @foreach($data['data'] as $key => $quesdata)
                                            @php
                                                $map_type = explode(',', $quesdata->type_name);
                                                $map_value = explode(',', $quesdata->mapping_type);
                                                $j =1;
                                                $edit = 0;
                                                if($quesdata->attempt_question!=0){
                                                    $edit = 1;
                                                }
                                            @endphp
                                            <tr id="question-row-{{ $quesdata->id }}">
                                                <td>@php echo $i++;@endphp</td>
                                               {{-- <td>{{$quesdata->grade_name}}</td>
                                                <td>{{$quesdata->standard_name}}</td>
                                                <td>{{$quesdata->subject_name}}</td>--}}
                                                <td>{{$quesdata->chapter_name}}</td>
                                                <td>{!! html_entity_decode($quesdata->question_title, ENT_QUOTES | ENT_HTML5, 'UTF-8') !!}</td> <!-- added by saroj uma on 02-02-2026 -->
                                                <td>{{ucwords($quesdata->question_type)}}</td>
                                                <td>
                                                    @foreach($map_type as $key => $map)
                                                        @if(!empty($map))
                                                            <span
                                                                style="display: block">{{ $j++ . ") " . $map_value[$key] . " : " . $map }}</span>
                                                            <br>
                                                        @endif
                                                    @endforeach
                                                </td>
                                                <td>
                                                    @if($quesdata->multiple_answer == 1)
                                                        Yes
                                                    @else
                                                        No
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($quesdata->status == 1)
                                                        Show
                                                    @else
                                                        Hide
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center justify-content-end">
                                                        <a class="btn btn-outline-success"
                                                           href="{{ route('question_master.edit',$quesdata->id)}}?question_type={{$edit}}">
                                                            <i class="ti-pencil-alt"></i>
                                                        </a>
                                                        @if($edit==0)
                                                        <button type="button"
                                                                class="btn btn-outline-danger"
                                                                data-delete-url="{{ route('question_master.destroy', $quesdata->id) }}"
                                                                onclick="delete_question({{ $quesdata->id }}, this);"><i
                                                                class="ti-trash"></i></button>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="10">
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

@include('includes.lmsfooterJs')

@include('lms.assessment_preview')

<script src="//cdn.mathjax.org/mathjax/latest/MathJax.js">
    MathJax.Hub.Config({
        extensions: ["mml2jax.js"],
        jax: ["input/MathML", "output/HTML-CSS"]
    });
</script>

<script>
    $(document).ready(function () {
        $("#standard").change(function () {
            var std_id = $("#standard").val();
            var path = "{{ route('ajax_StandardwiseSubject') }}";
            $('#subject').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
            $.ajax({
                url: path, data: 'std_id=' + std_id, success: function (result) {
                    for (var i = 0; i < result.length; i++) {
                        $("#subject").append($("<option></option>").val(result[i]['subject_id']).html(result[i]['display_name']));
                    }
                }
            });
        })
    });

    // Removes the question row from the table without a page reload. Falls back
    // to a plain row removal when the table is not a DataTable.
    function remove_question_row(question_id) {
        var row = document.getElementById('question-row-' + question_id);
        if (!row) {
            return;
        }

        var table = $(row).closest('table');
        if ($.fn.DataTable && $.fn.DataTable.isDataTable(table)) {
            table.DataTable().row(row).remove().draw(false);
        } else {
            $(row).remove();
        }

        if ($('#subject_list tbody tr').length === 0) {
            $('#subject_list tbody').html('<tr><td colspan="10"><center>No records</center></td></tr>');
        }
    }

    function delete_question(question_id, button) {
        if (!confirm('Are you sure?')) {
            return false;
        }

        $(button).prop('disabled', true);

        $.ajax({
            url: "{{ route('ajax_questionDependencies') }}",
            data: {question_id: question_id},
            success: function (result) {
                if (parseInt(result, 10) > 0) {
                    alert("You cannot delete Question.Question is having dependencies in Other Module");
                    $(button).prop('disabled', false);
                    return;
                }

                $.ajax({
                    url: $(button).data('delete-url'),
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _token: "{{ csrf_token() }}",
                        _method: 'DELETE'
                    },
                    success: function (res) {
                        if (res && res.status_code == 1) {
                            remove_question_row(question_id);
                        } else {
                            alert((res && res.message) || 'Unable to delete question.');
                            $(button).prop('disabled', false);
                        }
                    },
                    error: function (xhr) {
                        alert('Unable to delete question. ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : ''));
                        $(button).prop('disabled', false);
                    }
                });
            },
            error: function (er) {
                alert('error' + er.responseText);
                $(button).prop('disabled', false);
            }
        });

        return false;
    }

</script>
@include('includes.footer')
@endsection
