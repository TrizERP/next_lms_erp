{{--
@include('includes.lmsheadcss')
--}}
@extends('lmslayout')
@section('container')
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
{{--@include('includes.header')
@include('includes.sideNavigation')--}}

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
           <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Add LMS Mappings
                    @if(isset($data['chapter_topic_data']['chapter_topic_name']))
                        <span style="color:#26dad2;"><b>for {{$data['chapter_topic_data']['chapter_topic_name']}}</b></span>
                    @endif
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Add LMS Mappings</li>
                    </ol>
                </nav>
            </div>
        </div>
        @if ($message = Session::get('success'))
        <div class="alert alert-success alert-block">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <strong>{{ $message }}</strong>
        </div>
        @endif
        <div class="row">
            <div class="col-md-8 mb-3">
                <form action="{{ route('lmsmapping.store') }}" method="post" enctype='multipart/form-data'>
                    {{ method_field("POST") }}
                    @csrf
                    <div class="card">
                        <div class="card-body">
                            <div class="form-group">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="title">Mapping Type</label>
                                        @if(isset($data['chapter_topic_data']['action']) && $data['chapter_topic_data']['action'] == "chapter")
                                            <input type="hidden" name="hid_chapter_id" id="hid_chapter_id" value="{{$data['chapter_topic_data']['chapter_topic_id']}}">
                                            <select name="mapping_type" id="mapping_type" required class="cust-select form-control mb-0 border-0">
                                                <option value="Learning Outcome - {{$data['chapter_topic_data']['chapter_topic_name']}}">Learning Outcome - {{$data['chapter_topic_data']['chapter_topic_name']}}</option>
                                                <option value="Learning Indicator - {{$data['chapter_topic_data']['chapter_topic_name']}}">Learning Indicator - {{$data['chapter_topic_data']['chapter_topic_name']}}</option>
                                            </select>
                                        @elseif(isset($data['chapter_topic_data']['action']) && $data['chapter_topic_data']['action'] == "topic")
                                            <input type="hidden" name="hid_topic_id" id="hid_topic_id" value="{{$data['chapter_topic_data']['chapter_topic_id']}}">
                                            <select name="mapping_type" id="mapping_type" required class="cust-select form-control mb-0 border-0">
                                                <option value="Learning Outcome - {{$data['chapter_topic_data']['chapter_topic_name']}}">Learning Outcome - {{$data['chapter_topic_data']['chapter_topic_name']}}</option>
                                                <option value="Learning Indicator - {{$data['chapter_topic_data']['chapter_topic_name']}}">Learning Indicator - {{$data['chapter_topic_data']['chapter_topic_name']}}</option>
                                            </select>
                                        @else
                                            <input type="text" id='mapping_type' name="mapping_type" value="@if(isset($data['lomaster_data']['title'])){{$data['lomaster_data']['title']}}@endif" class="form-control" required>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="form-group addButtonCheckbox mb-0 w-100">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="topicType2">Mapping Value</label>
                                            <input type="text" id='mapping_value[]' name="mapping_value[]" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="javascript:void(0);" onclick="addNewRow();" class="d-inline btn btn-success btn-sm mr-2"><i class="mdi mdi-plus"></i></a>
                                    </div>
                                </div>
                            </div>
                            @php
                            if(isset($_REQUEST['preload_lms'])){
                                $readonly="pointer-events: none";
                            }
                            @endphp
                            <div class="form-group">
                                <input type="submit" name="submit" value="Save" class="btn btn-success" style="{{$readonly ?? ''}}">
                            </div>
                        </div>
                    </div>


                    <!--  <div class="addButtonCheckbox">
                        <div class="col-md-2 form-group">
                            <label for="title">Mapping Value</label>
                            <input type="text" id='mapping_value[]' name="mapping_value[]" class="form-control" required>

                            <a href="javascript:void(0);" onclick="addNewRow();" class="d-inline btn btn-success btn-sm mr-2"><i class="mdi mdi-plus"></i></a>
                        </div>
                    </div>  -->

                </form>
            </div>
        </div>
    </div>
</div>

@include('includes.lmsfooterJs')
<script>
function addNewRow(){
    var html = '';
    html += '<div class="clearfix"></div><div class="form-group addButtonCheckbox mb-0 w-100"><div class="row align-items-center">';

    html += '<div class="col-md-6"><label for="title">Mapping Value</label><input type="text" id="mapping_value[]" name="mapping_value[]" class="form-control" required></div>';
    html += '<div class="col-md-4"><a href="javascript:void(0);" onclick="removeNewRow();" class="d-inline btn btn-danger btn-sm"><i class="mdi mdi-minus"></i></a></div></div></div>';
    $('.addButtonCheckbox:last').after(html);
}
function removeNewRow() {
    $(".addButtonCheckbox:last" ).remove();
}
</script>
@include('includes.footer')
@endsection
