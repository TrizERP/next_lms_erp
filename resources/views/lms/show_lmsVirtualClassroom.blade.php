{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<div class="content-main flex-fill">
    <div class="container-fluid mb-5">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Virtual Classroom</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item">Engagement</li>
                        <li class="breadcrumb-item">Show Virtual Classroom</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="tab-pane fade show active" id="notification" role="tabpanel" aria-labelledby="notification-tab">
            @if(isset($data['data']))
                @foreach($data['data'] as $key => $virtualdata)
                    @php
                        $date1 = new DateTime(date('Y-m-d H:i:s'));
                        $date2 = $date1->diff(new DateTime($virtualdata->event_datetime));
                        if($date2->d != 0){
                            $date2->h += ($date2->d * 24);
                        }
                        //echo $date2->d.' days'."\n";
                        //echo $date2->h.' hours'."\n";
                        //echo $date2->i.' minutes'."\n";

                    @endphp
				<div class="notification-box card">
					<div class="noti-author status success">By {{$virtualdata->teacher_name}}</div>
					<div class="row align-items-center">
                        <div class="col-md-9">
                            <div class="noti-title">{{$virtualdata->subject_name}}</div>
                            <div class="noti-des d-flex align-items-center">
                                <div class="mr-3"><b>Chapter :</b> {{$virtualdata->chapter_name}}</div>
                                <div class="mr-3"><b>Topic :</b> {{$virtualdata->topic_name}}</div>
                            </div>
                        </div>
                        <div class="col-md-3 text-md-right">
                            @if(strtoupper(Session::get('user_profile_name')) == 'LMS TEACHER')
                                <div class="d-inline">
                                    <a class="btn btn-outline-success"
                                       href="{{ route('lmsVirtualClassroom.edit',['lmsVirtualClassroom'=>$virtualdata['id'],'std_id'=>$virtualdata['standard_id']])}}">
                                        <i class="ti-pencil-alt"></i>
                                    </a>
                                    <form class="d-inline"
                                          action="{{ route('lmsVirtualClassroom.destroy', $virtualdata['id'] )}}"
                                          method="post">
                                        @csrf
                                        @method('DELETE')
                                        <button onclick="return confirmDelete();" type="submit"
                                                class="btn btn-outline-danger"><i class="ti-trash"></i></button>
                                    </form>
                                </div>
                            @endif
                            <div class="d-inline ml-1">
                                <a href="{{$virtualdata->url}}" class="btn btn-outline-dark" target="_blank">Join<i
                                        class="mdi mdi-arrow-right-bold-circle fa-fw"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="time d-flex text-primary">
                        <i class="mdi mdi-video-outline mdi-24px mr-2"><font
                                style="color:red;">@php echo $date2->h.':'.$date2->i.' Hours Remaining'; @endphp</font></i>
                    </div>
                </div>
                @endforeach
            @endif

            @if(count($data['data']) == 0)
                <div class="notification-box card">
                    <font color="red;">No Virtual Classroom Found !</font>
                </div>
        @endif

        <!-- <div class="notification-box card">
				<div class="noti-author status success">By Ravi Patel</div>
				<div class="row align-items-center">
					<div class="col-md-9">
						<div class="noti-title">English</div>
						<div class="noti-des d-flex align-items-center">
							<div class="mr-3"><b>Chapter :</b> 06-chapte name</div>
							<div class="mr-3"><b>Topic :</b> 06-chapte name</div>
						</div>
					</div>
					<div class="col-md-3 text-md-right">
					</div>
				</div>
				<div class="time d-flex text-primary">
					<i class="mdi mdi-video-outline mdi-24px mr-2"></i> <span>Recorded</span>
				</div>
			</div> -->
        </div>
    </div>
</div>

@include('includes.lmsfooterJs')
@include('includes.footer')
@endsection
