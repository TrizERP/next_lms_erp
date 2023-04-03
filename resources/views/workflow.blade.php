
<!-- Wizard CSS -->
<link href="{{ asset('../plugins/bower_components/jquery-wizard-master/steps.css') }}" rel="stylesheet">
<link href="{{ asset('/plugins/bower_components/summernote/dist/summernote.css') }}" rel="stylesheet" />
@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Workflow</h4>
            </div>
        </div>
        <div class="row">
            <div class="white-box">
                <div class="panel-body">


                    <div class="row">
                        <div class="col-12">
                            <div class="white-box">
                                <div class="card-body wizard-content">
                                    <form action="{{ route('workflow.store') }}" id="wkform" name="wkform" class="tab-wizard wizard-circle" enctype="multipart/form-data" method="post">
                                    <!-- Step 1 -->
                                        @csrf
                                        <h6>Schedule Workflow</h6>
                                        <section>
                                            <h5 class="card-title" style="font-weight: bold;">Step 1: Enter basic
                                                details of the Workflow</h5>
                                            <br>
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label for="wkname">Select Module :</label>
                                                        <select name="wkname" id="wkname"
                                                                class="custom-select form-control required"
                                                                onchange="bind_condition_field(this.value);">
                                                            <option value="">Select</option>
                                                            @foreach($data['module_data'] as $key =>$val)
                                                                @php
                                                                    $selected = '';
                                                                    if( isset($data['module_data']) && $data['module_data'] == $val->id )
                                                                    {
                                                                        $selected = 'selected';
                                                                    }
                                                                @endphp
                                                                <option
                                                                    {{$selected}} value="{{$val->modulename}}">{{$val->modulename}}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label for="wkdescription">Description :</label>
                                                        <textarea class="form-control" name="wkdescription"></textarea>
                                                    </div>
                                                </div>
                                                <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="wkexecute">Specify when to execute this workflow :</label>

													<div class="m-b-10">
                                                        <div class="radio radio-info">
                                                            <input type="radio" name="wkexecute" id="rd-1" value="1" class="execute_radio">
                                                            <label for="rd-1"> Only on the first save </label>
                                                        </div>
                                                        <div class="radio radio-info">
                                                            <input type="radio" name="wkexecute" id="rd-2" value="2" class="execute_radio">
                                                            <label for="rd-2"> Until the first time the condition is true </label>
                                                        </div>
														<!--div class="radio radio-info">
                                                            <input type="radio" name="wkexecute" id="rd-2" value="3" class="execute_radio">
                                                            <label for="rd-2"> Every time the record is saved </label>
                                                        </div-->
														<!--div class="radio radio-info">
                                                            <input type="radio" name="wkexecute" id="rd-2" value="4" class="execute_radio">
                                                            <label for="rd-2"> Every time a record is modified </label>
                                                        </div-->
														<div class="radio radio-info">
                                                            <input type="radio" name="wkexecute" id="rd-2" value="5" class="execute_radio">
                                                            <label for="rd-2"> Schedule</label>
                                                        </div>
														<div style="display:none;" id="schedule_details" class="white-box">
															<div class="form-group">
																<label for="schedule_run">Run Workflow :</label>
																<select name="schedule_run" id="schedule_run" onclick="datetime_details(this.value);">
																	<!--option value="Hourly">Hourly</option-->
																	<option value="Daily">Daily</option>
																	<option value="Weekly">Weekly</option>
																	<option value="SpecificDate">On Specific Date</option>
																	<option value="MonthlyDate">Monthly by Date</option>
																	<!--option value="yearly">Yearly</option-->
																</select>
																<br>

																<div id="weekly_div">
																	<label for="days" class="control-label">Weekdays :</label>
																	<select name="week_days[]" id="week_days" multiple>
																		<option value="Monday">Monday</option>
																		<option value="Tuesday">Tuesday</option>
																		<option value="Wednesday">Wednesday</option>
																		<option value="Thurday">Thurday</option>
																		<option value="Friday">Friday</option>
																		<option value="Saturday">Saturday</option>
																		<option value="Sunday">Sunday</option>
																	</select>
																</div>
																<br>

                                                                <div id="specificdate_div">
                                                                    <label for="days" class="control-label">Choose Date
                                                                        :</label>
                                                                    <input class="datepicker" id="specific_date"
                                                                           name="specific_date" type="text"
                                                                           placeholder="mm/dd/yyyy">
                                                                    <!--input type="text" name="specific_date" id="datepicker-autoclose" placeholder="mm/dd/yyyy"-->
                                                                </div>
                                                                <br>

                                                                <div id="MonthlyDate_div">
                                                                    <label for="days" class="control-label">Choose Days
                                                                        :</label>
                                                                    <select name="month_days[]" id="month_days"
                                                                            multiple>
                                                                        @php
																		for($i=1;$i<=30;$i++)
																		{
																			echo "<option value='".$i."'>$i</option>";
																		}
																		@endphp
                                                                    </select>
                                                                </div>
                                                                <br>

                                                                <div id="at_time_div">
                                                                    <label for="schedule_run">At Time :</label>
                                                                    <select name="at_time" id="at_time">
                                                                        <option value="">Select</option>
                                                                        <option value="00.00">00.00</option>
                                                                        <option value="00.30">00.30</option>
                                                                        <option value="1.00">1.00</option>
                                                                        <option value="1.30">1.30</option>
                                                                        <option value="2.00">2.00</option>
                                                                        <option value="2.30">2.30</option>
                                                                        <option value="3.00">3.00</option>
                                                                        <option value="3.30">3.30</option>
                                                                        <option value="4.00">4.00</option>
																		<option value="4.30">4.30</option>
                                                                        <option value="5.00">5.00</option>
                                                                        <option value="5.30">5.30</option>
                                                                        <option value="6.00">6.00</option>
                                                                        <option value="6.30">6.30</option>
                                                                        <option value="7.00">7.00</option>
                                                                        <option value="7.30">7.30</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                                </div>
                                            </div>
                                        </section>
                                        <!-- Step 2 -->
                                        <h6>Add Conditions</h6>
                                        <section>
                                            <h5 class="card-title" style="font-weight: bold;">Step 2: Choose filter
                                                conditions</h5>
                                            <br>

                                            <div class="row">
                                                <div class="col-md-2">
                                                    <div class="form-group">
													<a href="javascript:void(0);" onclick="addNewRow();"><span class="circle bg-success di form-control">Add Condition</span></a>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="addButtonCheckbox">
                                            </div>

                                            <div class="hiddenaddButtonCheckbox" style="display:none;">
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <select name="fieldname[]"
                                                                    class="custom-select form-control required cls_fieldname">
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <select name="fieldcondition[]"
                                                                    class="custom-select form-control required">
                                                                <option value="is">is</option>
                                                                <option value="contains">contains</option>
                                                                <option value="doesnotcontains">does not contains
                                                                </option>
                                                                <option value="startswith">starts with</option>
                                                                <option value="endswith">ends with</option>
                                                                <!--option value="haschanged">has changed</option-->
                                                                <option value="isempty">is empty</option>
                                                                <option value="isnotempty">is not empty</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <input type="text" name="fieldvalue[]" class="form-control">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <div class="form-group">
                                                            <select name="conditiontype[]" class="form-control">
                                                                <option value="and">AND</option>
                                                                <option value="or">OR</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-1 form-group">
                                                        <a href="javascript:void(0);" onclick="deleteRow(this);"><span
                                                                class="circle circle-sm bg-alert di form-control"><i
                                                                    class="ti-minus"></i></span></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                                        <!-- Step 3 -->
                                        <h6>Add Task</h6>
                                        <section>
                                            <h5 class="card-title" style="font-weight: bold;">Step 3: Add Action</h5>
                                            <br>
                                            <div class="row">
                                                <div class="col-md-6">

                                                    <div class="form-group">
                                                        <label for="task">Add To Do :</label>
                                                        <select class="custom-select form-control" id="wktask"
                                                                name="wktask" onchange="show_modal(this.value);">
                                                            <option value="">Select</option>
                                                            <option value="Mail">Send Mail</option>
                                                            <option value="SMS">Send SMS</option>
                                                            <option value="UpdateQuery">Update Query</option>
                                                        </select>
                                                    </div>

                                                    <div id="mail_data" class="col-lg-12 col-sm-12 col-xs-12">
                                                    </div>

                                                    <!-- START Mail Modal -->
                                                    <!--MAIL Modal -->
                                                    <div class="modal fade" id="mailmodalForm" role="dialog">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <!-- Modal Header -->
                                                                <div class="modal-header">
                                                                    <button type="button" class="close"
                                                                            data-dismiss="modal">
                                                                        <span aria-hidden="true">&times;</span>
                                                                        <span class="sr-only">Close</span>
                                                                    </button>
                                                                    <h4 class="modal-title" id="myModalLabel">Add Task
                                                                        for Workflow > Send Mail</h4>
                                                                </div>
                                                                <!-- Modal Body -->
                                                                <div class="modal-body">
                                                                    <p class="statusMsg"></p>
                                                                    <form role="form" name="MailForm" id="MailForm"
                                                                          method="post" action="">
                                                                        @csrf
																	<input type="hidden" name="hiddenmain_id" id="hiddenmain_id" />
																	<div class="form-group">
																		<label for="inputName">From</label>
																		<input type="text" class="form-control" id="send_from" name="send_from"/>
																	</div>
                                                                        <div class="form-group">
                                                                            <label for="inputEmail">To</label>
                                                                            <input type="email" class="form-control"
                                                                                   id="send_to" name="send_to"/>
                                                                        </div>
                                                                        <div class="form-group">
                                                                            <label for="inputMessage">Subject</label>
                                                                            <input type="text" class="form-control"
                                                                                   id="subject" name="subject"/>
                                                                        </div>
                                                                        <div class="form-group">
                                                                            <label for="inputMessage">Add Fields</label>
                                                                            <select name="mail_fieldname[]"
                                                                                    class="custom-select form-control required cls_fieldname"
                                                                                    onchange="appendcontent(this.value);">
                                                                            </select>
                                                                        </div>
                                                                        <div class="form-group">
                                                                            <label for="inputMessage">Content</label>
                                                                            <textarea class="summernote" name="content"
                                                                                      id="content"></textarea>
                                                                            </select>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                                <!-- Modal Footer -->
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-default"
                                                                            data-dismiss="modal">Close
                                                                    </button>
                                                                    <button type="button"
                                                                            class="btn btn-primary submitBtn"
                                                                            name="mailformsubmit" id="mailformsubmit">
                                                                        SUBMIT
                                                                    </button>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- END Mail Modal -->

                                                    <!-- START Update Query Modal -->
                                                    <div class="modal fade" id="updmodalForm" role="dialog">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <!-- Modal Header -->
                                                                <div class="modal-header">
                                                                    <button type="button" class="close"
                                                                            data-dismiss="modal">
                                                                        <span aria-hidden="true">&times;</span>
                                                                        <span class="sr-only">Close</span>
                                                                    </button>
                                                                    <h4 class="modal-title" id="myModalLabel">Add Task
                                                                        for Workflow > Update Query</h4>
                                                                </div>
                                                                <!-- Modal Body -->
                                                                <div class="modal-body">
                                                                    <p class="statusMsg"></p>
                                                                    <form role="form" name="updForm" id="updForm"
                                                                          method="post" action="">
                                                                        @csrf
																	<input type="hidden" name="upd_hiddenmain_id" id="upd_hiddenmain_id" />
																	<div class="row">
																		<div class="col-md-3">
																			<div class="form-group">
                                                                                <a href="javascript:void(0);"
                                                                                   onclick="upd_addNewRow();"><span
                                                                                        class="circle circle-sm bg-success di form-control">Add Field</span></a>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                        <div class="updCheckbox"></div>

                                                                        <div class="hiddenupdCheckbox"
                                                                             style="display:none;">
                                                                            <div class="row">
                                                                                <div class="col-md-3">
                                                                                    <div class="form-group">
                                                                                        <select name="upd_fieldname[]"
                                                                                                class="custom-select form-control required cls_fieldname">
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-3">
                                                                                    <div class="form-group">
                                                                                        <input type="text"
                                                                                               name="upd_fieldvalue[]"
                                                                                               class="form-control">
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-2 form-group">
                                                                                    <a href="javascript:void(0);"
                                                                                       onclick="upd_deleteRow(this);"><span
                                                                                            class="circle circle-sm bg-alert di form-control"><i
                                                                                                class="ti-minus"></i></span></a>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                                <!-- Modal Footer -->
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-default"
                                                                            data-dismiss="modal">Close
                                                                    </button>
                                                                    <button type="button"
                                                                            class="btn btn-primary submitBtn"
                                                                            name="updformsubmit" id="updformsubmit">
                                                                        SUBMIT
                                                                    </button>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- END Update Query Modal -->

                                                    <!-- START SMS Modal -->
                                                    <div class="modal fade" id="smsmodalForm" role="dialog">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <!-- Modal Header -->
                                                                <div class="modal-header">
                                                                    <button type="button" class="close"
                                                                            data-dismiss="modal">
                                                                        <span aria-hidden="true">&times;</span>
                                                                        <span class="sr-only">Close</span>
                                                                    </button>
                                                                    <h4 class="modal-title" id="myModalLabel">Add Task
                                                                        for Workflow > Send SMS</h4>
                                                                </div>
                                                                <!-- Modal Body -->
                                                                <div class="modal-body">
                                                                    <p class="statusMsg"></p>
                                                                    <form role="form" name="smsForm" id="smsForm"
                                                                          method="post" action="">
                                                                        @csrf
                                                                        <input type="hidden" name="sms_hiddenmain_id"
                                                                               id="sms_hiddenmain_id"/>
                                                                        <div class="form-group">
                                                                            <label for="inputName">Recepients</label>
                                                                            <input type="text" class="form-control"
                                                                                   id="recepients" name="recepients"
                                                                                   required/>
                                                                        </div>
                                                                        <div class="form-group">
                                                                            <label for="inputMessage">Add Fields</label>
                                                                            <select name="sms_fieldname[]"
                                                                                    class="custom-select form-control required cls_fieldname"
                                                                                    onchange="appendsmstext(this.value);">
                                                                            </select>
                                                                        </div>
                                                                        <div class="form-group">
                                                                            <label for="inputMessage">SMS Text</label>
                                                                            <textarea class="form-control"
                                                                                      name="smstext"
                                                                                      id="smstext"></textarea>
                                                                            </select>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                                <!-- Modal Footer -->
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-default"
                                                                            data-dismiss="modal">Close
                                                                    </button>
                                                                    <button type="button"
                                                                            class="btn btn-primary submitBtn"
                                                                            name="smsformsubmit" id="smsformsubmit">
                                                                        SUBMIT
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- END SMS Modal -->


                                                </div>
                                            </div>
                                        </section>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Validation wizard -->


                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<!-- Form Wizard JavaScript -->
