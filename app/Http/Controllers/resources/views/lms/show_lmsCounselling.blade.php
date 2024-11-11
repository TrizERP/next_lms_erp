{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}

@extends('layout')
@section('container')
<!-- Content main Section -->
<div class="content-main flex-fill">
	<div class="container-fluid mb-5 mt-4">
		<div class="card border-0">
			<div class="card-body">
				<div class="h4">Counselling</div>
				<div class="border rounded mb-3 mb-md-4 mt-3">
					<div class="cta-box bg-light py-4">
						<div class="row justify-content-center text-center">
							<div class="col-lg-6">
								<div class="h3 mb-3">Take a Free Personality Test!</div>
								<p>Today, the art of talking therapies such as counselling, are used to help people come to terms with many problems they are facing, with an ultimate aim of overcoming them.</p>
								<a href="#" class="btn btn-primary">Take the Test</a>
							</div>
						</div>
					</div>
				</div>
				<div class="h4 mb-3">Suggested Short Courses in Counselling</div>
				<div class="row mb-4">
					<div class="col-md-6 col-lg-3 mb-3">
						<div class="card">
							<div class="card-body">
								<div class="badge badge-info badge-outlined mb-3">Short Course</div>
								<div class="h5 mb-3">Face-to-face</div>
								<p class="mb-0">This is when you make an appointment with a counsellor to see them in person, usually at their practice. Face-to-face sessions are one of the more popular therapy formats because they provide an opportunity for you to react to any emotions that arise there and then. </p>
							</div>
							<div class="d-flex align-items-center border-top p-3">
								<img src="../../../storage/assets/images/lms-head.png" width="40" alt="">
								<div class="h6 mb-0 ml-2">What to expect from counselling?</div>
							</div>
						</div>
					</div>
					<div class="col-md-6 col-lg-3 mb-3">
						<div class="card">
							<div class="card-body">
								<div class="badge badge-info badge-outlined mb-3">Short Course</div>
								<div class="h5 mb-3">Individual or group</div>
								<p class="mb-0">You may choose to see a counsellor by yourself, or if you prefer you could join a counselling group with people experiencing similar issues. Going to a group counselling session can be helpful if you want to discuss your issues with people who are going through similar.</p>
							</div>
							<div class="d-flex align-items-center border-top p-3">
								<img src="../../../storage/assets/images/lms-head.png" width="40" alt="">
								<div class="h6 mb-0 ml-2">Why are you seeking counselling?</div>
							</div>
						</div>
					</div>
					<div class="col-md-6 col-lg-3 mb-3">
						<div class="card">
							<div class="card-body">
								<div class="badge badge-info badge-outlined mb-3">Short Course</div>
								<div class="h5 mb-3">Telephone counselling</div>
								<p class="mb-0">For some, telephone counselling offers a helpful alternative to face-to-face counselling. This involves talking to your counsellor over the phone instead of in person. This form of counselling can be particularly useful for those too busy to attend face-to-face sessions.</p>
							</div>
							<div class="d-flex align-items-center border-top p-3">
								<img src="../../../storage/assets/images/lms-head.png" width="40" alt="">
								<div class="h6 mb-0 ml-2">What is your current personal history?</div>
							</div>
						</div>
					</div>
					<div class="col-md-6 col-lg-3 mb-3">
						<div class="card">
							<div class="card-body">
								<div class="badge badge-info badge-outlined mb-3">Short Course</div>
								<div class="h5 mb-3">Online counselling</div>
								<p class="mb-0"> Some people prefer not to physically speak to a counsellor at all, utilising technology and emailing their counsellor instead. This form of counselling allows you think through what you wish to discuss, and many find the act physically writing their issues down cathartic.</p>
							</div>
							<div class="d-flex align-items-center border-top p-3">
								<img src="../../../storage/assets/images/lms-head.png" width="40" alt="">
								<div class="h6 mb-0 ml-2">What symptoms are you experiencing?</div>
							</div>
						</div>
					</div>
				</div>
				<div class="text-center">
					<a href="#" class="btn btn-primary">View all Short Courses in Counselling</a>
				</div>
			</div>
		</div>
    </div>

</div>


@include('includes.footerJs')
@include('includes.footer')
@endsection
