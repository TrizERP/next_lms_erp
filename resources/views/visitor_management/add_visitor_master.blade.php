@include('includes.headcss')
    <link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
	 <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">                                           
                    @if(!isset($data->name))
                    Add Visitor
                    @else
                    Edit Visitor
                    @endif                    
                </h4>
            </div>            
        </div>
        
            <div class="card">
                <div class="row">
                    @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                    @endif
                       
                    <div class="col-lg-12 col-sm-12 col-xs-12">  
                        <form enctype='multipart/form-data' action="
                          @if (isset($data->name))
                          {{ route('add_visitor_master.update', $data->id) }}
                          @else
                          {{ route('add_visitor_master.store') }}
                          @endif" method="post">

                        @if(!isset($data->name))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif

                        {{csrf_field()}}
                        	<div class="row">
								<div class="col-md-4 form-group">
									<label class="control-label">Appointment Type</label>
									<div class="radio-list">
										<label class="radio-inline p-0">
											<div class="radio radio-success">
												<input type="radio" name="appointment_type" id="direct" value="Direct" required onclick="show_date_time(this.value);"
												@if(isset($data->appointment_type)) 
													@if($data->appointment_type == 'Direct') checked @endif 
												@endif >
												<label for="direct">Direct Appointment</label>
											</div>
										</label>
										<label class="radio-inline">
											<div class="radio radio-success">
												<input type="radio" name="appointment_type" id="prior" value="Prior" required onclick="show_date_time(this.value);"
												@if(isset($data->appointment_type)) 
													@if($data->appointment_type == 'Prior') checked @endif 
												@endif >
												<label for="prior">Prior Appointment</label>
											</div>
										</label>
									</div>
								</div> 
																
								<div class="col-md-4 form-group">                   
									<label class="control-label">Visitor Type</label>
									<select class="form-control" name="visitor_type" required>
										<option value="">Select</option>
										@if(isset($data['visitor_type_data'])) 
											@foreach($data['visitor_type_data'] as $key => $value)
											<option value="{{$value['id']}}" @if(isset($data->visitor_type)) @if($data->visitor_type==$value['id']) selected='selected' @endif @endif>{{$value['title']}}</option>										
											@endforeach                      
										@endif     
									</select>
								</div>
								
								<div class="col-md-4 form-group">
									<label>Visitor Name </label>
									<input type="text" id='name' required name="name" value="@if(isset($data->name)) {{ $data->name }} @endif" class="form-control">									
									<input type="hidden" id='hid_exit_msg_sent' name="hid_exit_msg_sent" value="@if(isset($data->exit_msg_sent)){{$data->exit_msg_sent}}@endif" class="form-control">									
								</div>
								
								<div class="col-md-4 form-group">
									<label>Visitor Contact </label>	
									
									<input onkeypress="return isNumber(event)" type="text" id='contact' required name="contact" value='@if(isset($data->contact)){{$data->contact}}@endif' class="form-control">
									<span style="float:left;color:red;" id="errorMsg"></span>
								</div>							
																								
								<div class="col-md-4 form-group">
									<label>Visitor Email </label>
									<input type="email" id='email' required name="email" value="@if(isset($data->email)) {{ $data->email }} @endif" class="form-control">
								</div>
							
								<div class="col-md-4 form-group">
									<label>Coming From </label>
									<input type="text" id='coming_from' required name="coming_from" value="@if(isset($data->coming_from)) {{ $data->coming_from }} @endif" class="form-control">
								</div>

								<div class="col-md-4 form-group">                   
									<label class="control-label">To Meet</label>
									<select class="form-control" name="to_meet">
										<option value="">Select</option>
										@if(isset($data['to_meet_array'])) 
											@foreach($data['to_meet_array'] as $key => $value)
											<option value="{{$value['id']}}" @if(isset($data->to_meet)) @if($data->to_meet == $value['id']) selected='selected' @endif @endif>{{$value['staff_name']}}</option>																				
											@endforeach                      
										@endif 
									</select>
								</div>																
								
								<div class="col-md-4 form-group">
									<label>Relation With </label>
									<input type="text" id='relation' required name="relation" value="@if(isset($data->relation)) {{ $data->relation }} @endif" class="form-control">
								</div>
								
								<div class="col-md-4 form-group">
									<label>Purpose </label>
									<textarea id='purpose' required name="purpose" class="form-control">@if(isset($data->purpose)) {{ $data->purpose }} @endif</textarea>
								</div>
								
								<div class="col-md-4 form-group">
									<label>Visitor Id Card No. </label>
									<input type="text" id='visitor_idcard' required name="visitor_idcard" value="@if(isset($data->visitor_idcard)) {{ $data->visitor_idcard }} @endif" class="form-control">
								</div>
																
								<div class="col-md-4 form-group">
									<label>Visitor Photo</label>
									<input type="file" id='visitor_photo' name="visitor_photo" class="form-control">
									@php
									if(isset($data->photo) && $data->photo !="")
									{
										echo "<img src='/storage/visitor_photo/$data->photo' height='80' width='80'>";
										echo "<input type='hidden' name='hid_photo' value='/$data->photo'>";										
									}								
									@endphp
								</div>

								<div class="col-md-4 form-group">
									<label>Meet Date</label>
									<div class="input-daterange input-group" id="date-range">
										<input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy" value="@if(isset($data->meet_date)){{$data->meet_date}}@endif" name="meet_date" id="meet_date" autocomplete="off">
										<span class="input-group-addon"><i class="icon-calender"></i></span> 
									</div>
								</div>
						  
								<input type="hidden" name="hid_out_time" id="hid_out_time" value="@if(isset($data->out_time)){{$data->out_time}}@endif">

								<div class="col-md-4 form-group ml-0 mr-auto">
									<label>Checkin Time </label>
									<div class="input-group clockpicker " data-placement="bottom" data-align="top" data-autoclose="true">
										<input type="text" id='in_time' required name="in_time" class="form-control" value="@if(isset($data->in_time)) {{ $data->in_time }} @endif"> 
										<span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
									</div>
								</div>	
								
	                            <!--div class="col-md-4 form-group">
	                                <label>Checkout Time </label>
	                                <div class="input-group clockpicker " data-placement="bottom" data-align="top" data-autoclose="true">
	                                    <input type="text" id='out_time' name="out_time" class="form-control" value="@if(isset($data->out_time)) {{ $data->out_time }} @endif"> 
	                                    <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
	                                </div>
	                            </div-->							

	                            <div class="col-md-12 form-group">
	                                <center>
	                                    <input type="submit" name="submit" value="Save" class="btn btn-success" onclick="return ValidateNo();">
	                                </center>
	                            </div>
	                        </div>

                        </form>
                    </div>

                    @if (count($errors) > 0)
                    <div class="alert alert-danger">
                        <strong>Whoops!</strong> There were some problems with your input.<br><br>
                        <ul>
                            @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
            </div>            
    </div>
