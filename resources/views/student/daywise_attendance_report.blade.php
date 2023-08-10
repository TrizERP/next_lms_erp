@include('includes.headcss')

@include('includes.header')
@include('includes.sideNavigation')

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
                        <form action="{{ route('show_daywise_student_attendance_report') }}"
                              enctype="multipart/form-data" method="post">
                            @csrf
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label>Attendance Date</label>
                                    <input type="text" name="date" autocomplete="off"
                                           @if(isset($data['date'])) value="{{$data['date']}}"
                                           @endif class="form-control mydatepicker" required="required"
                                           placeholder="Please select date to view report.">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Taken</label>
                                    <select name="taken" class="form-control" required="required">
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
        $j = 1;
            if(isset($data['attendance_data'])){
                $attendance_data = $data['attendance_data'];
            }
        @endphp
                        <div class="card">
                            <div class="table-responsive">
                                @php
                                    echo App\Helpers\get_school_details("","","");
                                    echo '<br><center><span style="font-size: 14px; font-weight: 600; font-family: Arial, Helvetica, sans-serif !important">';
                                    echo 'From Date : ' . (isset($data['date']) ? date('d-m-Y', strtotime($data['date'])) : '');
                                    echo '</span> <span style="font-size: 14px; font-weight: 600; font-family: Arial, Helvetica, sans-serif !important">';
                                    echo 'Token : ' . (isset($data['taken']) ? $data['taken'] : '');
                                    echo '</span></center><br>';
                                @endphp
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
                                <td> {{ucfirst($data['taken'])}} </td>
                                <td> {{$per_b_g}}% </td>
                                <td> </td>
                            </tr>
                        @endforeach
                    </tbody>
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
