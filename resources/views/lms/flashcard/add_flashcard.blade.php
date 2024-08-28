{{--@include('includes.lmsheadcss')--}}
@extends('lmslayout')
@section('container')
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
<style>
.tooltip-inner {
    max-width: 1100px !important;
}
</style>
{{--@include('includes.header')
@include('includes.sideNavigation')--}}
<!-- Content main Section -->
<div id="page-wrapper">
    <div class="container-fluid mb-5">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Add Flash Card</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('chapter_master.index',['standard_id'=>$data['breadcrum_data']->standard_id ?? '' ,'subject_id'=>$data['breadcrum_data']->subject_id ?? '' ]) }}">{{$data['breadcrum_data']->subject_name ?? '' }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('topic_master.index',['id'=>$data['breadcrum_data']->chapter_id ?? '' ]) }}">{{$data['breadcrum_data']->chapter_name ?? '' }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('topic_master.index',['id'=>$data['breadcrum_data']->chapter_id ?? '' ]) }}">{{$data['breadcrum_data']->topic_name ?? '' }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Add Flash Card</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="card border-0">
            <div class="card-body">
                <form action="@if (isset($data['flashcard_data']))
                          {{ route('lms_flashcard.update', $data['flashcard_data']['id']) }}
                          @else
                          {{ route('lms_flashcard.store') }}
                          @endif" enctype="multipart/form-data" method="post">
                            @if(!isset($data['flashcard_data']))
                            {{ method_field("POST") }}
                            @else
                            {{ method_field("PUT") }}
                            @endif
                        @csrf

                    <input type="hidden" name="standard_id" id="standard_id" value="{{$data['breadcrum_data']->standard_id ?? '' }}">
                    <input type="hidden" name="subject_id" id="subject_id" value="{{$data['breadcrum_data']->subject_id ?? '' }}">
                    <input type="hidden" name="chapter_id" id="chapter_id" value="{{$data['breadcrum_data']->chapter_id ?? '' }}">
                    <input type="hidden" name="topic_id" id="topic_id" value="{{$data['breadcrum_data']->topic_id ?? '' }}">
                   <input type="hidden" name="content_id" id="content_id" value="@if( isset($data['flashcard_data']['content_id'])){{$data['flashcard_data']['content_id']}} @else {{$_REQUEST['content_id']}} @endif">

                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="topicType">Title</label>
                                <input type="text" name="title" id="title" value="@if( isset($data['flashcard_data']['title'])){{$data['flashcard_data']['title']}}@endif" class="form-control"/>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="topicType">Front Text</label>
                                <textarea name="front_text" id="front_text" contenteditable="true">
                                @if( isset($data['flashcard_data']['front_text']))
                                {{$data['flashcard_data']['front_text']}}
                                @endif
                                </textarea>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="topicType">Back Text</label>
                                <textarea name="back_text" id="back_text" contenteditable="true">
                                @if( isset($data['flashcard_data']['back_text']))
                                {{$data['flashcard_data']['back_text']}}
                                @endif
                                </textarea>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="status">Show</label>
                                <input type="checkbox" id="status" name="status" value="1"
                                @if( isset($data['flashcard_data']['status']) && $data['flashcard_data']['status'] == 1)
                                checked
                                @elseif(!isset($data['flashcard_data']))
                                checked
                                @endif
                                >
                            </div>
                        </div>

                    </div>
                    @php
                    if(isset($_REQUEST['preload_lms'])){
                        $readonly = "pointer-events:none";
                    }
                    @endphp
                    <button class="btn btn-primary" type="submit" style="{{$readonly ?? ''}}">Submit</button>
                </form>
            </div>
        </div>
    </div>
</div>
@include('includes.lmsfooterJs')
<script src="{{ asset("/ckeditor_wiris/ckeditor4/ckeditor.js") }}"></script>
<script>
    CKEDITOR.config.toolbar_Full =
        [
        { name: 'document', items : [ 'Source'] },
        { name: 'clipboard', items : [ 'Cut','Copy','Paste','-','Undo','Redo' ] },
        { name: 'editing', items : [ 'Find'] },
        { name: 'basicstyles', items : [ 'Bold','Italic','Underline'] },
        { name: 'paragraph', items : [ 'JustifyLeft','JustifyCenter','JustifyRight'] }
        ];
    CKEDITOR.config.height = '40px';

    CKEDITOR.plugins.addExternal('divarea', '../examples/extraplugins/divarea/', 'plugin.js');
    CKEDITOR.plugins.addExternal('sharedspace', '../examples/extraplugins/sharedspace/', 'plugin.js');
    CKEDITOR.plugins.addExternal('filebrowser', '../examples/extraplugins/filebrowser/', 'plugin.js');
    CKEDITOR.config.removePlugins = 'maximize';
    CKEDITOR.config.removePlugins = 'resize';
    CKEDITOR.config.sharedSpaces = { top: 'toolbar1'};
    CKEDITOR.replace('front_text', {
         extraPlugins: 'filebrowser,divarea,sharedspace,ckeditor_wiris',
         language: 'en',
         filebrowserUploadUrl: "{{route('uploadimage',['_token' => csrf_token() ])}}",
         filebrowserUploadMethod: 'form'
    });

    CKEDITOR.replace('back_text', {
         extraPlugins: 'filebrowser,divarea,sharedspace,ckeditor_wiris',
         language: 'en',
         filebrowserUploadUrl: "{{route('uploadimage',['_token' => csrf_token() ])}}",
         filebrowserUploadMethod: 'form'
    });
</script>

@include('includes.footer')
@endsection
