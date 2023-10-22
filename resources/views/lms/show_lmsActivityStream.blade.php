@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')

<div class="content-main flex-fill">
    <div class="container-fluid mb-5">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Activity Stream</h1>
                 <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item">Engagement</li>
                        <li class="breadcrumb-item">Show Activity Stream</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="activity-box">
            @if(isset($data['activitystream_upcoming_data']) && count($data['activitystream_upcoming_data']) > 0)
            <div class="video-sec-title"><div class="h4 mb-0">Upcoming Activity/Events</div></div>
            @foreach($data['activitystream_upcoming_data'] as $key => $upcoming_event)
                <div class="notification-box">
                    <div class="card border-0 p-3">
                        <div class="video-sec-title"><div class="icon-box"><i class="mdi mdi-video-check-outline"></i></div></div>
                        <div class="noti-title"><span style="color: #25bdea" class="h4">{{$upcoming_event->action}} </span> / {{$upcoming_event->title}}</div>
                        <div class="noti-des">{{$upcoming_event->description}}</div>

                        <div class="noti-title">{{$upcoming_event->subject_name}}</div>
                        <div class="noti-des d-flex align-items-center"></div>
                        <div class="noti-author status success">By soni  Date : {{$upcoming_event->event_date}}</div>
                        @if($upcoming_event->action == 'Virtual Classroom')
                            <a href="{{$upcoming_event->url}}" target="_blank" class="btn btn-outline-primary mt-3">Join</a>
                        @endif

                    </div>
                </div>

            @endforeach
            @endif

        </div>
        @if(isset($data['activitystream_today_data']) && count($data['activitystream_today_data']) > 0)
        <div class="activity-box">
            <div class="video-sec-title"><div class="h4 mb-0">Today Activity/Events</div></div>
            @foreach($data['activitystream_today_data'] as $key => $today_event)
                <div class="notification-box">
                    <div class="card border-0 p-3">
                        <div class="video-sec-title"><div class="icon-box"><i class="mdi mdi-video-check-outline"></i></div></div>
                        <div class="noti-title"><span style="color: #25bdea"  class="h4">{{$today_event->action}} </span> / {{$today_event->title}}</div>
                        <div class="noti-des">{{$today_event->description}}</div>


                        <div class="noti-title">{{$today_event->subject_name}}</div>
                        <div class="noti-des d-flex align-items-center"></div>
                        <div class="noti-author status success">By soni  Date : {{$today_event->event_date}}</div>


                        @if($today_event->action == 'Virtual Classroom')
                            <a href="{{$today_event->url}}" target="_blank" class="btn btn-outline-primary mt-3">Join</a>
                        @endif

                    </div>
                </div>

            @endforeach
        </div>
        @endif
    </div>

</div>

@include('includes.lmsfooterJs')
@include('includes.footer')
