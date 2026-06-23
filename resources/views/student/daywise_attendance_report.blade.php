@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Boys Girls Daywise Attendance Report</h4>
            </div>
        </div>
        <div class="card">
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
                        <form action="{{ route('show_daywise_student_attendance_report') }}" enctype="multipart/form-data" method="post">
                            @csrf
                            @php 
                                $date = now();
                                if(isset($data['date'])){
                                    $date = $data['date'];
                                }
                            @endphp
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label>Attendance Date</label>
                                    <input type="text" name="date" autocomplete="off" value="{{$date}}" class="form-control mydatepicker" required="required" placeholder="Please select date to view report.">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Taken</label>
                                    <select name="taken" class="form-control">
                                        <option value="">Select Taken</option>
                                        <option value="yes"
                                                @if(isset($data['taken'])) @if($data['taken'] == 'yes') selected="selected" @endif @endif>
                                            Yes
                                        </option>
                                        <option value="no"
                                                @if(isset($data['taken'])) @if($data['taken'] == 'no') selected="selected" @endif @endif>
                                            No
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-4 form-group mt-4">
                                    <center>
                                        <input type="submit" name="submit" value="Search" class="btn btn-success">
                                    </center>
                                </div>
                            </div>
                        </form>
                    </div>

        @if(isset($data['attendance_data']))
        @php
            if(isset($data['attendance_data'])){
                $attendance_data = $data['attendance_data'];
            }
            $attendance_totals = isset($data['attendance_totals']) ? $data['attendance_totals'] : null;
            $token = isset($data['taken']) ? $data['taken'] : '';
        @endphp
            <div class="card">
                <div class="table-responsive">
                    {!! App\Helpers\get_school_details("","","") !!} 
                    <br><center><span style="font-size: 14px; font-weight: 600; font-family: Arial, Helvetica, sans-serif !important">
                    From Date : {{date('d-m-Y', strtotime($data['date'])) }}
                    </span> <span style="font-size: 14px; font-weight: 600; font-family: Arial, Helvetica, sans-serif !important">
                    Taken : {{$token}}   </span></center><br>
                    <table id="daywise_attendance" class="table table-striped table-bordered" border="1"
                            style="border-collapse: collapse;">
                        <thead>
                        <tr>
                            <th rowspan="2">{{App\Helpers\get_string('standard','request')}}</th>
                            <th colspan="3">Total Student</th>
                            <th colspan="3">Present</th>
                            <th colspan="3">Absent</th>
                            <th rowspan="2">Taken</th>
                            <th rowspan="2">Average</th>
                            <th rowspan="2">Staff Signature</th>
                            </tr>
                            <tr>
                                <th>B</th>
                                <th>G</th>
                                <th>T</th>
                                <th>B</th>
                                <th>G</th>
                                <th>T</th>
                                <th>B</th>
                                <th>G</th>
                                <th>T</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($attendance_data as $key => $value)
                                @php
                                    $TBGP = ($value->TBP + $value->TGP);
                                    $TBG = ($value->BOY + $value->GIRL);
                                    if($TBG > 0)
                                    {
                                        $avg_b_g = ($value->TBP + $value->TGP) / ($value->BOY + $value->GIRL);
                                        $per_b_g = number_format((100 * $avg_b_g),2);
                                    }else{
                                        $per_b_g = 0;
                                    }
                                    $attendance_taken = (($value->TBP + $value->TGP + $value->TBA + $value->TGA) > 0) ? 'Yes' : 'No';
                                @endphp
                                <tr>
                                    <td> {{$value->standard_name}} </td>
                                    <td> {{$value->BOY}} </td>
                                    <td> {{$value->GIRL}} </td>
                                    <td> {{($value->BOY + $value->GIRL)}} </td>
                                    <td> {{$value->TBP}} </td>
                                    <td> {{$value->TGP}} </td>
                                    <td> {{($value->TBP + $value->TGP)}} </td>
                                    <td> {{$value->TBA}} </td>
                                    <td> {{$value->TGA}} </td>
                                    <td> {{($value->TBA + $value->TGA)}} </td>
                                    <td> {{$attendance_taken}} </td>
                                    <td> {{$per_b_g}}% </td>
                                    <td> </td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if($attendance_totals)
                            @php
                                $total_present = $attendance_totals->total_present;
                                $total_absent = $attendance_totals->total_absent;
                                $total_student = $attendance_totals->total_student;
                                $total_percentage = ($total_student > 0) ? number_format((100 * ($total_present / $total_student)), 2) : 0;
                                $total_taken = (($total_present + $total_absent) > 0) ? 'Yes' : 'No';
                            @endphp
                            <tfoot>
                                <tr style="font-weight: 600;">
                                    <td>TOTAL</td>
                                    <td> {{$attendance_totals->BOY}} </td>
                                    <td> {{$attendance_totals->GIRL}} </td>
                                    <td style="background-color: #FADB2E;"> {{$total_student}} </td>
                                    <td> {{$attendance_totals->TBP}} </td>
                                    <td> {{$attendance_totals->TGP}} </td>
                                    <td style="background-color: #9ACD74;"> {{$total_present}} </td>
                                    <td> {{$attendance_totals->TBA}} </td>
                                    <td> {{$attendance_totals->TGA}} </td>
                                    <td style="background-color: #FF9797;"> {{$total_absent}} </td>
                                    <td> </td>
                                    <td style="background-color: #F4CD8A;"> {{$total_percentage}}% </td>
                                    <td> </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                    <center>
                        <button
                            onclick="exportTableToExcel('daywise_attendance', 'Boys Girls Daywise Attendance Report')"
                            class="btn btn-success mt-2">Excel Export
                        </button>
                    </center>
                </div>
            </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>
    function exportTableToExcel(tableID, filename = '')
    {
        var downloadLink;
        var dataType = 'application/vnd.ms-excel';
        var tableSelect = document.getElementById(tableID);
        var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');

        // Specify file name
        filename = filename?filename+'.xls':'excel_data.xls';

        // Create download link element
        downloadLink = document.createElement("a");

        document.body.appendChild(downloadLink);

        if(navigator.msSaveOrOpenBlob){
            var blob = new Blob(['\ufeff', tableHTML], {
                type: dataType
            });
            navigator.msSaveOrOpenBlob( blob, filename);
        }else{
            // Create a link to the file
            downloadLink.href = 'data:' + dataType + ', ' + tableHTML;

            // Setting the file name
            downloadLink.download = filename;

            //triggering the function
            downloadLink.click();
        }
    }
</script>
@include('includes.footer')
@endsection