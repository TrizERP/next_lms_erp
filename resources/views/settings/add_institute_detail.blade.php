@extends('layout')
@section('container')
@include('includes.headcss')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Institute Detail</h4>
            </div>
        </div>       
        <div class="card">            
        <style>
            .inst-nav{
                margin-bottom: 0px !important;
            }
        </style>
            @if ($sessionData = Session::get('data')) 
                @if($sessionData['status_code'] == 1)
                    <div class="alert alert-success alert-block">
                @else
                    <div class="alert alert-danger alert-block">
                @endif
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
            @endif
           
        <center>       
            <ul class="nav nav-tabs tab-title mb-4 inst-nav">
                <li class="nav-item"><a href="#section-linemove-1" class="nav-link section-linemove-1 active" aria-selected="true" data-toggle="tab"><span>Institute Details</span></a></li>
                <li class="nav-item"><a href="#section-linemove-2" class="nav-link section-linemove-2" aria-selected="false" data-toggle="tab"><span>Add Departments</span></a></li>
                <li class="nav-item"><a href="#section-linemove-5" class="nav-link section-linemove-5" aria-selected="false" data-toggle="tab"><span>School Handbook</span></a></li>
                <li class="nav-item"><a href="#section-linemove-4" class="nav-link section-linemove-4" aria-selected="false" data-toggle="tab"><span>Organization Chart</span></a></li>
            </ul> 
        </center>

            <!-- Start tabs  -->
            <div class="tab-content">
                <!-- tab 1  -->
                <div class="tab-pane p-3 active" id="section-linemove-1" role="tabpanel">   
                    <form action="{{ route('institute_detail.store') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("POST") }}                       
                    @csrf
                        <div class="row">                
                            <div class="col-md-4 form-group">
                                <label>College Name </label>
                                <input type="text" id='college_name' name="college_name" class="form-control" value="@if(isset($data['data']['SchoolName'])){{$data['data']['SchoolName']}}@endif" readonly>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Principal Name</label>
                                <input type="text" id='principal_name' name="principal_name" class="form-control" value="@if(isset($data['data']['principal_name'])){{$data['data']['principal_name']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Principal Mobile</label>
                                <input type="text" id='principal_mobile' name="principal_mobile" class="form-control" value="@if(isset($data['data']['principal_mobile'])){{$data['data']['principal_mobile']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Manager Name</label>
                                <input type="text" id='manager_name' name="manager_name" class="form-control" value="@if(isset($data['data']['manager_name'])){{$data['data']['manager_name']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Manager Mobile</label>
                                <input type="text" id='manager_mobile' name="manager_mobile" class="form-control" value="@if(isset($data['data']['manager_mobile'])){{$data['data']['manager_mobile']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Location Condition</label>
                                <input type="text" id='college_location_condition' name="college_location_condition" class="form-control" value="@if(isset($data['data']['college_location_condition'])){{$data['data']['college_location_condition']}}@endif">
                            </div>                    
                            <div class="col-md-4 form-group">
                                <label>Total Seats Available for exam</label>
                                <input type="text" id='total_seats_for_exam' name="total_seats_for_exam" class="form-control" value="@if(isset($data['data']['total_seats_for_exam'])){{$data['data']['total_seats_for_exam']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Total Furniture</label>
                                <input type="text" id='total_furniture' name="total_furniture" class="form-control" value="@if(isset($data['data']['total_furniture'])){{$data['data']['total_furniture']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Electricity Condition</label>
                                <input type="text" id='electricity_condition' name="electricity_condition" class="form-control" value="@if(isset($data['data']['electricity_condition'])){{$data['data']['electricity_condition']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Generator/Inverter Condition</label>
                                <input type="text" id='generator_inverter_condition' name="generator_inverter_condition" class="form-control" value="@if(isset($data['data']['generator_inverter_condition'])){{$data['data']['generator_inverter_condition']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Drinking water condition</label>
                                <input type="text" id='drinking_water_condition' name="drinking_water_condition" class="form-control" value="@if(isset($data['data']['drinking_water_condition'])){{$data['data']['drinking_water_condition']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Toilet Condition</label>
                                <input type="text" id='toilet_condition' name="toilet_condition" class="form-control" value="@if(isset($data['data']['toilet_condition'])){{$data['data']['toilet_condition']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Fire Fighting Condition</label>
                                <input type="text" id='fire_fighting_condition' name="fire_fighting_condition" class="form-control" value="@if(isset($data['data']['fire_fighting_condition'])){{$data['data']['fire_fighting_condition']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Parking Condition</label>
                                <input type="text" id='parking_condition' name="parking_condition" class="form-control" value="@if(isset($data['data']['parking_condition'])){{$data['data']['parking_condition']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>School to road condition & distane</label>
                                <input type="text" id='school_to_road_condition_distance' name="school_to_road_condition_distance" class="form-control" value="@if(isset($data['data']['school_to_road_condition_distance'])){{$data['data']['school_to_road_condition_distance']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>CCTV Condition</label>
                                <input type="text" id='cctv_condition' name="cctv_condition" class="form-control" value="@if(isset($data['data']['cctv_condition'])){{$data['data']['cctv_condition']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Total Rooms (with size)</label>
                                <input type="text" id='total_rooms_with_size' name="total_rooms_with_size" class="form-control" value="@if(isset($data['data']['total_rooms_with_size'])){{$data['data']['total_rooms_with_size']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Store Room Condition</label>
                                <input type="text" id='storeroom_condition' name="storeroom_condition" class="form-control" value="@if(isset($data['data']['storeroom_condition'])){{$data['data']['storeroom_condition']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>College Boundary and Main Gate Condition</label>
                                <input type="text" id='college_boundary_gate_condition' name="college_boundary_gate_condition" class="form-control" value="@if(isset($data['data']['college_boundary_gate_condition'])){{$data['data']['college_boundary_gate_condition']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Is there Princial house inside college premises ?</label>
                                <input type="text" id='principal_house_inside_college' name="principal_house_inside_college" class="form-control" value="@if(isset($data['data']['principal_house_inside_college'])){{$data['data']['principal_house_inside_college']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Declared dibar ? If yes when ?</label>
                                <input type="text" id='declared_dibar' name="declared_dibar" class="form-control" value="@if(isset($data['data']['declared_dibar'])){{$data['data']['declared_dibar']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Data Available on AISHE ?</label>
                                <input type="text" id='data_available_AISHE' name="data_available_AISHE" class="form-control" value="@if(isset($data['data']['data_available_AISHE'])){{$data['data']['data_available_AISHE']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Conflict in trustee ? </label>
                                <input type="text" id='trustee_conflict' name="trustee_conflict" class="form-control" value="@if(isset($data['data']['trustee_conflict'])){{$data['data']['trustee_conflict']}}@endif">
                            </div>                    
                            <div class="col-md-4 form-group">
                                <label>Affiliated college condition</label>
                                <input type="text" id='affilitated_college_condition' name="affilitated_college_condition" class="form-control" value="@if(isset($data['data']['affilitated_college_condition'])){{$data['data']['affilitated_college_condition']}}@endif">
                            </div>
                            <div class="col-md-12 form-group">
                                <center>                            
                                    <input type="submit" name="submit" id="Submit" value="Save" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>  
                </div>
                <!-- tab 1 ends  -->
               
                 <!-- tab 3 starts  -->
                 <div class="tab-pane p-3" id="section-linemove-3" role="tabpanel">    
                   @include('lms.triz_skills')
                </div>
                <!-- tab 3 ends  -->
                 <!-- tab 2 start  -->
                 <div class="tab-pane p-3" id="section-linemove-2" role="tabpanel">    
                   @include('HRMS.department.tabDepartment')
                </div>
                <!-- tab 2 ends  -->
            </div >
            <!-- end tabs  -->
        </div>
    </div>        
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
@include('includes.footerJs')
@if(isset($_REQUEST['module']) && $_REQUEST['module']=='add_task')
<script>
    //@include('HRMS.department.tabDepartment')
    $(document).ready(function(){
        $(document).ready(function() {
        setTimeout(function() {
            
            $('#section-linemove-1').removeClass('active show');
            $('.section-linemove-1').removeClass('active');

            $('#section-linemove-2').addClass('active show');
            $('.section-linemove-2').addClass('active');
            $('#section-linemove-3').addClass('active show');
            $('.section-linemove-3').addClass('active');
        }, 500);
    });
});
</script>
@endif
@include('includes.footer')
@endsection
