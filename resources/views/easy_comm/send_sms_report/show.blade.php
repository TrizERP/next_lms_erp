{{--@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')--}}
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">       
            <div class="card">
                @if(!empty($data['message']))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('send_sms_report.create') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("GET") }}
                        {{csrf_field()}}
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label>From Date</label>
                                <input type="text" name="from_date" class="form-control mydatepicker" autocomplete="off" />
                            </div>
                            <div class="col-md-3 form-group">
                                <label>To Date</label>
                                <input type="text" name="to_date" class="form-control mydatepicker" autocomplete="off" />
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Select</label>
                                <select name="tbl" id="tblSelect" class="form-control" required>
                                    <option value="parent">Parents</option>
                                    <option value="staff">Staff</option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group" id="admissionYearDiv">
                                <label>Admission Year</label>
                                <select name="academic_year" class="form-control">
                                    <option value="">--Select--</option>
                                    @foreach($data['academicYears'] as $year)
                                        <option value="{{ $year->syear }}">{{ $year->syear }}</option>
                                    @endforeach               
                                </select>
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
            </div>      
    </div>
</div>
<script>
$(document).ready(function () {

    function toggleYear() {
        let tbl = $('#tblSelect').val();
        if (tbl === 'staff') {
            $('#admissionYearDiv').hide();
        } else {
            $('#admissionYearDiv').show();
        }
    }

    // run on page load
    toggleYear();

    // run on change
    $('#tblSelect').change(function () {
        toggleYear();
    });

});
</script>

@include('includes.footerJs')
@include('includes.footer')
@endsection
