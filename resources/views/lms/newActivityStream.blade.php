@extends('lmslayout')
@section('container')
<link rel="stylesheet" href="{{asset('/activity_stream_asset/styles.css')}}">
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
        <!-- upcoming start  -->
        <div class="dropdown-container">
          <div style="width: 100%">
            <div id="dropdown-1" class="dropdownn">
                <!-- upcoming head  -->
              <div type="button" id="dropdownMenuButton" style="padding: 12px 30px">
                <h5 class="d-inline mr-2 sub-p"><b>Upcoming</b></h5>
                <img class="arrow-down" src="{{ asset('/activity_stream_asset/arrow-down-sign-to-navigate.png')}}" height="12"/>
              </div>
            <!-- upcoming body  -->
              <div class="dropdown-item-list d-none">
                @if(isset($data['upcoming']['class_schedule']) && !empty($data['upcoming']['class_schedule']))
                  @foreach($data['upcoming']['class_schedule'] as $key=>$value)
                    @php 
                      $startTime =($value->start_time) ? \Carbon\Carbon::parse($value->start_time)->format('H:i A') : '-';
                      $endTime=($value->end_time) ? \Carbon\Carbon::parse($value->end_time)->format('H:i A') : '-';
                      $startDate =($value->created_at) ? \Carbon\Carbon::parse($value->created_at)->format('d-m-Y') : '-';
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Schedule</span>
                        <span class="sub-heading">{{$value->title}} on {{$startTime}} to {{$endTime}} in {{$value->standard}}/{{$value->division}} go to for expert lecture</span>
                        <div class="link"><a href="{{route('classwisetimetable.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['upcoming']['homework']) && !empty($data['upcoming']['homework']))
                  @foreach($data['upcoming']['homework'] as $key=>$value)
                  @php 
                      $startTime =($value->created_on) ? \Carbon\Carbon::parse($value->created_on)->format('H:i A') : '-';
                      $startDate =($value->date) ? \Carbon\Carbon::parse($value->date)->format('d-m-Y') : '-';
                    @endphp
                  <div class="dropdown-itemm {{ ($value->completion_status !='N' ) ? 'active' : '' }}">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Homework</span>
                        <span class="sub-heading">Homework Submission {{$value->title}} in {{$value->standard}}/{{$value->division}}</span>
                        <div class="link"><a href="{{route('student_homework_report_index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['upcoming']['eventCalender']) && !empty($data['upcoming']['eventCalender']))
                  @foreach($data['upcoming']['eventCalender'] as $key=>$value)
                  @php 
                      $startTime =($value->created_at) ? \Carbon\Carbon::parse($value->created_at)->format('H:i A') : '-';
                      $startDate =($value->school_date) ? \Carbon\Carbon::parse($value->school_date)->format('d-m-Y') : '-';
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Events & Calender</span>
                        <span class="sub-heading">{{$value->title}} on {{$startDate}}</span>
                        <div class="link"><a href="{{route('calendar.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['upcoming']['announcementNotice']) && !empty($data['upcoming']['announcementNotice']))
                  @foreach($data['upcoming']['announcementNotice'] as $key=>$value)
                  @php 
                      $startTime =($value->created_at) ? \Carbon\Carbon::parse($value->created_at)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse(now())->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Announcement & Notice</span>
                        <span class="sub-heading">{{$value->title}} on {{$startDate}}</span>
                        <div class="link"><a href="{{route('announcements.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['upcoming']['dueBooks']) && !empty($data['upcoming']['dueBooks']))
                  @foreach($data['upcoming']['dueBooks'] as $key=>$value)
                  @php 
                      $startTime =($value->created_at) ? \Carbon\Carbon::parse($value->created_at)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->due_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Due Books</span>
                        <span class="sub-heading">"{{$value->book_name}}" due date is on {{$startDate}}</span>
                        <div class="link"><a href="{{route('book_issue_report.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['upcoming']['studentProgress']) && !empty($data['upcoming']['studentProgress']))
                  @foreach($data['upcoming']['studentProgress'] as $key=>$value)
                  @php 
                      $startTime =\Carbon\Carbon::parse(now())->format('H:i A');
                      $startDate =\Carbon\Carbon::parse(now())->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Student Progress</span>
                        <span class="sub-heading">Check Student Progress {{$value->name}}/{{$value->division}}</span>
                        <div class="link"><a href="{{route('lmsStudent_report.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['upcoming']['ptm']) && !empty($data['upcoming']['ptm']))
                  @foreach($data['upcoming']['ptm'] as $key=>$value)
                  @php 
                      $startTime =(isset($value->from_date)) ? \Carbon\Carbon::parse($value->from_time)->format('H:i A') : '-';
                      $endTime =(isset($value->to_time)) ? \Carbon\Carbon::parse($value->to_time)->format('H:i A') : '-';
                      $startDate =($value->ptm_date) ? \Carbon\Carbon::parse($value->ptm_date)->format('d-m-Y') : '-';
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">PTM</span>
                        <span class="sub-heading">PTM is on {{$startDate}} at {{$startDate}} - {{$endTime}} of {{$value->standard}}/{{$value->division}}</span>
                        <div class="link"><a href="{{route('add_ptm_time_slot_master.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['upcoming']['lessonPlan']) && !empty($data['upcoming']['lessonPlan']))
                  @foreach($data['upcoming']['lessonPlan'] as $key=>$value)
                  @php 
                      $startTime =($value->timecreated) ? \Carbon\Carbon::parse($value->timecreated)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse(now())->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Lesson Plan</span>
                        <span class="sub-heading">Lesson Plan for {{$value->chapter_name}} for {{$value->standard}}</span>
                        <div class="link"><a href="{{route('lessonplanning.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['upcoming']['hrmsPunchInOut']) && !empty($data['upcoming']['hrmsPunchInOut']))
                  @foreach($data['upcoming']['hrmsPunchInOut'] as $key=>$value)
                  @php 
                      $startTime =\Carbon\Carbon::parse($value->punch_in)->format('H:i A');
                      $endTime =\Carbon\Carbon::parse($value->punch_out)->format('H:i A');
                      $startDate =\Carbon\Carbon::parse(now())->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Punch In/Out</span>
                        <span class="sub-heading">Your {{$value->user_name}} time Punch In {{$startTime}} and Punch Out {{$endTime}}</span>
                        <div class="link"><a href="{{route('hrms_attendance_report.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['upcoming']['proxyLecture']) && !empty($data['upcoming']['proxyLecture']))
                  @foreach($data['upcoming']['proxyLecture'] as $key=>$value)
                  @php 
                      $startTime =(isset($value->from_date)) ? \Carbon\Carbon::parse($value->from_time)->format('H:i A') : '-';
                      $endTime =(isset($value->to_time)) ? \Carbon\Carbon::parse($value->to_time)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->proxy_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Proxy Lecture</span>
                        <span class="sub-heading">Proxy Lecture of {{$value->user_name}} on {{$startTime}}-{{$endTime}} in {{$value->standard}}/{{$value->division}}</span>
                        <div class="link"><a href="{{route('proxy_report.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['upcoming']['examMarks']) && !empty($data['upcoming']['examMarks']))
                  @foreach($data['upcoming']['examMarks'] as $key=>$value)
                  @php 
                      $startTime =\Carbon\Carbon::parse(now())->format('H:i A');
                      $startDate =\Carbon\Carbon::parse($value->exam_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Exam Marks</span>
                        <span class="sub-heading">Add exam Marks For {{$value->standard}}</span>
                        <div class="link"><a href="{{route('marks_entry.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['upcoming']['studentAttendance']) && !empty($data['upcoming']['studentAttendance']))
                  @foreach($data['upcoming']['studentAttendance'] as $key=>$value)
                  @php 
                      $startTime =($value->created_on) ? \Carbon\Carbon::parse($value->created_on)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->attendance_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Student Attendance</span>
                        <span class="sub-heading">Take Attendance for {{$value->standard}}</span>
                        <div class="link"><a href="{{route('student_attendance.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['upcoming']['taskAssigned']) && !empty($data['upcoming']['taskAssigned']))
                  @foreach($data['upcoming']['taskAssigned'] as $key=>$value)
                  @php 
                      $startTime =($value->created_on) ? \Carbon\Carbon::parse($value->CREATED_ON)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->TASK_DATE)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Task Assigned</span>
                        <span class="sub-heading">Task {{$value->TASK_TITLE}} Assigned to {{$value->task_user_name}}</span>
                        <div class="link"><a href="{{route('task.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['upcoming']['parentCommunication']) && !empty($data['upcoming']['parentCommunication']))
                  @foreach($data['upcoming']['parentCommunication'] as $key=>$value)
                  @php 
                      $startTime =($value->created_on) ? \Carbon\Carbon::parse($value->created_on)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->start_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm {{($value->reply!=null && $value->reply!='') ? 'active' : ''}}">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Parent Communication</span>
                        <span class="sub-heading">Send Replay on parent communication {{$value->title}} </span>
                        <div class="link"><a href="{{route('parent_communication.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['upcoming']['studentLeave']) && !empty($data['upcoming']['studentLeave']))
                  @foreach($data['upcoming']['studentLeave'] as $key=>$value)
                  @php 
                      $startTime =($value->created_at) ? \Carbon\Carbon::parse($value->created_at)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->apply_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm {{($value->status!=null && $value->status!='') ? 'Active' : ''}}">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Student Leave</span>
                        <span class="sub-heading">Student Leave {{$value->title}} for {{$value->standard}}</span>
                        <div class="link"><a href="{{route('leave_application.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
              </div>
              <!-- upcoming body end  -->
            </div>
          </div>
        </div>
        <!-- upcoming end  -->
        <!-- ============================================================================================================================== -->
        <!-- today start  -->
        <div class="dropdown-container">
          <div style="width: 100%">
            <div id="dropdown-2" class="dropdownn">
                <!-- today head  -->
              <div type="button" id="dropdownMenuButton" style="padding: 10px 30px" class="active">
                <h5 class="d-inline mr-2 sub-p"><b>Today</b></h5>
                <p class="d-inline" style="color: #7d7d7d; font-weight: 500">
                  {{$data['todaytitle']}}
                </p>
                <img class="arrow-down" src="{{ asset('/activity_stream_asset/arrow-down-sign-to-navigate.png')}}" height="12" />
              </div>
                <!-- today body  -->
              <div class="dropdown-item-list d-block">
               @if(isset($data['today']['class_schedule']) && !empty($data['today']['class_schedule']))
                  @foreach($data['today']['class_schedule'] as $key=>$value)
                    @php 
                      $startTime =\Carbon\Carbon::parse($value->start_time)->format('H:i A');
                      $endTime=\Carbon\Carbon::parse($value->end_time)->format('H:i A');
                    @endphp
                  <div class="dropdown-itemm {{ ($value->att !=0 ) ? 'active' : '' }}">
                      <span class="time">{{$startTime}}</span>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Schedule</span>
                        <span class="sub-heading">{{$value->title}} on {{$startTime}} to {{$endTime}} in {{$value->standard}}/{{$value->division}} go to for expert lecture</span>
                        <div class="link"><a href="{{route('classwisetimetable.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['today']['homework']) && !empty($data['today']['homework']))
                  @foreach($data['today']['homework'] as $key=>$value)
                    @php 
                      $startTime =($value->created_on) ? \Carbon\Carbon::parse($value->created_on)->format('H:i A') : '-';
                    @endphp
                  <div class="dropdown-itemm {{ ($value->completion_status !=0 ) ? 'active' : '' }}">
                      <span class="time">{{$startTime}}</span>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Schedule</span>
                        <span class="sub-heading">Homework Submission {{$value->title}} in {{$value->standard}}/{{$value->division}} </span>
                        <div class="link"><a href="{{route('student_homework_report_index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['today']['eventCalender']) && !empty($data['today']['eventCalender']))
                  @foreach($data['today']['eventCalender'] as $key=>$value)
                  @php 
                      $startTime =($value->created_at) ? \Carbon\Carbon::parse($value->created_at)->format('H:i A') : '-';
                      $startDate =($value->school_date) ? \Carbon\Carbon::parse($value->school_date)->format('d-m-Y') : '-';
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Events & Calender</span>
                        <span class="sub-heading">{{$value->title}} on {{$startDate}}</span>
                        <div class="link"><a href="{{route('calendar.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['today']['announcementNotice']) && !empty($data['today']['announcementNotice']))
                  @foreach($data['today']['announcementNotice'] as $key=>$value)
                  @php 
                      $startTime =($value->created_at) ? \Carbon\Carbon::parse($value->created_at)->format('H:i A') : '-';
                    @endphp
                  <div class="dropdown-itemm">
                    <span class="time">{{$startTime}}</span>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Announcement & Notice</span>
                        <span class="sub-heading">{{$value->title}} on {{$startDate}}</span>
                        <div class="link"><a href="{{route('announcements.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['today']['dueBooks']) && !empty($data['today']['dueBooks']))
                  @foreach($data['today']['dueBooks'] as $key=>$value)
                  @php 
                      $startTime =($value->created_at) ? \Carbon\Carbon::parse($value->created_at)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->due_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                    <span class="time">{{$startTime}}</span>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Due Books</span>
                        <span class="sub-heading">"{{$value->book_name}}" due date is on {{$startDate}}</span>
                        <div class="link"><a href="{{route('book_issue_report.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['today']['studentProgress']) && !empty($data['today']['studentProgress']))
                  @foreach($data['today']['studentProgress'] as $key=>$value)
                  @php 
                      $startTime =\Carbon\Carbon::parse(now())->format('H:i A');
                      $startDate =\Carbon\Carbon::parse(now())->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                    <span class="time">{{$startTime}}</span>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Student Progress</span>
                        <span class="sub-heading">Check Student Progress {{$value->name}}/{{$value->division}}</span>
                        <div class="link"><a href="{{route('lmsStudent_report.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['today']['ptm']) && !empty($data['today']['ptm']))
                  @foreach($data['today']['ptm'] as $key=>$value)
                  @php 
                      $startTime =(isset($value->from_date)) ? \Carbon\Carbon::parse($value->from_time)->format('H:i A') : '-';
                      $endTime =(isset($value->to_time)) ? \Carbon\Carbon::parse($value->to_time)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->ptm_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                    <span class="time">{{$startTime}}</span>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">PTM</span>
                        <span class="sub-heading">PTM is on {{$startDate}} at {{$startDate}} - {{$endTime}} of {{$value->standard}}/{{$value->division}}</span>
                        <div class="link"><a href="{{route('add_ptm_time_slot_master.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['today']['lessonPlan']) && !empty($data['today']['lessonPlan']))
                  @foreach($data['today']['lessonPlan'] as $key=>$value)
                  @php 
                      $startTime =($value->timecreated) ? \Carbon\Carbon::parse($value->timecreated)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse(now())->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                    <span class="time">{{$startTime}}</span>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Lesson Plan</span>
                        <span class="sub-heading">Lesson Plan for {{$value->chapter_name}} for {{$value->standard}}</span>
                        <div class="link"><a href="{{route('lessonplanning.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['today']['hrmsPunchInOut']) && !empty($data['today']['hrmsPunchInOut']))
                  @foreach($data['today']['hrmsPunchInOut'] as $key=>$value)
                  @php 
                      $startTime =\Carbon\Carbon::parse($value->punchin_time)->format('H:i A');
                      $endTime =\Carbon\Carbon::parse($value->punchout_time)->format('H:i A');
                      $startDate =\Carbon\Carbon::parse($value->day)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                    <span class="time">{{$startTime}}</span>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Punch In/Out</span>
                        <span class="sub-heading">Your {{$value->user_name}} time Punch In {{$startTime}} and Punch Out {{$endTime}}</span>
                        <div class="link"><a href="{{route('hrms_attendance_report.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['today']['proxyLecture']) && !empty($data['today']['proxyLecture']))
                  @foreach($data['today']['proxyLecture'] as $key=>$value)
                  @php 
                      $startTime =(isset($value->from_date)) ? \Carbon\Carbon::parse($value->from_time)->format('H:i A') : '-';
                      $endTime =(isset($value->to_time)) ? \Carbon\Carbon::parse($value->to_time)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->proxy_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                    <span class="time">{{$startTime}}</span>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Proxy Lecture</span>
                        <span class="sub-heading">Proxy Lecture of {{$value->user_name}} on {{$startTime}}-{{$endTime}} in {{$value->standard}}/{{$value->division}}</span>
                        <div class="link"><a href="{{route('proxy_report.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['today']['examMarks']) && !empty($data['today']['examMarks']))
                  @foreach($data['today']['examMarks'] as $key=>$value)
                  @php 
                      $startTime =\Carbon\Carbon::parse(now())->format('H:i A');
                      $startDate =\Carbon\Carbon::parse($value->exam_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                    <span class="time">{{$startTime}}</span>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Exam Marks</span>
                        <span class="sub-heading">Add exam Marks For {{$value->standard}}</span>
                        <div class="link"><a href="{{route('marks_entry.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['today']['studentAttendance']) && !empty($data['today']['studentAttendance']))
                  @foreach($data['today']['studentAttendance'] as $key=>$value)
                  @php 
                      $startTime =($value->created_on) ? \Carbon\Carbon::parse($value->created_on)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->attendance_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                    <span class="time">{{$startTime}}</span>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Student Attendance</span>
                        <span class="sub-heading">Take Attendance for {{$value->standard}}</span>
                        <div class="link"><a href="{{route('student_attendance.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['today']['taskAssigned']) && !empty($data['today']['taskAssigned']))
                  @foreach($data['today']['taskAssigned'] as $key=>$value)
                  @php 
                      $startTime =($value->created_on) ? \Carbon\Carbon::parse($value->CREATED_ON)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->TASK_DATE)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                    <span class="time">{{$startTime}}</span>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Task Assigned</span>
                        <span class="sub-heading">Task {{$value->TASK_TITLE}} Assigned to {{$value->task_user_name}}</span>
                        <div class="link"><a href="{{route('task.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['today']['parentCommunication']) && !empty($data['today']['parentCommunication']))
                  @foreach($data['today']['parentCommunication'] as $key=>$value)
                  @php 
                      $startTime =($value->created_on) ? \Carbon\Carbon::parse($value->created_on)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->start_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm {{($value->reply!=null && $value->reply!='') ? 'active' : ''}}">
                    <span class="time">{{$startTime}}</span>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Parent Communication</span>
                        <span class="sub-heading">Send Replay on parent communication {{$value->title}} </span>
                        <div class="link"><a href="{{route('parent_communication.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['today']['studentLeave']) && !empty($data['today']['studentLeave']))
                  @foreach($data['today']['studentLeave'] as $key=>$value)
                  @php 
                      $startTime =($value->created_at) ? \Carbon\Carbon::parse($value->created_at)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->apply_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm {{($value->status!=null && $value->status!='') ? 'Active' : ''}}">
                    <span class="time">{{$startTime}}</span>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Student Leave</span>
                        <span class="sub-heading">Student Leave {{$value->title}} for {{$value->standard}}</span>
                        <div class="link"><a href="{{route('leave_application.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                </div>
                <!-- check list  -->
            <div class="col-lg-12 col-sm-12 col-xs-12 col-md-12 pb-2 text-right">
              <a class="btn btn-info add-new" data-toggle="modal" data-target="#exampleModal">Today's Check List</a>
            </div>
            <!-- check list ends  -->
              </div>
              
            </div>
          </div>
        </div>
        <!-- today end   -->
        <!-- ============================================================================================================================== -->
        <!-- recent start  -->
        <div class="dropdown-container">
          <div style="width: 100%">
            <div id="dropdown-3" class="dropdownn">
                <!-- RECENT HEAD  -->
              <div type="button" id="dropdownMenuButton" style="padding: 10px 30px" >
                <h5 class="d-inline mr-2 sub-p">Recent</h5>
                <img class="arrow-down" src="{{ asset('/activity_stream_asset/arrow-down-sign-to-navigate.png')}}" height="12" />
              </div>
                <!-- recent body -->
              <div class="dropdown-item-list d-none">
                
                @if(isset($data['recent']['class_schedule']) && !empty($data['recent']['class_schedule']))
                  @foreach($data['recent']['class_schedule'] as $key=>$value)
                    @php 
                      $startTime =\Carbon\Carbon::parse($value->start_time)->format('H:i A');
                      $startDate =\Carbon\Carbon::parse($value->attendance_date)->format('H:i A');
                    @endphp
                  <div class="dropdown-itemm {{ ($value->att !=0 ) ? 'active' : '' }}">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Schedule</span>
                        <span class="sub-heading">{{$value->title}} on {{$startTime}} to {{$endTime}} in {{$value->standard}}/{{$value->division}} go to for expert lecture</span>
                        <div class="link"><a href="{{route('classwisetimetable.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['recent']['homework']) && !empty($data['recent']['homework']))
                  @foreach($data['recent']['homework'] as $key=>$value)
                  @php 
                      $startTime =($value->created_on) ? \Carbon\Carbon::parse($value->created_on)->format('H:i A') : '-';
                      $startDate =($value->date) ? \Carbon\Carbon::parse($value->date)->format('d-m-Y') : '-';
                    @endphp
                  <div class="dropdown-itemm {{ ($value->completion_status !='N' ) ? 'active' : '' }}">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Homework</span>
                        <span class="sub-heading">Homework Submission {{$value->title}} in {{$value->standard}}/{{$value->division}} </span>
                        <div class="link"><a href="{{route('student_homework_report_index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['recent']['eventCalender']) && !empty($data['recent']['eventCalender']))
                  @foreach($data['recent']['eventCalender'] as $key=>$value)
                  @php 
                      $startTime =($value->created_at) ? \Carbon\Carbon::parse($value->created_at)->format('H:i A') : '-';
                      $startDate =($value->school_date) ? \Carbon\Carbon::parse($value->school_date)->format('d-m-Y') : '-';
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Events & Calender</span>
                        <span class="sub-heading">{{$value->title}} on {{$startDate}}</span>
                        <div class="link"><a href="{{route('calendar.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['recent']['announcementNotice']) && !empty($data['recent']['announcementNotice']))
                  @foreach($data['recent']['announcementNotice'] as $key=>$value)
                  @php 
                      $startTime =($value->created_at) ? \Carbon\Carbon::parse($value->created_at)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse(now())->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm active">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Announcement & Notice</span>
                        <span class="sub-heading">{{$value->title}} on {{$startDate}}</span>
                        <div class="link"><a href="{{route('announcements.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['recent']['dueBooks']) && !empty($data['recent']['dueBooks']))
                  @foreach($data['recent']['dueBooks'] as $key=>$value)
                  @php 
                      $startTime =($value->created_at) ? \Carbon\Carbon::parse($value->created_at)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->due_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm {{($value->return_date!=null && $value->return_date!='') ? 'active' : ''}}">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Due Books</span>
                        <span class="sub-heading">"{{$value->book_name}}" due date is on {{$startDate}}</span>
                        <div class="link"><a href="{{route('book_issue_report.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['recent']['studentProgress']) && !empty($data['recent']['studentProgress']))
                  @foreach($data['recent']['studentProgress'] as $key=>$value)
                  @php 
                      $startTime =\Carbon\Carbon::parse(now())->format('H:i A');
                      $startDate =\Carbon\Carbon::parse(now())->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Student Progress</span>
                        <span class="sub-heading">Check Student Progress {{$value->name}}/{{$value->division}}</span>
                        <div class="link"><a href="{{route('lmsStudent_report.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['recent']['ptm']) && !empty($data['recent']['ptm']))
                  @foreach($data['recent']['ptm'] as $key=>$value)
                  @php 
                      $startTime =(isset($value->from_date)) ? \Carbon\Carbon::parse($value->from_time)->format('H:i A') : '-';
                      $endTime =(isset($value->to_time)) ? \Carbon\Carbon::parse($value->to_time)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->ptm_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">PTM</span>
                        <span class="sub-heading">PTM is on {{$startDate}} at {{$startDate}} - {{$endTime}} of {{$value->standard}}/{{$value->division}}</span>
                        <div class="link"><a href="{{route('add_ptm_time_slot_master.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['recent']['lessonPlan']) && !empty($data['recent']['lessonPlan']))
                  @foreach($data['recent']['lessonPlan'] as $key=>$value)
                  @php 
                      $startTime =($value->timecreated) ? \Carbon\Carbon::parse($value->timecreated)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse(now())->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Lesson Plan</span>
                        <span class="sub-heading">Lesson Plan for {{$value->chapter_name}} for {{$value->standard}}</span>
                        <div class="link"><a href="{{route('lessonplanning.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['recent']['hrmsPunchInOut']) && !empty($data['recent']['hrmsPunchInOut']))
                  @foreach($data['recent']['hrmsPunchInOut'] as $key=>$value)
                  @php 
                    $startTime =\Carbon\Carbon::parse($value->punchin_time)->format('H:i A');
                      $endTime =\Carbon\Carbon::parse($value->punchout_time)->format('H:i A');
                      $startDate =\Carbon\Carbon::parse($value->day)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Punch In/Out</span>
                        <span class="sub-heading">Your {{$value->user_name}} time Punch In {{$startTime}} and Punch Out {{$endTime}}</span>
                        <div class="link"><a href="{{route('hrms_attendance_report.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['recent']['proxyLecture']) && !empty($data['recent']['proxyLecture']))
                  @foreach($data['recent']['proxyLecture'] as $key=>$value)
                  @php 
                      $startTime =(isset($value->from_date)) ? \Carbon\Carbon::parse($value->from_time)->format('H:i A') : '-';
                      $endTime =(isset($value->to_time)) ? \Carbon\Carbon::parse($value->to_time)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->proxy_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Proxy Lecture</span>
                        <span class="sub-heading">Proxy Lecture of {{$value->user_name}} on {{$startTime}}-{{$endTime}} in {{$value->standard}}/{{$value->division}}</span>
                        <div class="link"><a href="{{route('proxy_report.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['recent']['examMarks']) && !empty($data['recent']['examMarks']))
                  @foreach($data['recent']['examMarks'] as $key=>$value)
                  @php 
                      $startTime =\Carbon\Carbon::parse(now())->format('H:i A');
                      $startDate =\Carbon\Carbon::parse($value->exam_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Exam Marks</span>
                        <span class="sub-heading">Add exam Marks For {{$value->standard}}</span>
                        <div class="link"><a href="{{route('marks_entry.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['recent']['studentAttendance']) && !empty($data['recent']['studentAttendance']))
                  @foreach($data['recent']['studentAttendance'] as $key=>$value)
                  @php 
                      $startTime =($value->created_on) ? \Carbon\Carbon::parse($value->created_on)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->attendance_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Student Attendance</span>
                        <span class="sub-heading">Take Attendance for {{$value->standard}}</span>
                        <div class="link"><a href="{{route('student_attendance.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['recent']['taskAssigned']) && !empty($data['recent']['taskAssigned']))
                  @foreach($data['recent']['taskAssigned'] as $key=>$value)
                  @php
				    $startTime = (isset($value->created_on) && $value->created_on) 
				                 ? \Carbon\Carbon::parse($value->created_on)->format('H:i A') 
				                 : '-';

				    $startDate = (isset($value->task_date) && $value->task_date) 
				                 ? \Carbon\Carbon::parse($value->task_date)->format('d-m-Y') 
				                 : '-';
				@endphp
                  <div class="dropdown-itemm">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Task Assigned</span>
                        <span class="sub-heading">Task {{$value->TASK_TITLE}} Assigned to {{$value->task_user_name}}</span>
                        <div class="link"><a href="{{route('task.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['recent']['parentCommunication']) && !empty($data['recent']['parentCommunication']))
                  @foreach($data['recent']['parentCommunication'] as $key=>$value)
                  @php 
                      $startTime =($value->created_on) ? \Carbon\Carbon::parse($value->created_on)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->start_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm {{($value->reply!=null && $value->reply!='') ? 'active' : ''}}">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Parent Communication</span>
                        <span class="sub-heading">Send Replay on parent communication {{$value->title}} </span>
                        <div class="link"><a href="{{route('parent_communication.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                @if(isset($data['recent']['studentLeave']) && !empty($data['recent']['studentLeave']))
                  @foreach($data['recent']['studentLeave'] as $key=>$value)
                  @php 
                      $startTime =($value->created_at) ? \Carbon\Carbon::parse($value->created_at)->format('H:i A') : '-';
                      $startDate =\Carbon\Carbon::parse($value->apply_date)->format('d-m-Y');
                    @endphp
                  <div class="dropdown-itemm {{($value->status!=null && $value->status!='') ? 'Active' : ''}}">
                  <div class="d-flex flex-column">
                    <span class="time">{{$startDate}}</span>
                    <span class="time">{{$startTime}}</span>
                  </div>
                      <div class="disc-and-line">
                        <div class="disc"></div>
                        <div class="line"></div>
                      </div>
                      <div>
                        <span class="heading">Student Leave</span>
                        <span class="sub-heading">Student Leave {{$value->title}} for {{$value->standard}}</span>
                        <div class="link"><a href="{{route('leave_application.index')}}">View Now</a></div>
                      </div>
                    </div>
                  @endforeach
                @endif
                </div>
                <!-- recent body end-->
            </div>
          </div>
        </div>
        <!-- recent end  -->
    <!-- container div end  -->
    </div>
</div>

<!--check list Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Today's Check List</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered" style="filter:unset !important">
            <thead>
                <tr>
                    <th>Sr No</th>
                    <th>Task</th>
                    <th>Status</th>
                    <th class="text-left">Reply</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['checkList'] as $k => $value)
                <tr>
                    <td>{{$k+1}}</td>
                    <td>{{$value->TASK_TITLE}}</td>
                    <td>{{$value->STATUS}}</td>
                    <td>{{$value->reply}}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<!-- check list modal ends  -->
@include('includes.footer')
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
<script src="{{asset('activity_stream_asset/script.js')}}"></script>
@endsection