@include('includes.headcss')
    <link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">      
            <div class="card">
                <div class="row">
                    @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                    @endif
                       
                    <div class="col-lg-12 col-sm-12 col-xs-12">  
                        <form action="
                          @if (isset($data))
                          {{ route('add_hostel_visitor_master.update', $data->id) }}
                          @else
                          {{ route('add_hostel_visitor_master.store') }}
                          @endif

                          " method="post">

                        @if(!isset($data))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif


                        {{csrf_field()}}
                        <div class="row">    
                            <div class="col-md-4 form-group">
                                <label>Name </label>
                                <input type="text" id='name' required name="name" value="@if(isset($data->name)) {{ $data->name }} @endif" class="form-control">
                            </div>
                            
                            <div class="col-md-4 form-group">
                                <label>Contact </label>
                                <input type="text" id='contact' required name="contact" value="@if(isset($data->contact)) {{ $data->contact }} @endif" class="form-control">
                            </div>
                        
                            <div class="col-md-4 form-group">
                                <label>Email </label>
                                <input type="email" id='email' required name="email" value="@if(isset($data->email)) {{ $data->email }} @endif" class="form-control">
                            </div>
                        
                            <div class="col-md-4 form-group">
                                <label>Coming From </label>
                                <input type="text" id='coming_from' required name="coming_from" value="@if(isset($data->coming_from)) {{ $data->coming_from }} @endif" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">                   
                                <label class="control-label">To Meet</label>
                                <select class="form-control" name="to_meet">
                                    <option value="">Select Student</option>
                                        <option value="">1</option>
                                            <option value="">2</option>
                                                <option value="">3</option>
                                </select>
                            </div>
                        
                            <div class="col-md-4 form-group">
                                <label>Relation With </label>
                                <input type="text" id='relation' required name="relation" value="@if(isset($data->relation)) {{ $data->relation }} @endif" class="form-control">
                            </div>
                        
                            <div class="col-md-4 form-group">
                                <label>Meet Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy" value="@if(isset($data->meet_date)){{date('d-m-Y', strtotime($data->meet_date))}}@endif" name="meet_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span> 
                                </div>
                            </div>
                      
                            <div class="col-md-4 form-group">
                                <label>Checkin Time </label>
                                <div class="input-group clockpicker " data-placement="bottom" data-align="top" data-autoclose="true">
                                    <input type="text" id='in_time' required name="in_time" class="form-control" value="@if(isset($data->in_time)) {{ $data->in_time }} @endif"> 
                                    <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                                </div>
                            </div>
                        
                            <div class="col-md-4 form-group">
                                <label>Checkout Time </label>
                                <div class="input-group clockpicker " data-placement="bottom" data-align="top" data-autoclose="true">
                                    <input type="text" id='out_time' required name="out_time" class="form-control" value="@if(isset($data->out_time)) {{ $data->out_time }} @endif"> 
                                    <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                                </div>
                            </div>

                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
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
@include('includes.footer')
