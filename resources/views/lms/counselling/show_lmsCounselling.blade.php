{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<style>
br{
	display:block !important;
}
</style>
<!-- Content main Section -->
<div class="content-main flex-fill">
	<div class="container-fluid mb-5 mt-4">
		<div class="row">
			<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 mb-4">
	            <h1 class="h4 mb-3">Counselling</h1>
	             <nav aria-label="breadcrumb">
	                <ol class="breadcrumb bg-transparent p-0">
	                    <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
	                    <li class="breadcrumb-item">Engagement</li>
	                    <li class="breadcrumb-item">Show Counselling</li>
	                </ol>
	            </nav>
	        </div>
        </div>
		<div class="card border-0">
			<div class="card-body">
				<div class="h4">Counselling</div>
				<div class="border rounded mb-3 mb-md-4 mt-3">
					<div class="cta-box bg-light py-4">
						<div class="row justify-content-center text-center">
							<div class="col-lg-6">
								<div class="h3 mb-3">Take a Free Personality Test!</div>
								<p>Today, the art of talking therapies such as counselling, are used to help people come to terms with many problems they are facing, with an ultimate aim of overcoming them.</p>
								<!-- <a href="#" class="btn btn-primary">Take the Test</a> -->
							</div>
						</div>
					</div>
				</div>
				<div class="h4 mb-3">Suggested Short Courses in Counselling</div>
				<div class="row mb-4">
					@if(isset($data['counselling_course']))
					@foreach($data['counselling_course'] as $key => $val)
						<div class="col-md-6 col-lg-3 mb-3">
							<div class="card h-100">
								<div class="d-flex align-items-center border-bottom p-3">
									<img src="../../../storage/counselling_course/{{$val['image']}}" width="40" alt="">

									@if($val['title'] == 'MBTI' || strtoupper
									 (session()->get
									 ('user_profile_name')) == 'STUDENT')
									 <div class="h6 mb-0 ml-2 badge
									 badge-info badge-outlined">{{$val
									 ['title']}} </div> @else <div class="h6
									 mb-0 ml-2 badge badge-info
									 badge-outlined" data-toggle="tooltip"
									 title="Add Question"> <a href="{{ route
									 ('lmsCounsellingQuestion.index',
									 ['course_id'=>$val['id']]) }}">{{$val
									 ['title']}}</a> </div> @endif </div>
									 <div class="card-body
									 p-3">
									 <p class="mb-0">{!!$val['description']!!}</p>
									</div>


								<!--START Show Attempted User Data -->
								@if(isset($data['user_data'][$val['id']]))
								<div class="d-flex align-items-center border-bottom p-3">
									<table class="table table-striped">
										<thead>
											<tr>
												<th>Attempt Date</th>
												@if($val['title'] == 'MBTI')
													<th>Result</th>
												@else
													<th>Marks</th>
													<th>Right</th>
													<th>Wrong</th>
												@endif

											</tr>
										</thead>
										<tbody>
											@foreach($data['user_data'][$val['id']] as $key => $user_data)
											@if($val['title'] == 'MBTI')
											<tr>
												<td><span class="text-dark">{{$user_data['exam_date']}}</span></td>
												<td><span class="font-weight-bold text-warning">{{$user_data['obtain_marks']}}</span></td>
											</tr>
											@else
											<tr>
												<td><span class="text-dark">{{$user_data['exam_date']}}</span></td>
												<td><span class="font-weight-bold text-warning">{{$user_data['obtain_marks']}}</span> / <span class="font-weight-bold text-primary">{{$user_data['total_points']}}</span></td>
												<td><span class="font-weight-bold" style="color:#1ce21c;">{{$user_data['total_right']}}</span> / <span class="font-weight-bold text-primary">{{$user_data['total_ques']}}</span></td>
												<td><span class="font-weight-bold text-danger">{{$user_data['total_wrong']}}</span> / <span class="font-weight-bold text-primary">{{$user_data['total_ques']}}</span></td>
											</tr>
											@endif
											@endforeach
										</tbody>
									</table>
								</div>
								@endif
								<!--END Show Attempted User Data -->

								@if($val['title'] == 'MBTI')
									<a href="{{route('lmsMBTIPaper.index',['course_id'=>$val['id']])}}" target="_blank" class="btn btn-primary">Take MBTI Test</a>
								@elseif($val['total_ques'] > 0)
									<a href="{{route('lmsCounsellingExam.index',['course_id'=>$val['id']])}}" target="_blank" class="btn btn-primary">Take the Test</a>
								@else
									<a href="#" class="btn btn-primary">&nbsp;</a>
								@endif
							</div>
						</div>
					@endforeach
					@endif
					<div class="row mb-4">
						<div class="embed-onet-ip"></div>
					</div>
				</div>
			</div>
		</div>
    </div>
</div>

@include('includes.lmsfooterJs')
<script src="https://services.onetcenter.org/embed/ip.js?client=trizinnovation"></script>

<script type="text/javascript">
$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip();
});
</script>
@include('includes.footer')
@endsection
