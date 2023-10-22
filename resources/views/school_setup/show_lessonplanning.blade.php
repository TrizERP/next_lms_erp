{{--@include('includes.headcss')
<!-- Calendar CSS -->
    <link href="{{ asset("/plugins/bower_components/calendar/dist/fullcalendar.css") }}" rel="stylesheet">
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
    <!-- Calendar CSS -->
    <link href="{{ asset("/plugins/bower_components/calendar/dist/fullcalendar.css") }}" rel="stylesheet">
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Lesson Planning</h4>
            </div>
        </div>
        <div class="card">
            @if(!empty($data['message']))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $data['message'] }}</strong>
                    </div>
        @endif
                    <!-- row -->
                <div class="row">
                    {{-- <div class="col-md-3">
                        <div class="white-box">
                             <h3 class="box-title">Drag and drop your event</h3>
                            <div class="row">
                                <div class="col-md-12 col-sm-12 col-xs-12">
                                    <div id="calendar-events" class="m-t-20">
                                        <div class="calendar-events" data-class="bg-info"><i class="fa fa-circle text-info"></i> My Event One</div>
                                        <div class="calendar-events" data-class="bg-success"><i class="fa fa-circle text-success"></i> My Event Two</div>
                                        <div class="calendar-events" data-class="bg-danger"><i class="fa fa-circle text-danger"></i> My Event Three</div>
                                        <div class="calendar-events" data-class="bg-warning"><i class="fa fa-circle text-warning"></i> My Event Four</div>
                                    </div>
                                    <!-- checkbox -->
                                    <div class="checkbox">
                                        <input id="drop-remove" type="checkbox">
                                        <label for="drop-remove">
                                            Remove after drop
                                        </label>
                                    </div>
                                    <a href="#" data-toggle="modal" data-target="#add-new-event" class="btn btn-lg m-t-40 btn-danger btn-block waves-effect waves-light">
                                        <i class="ti-plus"></i> Add New Event
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div> --}}
                    <div class="col-md-12">
                        <div class="white-box">
                            <div id="calendar"></div>
                        </div>
                    </div>
                </div>
                <!-- /.row -->
                <!-- BEGIN MODAL -->
                <div class="modal fade none-border" id="my-event">
                    <div class="modal-dialog">
                        <div class="modal-content">

                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                                <h4 class="modal-title"><strong>Add Lesson Planning</strong></h4>
                            </div>
                            <div class="modal-body">

                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-white waves-effect" data-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-success save-event waves-effect waves-light">Save</button>
                                <button type="button" class="btn btn-danger delete-event waves-effect waves-light" data-dismiss="modal">Delete</button>
                            </div>

                        </div>
                    </div>
                </div>
                <!-- Modal Add Category -->
                <div class="modal fade none-border" id="add-new-event">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                                <h4 class="modal-title"><strong>Add</strong> a category</h4>
                            </div>
                            <div class="modal-body">
                                <form role="form" method="post">
                                    {{ method_field("POST") }}
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="control-label">Category Name</label>
                                            <input class="form-control form-white" placeholder="Enter name" type="text" name="category-name" />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="control-label">Choose Category Color</label>
                                            <select class="form-control form-white" data-placeholder="Choose a color..." name="category-color">
                                                <option value="success">Success</option>
                                                <option value="danger">Danger</option>
                                                <option value="info">Info</option>
                                                <option value="primary">Primary</option>
                                                <option value="warning">Warning</option>
                                                <option value="inverse">Inverse</option>
                                            </select>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger waves-effect waves-light save-category"
                                        data-dismiss="modal">Save
                                </button>
                                <button type="button" class="btn btn-white waves-effect" data-dismiss="modal">Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <!-- END MODAL -->

        </div>
    </div>
</div>

@include('includes.footerJs')

 <!-- Calendar JavaScript -->
<script src="/plugins/bower_components/calendar/jquery-ui.min.js"></script>
<script src="/plugins/bower_components/moment/moment.js"></script>
<script src='/plugins/bower_components/calendar/dist/fullcalendar.min.js'></script>
<script src="/plugins/bower_components/calendar/dist/jquery.fullcalendar.js"></script>
{{-- <script src="/plugins/bower_components/calendar/dist/cal-init.js"></script> --}}
<script>

