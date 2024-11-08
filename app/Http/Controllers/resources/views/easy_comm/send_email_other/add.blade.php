{{--@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row" style=" margin-top: 25px;">
            <div class="panel-body white-box">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    @if(isset($data['stu_data']))
                    <form action="{{ route('send_email_parents.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <input type="hidden" name="grade" value="{{$data['grade']}}">
                        <input type="hidden" name="standard" value="{{$data['standard']}}">
                        <input type="hidden" name="division" value="{{$data['division']}}">
                        
                        <table class="table-bordered table-responsive table" id="myTable" width="100%">
                            <tr>
                                <th><input type="checkbox" name="all" id="ckbCheckAll" class="ckbox">  </th>
                                <th>No</th>
                                <th>Student Name</th>
                                <th>Mobile</th>
                            </tr>
                            @php

                            $arr = $data['stu_data'];
                            foreach ($arr as $id=>$col_arr){
                            @endphp
                            <tr>

                                <td><input type="checkbox" name="sendsms[{{$col_arr['email']}}]" class="ckbox1">  </td>
                                <td>{{$id+1}}</td>
                                <td>{{$col_arr['name']}}</td>
                                <td>{{$col_arr['mobile']}}</td>

                            </tr>
                            @php
                            }
                            @endphp
                        </table>

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>

                    </form>
                    @else
                    No Student Found.
                    @endif
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
    $(function () {
        var $tblChkBox = $("input:checkbox");
        $("#ckbCheckAll").on("click", function () {
            $($tblChkBox).prop('checked', $(this).prop('checked'));
        });
    });
</script>
@include('includes.footer')
@endsection