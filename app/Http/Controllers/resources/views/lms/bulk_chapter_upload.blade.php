{{--@include('includes.headcss')--}}
@extends('layout')
@section('container')
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
{{--@include('includes.header')
@include('includes.sideNavigation')--}}

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                Bulk Uploading
                </h4>
            </div>
        </div>

            <div class="card">

                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="row">
                    <div class="col-lg-12 col-sm-12 col-xs-12">
    					<div class="col-md-12 form-group">
    						@php
        						$servername = $_SERVER['HTTP_HOST'];
        						$sub_institute_id = Session::get('sub_institute_id');
        						$syear = Session::get('syear');
        						$user_id = Session::get('user_id');

                                $chapter_link = "/excel_upload/bulk_chapter_data.php?sub_institute_id=".$sub_institute_id."&syear=".$syear."&user_id=".$user_id;

                                $topic_link = "/excel_upload/bulk_topic_data.php?sub_institute_id=".$sub_institute_id."&syear=".$syear."&user_id=".$user_id;

        						$question_link = "/excel_upload/bulk_question_data.php?sub_institute_id=".$sub_institute_id."&syear=".$syear."&user_id=".$user_id;

                                $lo_link = "/excel_upload/bulk_lo_data.php?sub_institute_id=".$sub_institute_id."&syear=".$syear."&user_id=".$user_id;

                                $content_link = "/excel_upload/bulk_content_data.php?sub_institute_id=".$sub_institute_id."&syear=".$syear."&user_id=".$user_id;
                                @endphp
    						<a href="{{$chapter_link}}" target="_blank" class="btn btn-info add-new">Bulk Chapter Upload</a>

                            <a href="{{$topic_link}}" target="_blank" class="btn btn-info add-new">Bulk Topic Upload</a>

                            <a href="{{$question_link}}" target="_blank" class="btn btn-info add-new">Bulk Question Upload</a>

                            <a href="{{$lo_link}}" target="_blank" class="btn btn-info add-new">Bulk LO / LI Upload</a>

                            {{-- Bulk content upload : START --}}
                            <a href="{{$content_link}}" target="_blank" class="btn btn-info add-new">Bulk Content Upload</a>
                            {{-- Bulk content upload : END --}}
    					</div>
                    </div>
                </div>


    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