!function($) {
    "use strict";

    var CalendarApp = function() {
        this.$body = $("body")
        this.$calendar = $('#calendar'),
            this.$event = ('#calendar-events div.calendar-events'),
            this.$categoryForm = $('#add-new-event form'),
            this.$extEvents = $('#calendar-events'),
            this.$modal = $('#my-event'),
            this.$saveCategoryBtn = $('.save-category'),
            this.$calendarObj = null
    };


    /* on drop */
    CalendarApp.prototype.onDrop = function (eventObj, date) {
        var $this = this;
        // retrieve the dropped element's stored Event Object
        var originalEventObject = eventObj.data('eventObject');
        var $categoryClass = eventObj.attr('data-class');
        // we need to copy it, so that multiple events don't have a reference to the same object
        var copiedEventObject = $.extend({}, originalEventObject);
        // assign it the date that was reported
        copiedEventObject.start = date;
        if ($categoryClass)
            copiedEventObject['className'] = [$categoryClass];
        // render the event on the calendar
        $this.$calendar.fullCalendar('renderEvent', copiedEventObject, true);
        // is the "remove after drop" checkbox checked?
        if ($('#drop-remove').is(':checked')) {
            // if so, remove the element from the "Draggable Events" list
            eventObj.remove();
        }
    },
        /* on click on event */
        CalendarApp.prototype.onEventClick = function (calEvent, jsEvent, view) {
            var $this = this;

            var standardhtml = "";
            var subjecthtml = "";
            var divisionhtml = "";
            var statushtml = "";

            var standard_data = @php echo $data['standard_data']; @endphp;
            bind_subject_division(calEvent.standard_id, calEvent.subject_id, calEvent.division_id);
            standardhtml += "<select id='standard_id' class='form-control' onchange='bind_subject_division(this.value);'>";
            standardhtml += "<option value='0'>Select Standard</option>";
            for (var key in standard_data) {
                var selected1 = "";
                if (calEvent.standard_id == standard_data[key].std_id) {
                    selected1 = "selected";
                }
                standardhtml += "<option " + selected1 + " value=" + standard_data[key].std_id + ">" + standard_data[key].std_name + "</option>";
            }
            standardhtml += "</select>";

            var subject_data = @php echo $data['subject_data']; @endphp;
            subjecthtml += "<select id='subject_id' class='form-control'>";
            subjecthtml += "<option value='0'>Select Subject</option>";
            // for (var key in subject_data) {
            //     var selected2 = "";
            //     if(calEvent.subject_id == subject_data[key].sub_id)
            //     {
            //         selected2 = "selected";
            //     }
            //     subjecthtml+= "<option "+selected2+" value="+subject_data[key].sub_id+">"+subject_data[key].sub_name+"</option>";
            // }
            subjecthtml += "</select>";

            var division_data = @php echo $data['division_data']; @endphp;
            divisionhtml += "<select id='division_id' class='form-control'>";
            divisionhtml += "<option value='0'>Select Division</option>";
            // for (var key in division_data) {
            //     var selected3 = "";
            //     if(calEvent.division_id == division_data[key].div_id)
            //     {
            //         selected3 = "selected";
            //     }
            //     divisionhtml+= "<option "+selected3+" value="+division_data[key].div_id+">"+division_data[key].div_name+"</option>";
            // }
            divisionhtml += "</select>";

            var selectedYES = "";
            var selectedNO = "";
            statushtml += "<select id='lessonplan_status' class='form-control'>";
            if (calEvent.lessonplan_status == 'YES') {
                selectedYES = "selected";
            }
            if (calEvent.lessonplan_status == 'NO') {
                selectedNO = "selected";
            }
            statushtml += "<option " + selectedYES + " value='YES'>YES</option>";
            statushtml += "<option " + selectedNO + " value='NO'>NO</option>";
            statushtml += "</select>";
            if (calEvent.lessonplan_id == "" || calEvent.lessonplan_id == null) {
                var lessonplan_id = "";
            } else {
                var lessonplan_id = calEvent.lessonplan_id;
            }

            if (calEvent.lessonplan_reason == "" || calEvent.lessonplan_reason == null) {
                var lessonplan_reason = "";
            } else {
                var lessonplan_reason = calEvent.lessonplan_reason;
            }

            if (calEvent.lessonplan_date == "" || calEvent.lessonplan_date == null) {
                var lessonplan_date = "";
            } else {
                var lessonplan_date = calEvent.lessonplan_date;
            }

            var form = $("<form></form>");
            form.append("<div class='row'></div>");
            form.find(".row")
                .append("<div class='col-md-6'><div class='form-group'><label class='control-label'>Standard</label>" + standardhtml + "</div></div>")
                .append("<div class='col-md-6'><div class='form-group'><label class='control-label'>Subject</label>" + subjecthtml + "</div></div>")
                .append("<div class='col-md-6'><div class='form-group'><label class='control-label'>Division</label>" + divisionhtml + "</div></div>")
                .append("<div class='col-md-6'><div class='form-group'><label class='control-label'>Title</label><input class='form-control' placeholder='Insert Lesson Title' type='text' id='title' value='" + calEvent.title + "'/></div></div>")
                .append("<div class='col-md-12'><div class='form-group'><label class='control-label'>Description</label><textarea class='form-control' placeholder='Insert Lesson Description' id='description'>" + calEvent.description + "</textarea></div></div>")
                .append("<input type='hidden' id='hid_id' value='" + calEvent.id + "'>")
                .append("<input type='hidden' id='school_date' value='" + calEvent.start + "'>")
                .append("<div class='col-md-12'><hr></div><div class='col-md-6'><div class='form-group'><label class='control-label'>Lecture Date</label><input type='date' class='form-control' placeholder='YYYY/MM/DD' name='lessonplan_date' id='lessonplan_date' value=" + lessonplan_date + "></div></div>")
                .append("<div class='col-md-6'><div class='form-group'><label class='control-label'>Was Your topic Covered?</label>" + statushtml + "</div></div>")
                .append("<div class='col-md-12'><div class='form-group'><label class='control-label'>Remark</label><textarea class='form-control' placeholder='Insert Remark' id='lessonplan_reason'>" + lessonplan_reason + "</textarea></div></div>")
                .append("<input type='hidden' id='lessonplan_id' value='" + lessonplan_id + "'>")
                .append("</div></div>");
            //form.append("<div class='input-group'><span class='input-group-btn'><button type='submit' class='btn btn-success waves-effect waves-light'><i class='fa fa-check'></i> Save</button></span></div>");
            $this.$modal.modal({
                backdrop: 'static'
            });

            $this.$modal.find('.delete-event').show().end().find('.save-event').show().end().find('.modal-body').empty().prepend(form).end().find('.delete-event').unbind('click').click(function () {

                var hid_id = form.find("input[id='hid_id']").val();
                var hostname = "https://" + window.location.host;
                var path = hostname + "/school_setup/lessonplanning/" + hid_id;
                $.ajax({
                    url: path,
                    type: 'DELETE',
                    success: function (result) {
                        window.location.href = "{{ route('lessonplanning.index') }}";
                        $this.$modal.modal('hide');
                    }
                });
                $this.$calendarObj.fullCalendar('removeEvents', function (ev) {
                    return (ev._id == calEvent._id);
                });
                //$this.$modal.modal('hide');
            });

            $this.$modal.find('.save-event').unbind('click').click(function () {
                form.submit();
            });

            $this.$modal.find('form').on('submit', function () {
                //calEvent.title = form.find("input[type=text]").val();
                var standard_id = form.find("select[id='standard_id']  option:checked").val();
                var subject_id = form.find("select[id='subject_id']  option:checked").val();
                var division_id = form.find("select[id='division_id']  option:checked").val();
                var title = form.find("input[id='title']").val();
                var school_date = form.find("input[id='school_date']").val();
                var description = form.find("textarea[id='description']").val();
                var hid_id = form.find("input[id='hid_id']").val();
                var lessonplan_date = form.find("input[id='lessonplan_date']").val();
                var lessonplan_status = form.find("select[id='lessonplan_status']").val();
                var lessonplan_reason = form.find("textarea[id='lessonplan_reason']").val();
                var lessonplan_id = form.find("input[id='lessonplan_id']").val();

                //alert(lessonplan_id);
                var hostname = "https://" + window.location.host;
                var path = hostname + "/school_setup/lessonplanning/" + hid_id;
                if (title == null || title == "") {
                    alert("Please Fill Proper Values");
                    return false;
                }
                $.ajax({
                    url: path,
                    type: 'PUT',
                    data: {
                        'standard_id': standard_id,
                        'subject_id': subject_id,
                        'division_id': division_id,
                        'title': title,
                        'description': description,
                        'school_date': school_date,
                        'lessonplan_date': lessonplan_date,
                        'lessonplan_status': lessonplan_status,
                        'lessonplan_reason': lessonplan_reason,
                        'lessonplan_id': lessonplan_id
                    },
                    success: function (result) {
                        //alert(result);
                        window.location.href = "{{ route('lessonplanning.index') }}";
                        $this.$modal.modal('hide');
                    }
                });

                $this.$calendarObj.fullCalendar('updateEvent', calEvent);
                $this.$modal.modal('hide');
                return false;
            });
        },
        /* on select */
        CalendarApp.prototype.onSelect = function (start, end, allDay) {
            var $this = this;
            $this.$modal.modal({
                backdrop: 'static'
            });
            var form = $("<form></form>");

            var standardhtml = "";
            var subjecthtml = "";
            var divisionhtml = "";

            var standard_data = @php echo $data['standard_data']; @endphp;
            standardhtml += "<select id='standard_id' class='form-control' onchange='bind_subject_division(this.value);'>";
            standardhtml += "<option value='0'>Select Standard</option>";
            for (var key in standard_data) {
                standardhtml += "<option value=" + standard_data[key].std_id + ">" + standard_data[key].std_name + "</option>";
            }
            standardhtml += "</select>";

            var subject_data = @php echo $data['subject_data']; @endphp;
            subjecthtml += "<select id='subject_id' class='form-control'>";
            subjecthtml += "<option value='0'>Select Subject</option>";
            // for (var key in subject_data) {
            //         subjecthtml+= "<option value="+subject_data[key].sub_id+">"+subject_data[key].sub_name+"</option>";
            // }
            subjecthtml += "</select>";

            var division_data = @php echo $data['division_data']; @endphp;
            divisionhtml += "<select id='division_id' class='form-control'>";
            divisionhtml += "<option value='0'>Select Division</option>";
            // for (var key in division_data) {
            //         divisionhtml+= "<option value="+division_data[key].div_id+">"+division_data[key].div_name+"</option>";
            // }
            divisionhtml += "</select>";

            form.append("<div class='row'></div>");
            form.find(".row")
                .append("<div class='col-md-6'><div class='form-group'><label class='control-label'>Standard</label>" + standardhtml + "</div></div>")
                .append("<div class='col-md-6'><div class='form-group'><label class='control-label'>Subject</label>" + subjecthtml + "</div></div>")
                .append("<div class='col-md-6'><div class='form-group'><label class='control-label'>Division</label>" + divisionhtml + "</div></div>")
                .append("<div class='col-md-6'><div class='form-group'><label class='control-label'>Title</label><input class='form-control' placeholder='Insert Lesson Title' type='text' id='title'/></div></div>")
                .append("<div class='col-md-6'><div class='form-group'><label class='control-label'>Description</label><textarea class='form-control' placeholder='Insert Lesson Description' id='description'></textarea></div></div>")
                .append("<input class='form-control' type='hidden' id='school_date' value='" + start + "'/>")
                .append("</div></div>");

            $this.$modal.find('.delete-event').hide().end().find('.save-event').show().end().find('.modal-body').empty().prepend(form).end().find('.save-event').unbind('click').click(function () {
                form.submit();
            });

            $this.$modal.find('form').on('submit', function () {

                var standard_id = form.find("select[id='standard_id']  option:checked").val();
                var subject_id = form.find("select[id='subject_id']  option:checked").val();
                var division_id = form.find("select[id='division_id']  option:checked").val();
                var title = form.find("input[id='title']").val();
                var school_date = form.find("input[id='school_date']").val();
                var description = form.find("textarea").val();
                var path = "{{ route('lessonplanning.store') }}";
                if (title == null || title == "") {
                    alert("Please Fill Proper Values");
                    return false;
                }

                $.ajax({
                    url: path,
                    type: 'POST',
                    data: {
                        'standard_id': standard_id,
                        'subject_id': subject_id,
                        'division_id': division_id,
                        'title': title,
                        'description': description,
                        'school_date': school_date
                    },
                    success: function (result) {
                        //if (title !== null && title.length != 0) {
                        // $this.$calendarObj.fullCalendar('renderEvent', {
                        //     title: title,
                        //     start:start,
                        //     end: end,
                        //     allDay: false
                        // }, true);
                        window.location.href = "{{ route('lessonplanning.index') }}";
                        $this.$modal.modal('hide');
                        // }
                        //else{
                        // alert('You have to give a title to your event');
                        //}

                    }
                });
                return false;

            });
            $this.$calendarObj.fullCalendar('unselect');
    },
    CalendarApp.prototype.enableDrag = function() {
        //init events
        $(this.$event).each(function () {
            // create an Event Object (http://arshaw.com/fullcalendar/docs/event_data/Event_Object/)
            // it doesn't need to have a start or end
            var eventObject = {
                title: $.trim($(this).text()) // use the element's text as the event title
            };
            // store the Event Object in the DOM element so we can get to it later
            $(this).data('eventObject', eventObject);
            // make the event draggable using jQuery UI
            $(this).draggable({
                zIndex: 999,
                revert: true,      // will cause the event to go back to its
                revertDuration: 0  //  original position after the drag
            });
        });
    }
    /* Initializing */
    CalendarApp.prototype.init = function () {
        this.enableDrag();
        /*  Initialize the calendar  */
        var date = new Date();
        var d = date.getDate();
        var m = date.getMonth();
        var y = date.getFullYear();
        var form = '';
        var today = new Date($.now());

        var defaultEvents =  @php echo $data['calendarData']; @endphp;
        var edit_value = @php echo $data['editable']; @endphp;
        var $this = this;

        if (edit_value == true) {
            $this.$calendarObj = $this.$calendar.fullCalendar({
                slotDuration: '00:15:00', /* If we want to split day time each 15minutes */
                minTime: '08:00:00',
                maxTime: '19:00:00',
                defaultView: 'month',
                handleWindowResize: true,
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: ''
                },
                events: defaultEvents,
                //editable: true,
                droppable: true, // this allows things to be dropped onto the calendar !!!
                eventLimit: true, // allow "more" link when too many events
                selectable: true,
                eventRender: function (event, element) {
                    element.find('.fc-title').append("<br/>" + event.standard_name + ' / ' + event.division_name + ' / ' + event.subject_name);
                },
                drop: function (date) {
                    $this.onDrop($(this), date);
                },
                select: function (start, end, allDay) {
                    $this.onSelect(start, end, allDay);
                },
                eventClick: function (calEvent, jsEvent, view) {
                    $this.onEventClick(calEvent, jsEvent, view);
                }

            });
        } else {
            $this.$calendarObj = $this.$calendar.fullCalendar({
                slotDuration: '00:15:00', /* If we want to split day time each 15minutes */
                minTime: '08:00:00',
                maxTime: '19:00:00',
                defaultView: 'month',
                handleWindowResize: true,
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: ''
                },
                events: defaultEvents,
                //editable: false,
                droppable: false, // this allows things to be dropped onto the calendar !!!
                eventLimit: true, // allow "more" link when too many events
                selectable: false,
                eventRender: function (event, element) {
                    element.find('.fc-title').append("<br/>" + event.standard_name + ' / ' + event.division_name + ' / ' + event.subject_name);
                    element.find('.fc-title').append("<br/>" + event.teacher_name);
                }
            });
        }

        //on new event
        this.$saveCategoryBtn.on('click', function () {
            var categoryName = $this.$categoryForm.find("input[name='category-name']").val();
            var categoryColor = $this.$categoryForm.find("select[name='category-color']").val();
            if (categoryName !== null && categoryName.length != 0) {
                $this.$extEvents.append('<div class="calendar-events" data-class="bg-' + categoryColor + '" style="position: relative;"><i class="fa fa-circle text-' + categoryColor + '"></i>' + categoryName + '</div>')
                $this.enableDrag();
            }

        });
    },

   //init CalendarApp
    $.CalendarApp = new CalendarApp, $.CalendarApp.Constructor = CalendarApp

}(window.jQuery),

