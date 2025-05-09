@include('includes.headcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')
<style type="text/css">
.error {
  width: 80%;
  height: 35px;
  font-size: 1.1em;
  color: red;
  font-weight: bold;
}
.success {
    width: 80%;
    height: 35px;
    font-size: 1.1em;
    color: green;
    font-weight: bold;
}
</style>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Leave Apply</h4>
            </div>
        </div>

        <div class="card">
            @if ($sessionData = Session::get('data')) @if($sessionData['status_code'] == 1)
                <div class="alert alert-success alert-block">
            @else
            <div class="alert alert-danger alert-block">
                @endif
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <div class="row">
                <!-- apply leave start  -->
                <div class="col-md-4">
                    <form action="" id="frmApplyLeave" method="post">
                        <div class="modal-body">
                            <div class="form-group">
                                @php 
                                    $user_profile_name = DB::table('tbluser as u')
                                    ->selectRaw("u.user_profile_id, um.name as user_profile_name,u.department_id,u.id as user_id")
                                    ->join('tbluserprofilemaster as um', 'um.id', '=', 'u.user_profile_id')
                                    ->where('u.id', session()->get('user_id'))
                                    ->where('u.status',1)
                                    ->first();

                                    $user_id = $user_profile_name->user_id;
                                    $department_id = $user_profile_name->department_id;
                                    $year = session()->get('syear');
                                @endphp
                                <label for="">Type Leave</label>
                                <select name="type_leave" id="type_leave" class="form-control">
                                    <option value="">Select Type Leave</option>
                                    @if($user_profile_name->user_profile_name != "Teacher")
                                        <option value="employee">Employee</option>
                                    @endif
                                    <option value="self">Self</option>
                                </select>
                            </div>
                            <div class="form-group div-emp d-none">

                            {!! App\Helpers\HrmsDepartments("12","","","","","") !!}
                            </div>
                            <div class="form-group">
                                <label for="">Leave Type</label>
                                <select name="leave_type" id="leave_type" class="form-control">
                                    <option value="">Select Leave Type</option>
                                    @foreach ($data['leave_types'] as $key => $row)
                                        <option value="{{ $row->id }}" data-leaveTypeId="{{ $row->leave_type_id }}">{{ $row->leave_type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="">Day Type</label>
                                <select name="day_type" id="day_type" class="form-control">
                                    <option value="">Select Day Type</option>
                                    <option value="full">Full</option>
                                    <option value="half">Half</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>From Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input type="text" required class="form-control mydatepicker" placeholder="YYYY/MM/DD" name="from_date" id="from_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                            </div>
                            @php 
                                $sandwhichLeaves = $data['sandwichLeave'];
                                $casualLeaves = $data['causualLeave'];
                                $earnedLeaves = $data['earnedLeave'];
                            @endphp
                            <div class="form-group">
                                <label>To Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input type="text" required class="form-control mydatepicker" placeholder="YYYY/MM/DD" name="to_date" id="to_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                                    <span id="total_appear_days"></span>
                                    <input type="hidden" id="total_days" name="total_days" value="">
                                    <span id="criteria_validation"></span>
                                
                                    <span id="without_sandwich_total_appear_days"></span>
                                    <span id="without_sandwich_criteria_validation"></span>
                                    <input type="hidden" id="total_days" name="total_days" value="">
                                
                            </div>
                            <div class="form-group slot d-none">
                                <label for="">Slot</label>
                                <select name="slot" id="slot" class="form-control">
                                    <option value="">Select Slot</option>
                                    <option value="first_half">First half</option>
                                    <option value="second_half">Second Half</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="">Comment</label>
                                <textarea name="comment" id="comment" cols="30" rows="10" class="form-control"></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
            <!-- div for apply end  -->
            <!-- apply history start  -->
                <div class="col-md-8">
                    <h4><a onclick="openSummary('{{$user_id}}','{{$department_id}}','{{$year}}')"><u>My Leave History</u></a></h4>
                    <!-- table start  -->
                    <div class="table-responsive mt-20 tz-report-table">
                        <table id="example" class="table table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Sr No.</th>
                                    <th>From Date</th>
                                    <th>To Date</th>
                                    <th>No of Days</th>
                                    <th>Day type</th>
                                    <th>Leave Type</th>
                                    <th>Reasons</th>
                                    <th>HOD Comment</th>
                                    <th>HR Remarks</th>
                                    <th>Approved By</th>
                                    <th class="text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(!empty($data['leaveHistory']))
                                    @foreach($data['leaveHistory'] as $key=>$value)
                                    @php 
                                        $Mfrom_date = \Carbon\Carbon::parse($value->from_date);
                                        $from_date = \Carbon\Carbon::parse($value->from_date);
                                        $to_date = \Carbon\Carbon::parse($value->to_date);
                                        $dayType = $value->day_type ?? 0;
                                    @endphp
                                    <tr>
                                        <td>{{$key+1}}</td>
                                        <td>{{$Mfrom_date->format('d-m-Y')}}</td>
                                        <td>{{$to_date->format('d-m-Y')}}</td>
                                        <td>{{ App\Helpers\countDays($Mfrom_date,$to_date,$dayType) }}</td>
                                        <td>{{ ($value->day_type=="0.5") ? 'Half Day' : 'Full Day' }}</td>
                                        <td>{{$value->leave_type_name}}</td>
                                        <td>{{$value->comment}}</td>
                                        <td>{{$value->hod_comment}}</td>
                                        <td>{{$value->hr_remarks}}</td>
                                        <td>{{$value->approved_by}}</td>
                                        <td>{{$value->status}}</td>
                                    </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>  
                    <!-- end start  -->
                </div>
            <!-- apply histroy end  -->
            </div>

        </div>
    </div>
</div>

@include('includes.footerJs')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.min.js"></script>
<script type="text/javascript">
    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>

<script>
     $(document).ready(function () {
            var table = $('#example').DataTable({
                ordering: false,
                select: true,
                lengthMenu: [
                    [50, 100, 500, 1000, -1],
                    ['50', '100', '500', '1000', 'Show All']
                ],
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'pdfHtml5',
                        title: 'Leave Apply Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'Leave Apply Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'Leave Apply Report'},
                    {extend: 'print', text: ' PRINT', title: 'Leave Apply Report'},
                    'pageLength'
                ],
            });
            //table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');

            $('#example thead tr').clone(true).appendTo('#example thead');
            $('#example thead tr:eq(1) th').each(function (i) {
                var title = $(this).text();
                $(this).html('<input type="text" placeholder="Search ' + title + '" />');

                $('input', this).on('keyup change', function () {
                    if (table.column(i).search() !== this.value) {
                        table
                            .column(i)
                            .search(this.value)
                            .draw();
                    }
                });
            });
        });

    $(document).ready(function() {

        $('#criteria_validation').empty();
        $('#without_sandwich_criteria_validation').empty();
        $('#without_sandwich_total_appear_days').empty();
        $('#total_appear_days').empty();
       
        $(document).on("submit", "#frmApplyLeave", function(e) {
            e.preventDefault();
            $('.error').remove()
            var formData = $("#frmApplyLeave").serialize();
            /**Ajax code**/
            $.ajax({
                type: "post",
                url: "{{ route('leave-apply.store') }}",
                data: formData,
                success: function(data) {
                    console.log(data);
                    if(data){
                        alert('Leave Applied Successfully !!');
                    }
                    location.reload()
                },
                error: function(xhr) {
                    if (xhr.status == 422) {
                        var errors = JSON.parse(xhr.responseText);
                        $.each(errors.errors, function(i, error) {
                            $('#' + i).after(
                                '<span class="text-strong text-danger error">' +
                                error + '</span>')
                        })
                    }
                }
            });
        });

        $(document).on("change", "#type_leave", function(e) {
            if ($(this).val() == 'self') {
                $('.div-emp').addClass('d-none');
            } else {
                $('.div-emp').removeClass('d-none');
            }
        });

        $(document).on("change", "#day_type", function(e) {
            if ($(this).val() == 'half') {
                $('.to_date').addClass('d-none');
                $('.slot').removeClass('d-none');
            } else {
                $('.to_date').removeClass('d-none');
                $('.slot').addClass('d-none');
            }
        });

        $(document).on("click", ".btn-edit", function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var url = "{{ route('leave-apply.edit', ':id') }}";
            url = url.replace(':id', id);
            /**Ajax code**/
            $.ajax({
                type: "get",
                url: url,
                data: {
                    id: id
                },
                success: function(data) {
                    $('#addTypeMdl').modal('toggle');
                    $('#leave_type_name').val(data.data.leave_type);
                    $('#leave_id').val(data.data.id);
                },
                error: function(xhr) {
                    if (xhr.status == 422) {
                        var errors = JSON.parse(xhr.responseText);
                        $.each(errors.errors, function(i, error) {
                            $('#' + i).after(
                                '<span class="text-strong text-danger">' +
                                error + '</span>')
                        })
                    }
                }
            });
        });
        $(document).on("click", ".btn-delete", function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var url = "{{ route('leave-apply.destroy', ':id') }}";
            url = url.replace(':id', id);
            /**Ajax code**/
            if (confirm('Are you sure to delete leave type')) {
                $.ajax({
                    type: "delete",
                    url: url,
                    data: {
                        id: id
                    },
                    success: function(data) {
                        $('#tblLeaveType').DataTable().ajax.reload();
                    },
                    error: function(xhr) {
                        if (xhr.status == 422) {
                            var errors = JSON.parse(xhr.responseText);
                            $.each(errors.errors, function(i, error) {
                                $('#' + i).after(
                                    '<span class="text-strong text-danger">' +
                                    error + '</span>')
                            })
                        }
                    }
                });
            }
        });

	// Ajax call to get employees based on the selected department
        $(document).on("change", "#department_id", function(e) {
            var departmentId = $(this).val();
	
            $.ajax({
                type: "post",
                url: "{{ route('get-employees') }}",
                data: { department_id: departmentId },
                success: function(data) {
                    var options = '<option value="">Select Employee</option>';
                    $.each(data.employees, function(index, employee) {
                        options += '<option value="' + employee.id + '">' + employee.first_name + ' ' + employee.last_name + '</option>';
                    });
                    $('#employee_id').html(options);
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                }
            });
        });

        
    });
