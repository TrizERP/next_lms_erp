{{-- @include('includes.headcss') @include('includes.header') @include('includes.sideNavigation') --}} 
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">

        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Lo Marks Entry</h4>
            </div>
        </div>

        <div class="row" style=" margin-top: 0px;">
            <div class="white-box">
                <div class="panel-body">

                    @if(!empty($data['message']))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $data['message'] }}</strong>
                    </div>
                    @endif
                    <form action="{{ route('lo_marks_entry.create') }}" enctype="multipart/form-data" method="GET">
                        {{ method_field("GET") }}
                        {{csrf_field()}}
                        <br><br><br>
                        <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                            <!--<table id="example" class="table table-striped border dataTable" style="width:100%">-->
                            <div class="col-md-4 form-group">
                                <label>Exam Date </label>
                                <input type="text" id='examdate' required name="examdate" value=""
                                    class="form-control mydatepicker">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Medium</label>
                                <select class="form-control" onchange="getSubjects();" required name="medium" id="medium">
                                    <option value="">--Select Medium--</option>
                                    @foreach ($data['data']['medium'] as $item=>$val)
                                    <option value="{{ $item }}">{{ $val }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Standard</label>
                                <select class="form-control" onchange="getSubjects();" required name="std" id="std">
                                    <option value="">--Select Standard--</option>
                                    @foreach ($data['data']['std'] as $item=>$val)
                                    <option value="{{ $item }}">{{ $val }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Division</label>
                                <select class="form-control" name="div">
                                    <option value="">--Select Division--</option>
                                    @foreach ($data['data']['div'] as $item=>$val)
                                    <option value="{{ $val }}">{{ $val }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                    <label>Subject</label>
                                    <select class="form-control" required name="subject" id="subject">
                                        <option value="">--Select Subject--</option>
    
                                    </select>
                                </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success">

                                </center>
                            </div>

                    </form>
                </div>

            </div>
        </div>
    </div>
</div>
</div>

@include('includes.footerJs')
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
    function getSubjects (){
        var standardID = $("#std").val();
        var mediumID = $("#medium").val();

        if (standardID && mediumID) {
            $.ajax({
                type: "GET",
                url: "/api/get-lo-subject-list?standard_id=" + standardID +
                        "&medium_id=" + mediumID,
                success: function (res) {
                    if (res) {
                        $("#subject").empty();
                        $("#subject").append('<option value="">--Select Subject--</option>');
                        $.each(res, function (key, value) {
                            $("#subject").append('<option value="' + value + '">' + value + '</option>');
                        });

                    } else {
                        $("#subject").empty();
                    }
                }
            });
        } else {
            $("#subject").empty();
            $("#subject").append('<option value="">--Select Subject--</option>');
            // if (mediumID == "") {
            //     alert("Please Select Medium.");
            // }
        }

    }
</script>
@include('includes.footer')
@endsection