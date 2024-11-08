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
            @if(!isset($data))
            Add Portfolio
            @else
            Edit Portfolio
            @endif
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Portfolio</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="container-fluid mb-5">
        <div class="card border-0">
            <div class="card-body">
                <form action="@if (isset($data['portfolio_data']))
                          {{ route('lmsPortfolio.update', $data['portfolio_data']['id']) }}
                          @else
                          {{ route('lmsPortfolio.store') }}
                          @endif" enctype="multipart/form-data" method="post">

                    @if(!isset($data['portfolio_data']))
                    {{ method_field("POST") }}
                    @else
                    {{ method_field("PUT") }}
                    @endif
                    @csrf

                    @if($data['action']  == 'coursewise' && !isset($data['portfolio_data']) )
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
                    @endif

                    @php
                    $title_readonly = "";
                    if($data['action']  == 'coursewise')
                    {
                        $title_readonly = "readonly";
                    }
                    @endphp
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="title">Title</label>
                                <input {{$title_readonly}} type="text" class="form-control" id="title" name="title" placeholder="Title" value="@if(isset($data['portfolio_data']['title'])){{$data['portfolio_data']['title']}}@endif" required>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="type" id="type" value="{{$data['action']}}">

                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="description">Description</label>

                                <textarea id="description" name="description" class="form-control summernote" rows="3" cols="100" >
                                @if(isset($data['portfolio_data']['description'])){{$data['portfolio_data']['description']}}@endif
                                </textarea>

                                 <!-- <div class="summernote">
                                <h3>Default Summernote</h3> </div>     -->
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="title">Upload</label>
                                <input type="file" id='filename' name="filename" class="form-control">
                                @if( isset($data['portfolio_data']['file_name']) && $data['portfolio_data']['file_name'] != "" )
                                <a target="_blank" href="../../../storage/lms_portfolio/{{$data['portfolio_data']['file_name']}}">{{$data['portfolio_data']['file_name']}}</a>
                                <input type="hidden" name="hid_filename" id="hid_filename" value="{{$data['portfolio_data']['file_name']}}">
                                @endif
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
