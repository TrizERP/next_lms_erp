@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Academic Calendar</h4>
            </div>
        </div>
        <div class="card">
            @if(!empty($data['message']))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-md-12">
                    <div class="white-box">
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>
            <div class="modal fade none-border" id="my-event">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                            <h4 class="modal-title"><strong>Academic Calendar</strong></h4>
                        </div>
                        <div class="modal-body">
                        </div>
                        <div class="modal-footer">
                            <!-- <button type="button" class="btn btn-white waves-effect" data-dismiss="modal">Close</button> -->
                            <button type="submit" class="btn btn-success save-event waves-effect waves-light">Save</button>
                            <!-- <button type="button" class="btn btn-danger delete-event waves-effect waves-light" data-dismiss="modal">Delete</button> -->
                        </div>
                    </div>
                </div>
            </div>
            <!-- Modal Start -->
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
                            <button type="button" class="btn btn-danger waves-effect waves-light save-category" data-dismiss="modal">Save</button>
                            <button type="button" class="btn btn-white waves-effect" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Modal End -->
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="/plugins/bower_components/calendar/jquery-ui.min.js"></script>
<script src="/plugins/bower_components/moment/moment.js"></script>
<script src='/plugins/bower_components/calendar/dist/fullcalendar.min.js'></script>
<script src="/plugins/bower_components/calendar/dist/jquery.fullcalendar.js"></script>
<script>
    var standard = "<div class='form-group'>";
    standard = standard + "<label class='control-label'>Standard</label>";
    standard = standard + "    <select class='form-control' id='std' multiple name='standard[]' required>";
    <?php foreach ($data['standardData'] as $id => $val) { ?>
        standard = standard + "        <option value='<?php echo $id; ?>'><?php echo $val; ?></option>";
    <?php } ?>
    standard = standard + "    </select></div>";

    // alert(standard);
    ! function($) {
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
        //    CalendarApp.displayEventTime: false;
        /* on drop */
        CalendarApp.prototype.onDrop = function(eventObj, date) {
                //        alert('drop');
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
                //                alert('onEventClick');
                console.log(calEvent);
                var $this = this;
                var form = $("<form id='update_item_form'></form>");
                form.append("<input type='hidden' name='_token' value='{{ csrf_token() }}'>")
                form.append("<label>Event Name</label>");
                form.append("<input type='hidden' name='school_date' id='school_date' value='" + calEvent.start + "'>");
                form.append("<input class='form-control ' name='title' type=text value='" + calEvent.title + "' /><br>");
                form.append("<label>Event Description</label>");
                form.append("<div class='input-group'><textarea name='description' class='form-control'>" + calEvent.description + "</textarea><br><br></div>")
                    .append("<div class='std' id='std'></div><br>");
                form.append("<label class='control-label'>Event Type</label>");
                form.append("<div class='input-group'><select class='form-control' id='et' name='event_type' required></select><br></div>")
                    .find("select[name='event_type']")
                    .append("<option selected=selected value='holiday'>Holiday</option>")
                    .append("<option value='vacation'>Vacation</option>")
                    .append("<option value='event'>Event</option>")
                    .val(calEvent.event_type);
                form.append("<input type='hidden' id='hid_id' value='" + calEvent.id + "'>");
                form.append("<div class='modal-footer'><button type='submit' class='btn btn-success save-event waves-effect waves-light'>Save</button><button type='button' class='btn btn-danger delete-event waves-effect waves-light' data-dismiss='modal'>Delete</button></div></div>");

                form.find(".std")
                    .append(standard);
                if (calEvent.standard != '') {
                    var allStd = $.parseJSON(calEvent.standard);
                    form.find("#std").val(allStd);
                }


                $this.$modal.modal({
                    backdrop: 'static'
                });
                $this.$modal.find('.delete-event').show().end().find('.save-event').hide().end().find('.modal-body').empty().prepend(form).end().find('.delete-event').unbind('click').click(function() {
                    var hid_id = form.find("input[id='hid_id']").val();
                    // var hostname = "https://" + window.location.host;
                    var hostname = "{{ env('APP_URL') }}";

                    var path = hostname + "calendar/calendar/" + hid_id;
                    //                    alert(path);
                    $.ajax({
                        url: path,
                        type: 'DELETE',
                        success: function(result) {
                            //window.location.href = "{{ route('calendar.index') }}";
                            $this.$modal.modal('hide');
                        }
                    });
                    $this.$calendarObj.fullCalendar('removeEvents', function(ev) {
                        return (ev._id == calEvent._id);
                    });
                    $this.$modal.modal('hide');
                });

                //                $this.$modal.find('form').on('submit', function () {
                $this.$modal.find('.save-event').unbind('click').click(function() {
                    form.submit();
                });

                $this.$modal.find('form').on('submit', function() {
                    var hid_id = form.find("input[id='hid_id']").val();
                    // var hostname = "https://" + window.location.host;
                    var hostname = "{{ env('APP_URL') }}";

                    var path = hostname + "calendar/calendar/" + hid_id;
                    //                    if (title == null || title == "") {
                    //                        alert("Please Fill Proper Values");
                    //                        return false;
                    //                    }
                    // alert(hid_id);
                    // alert(path);

                    // alert(hostname);
                    $.ajax({
                        type: "PUT",
                        url: path,
                        data: $("#update_item_form").serialize(),
                        success: function(result) {
                            console.log(result);
                            window.location.href = "{{ route('calendar.index') }}";
                            // return false;
                        }
                    });
                    //alert("onupdate");

                    calEvent.title = form.find("input[type=text]").val();
                    $this.$calendarObj.fullCalendar('updateEvent', calEvent);
                    $this.$modal.modal('hide');
                    return false;
                });
            },
            /* on select */
            CalendarApp.prototype.onSelect = function (start, end, allDay) {
                //                alert('onselect');
                var $this = this;
                $this.$modal.modal({
                    backdrop: 'static'
                });
                var form = $("<form id=add_item_form></form>");
                form.append("<input type='hidden' name='_token' value='{{ csrf_token() }}'>")
                form.append("<div class='row'></div>");
                form.find(".row")
                    .append("<input class='form-control' type='hidden' name='school_date' id='school_date' value='" + start + "'/>")
                    .append("<div class='col-md-12'><div class='form-group'><label class='control-label'>Event Name</label><input class='form-control' placeholder='Insert Event Name' required type='text' name='title'/></div></div>")
                    .append("<div class='col-md-12'><div class='form-group'><label class='control-label'>Event Description</label><textarea class='form-control' placeholder='Description' type='text' name='description'></textarea></div></div>")
                    .append("<div class='col-md-12 std' id='std'></div>")
                    .append("<div class='col-md-12'><div class='form-group'><label class='control-label'>Event Type1</label><select class='form-control' name='event_type' required></select></div></div>")
                    .find("select[name='event_type']")
                    .append("<option selected=selected value='holiday'>Holiday</option>")
                    .append("<option value='vacation'>Vacation</option>")
                    .append("<option value='event'>Event</option></div></div>");
                form.find(".std")
                    .append(standard);

                // $("#std").html(standard);
                // .find(".std")
                // .append(standard);
                // alert(standard);


                $this.$modal.find('.delete-event').hide().end().find('.save-event').show().end().find('.modal-body').empty().prepend(form).end().find('.save-event').unbind('click').click(function() {
                    form.submit();
                });
                $this.$modal.find('form').on('submit', function() {
                    var title = form.find("input[name='title']").val();
                    var beginning = form.find("input[name='beginning']").val();
                    var ending = form.find("input[name='ending']").val();
                    //                    var categoryClass = form.find("select[name='category'] option:checked").val();
                    var categoryClass = "bg-info";
                    if (title !== null && title.length != 0) {
                        var path = "{{ route('calendar.store') }}";
                        $.ajax({
                            type: "POST",
                            url: path,
                            data: $("#add_item_form").serialize(),
                            success: function(data) {
                                console.log(data);
                                window.location.href = "{{ route('calendar.index') }}";
                            }
                        });
                        $this.$calendarObj.fullCalendar('renderEvent', {
                            title: title,
                            start: start,
                            end: end,
                            allDay: false,
                            className: categoryClass
                        }, true);
                        $this.$modal.modal('hide');
                    } else {
                        alert('Please enter event name');
                    }
                    return false;

                });
                $this.$calendarObj.fullCalendar('unselect');
            },
            CalendarApp.prototype.enableDrag = function() {
                //                alert('enabledrag');
                //init events
                $(this.$event).each(function() {
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
                        revert: true, // will cause the event to go back to its
                        revertDuration: 0 //  original position after the drag
                    });
                });
            }
        /* Initializing */
        CalendarApp.prototype.init = function() {
                //        this.enableDrag();
                //        alert("asdas");
                //        this.displayEventTime:false;
                /*  Initialize the calendar  */
                var date = new Date();
                var d = date.getDate();
                var m = date.getMonth();
                var y = date.getFullYear();
                var form = '';
                var today = new Date($.now());
                var defaultEvents = @php echo $data['calendarData'];
                @endphp;
                //        var defaultEvents = [
                //            {
                //                title: 'Released Ample Admin!',
                //                start: new Date($.now() + 506800000),
                //                className: 'bg-info'
                //            },
                //            {
                //                title: 'This is today check date',
                //                start: today,
                //                end: today,
                //                className: 'bg-danger'
                //            }, {
                //                title: 'This is your birthday',
                //                start: new Date($.now() + 848000000),
                //                className: 'bg-info'
                //            }, {
                //                title: 'your meeting with john',
                //                start: new Date($.now() - 1099000000),
                //                end: new Date($.now() - 919000000),
                //                className: 'bg-warning'
                //            }, {
                //                title: 'your meeting with john',
                //                start: new Date($.now() - 1199000000),
                //                end: new Date($.now() - 1199000000),
                //                className: 'bg-purple'
                //            }, {
                //                title: 'your meeting with john',
                //                start: new Date($.now() - 399000000),
                //                end: new Date($.now() - 219000000),
                //                className: 'bg-info'
                //            },
                //            {
                //                title: 'Hanns birthday',
                //                start: new Date($.now() + 868000000),
                //                className: 'bg-danger'
                //            }, {
                //                title: 'Like it?',
                //                start: new Date($.now() + 348000000),
                //                className: 'bg-success'
                //            }];

                var $this = this;
                $this.$calendarObj = $this.$calendar.fullCalendar({
                    slotDuration: '00:15:00',
                    /* If we want to split day time each 15minutes */
                    minTime: '08:00:00',
                    maxTime: '19:00:00',
                    defaultView: 'month',
                    handleWindowResize: true,

                    header: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'month,agendaWeek,agendaDay'
                    },
                    events: defaultEvents,
                    // editable: true,
                    droppable: true, // this allows things to be dropped onto the calendar !!!
                    eventLimit: true, // allow "more" link when too many events
                    selectable: true,
                    displayEventTime: false,
                    drop: function(date) {
                        $this.onDrop($(this), date);
                    },
                    select: function(start, end, allDay) {
                        $this.onSelect(start, end, allDay);
                    },
                    eventClick: function(calEvent, jsEvent, view) {
                        $this.onEventClick(calEvent, jsEvent, view);
                    }

                });

                //on new event
                this.$saveCategoryBtn.on('click', function() {
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
    function($) {
        "use strict";
        $.CalendarApp.init()
    }(window.jQuery);
</script>
@include('includes.footer')
