{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<link href="{{ asset('/plugins/bower_components/summernote/dist/summernote.css') }}" rel="stylesheet" />

<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="row">
        <div class="col-md-6">
            <h1 class="h4 mb-3">
            @if(!isset($data['doubt_data']))
            Add Doubts
            @else
            Edit Doubts
            @endif
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Doubts</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="container-fluid mb-5">
        <div class="card border-0">
            <div class="card-body">
                <form action="@if (isset($data['doubt_data']))
                      {{ route('lmsDoubt.update', $data['doubt_data']['id']) }}
                      @else
                      {{ route('lmsDoubt.store') }}
                      @endif" enctype="multipart/form-data" method="post">

                    @if(!isset($data['doubt_data']))
                    {{ method_field("POST") }}
                    @else
                    {{ method_field("PUT") }}
                    @endif
                    @csrf

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="description">Subject</label>
                                <select id="subject" name="subject" class="cust-select form-control" required>
                                    <option value="">Select Subject</option>
                                    @if(isset($data['subject_arr']))
                                        @foreach($data['subject_arr'] as $key => $value)
                                        <option value="{{$value['subject_id']}}">{{$value['display_name']}}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="description">Chapter</label>
                                <select id="chapter" name="chapter"  class="cust-select form-control">
                                    <option value="">Select Chapter</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="description">Topic</label>
                                <select id="topic" name="topic" class="cust-select form-control">
                                    <option value="">Select Topic</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="description">Title</label>
                                <input readonly type="text" class="form-control" id="title" name="title" placeholder="Title" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control summernote"  id="description" name="description"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="title">Upload</label>
                                <input type="file" id='filename' name="filename" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">Visibility</label>
                                <div class="radio-list">
                                    <label class="radio-inline p-0">
                                        <div class="radio radio-success">
                                            <input type="radio" name="visibility" id="private" value="private" required>
                                            <label for="private">Private</label>
                                        </div>
                                    </label>
                                    <label class="radio-inline">
                                        <div class="radio radio-success">
                                            <input type="radio" name="visibility" id="public" value="public" required>
                                            <label for="public">Public</label>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-primary" type="submit">Save</button>

                </form>
            </div>
        </div>
    </div>
</div>

@include('includes.lmsfooterJs')
<script src="{{asset('/plugins/bower_components/summernote/dist/summernote.min.js')}}"></script>

<script>

$( document ).ready(function() {

    $('.summernote').summernote({
        height: 200, // set editor height
        minHeight: null, // set minimum height of editor
        maxHeight: null, // set maximum height of editor
        focus: false // set focus to editable area after initializing summernote
    });

    $("#subject").change(function(){
        var sub_id = $("#subject").val();
        var path = "{{ route('ajax_LMS_SubjectwiseChapter') }}";
        $('#chapter').find('option').remove().end().append('<option value="">Select Chapter</option>').val('');
        $.ajax({url: path,data:'sub_id='+sub_id, success: function(result){
            for(var i=0;i < result.length;i++){
                $("#chapter").append($("<option></option>").val(result[i]['id']).html(result[i]['chapter_name']));
            }
        }
        });
        $("#title").val($("#subject option:selected").text());
    })

    $("#chapter").change(function(){
        var chapter_id = $("#chapter").val();
        var path = "{{ route('ajax_LMS_ChapterwiseTopic') }}";
        $('#topic').find('option').remove().end().append('<option value="">Select Topic</option>').val('');
        $.ajax({url: path,data:'chapter_id='+chapter_id, success: function(result){
            for(var i=0;i < result.length;i++){
                $("#topic").append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
            }
        }
        });
        title_val = $("#title").val() + ' / ' + $("#chapter option:selected").text();
        $("#title").val(title_val);
    })

    $("#topic").change(function(){
        title_val = $("#title").val() + ' / ' + $("#topic option:selected").text();
        $("#title").val(title_val);
    })

});


</script>
@include('includes.footer')
@endsection
