{{-- @include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation') --}}

@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row" style=" margin-top: 25px;">
            <div class="white-box">
                <div class="panel-body">
                    @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                    @endif
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <form action="{{ route('lo_master.update', $data['id']) }}"
                            enctype="multipart/form-data" method="post">

                            {{ method_field("PUT") }}
                            {{csrf_field()}}

                            <div class="col-md-4 form-group">
                                <label>Medium</label>
                                <select class="form-control" onchange="getSubjects();" required name="medium" id="medium">
                                    <option value="">--Select Medium--</option>
                                    @foreach ($data['medium'] as $item=>$val)
                                    <option {{ $data['selected_medium']== $item ? 'selected="selected"' : '' }}
                                        value="{{ $item }}">{{ $val }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Standard</label>
                                <select class="form-control" onchange="getSubjects();" required name="std" id="std">
                                    <option value="">--Select Standard--</option>
                                    @foreach ($data['std'] as $item=>$val)
                                    <option {{ $data['selected_std']== $item ? 'selected="selected"' : '' }}
                                        value="{{ $item }}">{{ $val }}</option>
                                    @endforeach
                                </select>
                            </div>

                            
                            <div class="col-md-4 form-group">
                                <label>Subject</label>
                                <select class="form-control" required name="subject" id="subject">
                                    <option value="">--Select Subject--</option>
                                    @foreach ($data['subject'] as $item=>$val)
                                    <option {{ $data['selected_subject']== $item ? 'selected="selected"' : '' }}
                                        value="{{ $item }}">{{ $val }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Learning Outcome</label>
                                <input type="text" id='totalmarks' required name="learning_outcome"
                                    value="{{ $data['learning_outcome'] }}" class="form-control">
                            </div>
                            

                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success">
                                </center>
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
</div>

@include('includes.footerJs')
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
                            $("#subject").append('<option value="' + key + '">' + value + '</option>');
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