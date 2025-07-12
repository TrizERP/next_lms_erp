@extends('layout')
@section('container')
    <link rel="stylesheet" href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css">
    <style>
        .status-circle {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 0px;
            margin-bottom: -5px;
            font
        }

        .absent {
            background-color: #FFB2B2;
        }

        .latecomer {
            background-color: orange;
        }

        .halfday {
            background-color: yellow;
        }

        .ondutyleave {
            background-color: #9191c7;
        }

        .weekend {
            background-color: #99D699;
        }

        .holiday {
            background-color: #4591e0;
        }

        .punchsame {
            background-color: red;
        }
    </style>
    <div id="page-wrapper">
        <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Employees Attendance Report</h4>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    @if ($sessionData = Session::get('data'))
                        @if ($sessionData['status_code'] == 1)
                            <div class="alert alert-success alert-block">
                            @else
                                <div class="alert alert-danger alert-block">
                        @endif
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                </div>
                @endif
                <form action="{{ route('multiple_attendance_report.create') }}" class="row">
                    @csrf
                    @php
                        $dep_id = $emp_id = '';
                        if (isset($data['selDept'])) {
                            $dep_id = $data['selDept'];
                        }

                        if (isset($data['selEmp'])) {
                            $emp_id = $data['selEmp'];
                        }

                        $from_date = $to_date = now();
                        if (isset($data['from_date'])) {
                            $from_date = $data['from_date'];
                        }

                        if (isset($data['to_date'])) {
                            $to_date = $data['to_date'];
                        }
                    @endphp

                    {!! App\Helpers\HrmsDepartments('', 'multiple', $dep_id, 'multiple', $emp_id, '') !!}
                    <div class="col-md-3 form-group">
                        <label>From Date</label>
                        <div class="input-daterange input-group" id="date-range">
                            <input type="text" class="form-control mydatepicker" name="from_date" id="from_date"
                                value="{{ $from_date }}" autocomplete="off" require>
                            <span class="input-group-addon"><i class="icon-calender"></i></span>
                        </div>
                    </div>

                    <div class="col-md-3 form-group">
                        <label>To Date</label>
                        <div class="input-daterange input-group" id="date-range">
                            <input type="text" class="form-control mydatepicker" name="to_date" id="to_date"
                                value="{{ $to_date }}" autocomplete="off" require>
                            <span class="input-group-addon"><i class="icon-calender"></i></span>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-offset-4 text-center form-group">
                        <input type="submit" name="submit" value="Search" class="btn btn-success">
                    </div>
            </div>
            </form>
        </div>
    </div>
    <div class="sttabs tabs-style-linemove triz-verTab bg-white text-center style2 p-2">
        @if(session()->get('user_profile_name')=="Admin")
        <ul class="nav nav-tabs tab-title mb-4">
            <li class="nav-item"><a href="#section-linemove-1" class="nav-link active" aria-selected="true"
                    data-toggle="tab"><span>Summary Report</span></a></li>
            <li class="nav-item"><a href="#section-linemove-2" class="nav-link" aria-selected="false"
                    data-toggle="tab"><span>Detail Report</span></a></li>
        </ul>
        @endif
        <div class="tab-content">
            <div class="tab-pane active" id="section-linemove-1" role="tabpanel">
                <!-- summary table div start  -->
                <div class="card">
                    <div class="col-lg-12 col-md-4 col-sm-4 col-xs-12 page-title">
                        Colours Description =>
                        Absent: <span class="status-circle absent"></span>
                        Latecomer: <span class="status-circle latecomer"></span>
                        HalfDay: <span class="status-circle halfday"></span>
                        On Duty Leave: <span class="status-circle ondutyleave"></span>
                        Weekend: <span class="status-circle weekend"></span>
                        Holiday: <span class="status-circle holiday"></span>
                        Punch-in and Punch-out time are same: <span class="status-circle punchsame"></span>
                    </div><br>
                    <div class="table-responsive mt-20 tz-report-table">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr No.</th>
                                    <th>Date</th>
                                    <th>Emp No</th>
                                    <th>Department</th>
                                    <th>Employee Name</th>
                                    <th>In Time</th>
                                    <th>Out Time</th>
                                    <th class="text-left">Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $i=1; @endphp
                                @if (isset($data['allData']) && !empty($data['allData']))
                                    @php $i=1; @endphp
                                    @foreach ($data['allData'] as $date => $empArr)
                                        @foreach ($empArr as $empId => $value)
                                            @php
                                                $att_status = $day_name = '';
                                                $get_format_punchin_time = $get_format_punchout_time = '-';
                                                $holidays = $cl_leave = $on_duty_leave = [];
                                                $hrms_date = \Carbon\Carbon::createFromFormat('Y-m-d', $date);
                                                $day_name = lcfirst($hrms_date->format('l'));
                                                if (!empty($value)) {
                                                    $hrmsAttendance = $value;
                                                }
                                                if (isset($value['punchin_time']) && !empty($value)) {
                                                    $dateDayTime = \Carbon\Carbon::parse(
                                                        $hrmsAttendance[$day_name . '_in_date'],
                                                    )->format('H:i A');

                                                    $get_format_punchin_time =
                                                        $hrmsAttendance['punchin_time'] != ''
                                                            ? \Carbon\Carbon::parse(
                                                                $hrmsAttendance['punchin_time'],
                                                            )->format('H:i A')
                                                            : '-';

                                                    $get_format_punchout_time =
                                                        $hrmsAttendance['punchout_time'] != ''
                                                            ? \Carbon\Carbon::parse(
                                                                $hrmsAttendance['punchout_time'],
                                                            )->format('H:i A')
                                                            : '-';

                                                    if ($get_format_punchin_time == $get_format_punchout_time) {
                                                        $att_status = 'background-color: red;';
                                                    } elseif (
                                                        $hrmsAttendance['timestamp_diff'] <= '04:00:00' &&
                                                        $get_format_punchin_time > $hrmsAttendance['in_time']
                                                    ) {
                                                        $att_status = 'background-color: yellow;';
                                                    } elseif ($dateDayTime < $get_format_punchin_time) {
                                                        $att_status = 'background-color: orange;';
                                                    }
                                                }
                                                // for off days
                                                elseif (
                                                    isset($value['day_status']) &&
                                                    $value['day_status'] == 'offday' &&
                                                    $get_format_punchin_time == '-' &&
                                                    $get_format_punchout_time == '-'
                                                ) {
                                                    $att_status = 'background-color: #FFB2B2;';
                                                    $get_format_punchin_time = $get_format_punchout_time = '-';
                                                } elseif ($get_format_punchin_time == '-') {
                                                    $att_status = 'background-color: #FFB2B2;';
                                                    $get_format_punchin_time = $get_format_punchout_time = 'N/A';
                                                } elseif (
                                                    isset($hrmsAttendance['leave'][0]) &&
                                                    !empty($hrmsAttendance['leave'][0])
                                                ) {
                                                    $leaveData = $hrmsAttendance['leave'][0];

                                                    $from_date_cl = $leaveData->from_date;
                                                    $to_date_cl = $leaveData->to_date;

                                                    $from_date_on_duty_leave = $leaveData->from_date;
                                                    $to_date_on_duty_leave = $leaveData->to_date;

                                                    if (
                                                        $leaveData->leave_type_id == 'LTY001' ||
                                                        $leaveData->leave_type_id == 'LTY002' ||
                                                        $leaveData->leave_type_id == 'LTY003' ||
                                                        $leaveData->leave_type_id == 'LTY005' ||
                                                        $leaveData->leave_type_id == 'LTY006'
                                                    ) {
                                                        while (strtotime($from_date_cl) <= strtotime($to_date_cl)) {
                                                            $cl_leave[] = $from_date_cl;
                                                            $from_date_cl = date(
                                                                'Y-m-d',
                                                                strtotime('+1 day', strtotime($from_date_cl)),
                                                            );
                                                        }
                                                    } elseif ($leaveData->leave_type_id == 'LTY004') {
                                                        while (
                                                            strtotime($from_date_on_duty_leave) <=
                                                            strtotime($to_date_on_duty_leave)
                                                        ) {
                                                            $on_duty_leave[] = $from_date_on_duty_leave;
                                                            $from_date_on_duty_leave = date(
                                                                'Y-m-d',
                                                                strtotime(
                                                                    '+1 day',
                                                                    strtotime($from_date_on_duty_leave),
                                                                ),
                                                            );
                                                        }
                                                    }
                                                }
                                                if (
                                                    isset($hrmsAttendance['holiday'][0]) &&
                                                    !empty($hrmsAttendance['holiday'][0])
                                                ) {
                                                    $holidayData = $hrmsAttendance['holiday'][0];
                                                    $hfrom_date_new = \Carbon\Carbon::parse($holidayData['from_date']);
                                                    $hto_date_new = \Carbon\Carbon::parse($holidayData['to_date']);

                                                    while (strtotime($hfrom_date_new) <= strtotime($hto_date_new)) {
                                                        $holidays[] = \Carbon\Carbon::parse($hfrom_date_new)->format(
                                                            'Y-m-d',
                                                        );
                                                        $hfrom_date_new = date(
                                                            'Y-m-d',
                                                            strtotime('+1 day', strtotime($hfrom_date_new)),
                                                        );
                                                    }
                                                }

                                                if (in_array($date, $holidays)) {
                                                    $att_status = 'background-color:#4591e0;';
                                                } elseif (in_array($date, $cl_leave)) {
                                                    $att_status = 'background-color:#FFB2B2;';
                                                } elseif (in_array($date, $on_duty_leave)) {
                                                    $att_status = 'background-color:#9191c7;';
                                                } elseif (in_array($day_name, ['sunday', 'Sunday', 'sun', 'Sun'])) {
                                                    $att_status = 'background-color:#99D699;';
                                                }

                                                $timediff = isset($value['timestamp_diff'])
                                                    ? \Carbon\Carbon::parse($value['timestamp_diff'])->format('H:i')
                                                    : '-';

                                            @endphp
                                            <tr style="{{ $att_status }}">
                                                <td>{{ $i++ }}</td>
                                                <td>{{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}</td>
                                                <td>{{ $value['employee_no'] ?? '-' }}</td>
                                                <td>{{ $value['depName'] ?? '-' }}</td>
                                                <td>{{ $value['full_name'] ?? '-' }}</td>
                                                <td>
                                                    {{-- Check if ipaddress_in exists, is not empty, and its length is greater than 15 --}}
                                                    @if (isset($value['ipaddress_in']) && !empty($value['ipaddress_in']) && strlen($value['ipaddress_in']) > 15)
                                                        <a href="javascript:void(0);"
                                                            onclick="openMapPopup('{{ $value['ipaddress_in'] }}')">
                                                            {{-- Display punchin_time, formatted, or '-' if not set --}}
                                                            {{ $get_format_punchin_time }}
                                                        </a>
                                                    @else
                                                        {{-- If conditions are not met, just display punchin_time --}}
                                                        {{ $get_format_punchin_time }}
                                                    @endif
                                                </td>
                                                <td>
                                                    {{-- Check if ipaddress_out exists, is not empty, and its length is greater than 15 --}}
                                                    @if (isset($value['ipaddress_out']) && !empty($value['ipaddress_out']) && strlen($value['ipaddress_out']) > 15)
                                                        <a href="javascript:void(0);"
                                                            onclick="openMapPopup('{{ $value['ipaddress_out'] }}')">
                                                            {{-- Display punchout_time, formatted, or '-' if not set --}}
                                                            {{ $get_format_punchout_time }}
                                                        </a>
                                                    @else
                                                        {{-- If conditions are not met, just display punchout_time --}}
                                                        {{ $get_format_punchout_time }}
                                                    @endif
                                                </td>
                                                <td class="text-left">{{ $timediff }}</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- summary table div end  -->
            </div>
            {{-- details 2 start  --}}
            <div class="tab-pane" id="section-linemove-2" role="tabpanel">
                <div class="alert alert-secondary">
                    Time must be in H:i:s Format
                </div>
                <form action="{{route('update_user_att')}}" method="post">
                @csrf
                <div class="table-responsive mt-20 tz-report-table">
                    <table id="example2" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Sr No.</th>
                                <th>Employee Name</th>
                                <th>Date</th>
                                <th>In Time</th>
                                <th>In Note</th>
                                <th>Out Time</th>
                                <th>Out Note</th>
                                <th class="text-left">Duration</th>
                                <th class="text-left">Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $i=1; @endphp
                            @if (isset($data['allData']) && !empty($data['allData']))
                                @php $i=1; @endphp
                                @foreach ($data['allData'] as $date => $empArr)
                                    @foreach ($empArr as $empId => $value)
                                @if(isset($value['empId']))
                                        @php
                                            $timediff = isset($value['timestamp_diff'])
                                                ? \Carbon\Carbon::parse($value['timestamp_diff'])->format('H:i')
                                                : '-';

                                            $att_id = isset($value['att_id']) ? $value['att_id'] : 0;
                                            $punch_in =
                                                isset($value['att_punch_in']) &&
                                                $value['att_punch_in'] != '' &&
                                                $value['att_punch_in'] != null
                                                    ? $value['att_punch_in']
                                                    : '-';
                                            $punch_out =
                                                isset($value['att_punch_out']) &&
                                                $value['att_punch_out'] != '' &&
                                                $value['att_punch_out'] != null
                                                    ? $value['att_punch_out']
                                                    : '-';
                                        @endphp
                                        <tr>
                                            <td>{{ $i++ }}</td>
                                            <td>{{ $value['full_name'] ?? '-' }}</td>
                                            <td>{{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}</td>
                                            <td title="Time must be in H:i:s format">
                                                <input type="text" name="in_time[{{ $date }}][{{$value['empId']}}]" id=""
                                                    value="{{ $punch_in }}" class="form-control clockpicker">
                                                <span class="input-group-addon"><span
                                                        class="glyphicon glyphicon-time"></span></span>
                                            </td>
                                            <td>Day Start</td>
                                            <td title="Time must be in H:i:s format">
                                                <input type="text" name="out_time[{{ $date }}][{{$value['empId']}}]" id=""
                                                    value="{{ $punch_out }}" class="form-control clockpicker">
                                                <span class="input-group-addon"><span
                                                        class="glyphicon glyphicon-time"></span></span>
                                            </td>
                                            <td>Day End</td>
                                            <td>{{ $timediff }}</td>
                                            <td><input type="checkbox" name="deleteAtt[{{ $att_id }}]"
                                                    id=""></td>
                                        </tr>
                                    @endif
                                    @endforeach
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
                <div class="col-md-12 pt-2">
                    <center>
                        <input type="submit" value="Update" class="btn btn-primary">
                    </center>
                </div>
                </form>
            </div>
            {{-- details 2 tab end  --}}
        </div>
    </div>

    </div>
    </div>

    @include('includes.footerJs')
    <script>
        $(document).ready(function() {
            // $('#department_ids').prop('required',true);
            var table = $('#example').DataTable({
                select: true,
                lengthMenu: [
                    [100, 500, 1000, -1],
                    ['100', '500', '1000', 'Show All']
                ],
                dom: 'Bfrtip',
                buttons: [{
                        extend: 'pdfHtml5',
                        title: 'Report Attendance Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {
                        extend: 'csv',
                        text: ' CSV',
                        title: 'Report Attendance Report'
                    },
                    {
                        extend: 'excel',
                        text: ' EXCEL',
                        title: 'Report Attendance Report'
                    },
                    {
                        extend: 'print',
                        text: ' PRINT',
                        title: 'Report Attendance Report'
                    },
                    'pageLength'
                ],
            });
            //table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');

            $('#example thead tr').clone(true).appendTo('#example thead');
            $('#example thead tr:eq(1) th').each(function(i) {
                var title = $(this).text();
                $(this).html('<input type="text" placeholder="Search ' + title + '" />');

                $('input', this).on('keyup change', function() {
                    if (table.column(i).search() !== this.value) {
                        table
                            .column(i)
                            .search(this.value)
                            .draw();
                    }
                });
            });
        });
    </script>
    <script>
        // Ajax call to get employees based on the selected department
        $(document).on("change", "#department_id", function(e) {
            $('#employee_id').empty();
            var departmentId = $(this).val();

            $.ajax({
                type: "post",
                url: "{{ route('get.employees.list') }}",
                data: {
                    department_id: departmentId
                },
                success: function(data) {
                    var options = '';
                    $.each(data.employees, function(index, employee) {
                        options += '<option value="' + employee.id + '" >' + employee
                            .first_name + ' ' + employee.last_name + '</option>';
                    });
                    $('#employee_id').append(options);
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                }
            });

            // Initialize clockpicker
            $('.clockpicker').clockpicker({
                donetext: 'Done',
            }).find('input').change(function() {
                console.log(this.value);
            });

        });

        function openMapPopup(coords, searchTerm = '') {
            const baseUrl = 'https://www.google.com/maps/search/?api=1&query=';

            let url;
            if (searchTerm) {
                url = `${baseUrl}${encodeURIComponent(searchTerm)},${coords}`;
            } else {
                url = `https://www.google.com/maps/place/${coords}/@${coords},15z/data=!4m2!3m1!1s0x0:0x0`;
            }

            window.open(url, 'mapPopup', 'width=1024,height=600,resizable=yes,scrollbars=yes');
        }
    </script>
    @include('includes.footer')
@endsection
