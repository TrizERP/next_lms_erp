@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Employee Leave Summary Reports</h4>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                @if ($sessionData = Session::get('data'))
                    @if($sessionData->status_code == 1)
                        <div class="alert alert-success alert-block">
                    @else
                        <div class="alert alert-danger alert-block">
                    @endif
                        <button type="button" class="close" data-dismiss="alert">Ã—</button>
                        <strong>{{ $sessionData->message }}</strong>
                    </div>
                @endif
                <form action="{{route('leave.summary.report.show')}}" enctype="multipart/form-data" method="post">
                @csrf
                    <div class="row">
                        {{-- <div class="col-md-4 form-group">
                            <label>Department List</label>
                            <select id='department_id' name="department_id" class="form-control">
                                <option value="">--Select Department--</option>
                                @foreach($data['departments'] as $id => $department)
                                    <option value="{{$id}}"
                                    @if(isset($data['department_id']))
                                        @if($data['department_id'] == $id)
                                        selected='selected'
                                        @endif
                                    @endif
                                    >{{ $department }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Employee List</label>
                            <select id='employee_id' name="employee_id" class="form-control">
                                <option value="">--Select Employee--</option>
                                @if(!empty($data['employees']))
                                    @foreach($data['employees'] as $key=>$value)
                                        <option value="{{$value['id']}}" @if(isset($data['employee_id']) && $data['employee_id'] == $value['id']) selected @endif>{{$value['first_name'] ?? ''}} {{$value['last_name'] ?? ''}}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div> --}}
                        @php 
                        $department_id = $emp_id = '';
                            if(isset($data['department_id'])){
                                $department_id = $data['department_id'];
                            }
                            if(isset($data['employee_id'])){
                                $emp_id = $data['employee_id'];
                            }
                            $currentYear = date('Y');
                        @endphp 
                        {!! App\Helpers\HrmsDepartments("4","",$department_id,"",$emp_id,"") !!}

                        <div class="col-md-4 form-group">
                            <label>Year</label>
                            <select id='years' name="years" class="form-control">
                                @foreach($data['allyears'] as $key=> $year)
                                    <option @if(isset($data['years']) && $data['years'] == $key) selected @elseif($key==$currentYear) Selected @endif value="{{$key}}">{{$year}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-offset-4 text-center form-group">
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @if(isset($data['get_employee_leave_lists']))
            <div class="card">
                <h4><span style="color:red;">Note: Red color indicates employee under probation period</span></h4>
                <div class="table-responsive mt-20 tz-report-table">
                    <table id="example" class="table table-striped">
                        <thead style="text-align:center;">
                            <tr>
                                <th rowspan="3" style="padding-top:40px;">Sr.No</th>
                                <th rowspan="3" style="padding-top:40px;">Emp.No</th>
                                <th rowspan="3" style="padding-top:40px;">Employee Name</th>
                                <th rowspan="3" style="padding-top:40px;">Designation</th>
                                <th rowspan="3" style="padding-top:40px;">Joining Date</th>
                                <th colspan="{{count($data['get_hrms_leave_types']) * 3}}" style="text-align:center; !importanat">Taken</th>
                            </tr>
                            <tr>
                                @foreach($data['get_hrms_leave_types'] as $get_hrms_leave_type)
                                    @if($get_hrms_leave_type->leave_type_id=="LTY001" || $get_hrms_leave_type->leave_type_id=="LTY009")
                                    <th colspan="3" style="text-align:center; !importanat">{{ $get_hrms_leave_type->leave_type }}</th>
                                    @else
                                    <th style="text-align:center; !importanat">{{ $get_hrms_leave_type->leave_type }}</th>
                                    @endif
                                @endforeach
                            </tr>
                            <tr>
                                @foreach($data['get_hrms_leave_types'] as $get_hrms_leave_type)
                                @if($get_hrms_leave_type->leave_type_id=="LTY001" || $get_hrms_leave_type->leave_type_id=="LTY009")
                                    <th>Op Balance</th>
                                    <th>Taken</th>
                                    <th>Balance</th>
                                @else
                                    <th>Taken</th>
                                @endif

                                @endforeach
                            </tr>
                        </thead>
                        @php
                        $j = 1;
                        @endphp
                        <tbody>
                            @foreach($data['get_employee_leave_lists'] as $key => $get_employee_leave_list)
                                @php 
                                    $att_status = '';
                                    $get_probation_period_from = \Carbon\Carbon::parse($get_employee_leave_list->probation_period_from)->format('Y-m-d');

                                    $get_probation_period_to = \Carbon\Carbon::parse($get_employee_leave_list->probation_period_to)->format('Y-m-d');
                                @endphp

                                @if($get_probation_period_from <= now() && now() <= $get_probation_period_to) 
                                    @php 
                                        $att_status = 'background-color: #ff7373';  
                                    @endphp
                                @endif

                                <tr style="text-align:center; !importanat; {{ $att_status }}">
                                    <td>{{ $j++ }}</td>
                                    <td>{{ $get_employee_leave_list->employee_no }}</td>
                                    <td>
                                        @if(is_array($get_employee_leave_list) && isset($get_employee_leave_list['employee_name']))
                                            {{ $get_employee_leave_list['employee_name'] }}
                                        @elseif(is_object($get_employee_leave_list) && isset($get_employee_leave_list->employee_name))
                                            {{ $get_employee_leave_list->employee_name }}
                                        @endif
                                    </td>
                                    <td>{{ $get_employee_leave_list->department_name }}</td>
                                    <td>
                                        @if(is_array($get_employee_leave_list) && isset($get_employee_leave_list['joined_date']))
                                            {{ \Carbon\Carbon::parse($get_employee_leave_list['joined_date'])->format('d-M-Y') }}
                                        @elseif(is_object($get_employee_leave_list) && isset($get_employee_leave_list->joined_date))
                                            {{ \Carbon\Carbon::parse($get_employee_leave_list->joined_date)->format('d-M-Y') }}
                                        @endif
                                    </td>

                                    @foreach($data['get_hrms_leave_types'] as $get_hrms_leave_type)
                                        @php 
                                            $total_op = 0; $total_taken = 0; $total_remain = 0;
                                        @endphp
                                        @if($get_hrms_leave_type->leave_type_id=="LTY001" || $get_hrms_leave_type->leave_type_id=="LTY009")
                                        <td>
                                            @if(isset($data['op_data'][$get_hrms_leave_type->leave_type][$get_employee_leave_list->user_id]) && $data['op_data'][$get_hrms_leave_type->leave_type][$get_employee_leave_list->user_id] != '')
                                                @php 
                                                    $total_op = $data['op_data'][$get_hrms_leave_type->leave_type][$get_employee_leave_list->user_id];
                                                @endphp

                                                {{ $total_op }}
                                            @else
                                                0
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($data['new_data'][$get_hrms_leave_type->leave_type]) && $data['new_data'][$get_hrms_leave_type->leave_type] != '')
                                                @php 
                                                    $total_taken = $data['new_data'][$get_hrms_leave_type->leave_type][$get_employee_leave_list->id] ?? 0; 
                                                @endphp

                                                <a style="text-decoration:underline !important" onclick="getLeaveList('{{$get_employee_leave_list->id}}','{{$get_employee_leave_list->department_id}}','{{$get_hrms_leave_type->id}}')">{{ $total_taken }}</a> 
                                            @else
                                                0
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $total_remain = ($total_op - $total_taken);//(int) 
                                            @endphp

                                            {{ $total_remain }}
                                        </td>
                                    @else
                                    <td>
                                            @if(isset($data['new_data'][$get_hrms_leave_type->leave_type]) && $data['new_data'][$get_hrms_leave_type->leave_type] != '')
                                                @php 
                                                    $total_taken = $data['new_data'][$get_hrms_leave_type->leave_type][$get_employee_leave_list->id] ?? 0; 
                                                @endphp

                                                <a style="text-decoration:underline !important" onclick="getLeaveList('{{$get_employee_leave_list->id}}','{{$get_employee_leave_list->department_id}}','{{$get_hrms_leave_type->id}}')">{{ $total_taken }}</a> 
                                            @else
                                                0
                                            @endif
                                    </td>
                                    @endif
                                    
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
               
            </div>
        @endif
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width: 1260px !important;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">View List</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="modalBody">
        
      </div>
    </div>
  </div>
</div>
<!-- end modal  -->
@include('includes.footerJs')
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>

<script>
    $(document).ready(function () {
        var table = $('#example').DataTable({
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1]
                ['100', '500', '1000', 'Show All']
            ],
            pageLength: 100,  // Default page length
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Leave Summary Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Leave Summary Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Leave Summary Report'},
                {extend: 'print', text: ' PRINT', title: 'Leave Summary Report'},
                'pageLength'
            ],
        });
        table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');
    });
