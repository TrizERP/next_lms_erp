{{--@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')--}}
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Other Fees Mapping</h4>
            </div>
        </div>
        <div class="card">
        <div class="row mb-2">
        <div class="col-lg-12 col-sm-12 col-xs-12">
        <span class="d-block p-2  alert-warning">Note: Please Select Checkbox while Adding Data</span>
        </div>
        </div>
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">x</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    @php
                    if(isset($data['stu_data'])){
                    @endphp
                    <form action="{{ route('other_fee_map.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <input type="hidden" name="grade" value="{{$data['grade']}}">
                        <input type="hidden" name="standard" value="{{$data['standard']}}">
                        <input type="hidden" name="division" value="{{$data['division']}}">
                        <div class="table-responsive">
                            <table class="table table-striped" id="myTable">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" name="all" id="ckbCheckAll" class="ckbox">  </th>
                                        <th>Sr. No.</th>
                                        <th>{{ App\Helpers\get_string('studentname','request')}}</th>
                                        <th>{{ App\Helpers\get_string('std/div','request')}}</th>
                                        <th>Mobile</th>
                                        @php
                                        $arr_title = $data['month_head'];
                                        @endphp
                                        @foreach ($arr_title as $id=>$tit_arr)
                                        <th class="text-left">{{$tit_arr}} </th>
                                        @endforeach

                                    </tr>
                                </thead>
                                <tbody>
                                @php
                                    $arr = $data['stu_data'];
                                    $month = $data['months_id'];
                                    @endphp
                                @foreach ($arr as $id => $col_arr)
                                    <tr>
                                        <td><input type="checkbox" name="student_id[{{$col_arr['student_id']}}]" class="ckbox1"></td>
                                        <td>{{$id + 1}}</td>
                                        <td>{{$col_arr['name']}}</td>
                                        <td>{{$col_arr['std'] . ' / ' . $col_arr['div']}}</td>
                                        <td>{{$col_arr['mobile']}}</td>
                                        @php
                                            $arr_title = $data['fees_title'];
                                        @endphp
                                            @foreach ($month as $key=>$month_id)

                                            @foreach ($arr_title['data'] as $ids=>$tit_arr)

                                            <th><input type="text" value="@if(isset($col_arr[$month_id][$tit_arr['display_name']]['amount'])){{ $col_arr[$month_id][$tit_arr['display_name']]['amount']}}@endif" name="values[{{$col_arr['student_id']}}][{{$month_id}}][{{$tit_arr['fees_title']}}]"></th>
                                            @endforeach
                                        @endforeach
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                </center>
                            </div>
                        </div>

                    </form>
                    @php
                    }else{
                    @endphp
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <center>No Data Found !</center>
                            </div>
                        </div>
                    @php
                    }
                    @endphp
                </div>
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
<script>
$(document).ready(function () {
    $('#myTable').DataTable();
});

</script>
@include('includes.footer')
@endsection
