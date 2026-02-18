{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit Fees Late</h4>
            </div>
        </div>
        <div class="card">
			<!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
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
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('fees_late_master.update', $data['editData']['id']) }}" enctype="multipart/form-data" method="post">
                        {{ method_field("PUT") }}
                        @csrf
                        <div class="row">
	                        <div class="col-md-4 form-group">
	                        	<label>{{ App\Helpers\get_string('standard','request')}} </label>
	                            <select name="standard_id" id="standard_id" class="form-control" disabled>
	                                @foreach($data['standard_list'] as $key => $value)
	                                   <option value="{{$value['id']}}" @if(isset($data['editData']['standard_id']) && $value['id'] == $data['editData']['standard_id']) selected @endif>{{$value['name']}}</option>
	                                @endforeach
	                            </select>
	                        </div>
	                        <div class="col-md-4 form-group">
	                            <label>Late Fees Start Date</label>
	                            <input type="text" id='late_date' value="@if(isset($data['editData']['late_date'])){{ $data['editData']['late_date'] }}@endif" required name="late_date" class="form-control mydatepicker">
	                        </div>
	                        <!-- select fees month  -->
                            <div class="col-md-4">
                                <label for="fees_month">Select Fees Month</label>
                                <select name="fees_month" id="fees_month" class="form-control" disabled>
                                    <option value="">Select Fees Month</option>
                                    @foreach($data['fees_month'] as $month_id=>$value)
                                    <option value="{{$month_id}}"  @if(isset($data['editData']['month_id']) && $data['editData']['month_id']==$month_id) selected @endif>{{$value}}</option>
                                    @endforeach
                                </select>
                            </div>
							
                            <div class="col-md-4">
                                <label for="fees_type">Fine Counting Type</label>
                                <select name="fine_type" id="fine-type" class="form-control" required>
                                    <option value="">Select type</option>
                                    @foreach($data['fine_types'] as $key=>$value)
                                    <option value="{{$value}}"  @if(isset($data['editData']['fine_type']) && $data['editData']['fine_type']==$value) selected @endif>{{$value}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="status">Status</label><br>
                                <label class="switch">
                                    <input type="checkbox" name="status" class="roundCheckbox check_status"   @if(isset($data['editData']['status']) && $data['editData']['status']==	1) checked @endif value="1">
                                    <span class="slider round"></span>
                                </label>
                            </div>
	                        <div class="col-md-12 form-group">
	                        	<center>
	                                <input type="submit" name="submit" value="Update" class="btn btn-success" >
	                        	</center>
	                        </div>
                        </div>
                    </form>
            	</div>
            </div>
        </div>
	</div>
</div>

@include('includes.footerJs')
@include('includes.footer')
@endsection
