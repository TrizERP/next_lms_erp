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
                <form action="{{route('daywise_attendance_report.create')}}" enctype="multipart/form-data">
                @csrf
                    <div class="row">
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
                        {!! App\Helpers\HrmsDepartments("3","multiple",$department_id,"multiple",$emp_id,"") !!}

                        <div class="col-md-3 form-group">
                            <label>From Date</label>
                            <input type="text" class="form-control mydatepicker" name="from_date" id="from_date" @if(isset($data['from_date'])) value="{{$data['from_date']}}"  @endif required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>To Date</label>
                            <input type="text" class="form-control mydatepicker" name="to_date" id="to_date" @if(isset($data['to_date'])) value="{{$data['to_date']}}"  @endif required>
                        </div>
                        <div class="col-md-12 col-sm-offset-4 text-center form-group">
                            <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @if(isset($data['attDetails']))
            <div class="card">
                <h4><span style="color:red;">Note: Red color indicates employee under probation period</span></h4>
                <div class="table-responsive mt-20 tz-report-table">
                    <table id="example" class="table table-bordered">
                        <thead style="text-align:center;">
                            <tr>
                                <th rowspan="2">Sr.No</th>
                                <th rowspan="2">Emp.No</th>
                                <th rowspan="2">Employee Name</th>
                                <!-- selected dates  -->
                                @foreach($data['selDates'] as $k=>$v)
                                <th>{{$v}}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach($data['selDays'] as $k=>$v)
                                <th>{{$v}}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['attDetails'] as $key=>$value)
                            <tr>
                                <td>{{$key+1}}</td>
                                <td>{{$value['employee_no']}}</td>
                                <td>{{$value['full_name']}}</td>
                                <!-- attendance by date  -->
                                @if(isset($value['attData'][$value['id']]))
                                @foreach($value['attData'][$value['id']] as $k=>$v)
                                    @php 
                                    $style="";
                                    if($v=="LT"){
                                        $style="background:#61d0db;color:#4598a1";
                                    }
                                    else if($v=="HD"){
                                        $style="background:#629ddb;";
                                    }
                                    else if($v=="ED"){
                                        $style="background:#99D699;";
                                    }
                                    else if($v=="A"){
                                        $style="background:orange;color:red";
                                    }
                                    @endphp
                                    <td style="{{$style}}">{{$v}}</td>
                                @endforeach
                                @endif
                                <!-- end attendance by date  -->
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
               
            </div>
        @endif
    </div>
</div>


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

        $('#example thead tr:eq(0)').clone(true).appendTo('#example thead');
        $('#example thead tr:eq(2) th').each(function (i) {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search here" />');

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
</script>
@include('includes.footer')
