{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div class="content-main flex-fill">
	<!-- <h1 class="h4 mb-3">LMS</h1> -->
	<!-- <nav aria-label="breadcrumb">
		<ol class="breadcrumb bg-transparent p-0">
			<li class="breadcrumb-item"><a href="http://202.47.117.124/triz-lms">Communication</a></li>
			<li class="breadcrumb-item"><a href="http://202.47.117.124/triz-lms/my-courses.html">My Courses</a></li>
			<li class="breadcrumb-item"><a href="http://202.47.117.124/triz-lms/single-course.html">Biology</a></li>
			<li class="breadcrumb-item"><a href="http://202.47.117.124/triz-lms/course-video.html">Getting to Know Plants</a></li>
			<li class="breadcrumb-item active" aria-current="page">Communication</li>
		</ol>
	</nav> -->

    <div class="container-fluid mb-5">
		<div class="course-grid-tab tab-pane fade show active" id="grid" role="tabpanel" aria-labelledby="grid-tab">
			<ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
				<li class="nav-item">
				  <a class="nav-link active" id="notification-tab" data-toggle="pill" href="#notification" role="tab" aria-controls="pills-home" aria-selected="true">Notification</a>
				</li>
				<li class="nav-item">
				  <a class="nav-link" id="message-tab" data-toggle="pill" href="#message" role="tab" aria-controls="pills-profile" aria-selected="false">Message</a>
				</li>
				<li class="nav-item">
				  <a class="nav-link" id="chat-tab" data-toggle="pill" href="#chat" role="tab" aria-controls="chat-tab" aria-selected="false">Chat</a>
				</li>
			</ul>
			<div class="tab-content" id="pills-tabContent">
				<div class="tab-pane fade show active" id="notification" role="tabpanel" aria-labelledby="notification-tab">
					<div class="notification-box card">
						<div class="noti-author status pending">Mrs. Johnson's 3rd Grade</div>
						<div class="noti-title">Spelling Test Tomorrow!</div>
						<div class="noti-des">We will be covering the words from this weeks reading assignment.</div>
						<div class="time">8:55 AM</div>
					</div>
					<div class="notification-box card">
						<div class="noti-author status success">Mrs. Johnson's 3rd Grade</div>
						<div class="noti-title">Spelling Test Tomorrow!</div>
						<div class="noti-des">We will be covering the words from this weeks reading assignment.</div>
						<div class="time">8:55 AM</div>
					</div>
					<div class="notification-box card">
						<div class="noti-author status closed">Mrs. Johnson's 3rd Grade</div>
						<div class="noti-title">Spelling Test Tomorrow!</div>
						<div class="noti-des">We will be covering the words from this weeks reading assignment.</div>
						<div class="time">8:55 AM</div>
					</div>
				</div>
				<div class="tab-pane fade" id="message" role="tabpanel" aria-labelledby="message-tab">Message</div>
				<div class="tab-pane fade" id="chat" role="tabpanel" aria-labelledby="chat-tab">Chat</div>
			</div>
		</div>
    </div>
</div>

@include('includes.lmsfooterJs')
@include('includes.footer')
@endsection