//initializing CalendarApp
        function ($) {
            "use strict";
            $.CalendarApp.init()
        }(window.jQuery);

    function bind_subject_division(std_id, sub_id = "", div_id = "") {
        var path = "{{ route('ajax_getlp_subject') }}";
        $('#subject_id').find('option').remove().end().append('<option value="0">Select Subject</option>').val('');
        $.ajax({
            url: path, data: 'standard_id=' + std_id, success: function (result) {
                for (var i = 0; i < result.length; i++) {
                    if (sub_id == result[i]['sub_id']) {
                        $("#subject_id").append($("<option selected></option>").val(result[i]['sub_id']).html(result[i]['sub_name']));
                    } else {
                        $("#subject_id").append($("<option></option>").val(result[i]['sub_id']).html(result[i]['sub_name']));
                    }
                }
            }
        });

        var path = "{{ route('ajax_getlp_division') }}";
        $('#division_id').find('option').remove().end().append('<option value="0">Select Division</option>').val('');
        $.ajax({
            url: path, data: 'standard_id=' + std_id, success: function (result) {
                for (var i = 0; i < result.length; i++) {
                    if (div_id == result[i]['div_id']) {
                        $("#division_id").append($("<option selected></option>").val(result[i]['div_id']).html(result[i]['div_name']));
                    } else {
                        $("#division_id").append($("<option></option>").val(result[i]['div_id']).html(result[i]['div_name']));
                    }
                }
            }
        });

    }
</script>
@include('includes.footer')
@endsection
