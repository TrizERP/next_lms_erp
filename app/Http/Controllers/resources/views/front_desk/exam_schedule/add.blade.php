{{--@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')--}}
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row" style=" margin-top: 25px;">
            <div class="panel-body white-box" style="overflow-x: auto;">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    @php
                    if(isset($data['stu_data'])){
                    @endphp
                    <form action="{{ route('exam_schedule.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <center>
                            <h3>
                                Leave Application
                            </h3>
                        </center>
                        <!--<center><textarea name="smsText" required></textarea></center><br><br>-->
                        <table class="table-bordered table-responsive table" id="myTable" width="100%">
                            <tr>
                                <!--<th><input type="checkbox" name="all" id="ckbCheckAll" class="ckbox">  </th>-->
                                <th>No</th>
                                <th>Student Name</th>
                                <th>STD/DIV</th>
                                <th>Mobile</th>
                                <th>Apply Date</th>
                                <th>From Date</th>
                                <th>To Date</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Reply</th>
                            </tr>
                            @php

                            $arr = $data['stu_data'];
                            foreach ($arr as $id=>$col_arr){
                            @endphp
                            <tr>

                                <!--<td><input type="checkbox" name="@php echo 'sendsms['.$col_arr['mobile'].']'; @endphp" class="ckbox1">  </td>-->
                                <td>{{$id+1}}</td>
                                <td>{{$col_arr['name']}}</td>
                                <td>{{$col_arr['stddiv']}}</td>
                                <td>{{$col_arr['mobile']}}</td>
                                <td>{{ $col_arr['apply_date']}}</td>
                                <td>{{$col_arr['from_date']}}</td>
                                <td>{{$col_arr['to_date']}}</td>
                                <td>{{$col_arr['message']}}</td>
                                <td><textarea name="reply[{{$col_arr['student_id']}}]" >{{$col_arr['reply']}}</textarea></td>
                                <td>
                                    <select name="status[{{$col_arr['student_id']}}]" class="form-control" style="width: 135px;">
                                        @php
                                        $ap_select = "";
                                        $rj_select = "";
                                        $mta_select = "";
                                        $mtp_select = "";
                                        if ($col_arr['status'] == 'Approved') {
                                            $ap_select = "selected=selcted";
                                        }
                                        if ($col_arr['status'] == 'Rejected') {
                                            $rj_select = "selected=selcted";
                                        }
                                        if ($col_arr['status'] == 'Meet To Administrators') {
                                            $mta_select = "selected=selcted";
                                        }
                                        if ($col_arr['status'] == 'Meet To Principal') {
                                            $mtp_select = "selected=selcted";
                                        }

                                        @endphp
                                        <option value="">Select</option>
                                        <option {{$ap_select}} value="Approved">Approved</option>
                                        <option {{$rj_select}} value="Rejected">Rejected</option>
                                        <option {{$mta_select}} value="Meet To Administrators">Meet To Administrators</option>
                                        <option {{$mtp_select}} value="Meet To Principal">Meet To Principal</option>
                                    </select>
                                </td>

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
                    @php
                    }else{
                    echo "No Student Found.";
                    }
                    @endphp
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
//    $(function () {
//        var $tblChkBox = $("input:checkbox");
//        $("#ckbCheckAll").on("click", function () {
//            $($tblChkBox).prop('checked', $(this).prop('checked'));
//        });
//    });
</script>
@include('includes.footer')
@endsection