</script>
<script>
     $(document).on('change', '#leave_type', function() 
     {
        $('#to_date').val('');
        $('#without_sandwich_total_appear_days').empty();
        $('#without_sandwich_criteria_validation').empty();
        $('#criteria_validation').empty();
        $('.btn-primary').hide();
     })
     $(document).on('change', '#day_type', function() 
     {
        $('#to_date').val('');
        $('#without_sandwich_total_appear_days').empty();
        $('#without_sandwich_criteria_validation').empty();
        $('#criteria_validation').empty();
        $('.btn-primary').hide();
     })

    $(document).on('change', '#to_date', function() 
    {
        var fromDateValue = $('#from_date').val();
        var toDateValue = $(this).val();
        var leaveType = $('#leave_type').val(); // 22-01-2025
        var day_type = $('#day_type').val(); // 08-05-2025
        var leaveTypeId = $('#leave_type option:selected').attr('data-leaveTypeId');
        var HalfAllowedDays = "{{ (isset($data['half_days_allowed']->fieldvalue)) ? $data['half_days_allowed']->fieldvalue : ''}}";
        var leaveIds = '{!! (isset($data['half_days_allowed']->extra_field1)) ? $data['half_days_allowed']->extra_field1 : ''!!}';
        // console.log('HalfAllowedDays='+HalfAllowedDays);
        // console.log('leaveIds='+leaveIds);
        // console.log('leaveType='+leaveType);
        // console.log('leaveTypeId='+leaveTypeId);
        if(leaveType===''){
            alert('please select leave type');
        }
        if(day_type===''){
            alert('please select day type');
        }

        $('#total_appear_days').empty();
        $('#without_sandwich_total_appear_days').empty();
        $('#without_sandwich_criteria_validation').empty();
        $('#criteria_validation').empty();


        // // alert('LeaveTypeId-' + leaveType);
        // // Manually parse the date strings into a format compatible with the Date constructor
        // var fromParts = fromDateValue.split('-');
        // var fromDate = new Date(fromParts[2], fromParts[1] - 1, fromParts[0]); // Year, Month (0-indexed), Day

        // var toParts = toDateValue.split('-');
        // var toDate = new Date(toParts[2], toParts[1] - 1, toParts[0]); // Year, Month (0-indexed), Day

        // $('#criteria_validation').empty();
        // $('#without_sandwich_criteria_validation').empty();
        // $('#without_sandwich_total_appear_days').empty();
        // $('#total_appear_days').empty();

        // if (isNaN(fromDate.getTime()) || isNaN(toDate.getTime())) {
        //     alert("Invalid date format, Please select from date first !");
        //     $('#to_date').val('');
        //     return;
        // }

        // // Calculate the difference in days, including both start and end dates
        // var timeDiff = toDate.getTime() - fromDate.getTime();
        // var diffDays = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
        // var SundayDiffDays = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;

    // Convert DD-MM-YYYY to YYYY-MM-DD
        function parseDate(dateString) {
            const [day, month, year] = dateString.split('-');
            return new Date(`${year}-${month}-${day}`);
        }

        let fromDate = parseDate(fromDateValue);
        let toDate = parseDate(toDateValue);

        // Calculate the difference in milliseconds
        let diffInTime = toDate.getTime() - fromDate.getTime();

        // Convert milliseconds to days
        let diffDays = diffInTime / (1000 * 3600 * 24) + 1;
        let SundayDiffDays = Math.ceil(diffInTime / (1000 * 3600 * 24)) + 1;

        var sandwhichLeaves = {!! json_encode($sandwhichLeaves) !!};
        var casualLeaves = {!! json_encode($casualLeaves) !!};
        var earnedLeaves = {!! json_encode($earnedLeaves) !!};
        
        if(leaveTypeId === 'LTY001')
        {
            if (sandwhichLeaves.fieldvalue == 'Yes' && casualLeaves.fieldvalue == '2') 
            {

                if (diffDays <= casualLeaves.fieldvalue) {
                    $('#criteria_validation').empty();
                    $('#total_appear_days').addClass("success").text('Appear leave - ' + diffDays + ' days');
                    $('#total_days').val(diffDays); // Set the value of the hidden input field to diffDays
                    $('.btn-primary').show();  // hide and display save button
                } 
                else if (diffDays >= casualLeaves.fieldvalue) {
                    $('#total_appear_days').empty();
                    $('#criteria_validation').addClass("error").text('The system will not allow more than the ' + casualLeaves.fieldvalue + ' criteria set by the institute.');
                    $('.btn-primary').hide();  // hide and display save button
                }
            }
           else if (sandwhichLeaves.fieldvalue == 'No' && casualLeaves.fieldvalue == '2') 
            {
                $.ajax({
                    url: '/getHolidays',
                    type: 'GET',
                    data: {
                        fromDate: fromDateValue,
                        toDate: toDateValue
                    },
                    success: function(response) {
                        var holidays = response; // Use the response directly
                        var holidaysCount = holidays.length;

                        // Subtract Saturdays and Sundays
                        var saturdaysSundaysCount = 0;

                        for (var date = new Date(fromDate); date <= toDate; date.setDate(date.getDate() + 1)) {
                            var dayOfWeek = date.getDay();
                            if (dayOfWeek === 0 || dayOfWeek === 6) // 0 is Sunday, 6 is Saturday
                            { 
                                saturdaysSundaysCount++;
                            }
                        }

                        diffDays -= (holidaysCount + saturdaysSundaysCount);

                        if (diffDays <= casualLeaves.fieldvalue) {
                            $('#without_sandwich_criteria_validation').empty();
                            $('#without_sandwich_total_appear_days').addClass("success").text('Appear leave - ' + diffDays + ' days');
                            $('#total_days').val(diffDays); // Set the value of the hidden input field to diffDays
                            $('.btn-primary').show();  // hide and display save button
                        } 
                        else if (diffDays >= casualLeaves.fieldvalue) {
                            $('#without_sandwich_total_appear_days').empty();
                            $('#without_sandwich_criteria_validation').addClass("error").text('The system will not allow more than the ' + casualLeaves.fieldvalue + ' criteria set by the institute.');
                            $('.btn-primary').hide();  // hide and display save button
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                    }
                });
            }
            else
            {
                $('#without_sandwich_total_appear_days').addClass("success").text('Appear leave - ' + diffDays + ' days');
                $('#total_days').val(diffDays); // Set the value of the hidden input field to diffDays
                $('.btn-primary').show();  // hide and display save button
            }
        }
        else
        {
            // $.ajax({
            //     url: '/getHolidays',
            //     type: 'GET',
            //     data: {
            //         fromDate: fromDateValue,
            //         toDate: toDateValue
            //     },
            //     success: function(response) {
            //         var holidays = response; // Use the response directly
            //         var holidaysCount = holidays.length;

            //         // Subtract Saturdays and Sundays
            //         var saturdaysSundaysCount = 0;
            //         // Subtract Sundays
            //         var SundaysCount = 0;
            //         for (var date = new Date(fromDateValue); date <= toDateValue; date.setDate(date.getDate() + 1)) {
            //             var dayOfWeek = date.getDay();
            //             // Subtract Saturdays and Sundays
            //             if (dayOfWeek === 0 || dayOfWeek === 6) // 0 is Sunday, 6 is Saturday
            //             { 
            //                 saturdaysSundaysCount++;
            //             }
            //             // Subtract Sundays
            //             if (dayOfWeek === 0) // 0 is Sunday
            //             { 
            //                 SundaysCount++;
            //             }
            //         }
            //         diffDays -= (holidaysCount + saturdaysSundaysCount);
            //         SundayDiffDays -= (holidaysCount); // Subtract Sundays
            //         // console.log(SundayDiffDays);
            //         $('#without_sandwich_total_appear_days').empty();
            //         $('#without_sandwich_criteria_validation').empty();
            //         console.log('fromDate='+fromDateValue+'==toDate='+toDateValue);

            //         console.log('diffDays='+diffDays);
            //         console.log('saturdaysSundaysCount='+saturdaysSundaysCount);
            //         console.log('SundayDiffDays='+SundayDiffDays);
            //         console.log('earnedLeaves='+earnedLeaves.fieldvalue);
            //         if(leaveType === '9' && earnedLeaves.fieldvalue && earnedLeaves.fieldvalue!=='' && SundayDiffDays > earnedLeaves.fieldvalue){
            //             $('#without_sandwich_criteria_validation').removeClass('success');
            //             $('#without_sandwich_criteria_validation').addClass("error").text('The system will not allow more than the ' + earnedLeaves.fieldvalue + ' criteria set by the institute.');
            //         }else{
            //             $('#without_sandwich_criteria_validation').removeClass('error');
            //             $('#without_sandwich_total_appear_days').addClass("success").text('Appear leave - ' + SundayDiffDays + ' days');
            //             $('#total_days').val(SundayDiffDays);
            //         }                    
            //     },
            //     error: function(xhr, status, error) {
            //         console.error(error);
            //     }
            // });
            
            // 17-01-2025 added for minimum days earned leave
            // Function to convert DD-MM-YYYY to YYYY-MM-DD
            $.ajax({
                url: '/getHolidays',
                type: 'GET',
                data: {
                    fromDate: fromDateValue,
                    toDate: toDateValue
                },
                success: function(response) {
                    var holidays = response;
                    var holidaysCount = holidays.length;
                    // added code for getting proper day count as per day
                    var startDate = new Date(fromDate);
                    var endDate = new Date(toDate);

                    var saturdaysSundaysCount = 0;
                    var SundayDiffDays = diffDays;
                    var nosaturdaysSundays = 0;
                    for (var date = new Date(fromDate); date <= endDate; date.setDate(date.getDate() + 1)) {
                        var dayOfWeek = date.getDay();
                        // Count Saturdays and Sundays
                        if (dayOfWeek === 0 || dayOfWeek === 6) { 
                            saturdaysSundaysCount++;
                        }
                        // Subtract Sundays
                        if (dayOfWeek === 0) { // 0 is Sunday
                            SundayDiffDays--;
                        }
                        nosaturdaysSundays++;
                    }

                    // Adjust diffDays based on holidays and weekends
                    diffDays -= (holidaysCount + saturdaysSundaysCount);
                    SundayDiffDays -= holidaysCount;

                    // Update UI
                    $('#without_sandwich_total_appear_days').empty();
                    $('#without_sandwich_criteria_validation').empty();
                    // console.log('fromDate=' + fromDateValue + '==toDate=' + toDateValue);

                    console.log('diffDays=' + diffDays);
                    // console.log('saturdaysSundaysCount=' + saturdaysSundaysCount);
                    // console.log('SundayDiffDays=' + SundayDiffDays);
                    // console.log('earnedLeaves=' + earnedLeaves.fieldvalue);
                    // console.log('nosaturdaysSundays=' + nosaturdaysSundays);

                    // Validate leave type and display appropriate messages for sandwish leave no saturdays Sundays off
                    if (leaveType === '9' && earnedLeaves.fieldvalue && earnedLeaves.fieldvalue !== '' && nosaturdaysSundays < earnedLeaves.fieldvalue && sandwhichLeaves.fieldvalue == 'Yes') {
                        $('#without_sandwich_criteria_validation').removeClass('success');
                        $('#without_sandwich_criteria_validation').addClass("error").text('Minimun Leave set by institute is ' + earnedLeaves.fieldvalue + '. The system will not allow less than the ' + earnedLeaves.fieldvalue + ' criteria set by the institute.');
                        $('.btn-primary').hide(); // hide and display save button
                    }
                    // Validate leave type and display appropriate messages for No sandwish leave
                    else if (leaveType === '9' && earnedLeaves.fieldvalue && earnedLeaves.fieldvalue !== '' && SundayDiffDays < earnedLeaves.fieldvalue  && sandwhichLeaves.fieldvalue == 'No') {
                        $('#without_sandwich_criteria_validation').removeClass('success');
                        $('#without_sandwich_criteria_validation').addClass("error").text('Minimun Leave set by institute' + earnedLeaves.fieldvalue + '. The system will not allow less than the ' + earnedLeaves.fieldvalue + ' criteria set by the institute.');
                        $('.btn-primary').hide(); // hide and display save button
                    }
                    else if(HalfAllowedDays!='' && HalfAllowedDays < SundayDiffDays && leaveIds.includes(leaveType) && $('#day_type').val()==="half"){
                        $('#without_sandwich_criteria_validation').removeClass('success');
                        $('#without_sandwich_criteria_validation').addClass("error").text('Minimun Leave set by institute is ' + HalfAllowedDays + '. The system will not allow less than the ' + HalfAllowedDays + ' criteria set by the institute.');
                        $('.btn-primary').hide();
                    } 
                    else if(sandwhichLeaves.fieldvalue == 'Yes'){
                        $('#without_sandwich_criteria_validation').removeClass('error');
                        $('#without_sandwich_total_appear_days').addClass("success").text('Appear leave - ' + nosaturdaysSundays + ' days');
                        $('#total_days').val(nosaturdaysSundays);
                        $('.btn-primary').show(); // hide and display save button
                    } 
                     else {
                        $('#without_sandwich_criteria_validation').removeClass('error');
                        $('#without_sandwich_total_appear_days').addClass("success").text('Appear leave - ' + SundayDiffDays + ' days');
                        $('#total_days').val(SundayDiffDays);
                        $('.btn-primary').show(); // hide and display save button
                    }
                },
                error: function(xhr, status, error) {
                    console.error(error);
                }
            });

        }
        
    });
    function openSummary(user_id,dep_id,year) {
        window.open('leave-summary-report/create?department_id=' + dep_id + '&emp_id=' + user_id + '&years=' + year, '_blank', 'scrollbars=yes,resizable=no,status=no,location=no,toolbar=no,menubar=no,width=1200,height=1200,left=100,top=100');
    }

</script>
@include('includes.footer')
