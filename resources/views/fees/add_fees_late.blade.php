@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Fees Late</h4>
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
                    <form action="{{ route('fees_late_master.store') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("POST") }}
                    @csrf
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label>{{ App\Helpers\get_string('standard','request')}}</label>
                                <select name="standard_id[]" id="standard_id" class="form-control resizable" required multiple>
                                    @foreach($data['standard_list'] as $key => $value)
                                        <option value="{{$value['id']}}">{{$value['name']}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <!-- <div class="col-md-4 form-group">
                                <label>Late Fees Start Date </label>
                                <input type="text" id='late_date' required name="late_date" class="form-control mydatepicker">
                            </div> -->
                            <div class="col-md-3">
                                <label for="late_date">Late Fees Start Date</label>
                                <input type="number" name="late_date" class="form-control" placeholder="Enter date of month to start counting" required>
                            </div>
                            <div class="col-md-3">
                                <label for="fees_type">Fine Counting Type</label>
                                <select name="fine_type" id="fine-type" class="form-control" required>
                                    <option value="">Select type</option>
                                    @foreach($data['fine_types'] as $key=>$value)
                                    <option value="{{$value}}">{{$value}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status">Status</label><br>
                                <label class="switch">
                                    <input type="checkbox" name="status" class="roundCheckbox check_status" value="1" checked>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <input type="hidden" name="term_id" value="0">
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
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
<script src="../../../plugins/bower_components/dropify/dist/js/dropify.min.js"></script>
@include('includes.footer')
@endsection
