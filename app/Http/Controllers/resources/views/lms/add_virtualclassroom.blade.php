{{--@include('includes.lmsheadcss')--}}
@extends('lmslayout')
@section('container')
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
{{--@include('includes.header')
@include('includes.sideNavigation')--}}
<style>
    #overlay {
        position: fixed; /* Sit on top of the page content */
        display: none; /* Hidden by default */
        width: 100%; /* Full width (cover the whole page) */
        height: 100%; /* Full height (cover the whole page) */
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5); /* Black background with opacity */
        z-index: 2; /* Specify a stack order in case you're using a different order for other elements */
        cursor: pointer; /* Add a pointer on hover */
    }
</style>
<div id="overlay" style="display:none;">
    <center>
        <p style="margin-top: 273px;color:red;font-weight: 700;">
            Please do not refresh the page, while the process is going on.
        </p>
        <img src="../../admin_dep/images/loader.gif">
    </center>
</div>

<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="row bg-title align-items-center justify-content-between">
        <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
            <h1 class="h4 mb-3">Add Virtual Classroom</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                    <li class="breadcrumb-item"><a
                            href="{{ route('chapter_master.index',['standard_id'=>$data['breadcrum_data']->standard_id,'subject_id'=>$data['breadcrum_data']->subject_id]) }}">{{$data['breadcrum_data']->subject_name}}</a>
                    </li>
                    <li class="breadcrumb-item"><a
                            href="{{ route('topic_master.index',['id'=>$data['breadcrum_data']->chapter_id]) }}">{{$data['breadcrum_data']->chapter_name}}</a>
                    </li>
                    <li class="breadcrumb-item"><a
                            href="{{ route('topic_master.index',['id'=>$data['breadcrum_data']->chapter_id]) }}">{{$data['breadcrum_data']->topic_name}}</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Add Virtual Classroom</li>
                </ol>
            </nav>
        </div>
    </div>
    <div class="container-fluid mb-5">
        <div class="card border-0">
            <div class="card-body">
                <form action="{{route('virtual_classroom_master.store')}}" method="post">
                    @csrf
                    <input type="hidden" name="hid_chapter_id" id="hid_chapter_id" value="{{$_REQUEST['chapter_id']}}">
                    <input type="hidden" name="hid_topic_id" id="hid_topic_id" value="{{$_REQUEST['topic_id']}}">

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="room_name">Room Name(Topic)</label>
                                <input type="text" class="form-control" id="room_name" name="room_name" placeholder="Room Name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea type="text" rows="4" class="form-control" id="description" name="description" placeholder="Description"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Recurring</label>
                                <select id='recurring' name="recurring" class="form-control"
                                        onchange="showothers(value);">
                                    <option>--Select Recurring--</option>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group" id="event_date_div" style="display: none;">
                                <label for="event_date">Event Date</label>
                                <input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy"
                                       value="@if(isset($data->event_date)){{date('d-m-Y', strtotime($data->event_date))}}@endif"
                                       name="event_date" autocomplete="off">
                                <span class="input-group-addon"><i class="icon-calender"></i></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group" id="from_time_div" style="display: none;">
                                <label>From Time</label>
                                <div class="input-group clockpicker " data-placement="bottom" data-align="top"
                                     data-autoclose="true">
                                    <input type="text" id='from_time' name="from_time" class="form-control"
                                           value="@if(isset($data->from_time)){{$data->from_time}}@endif">
                                    <span class="input-group-addon"><span
                                            class="glyphicon glyphicon-time"></span></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group" id="to_time_div" style="display: none;">
                                <label>To Time</label>
                                <div class="input-group clockpicker " data-placement="bottom" data-align="top"
                                     data-autoclose="true">
                                    <input type="text" id='to_time' name="to_time" class="form-control"
                                           value="@if(isset($data->to_time)){{$data->to_time}}@endif">
                                    <span class="input-group-addon"><span
                                            class="glyphicon glyphicon-time"></span></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="url">URL</label>
                                <input type="text" class="form-control" id="url" name="url" placeholder="Url" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Display Status</label>
                                <select id='status' name="status" class="form-control">
                                    <option>--Select Status--</option>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Notification</label>
                                <select id='notification' name="notification" class="form-control">
                                    <option>--Select Notification--</option>
                                    <option value="Yes">Want to send notification to students?</option>
                                    <option value="No">Don't want to send notification to students.</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="sort_order">Sort Order</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order" required>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit">Save</button>
                </form>
            </div>
        </div>
    </div>
</div>
@include('includes.lmsfooterJs')

<script type="text/javascript">
    $(function() {
         $(document).ready(function()
         {
            //var bar = $('.bar');
            //var percent = $('.percent');

          $('form').ajaxForm({
            beforeSend: function() {
                $("#overlay").css("display","block");
                //var percentVal = '0%';
                //bar.width(percentVal)
                //percent.html(percentVal);
            },
            uploadProgress: function(event, position, total, percentComplete) {
                //var percentVal = percentComplete + '%';
                //bar.width(percentVal)
                //percent.html(percentVal);
            },
            complete: function(xhr) {
                //alert('File Uploaded Successfully');
                $("#overlay").css("display","none");
                window.location.href = "{{ url()->previous() }}";
            }
          });
         });
    });
</script>
<script type="text/javascript">
    function showothers(other) {
        if (other === 'No') {
            // alert('yes');
            document.getElementById("event_date_div").style.display = "block";
            document.getElementById("from_time_div").style.display = "block";
            document.getElementById("to_time_div").style.display = "block";
        } else {
            // alert('no');
            document.getElementById("event_date_div").style.display = "none";
            document.getElementById("from_time_div").style.display = "none";
            document.getElementById("to_time_div").style.display = "none";
        }
    }
</script>
@include('includes.footer')
@endsection
