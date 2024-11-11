{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                Edit LMS Mapping
                </h4>
            </div>
        </div>
        <div class="row" style=" margin-top: 25px;">
            <div class="white-box">
            <div class="panel-body">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('lmsmapping.update', $data['lmsmapping_data']['id']) }}"
                    enctype="multipart/form-data" method="post">
                    {{ method_field("PUT") }}
                    @csrf

                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="title">Mapping Name</label>
                            <input type="text" id='mapping_name' name="mapping_name" value="@if(isset($data['lmsmapping_data']['name'])){{$data['lmsmapping_data']['name']}}@endif" class="form-control" required>
                            <input type="hidden" id='hid_chapter_id' name="hid_chapter_id" value="@if(isset($data['lmsmapping_data']['chapter_id'])){{$data['lmsmapping_data']['chapter_id']}}@endif" class="form-control" required>
                            <input type="hidden" id='hid_topic_id' name="hid_topic_id" value="@if(isset($data['lmsmapping_data']['topic_id'])){{$data['lmsmapping_data']['topic_id']}}@endif" class="form-control" required>
                        </div>
                    </div>
    @php
        if(isset($_REQUEST['preload_lms'])){
           $readonly = "pointer-events: none";
        }
    @endphp
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Update" class="btn btn-success" style="{{$readonly ?? ''}}" >
                        </center>
                    </div>
                    </form>

                </div>
            </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
@endsection