</script>
<script>
	$(document).on("change", "#department_id", function(e) {
        $('#employee_id').empty().append('<option value="">--Select Employee--</option>');
        var departmentId = $(this).val();
        
        $.ajax({
            type: "post",
            url: "{{ route('emp-list') }}",
            data: { department_id: departmentId },
            success: function(data) {
                var options = '';
                $.each(data.employees, function(index, employee) {
                    options += '<option value="' + employee.id + '" >' + employee.first_name + ' ' + employee.last_name + '</option>';
                });
                $('#employee_id').append(options);
            },
            error: function(xhr) {
                console.error(xhr.responseText);
            }
        });
    });

    function getLeaveList(empId,depId,leaveId){
        $('#modalBody').empty();
        var year = {{isset($data['years']) ? $data['years'] : session()->get('syear') }};
        $.ajax({
            url : "{{route('leavelist')}}",
            data : {emp_id:empId,department_id:depId,leave_type_id:leaveId,year:year},
            type : 'GET',
            success : function(response){
               if(Array.isArray(response)){
                $('#modalBody').empty();
                var table = `<table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>From Date</th>
                            <th>To Date</th>
                            <th>Employee Name</th>
                            <th>Leave Type</th>
                            <th>Leave Length</th>
                            <th>Day Type</th>
                            <th>Status</th>
                            <th class="text-left">Comments</th>
                        </tr>
                    </thead>
                    <tbody>`;
                    var i =1;
                    response.forEach(element => {
                        var from_date = element.from_date;
                        var to_date = element.to_date;
                        var day_type = parseFloat(element.day_type); 

                        var fromDate = moment(from_date);
                        var toDate = moment(to_date);
                        var dayCount = 0;

                        for (var date = fromDate.clone(); date.isSameOrBefore(toDate); date.add(1, 'day')) {
                            dayCount= dayCount+day_type;
                        }
                        console.log('Number of days counted:', dayCount);
                        var day = "Half Day";
                        if(day_type >= 1){
                            var day = "Full Day";
                        }
                        table += `<tr>
                        <td>${(i++)}</td>
                        <td>${element.from_date}</td>
                        <td>${element.to_date}</td>
                        <td>${element.employee_name}</td>
                        <td>${element.leave_type}</td>
                        <td>${dayCount}</td>
                        <td>${day}</td>
                        <td>${element.status}</td>
                        <td>${element.comment}</td>
                     </tr>`;  
                    });
                    table += `</tbody>
                    </table>`;
                    $('#modalBody').append(table);
                    $('#exampleModal').modal('show');
               }else{
                alert('Something went wrong! Not Getting Data');
               }
            },
            error : function(error){
                console.error(error);
            }
        })
    }

</script>
@include('includes.footer')
