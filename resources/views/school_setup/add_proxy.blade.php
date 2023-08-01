@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style>
    .title {
        font-weight: 200;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Proxy Management</h4>
            </div>
        </div>
        <div class="card">
            @if ($message = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message['message'] }}</strong>
                </div>
            @endif
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('ajax_getproxyperiod') }}" enctype="multipart/form-data" method="post">
                        @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>From Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input value="@if(isset($data['from_date'])){{ $data['from_date'] }}@endif"
                                           type="text" required class="form-control mydatepicker"
                                           placeholder="YYYY/MM/DD" name="from_date" id="from_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>To Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input value="@if(isset($data['to_date'])){{ $data['to_date'] }}@endif" type="text"
                                           required class="form-control mydatepicker" placeholder="YYYY/MM/DD"
                                           name="to_date" id="to_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Absent Teacher</label>
                                <select class="selectpicker form-control" name="proxy_teacher_id" id="proxy_teacher_id">
                                    <option value="">Select Teacher</option>
                                    @if(isset($data['teacher_data']))
                                        @foreach($data['teacher_data'] as $key =>$val)
                                            @php
                                                $selected = '';
                                                if( isset($data['teacher']) && $data['teacher'] == $val->id )
                                                {
                                                    $selected = 'selected';
                                                }
                                            @endphp
                                            <option {{$selected}} value="{{$val->id}}">{{$val->teacher_name}}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Submit" class="btn btn-success"
                                           onclick="return validate_dates();">
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="alert alert-danger alert-dismissable" id='showerr' style="display:none;">
                        <div id='err'></div>
                    </div>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <form action="{{ route('proxy_master.store') }}" name="proxy" id="proxy" enctype="multipart/form-data" method="post">
                        @if(isset($data['proxydata']))
                                {{ method_field("POST") }}
                            @endif
                            @csrf
                            @if( isset($data['proxydata']) )
                                <table id="proxy_list" class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th></th>
                                        <th>Sr.No.</th>
                                        <th>Date</th>
                                        <th>Week Day</th>
                                        <th>Standard</th>
                                        <th>Division</th>
                                        <th>Batch</th>
                                        <th>Period</th>
                                        <th>Subject</th>
                                        <th>Proxy Teacher</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @php
                                        $i=1;
                                    @endphp
                                    @foreach($data['proxydata'] as $key =>$val)
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="period"
                                                       name="proxy_id['{{$val['date']}}/{{$val['timetable_id']}}']"
                                                       id="proxy_id['{{$val['date']}}/{{$val['timetable_id']}}']">
                                            </td>
                                            <td>{{$i++}}</td>
                                            <td>{{$val['date']}}</td>
                                            <td>{{$val['week_day']}}</td>
                                            <td>{{$val['standard_name']}}</td>
                                            <td>{{$val['division_name']}}</td>
                                            <td>
                                        @if( isset($val['batch_name']) )
                                        {{$val['batch_name']}}
                                        @else
                                        -
                                        @endif
                                    </td>
                                            <td>{{$val['period_name']}}</td>
                                            <td>{{$val['subject_name']}}</td>
                                            @php
                                                $check = DB::table('proxy_master')
                                                    ->whereRaw('sub_institute_id='.session()->get('sub_institute_id').
                                                            ' and syear='.session()->get('syear').
                                                            ' and teacher_id='.$data['teacher'].
                                                            ' and period_id='.$val['period_id'].
                                                            ' and subject_id='.$val['subject_id'].'')
                                                    ->whereBetween('proxy_date', [$data['from_date'], $data['to_date']])
                                                    ->get()
                                                    ->toArray();
                                            @endphp
                                            <td>
                                                <select class="selectpicker form-control"
                                                        name="teacher_id['{{$val['date']}}/{{$val['timetable_id']}}']"
                                                        id="teacher_id['{{$val['date']}}/{{$val['timetable_id']}}']"  @if(!empty($check)) style="pointer-events:none" readonly @endif>
                                                    <option value="">Select Teacher</option>
                                                    @if(isset($val['teacher_data']))
                                                        @foreach($val['teacher_data'] as $key =>$val)
                                                            @if( isset($data['teacher']) && $data['teacher'] != $val->id )
                                                                <option
                                                                    value="{{$val->id}}" @if(!empty($check) && $val->id == $check[0]->proxy_teacher_id)) selected @endif>{{$val->teacher_name}}</option>
                                                            @endif
                                                        @endforeach
                                                    @endif
                                                </select>
                                            </td>
                                          
                                        </tr>
                                    @endforeach
                                    </tbody>
                                    @if( count($data['proxydata']) > 0 )
                                        <tr align="center">
                                            <td colspan="10">
                                                <center>
                                                    <input onclick="return validate_data();" type="submit" name="Save"
                                                           value="Save" class="btn btn-success">
                                                </center>
                                            </td>
                                        </tr>
                                    @else
                                    <tr align="center">
                                        <td colspan="10">
                                            No Records Found!
                                        </td>
                                    </tr>
                                @endif
                            </table>
                        </form>
                        @endif
                    </div>
                </div>
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

@include('includes.footerJs')
<script>
    function validate_data() {
        var err = 0;
        var selected_checkbox = $("input:checkbox[class=period]:checked").length;
        $("input:checkbox[class=period]:checked").each(function () {
            var id = $(this).attr("id");
            tval = id.replace("proxy_id", "teacher_id");
            var tval = document.getElementById(tval).value;
            ;
            if (tval == "" || tval == "undefined") {
                err = 1;
            }
        });
        if (err == 1) {
            $("#showerr").css("display", "block");
            $("#err").html("Please Select Teacher");
            //alert("Please Select Teacher");
            return false;
        } else if (selected_checkbox <= 0) {
            $("#showerr").css("display", "block");
            $("#err").html("Please Select Atleast One Proxy Lecture");
            //alert("Please Select Atleast One Proxy Lecture");
            return false;
        } else {
            return true;
        }
    }

    /*function validate_dates(){
        var from_date = $("#from_date").val();
        var to_date = $("#to_date").val();

        var new_from_date = parseDate(from_date);
        var new_to_date = parseDate(to_date);

        if(Date.parse(new_from_date) < Date.parse(new_to_date)){
            return true;
        }else{
            $("#showerr").css("display", "block");
            $("#err").html("Please select Proper Dates");
            //alert("Please select Proper Dates");
            return false;
        }
        return false;
    }*/

    function parseDate(input) {

        let parts = input.split('-');

        // new Date(year, month [, day [, hours[, minutes[, seconds[, ms]]]]])
        return new Date(parts[2], parts[1] - 1, parts[0]); // Note: months are 0-based
    }

</script>
@include('includes.footer')