<script src="{{ asset('/plugins/bower_components/moment/moment.js') }}"></script>
<script src="{{ asset('/plugins/bower_components/jquery-wizard-master/jquery.steps.min.js') }}"></script>
<script src="{{ asset('/plugins/bower_components/jquery-wizard-master/jquery.validate.min.js') }}"></script>
<script src="{{ asset('/plugins/bower_components/sweetalert/sweetalert.min.js') }}"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<!-- <script src="{{ asset('../plugins/bower_components/summernote/dist/summernote.min.js') }}"></script> -->

<script src="{{asset('/plugins/bower_components/summernote/dist/summernote.min.js')}}"></script>
<script>
    function addNewRow() {
        var ele = $('.hiddenaddButtonCheckbox').clone(true).attr('style', '').attr('class', 'addButtonCheckbox');
        $('.addButtonCheckbox:last').after(ele);
    }

    function deleteRow(ele) {
        $(ele).closest('.addButtonCheckbox').remove();
        //$(ele).parent().parent().remove();
    }

    function upd_addNewRow() {
        var ele = $('.hiddenupdCheckbox').clone(true).attr('style', '').attr('class', 'updCheckbox');
        $('.updCheckbox:last').after(ele);
    }

    function upd_deleteRow(ele) {
        $(ele).closest('.updCheckbox').remove();
    }

    function appendcontent(eval) {
        var old_content = $('#content').summernote('code');
        var new_content = old_content + " [" + eval.toUpperCase() + "] ";
        $('#content').summernote('code', new_content);
        //var cnt = $("#content");
        //cnt.val(cnt.val() + " [" + eval.toUpperCase() + "] ");
    }

    function appendsmstext(eval) {
        var st = $("#smstext");
        st.val(st.val() + " [" + eval.toUpperCase() + "] ");
    }

	$(document).ready(function(){
		$(".datepicker").datepicker();
		$('.summernote').summernote({
			height: 200, // set editor height
			minHeight: null, // set minimum height of editor
            maxHeight: null, // set maximum height of editor
            focus: false // set focus to editable area after initializing summernote
        });

        //$('#at_time_div').hide();
        $('#weekly_div').hide();
        $('#specificdate_div').hide();
        $('#MonthlyDate_div').hide();

        $('.execute_radio').click(function () {
            var val = $(this).attr("value");
            if (val == 5)//Schedule Selection
            {
                $('#schedule_details').attr('style', '')
            } else {
                $('#schedule_details').attr('style', 'display:none')
            }
        });

		//Mail Modal submit form
		$('#mailformsubmit').on('click', function(e) {
			e.preventDefault();

            var send_to = $("#send_to").val();
			var send_from = $("#send_from").val();
			var subject = $("#subject").val();
			var content = $("#content").val();
			var hiddenmain_id = $("#hiddenmain_id").val();

            var path = "{{ route('ajax_wk_savemail') }}";
            $.ajax({
                type: "POST",
                url: path,
                data: {
                    "send_to": send_to,
                    "send_from": send_from,
                    "subject": subject,
                    "content": content,
                    "hiddenmain_id": hiddenmain_id
                },
                success: function (response) {
                    $('#mailmodalForm').modal('hide');
                    $("#mail_data").html(response);
                },
                error: function () {
                    alert('Error');
                }
            });
            return false;
        });

        //Update Query Modal submit form
        $('#updformsubmit').on('click', function (e) {
            e.preventDefault();

            var path1 = "{{ route('ajax_wk_saveupdatequery') }}";
            $.ajax({
                type: "POST",
                url: path1,
                data: $("#updForm").serialize(),
                success: function (response) {
                    $('#updmodalForm').modal('hide');
                    $("#mail_data").html(response);
                },
                error: function () {
                    alert('Error');
                }
            });
            return false;
        });

        //SMS Modal submit form
        $('#smsformsubmit').on('click', function (e) {
            e.preventDefault();

            var path1 = "{{ route('ajax_wk_savesms') }}";
            $.ajax({
                type: "POST",
                url: path1,
                data: $("#smsForm").serialize(),
                success: function (response) {
                    $('#smsmodalForm').modal('hide');
                    $("#mail_data").html(response);
                },
                error: function () {
                    alert('Error');
                }
            });
            return false;
        });


    });

    function datetime_details(val) {
        if (val != "Hourly") {
            $('#at_time_div').show();
        } else {
			$('#at_time_div').hide();
		}

        if(val == "Weekly")
		{
			$('#weekly_div').show();
		}else{
			$('#weekly_div').hide();
		}

        if(val == "SpecificDate")
		{
			$("#specificdate_div").show();
		}
		else{
			$("#specificdate_div").hide();
		}

        if (val == "MonthlyDate") {
            $("#MonthlyDate_div").show();
        } else {
            $("#MonthlyDate_div").hide();
        }

    }

    function bind_condition_field(module_name) {
        var path = "{{ route('ajax_wk_modulewise_fields') }}";
        $('.cls_fieldname').find('option').remove().end();
        $.ajax({
            url: path, data: 'module_name=' + module_name, success: function (result) {
                for (var i = 0; i < result.length; i++) {
                    $(".cls_fieldname").append($("<option></option>").val(result[i]['fieldname']).html(result[i]['displayname']));
                }
            }
        });
    }

    function show_modal(val) {
        if (val == "Mail") {
            $('#mailmodalForm').modal({show: true});
        } else if (val == "UpdateQuery") {
            $('#updmodalForm').modal({show: true});
        } else if (val == "SMS") {
            $('#smsmodalForm').modal({show: true});
        }
    }

    //Custom design form example
    $(".tab-wizard").steps({
        headerTag: "h6",
        bodyTag: "section",
        transitionEffect: "fade",
        titleTemplate: '<span class="step">#index#</span> #title#',
        onStepChanging: function (event, currentIndex, priorIndex) {
            if (currentIndex == 0 && $("#wkname").val() == "") {
                alert("Please Select Module Name");
                return false;
            } else if (currentIndex == 1) {
                var go = 0;
                var form = $(this);
                // Submit form input
                //form.submit();
                var frm = $('#wkform');
                $.ajax({
                    type: 'post',
                    url: frm.attr('action'),
                    data: frm.serialize(),
                    success: function (data) {
                        var id = data;
                        $("#hiddenmain_id").val(id);
                        $("#upd_hiddenmain_id").val(id);
                        $("#sms_hiddenmain_id").val(id);
                        //go = 1;
                    },
                    error: function (data) {
                        //go = 0;
                    },
                });

                // setTimeout(function(){
                // alert(go);
                // if(go == 1)
                // {
                // return true;
                // }
                // else{
                // return false;
                // }
                // }, 3000);
                return true;
            } else {
                return true;
            }
        },
        labels: {
            finish: "Submit"
        },
        onFinished: function (event, currentIndex) {
            window.location.href = "{{ route('workflow.index')}}";
			//swal("Form Submitted!", "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed lorem erat eleifend ex semper, lobortis purus sed.");
		}
	});
</script>

@include('includes.footer')
