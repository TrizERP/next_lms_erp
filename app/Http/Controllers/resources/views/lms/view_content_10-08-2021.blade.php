{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<!-- Content main Section -->
                <div class="content-main flex-fill">
                    <h1 class="h4 mb-3">LMS</h1>
                    <nav aria-label="breadcrumb">

                    </nav>

                    <div class="container-fluid mb-5">
                        <div class="row">
                            <div class="col-md-12 mb-3 mb-md-4">
                                <div class="video-box mb-4">
                                    <div class="embed-responsive embed-responsive-16by9">
                                        <iframe autoplay="false" class="embed-responsive-item" src="../../../storage{{$data['content_data']['file_folder']}}/{{$data['content_data']['filename']}}" allowfullscreen></iframe>

                                    </div>
                                </div>
                                <div class="video-title h4 mb-3">{{$data['content_data']['description']}}</div>
                                <div class="course-box p-0">
                                    <div class="course-bottom course-bottom justify-content-start p-0">
                                        <!-- <div class="single-item mr-2">
                                            <a href="#">
                                                <i class="mdi mdi-heart-outline"></i> Bookmark
                                            </a>
                                        </div>
                                        <div class="single-item mr-2">
                                            <a href="#">
                                                <i class="mdi mdi-chat-outline"></i> 5 Comments
                                            </a>
                                        </div> -->
                                    </div>
                                </div>
                            </div>
                            <!-- <div class="col-md-3 mb-3 mb-md-4">
                                <div class="h4 mb-3">Related videos</div>
                                <div class="related-video">
                                    <div class="video-box mb-2">
                                        <div class="video-img-box">
                                            <div class="video-img">
                                                <img src="assets/images/slide1.jpg" alt="">
                                            </div>
                                            <a href="single-video.html" class="view-box">
                                                <i class="mdi mdi-eye-outline"></i>
                                            </a>
                                        </div>
                                        <div class="video-details">
                                            <a href="single-video.html" class="video-title">Classifications of Plants</a>
                                            <div class="d-flex justify-content-between">
                                                <div class="video-dur">00:01:45</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="video-box mb-2">
                                        <div class="video-img-box">
                                            <div class="video-img">
                                                <img src="assets/images/slide1.jpg" alt="">
                                            </div>
                                            <a href="single-video.html" class="view-box">
                                                <i class="mdi mdi-eye-outline"></i>
                                            </a>
                                        </div>
                                        <div class="video-details">
                                            <a href="single-video.html" class="video-title">Classifications of Plants</a>
                                            <div class="d-flex justify-content-between">
                                                <div class="video-dur">00:01:45</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="video-box mb-2">
                                        <div class="video-img-box">
                                            <div class="video-img">
                                                <img src="assets/images/slide1.jpg" alt="">
                                            </div>
                                            <a href="single-video.html" class="view-box">
                                                <i class="mdi mdi-eye-outline"></i>
                                            </a>
                                        </div>
                                        <div class="video-details">
                                            <a href="single-video.html" class="video-title">Classifications of Plants</a>
                                            <div class="d-flex justify-content-between">
                                                <div class="video-dur">00:01:45</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div> -->
                        </div>
                    </div>

                </div>
@include('includes.footerJs')
@include('includes.footer')
@endsection