</div>

@include('includes.footerJs')
<script>
function isNumber(evt) {
	evt = (evt) ? evt : window.event;
	var charCode = (evt.which) ? evt.which : evt.keyCode;
	if (charCode > 31 && (charCode < 48 || charCode > 57)) {
		var errorMsg = document.getElementById("errorMsg");
		errorMsg.style.display = "block";
		document.getElementById("errorMsg").innerHTML = "  Please enter only Numbers.  ";
		return false;
	}

	return true;
}

function ValidateNo() {
	var phoneNo = document.getElementById('contact');
	var errorMsg = document.getElementById("errorMsg");	

	if (phoneNo.value == "" || phoneNo.value == null) {
		errorMsg.style.display = "block";		
		document.getElementById("errorMsg").innerHTML = "  Please enter your Mobile No.  ";
		return false;
	}
	if (phoneNo.value.length < 10 || phoneNo.value.length > 10) {
		errorMsg.style.display = "block";		
		document.getElementById("errorMsg").innerHTML = "  Mobile No. is not valid, Please Enter 10 Digit Mobile No. ";
		return false;
	}
		
	errorMsg.style.display = "none";
	return true;
}

function show_date_time(type)
{
    if(type == 'Direct')//Show Date and Time
    {
		//$('.clockpicker').clockpicker('hide');
        $("#meet_date").prop('disabled',true);        
        $("#in_time").prop('disabled',true);        
        $("#out_time").prop('disabled',true);        
    }else{ //Hide Date And Time
        $("#meet_date").prop('disabled',false);  
		$("#in_time").prop('disabled',false);        
        $("#out_time").prop('disabled',false);        
    }
}
</script>
@include('includes.footer')
