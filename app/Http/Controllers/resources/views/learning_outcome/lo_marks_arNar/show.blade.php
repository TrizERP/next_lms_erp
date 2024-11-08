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
        <div class="card">
            @if(!empty($data['message']))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
            <form action="{{ route('lo_marks_arNar.create') }}" enctype="multipart/form-data" method="GET">
            {{ method_field("GET") }}
            {{csrf_field()}}
                <div class="row">                    
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="row">                            
                            <div class="col-md-4 form-group">
                                <label>Medium</label>
                                <select class="form-control" onchange="getSubjects();" required name="medium"
                                    id="medium">
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
                                <label>Subject</label>
                                <select class="form-control" required name="subject" onchange="getLO();" id="subject">
                                    <option value="">--Select Subject--</option>
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>LO</label>
                                <select class="form-control" name="lo" id="lo">
                                    <option value="">--Select--</option>
                                </select>
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success">
                                </center>
                            </div>
                        </div>
                    </div>
                </div>    
            </form>
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
    function getLO(){
        var standardID = $("#std").val();
        var mediumID = $("#medium").val();
        var subjectID = $("#subject").val();

        if (standardID && mediumID && subjectID) {
            $.ajax({
                type: "GET",
                url: "/api/get-lo?standard_id=" + standardID +
                        "&medium_id=" + mediumID +
                        "&subject_id=" + subjectID ,
                success: function (res) {
                    if (res) {
                        $("#lo").empty();
                        $("#lo").append('<option value="">--Select LO--</option>');
                        $.each(res, function (key, value) {
                            $("#lo").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#lo").empty();
                    }
                }
            });
        } else {
            $("#lo").empty();
            $("#lo").append('<option value="">--Select--</option>');
            // if (mediumID == "") {
            //     alert("Please Select Medium.");
            // }
        }
   
    }
</script>
@include('includes.footer')
@endsection