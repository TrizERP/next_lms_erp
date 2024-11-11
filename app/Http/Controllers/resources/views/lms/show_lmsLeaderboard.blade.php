{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<!-- Content main Section -->
@if(count($data['lb_Data']) <= 0)

<div class="content-main flex-fill">
    <div class="container-fluid mb-4">
    	<div class="row">
    		<div class="col-md-12">
    			<div class="card">
    				No Records
				</div>
			</div>
		</div>
	</div>
</div>
@else

<div class="content-main flex-fill">
    <div class="container-fluid mb-4">
    	<div class="row">
    		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Leader Board</h1>
                 <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item">Engagement</li>
                        <li class="breadcrumb-item">Show Leader Board</li>
                    </ol>
                </nav>
            </div>
    		<div class="col-md-4">
    			<div class="card leaderboard-box">
                    <div class="text-center card-body">
                        <div class="header-box d-flex align-items-center justify-content-between">
							<div class="h4 mb-0">My Points</div>
							<!-- <a href="#">View Details</a> -->
						</div>
                        <div class="board-details">

							<img src="../triz-lms/assets/images/lms-head.png" width="50" alt="">
							<div class="title">{{$data['lb_Data']['total_points']}}</div>
						</div>
                    </div>
    			</div>
    		</div>
    		<div class="col-md-4">
    			<div class="card leaderboard-box">
                    <div class="text-center card-body">
                        <div class="header-box d-flex align-items-center justify-content-between">
							<div class="h4 mb-0">Medals</div>
							<!-- <a href="#">View Details</a> -->
						</div>
                        <div class="board-details">
							<img src="../triz-lms/assets/images/lms-head.png" width="50" alt="">
							<div class="title">Bronze</div>
						</div>
                    </div>
    			</div>
    		</div>
    		<div class="col-md-4">
    			<div class="card leaderboard-box">
                    <div class="text-center card-body">
                        <div class="header-box d-flex align-items-center justify-content-between">
							<div class="h4 mb-0">Class Rank</div>
							<!-- <a href="#">View Details</a> -->
						</div>
                        <div class="board-details">
							<img src="../triz-lms/assets/images/lms-head.png" width="50" alt="">
							<div class="title">#{{$data['lb_Data']['student_rank']}} In Class</div>
						</div>
                    </div>
    			</div>
    		</div>
    	</div>
	</div>
	<div class="container-fluid mb-3">
		<div class="row">
			<div class="col-md-6">
				<div class="card border-0">
					<div class="card-body">
						<div class="h4 pb-3 border-bottom">Points Details</div>
						<div class="radio-group">
							<ul class="lb-list">
								@if(isset($data['lb_Data']['modulewise_points']))
									@foreach($data['lb_Data']['modulewise_points'] as $key => $val)
									<li>
										<div class="h6 text-secondary mb-0 fa">{{ucfirst($key)}} &#{{($val['ICON'])}};</div>
										@php
										$tot = 0;
										foreach($val['DATA'] as $k => $v)
										{
											$tot += $v;
										}
										echo $tot;
										@endphp

									</li>
									@endforeach
								@endif
							</ul>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="card border-0">
					<div class="card-body">
						<div class="h4 pb-3 border-bottom">Class Toppers</div>
						<div class="radio-group">
							<ul class="list-unstyled">
								@if(isset($data['lb_Data']['classdata']))
									@php $i = 1; @endphp
									@foreach($data['lb_Data']['classdata'] as $classkey => $classval)
									<li class="media justify-content-between">
										<div class="media-body d-flex">
											<img class="mr-3" src="../triz-lms/assets/images/lms-head.png" height="50" alt="Generic placeholder image">
											<div class="count">{{$i++}}</div>
											<div class="content">
												<h5 class="mt-0 mb-1">{{$classval['student_name']}}</h5>
												<!-- <span>Diamond</span> -->
											</div>
										</div>
										<div class="total-points">{{$classval['total_points']}} Points</div>
									</li>
									@endforeach
								@endif
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endif

@include('includes.lmsfooterJs')
@include('includes.footer')
@endsection
