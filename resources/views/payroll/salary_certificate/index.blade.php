@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Salary Certificate</h4>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ session('success') }}</strong>
                    </div>
                @endif
                <form action="{{ route('hrms_salary_certificate.report') }}" enctype="multipart/form-data" method="post">
                @csrf
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Department List</label>
                            <select id='department_id' name="department_id" class="form-control" required>
                                <option value="">Select Department</option>
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
                            <select id='employee_id' name="employee_id" class="form-control" required>
                                <option value="">Select Employee</option>
                                @if(!empty($data['employees']))
                                    @foreach($data['employees'] as $key=>$value)
                                        <option value="{{$value['id']}}" @if(isset($data['employee_id']) && $data['employee_id'] == $value['id']) selected @endif>{{$value['first_name'] ?? ''}} {{$value['last_name'] ?? ''}}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Select Year</label>
                            <select id='year' name="year" class="form-control" required>
                                <option>Select Year</option>
                               @foreach($data['years'] as $key=>$value)
                               <option value="{{$value}}" {{ (isset($data['year']) && $data['year']==$value) ? 'Selected' : '' }}>{{$value}}</option>
                               @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Month</label>
                            <select id='month_id' name="month_id[]" class="form-control" multiple required>
                                @foreach($data['month_ids'] as $key=>$value)
                                <option value="{{$key}}" @if(isset($data['selMonths']) && in_array($key,$data['selMonths']))  Selected @elseif(!isset($data['selMonths']) && $key==1) Selected @endif>{{$value}}</option>
                               @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Payroll Type</label>
                            <select id='payroll_type_id' name="payroll_type_id[]" class="form-control" multiple required>
                                @foreach($data['payrollTypes'] as $id => $payrollType)
                                    <option value="{{$payrollType['id']}}"
                                    @if($payrollType['payroll_name'] == 'BASIC')
                                        selected
                                    @endif
                                    >{{ $payrollType['payroll_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Reason</label>
                            <input type="text" class="form-control" name="reason" id="reason" value="">
                        </div>
                        <div class="col-md-3 col-sm-offset-4 text-center form-group">
                            <input type="submit" name="submit" value="Generate" class="btn btn-success">
                        </div>
                    </div>
                </form>
                @if(session('success'))
                    <div id="download-link" align="center">
                        <h4 style="color:red;">Download File : <a href="{{ route('salary_certificate_pdf_download', ['emp_id' => $data['employee_id'], 'year' => $data['year'], 'sub_institute_id' => session()->get('sub_institute_id')]) }}">Download Salary Certificate</a></h4>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>
    $(document).ready(function () {
        var table = $('#example').DataTable({
            ordering: false,
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Student Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Student Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Student Report'},
                {extend: 'print', text: ' PRINT', title: 'Student Report'},
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
</script>
<script>
    $(document).on("change", "#department_id", function(e) {
        $('#employee_id').empty();
        var departmentId = $(this).val();
        
        $.ajax({
            type: "post",
            url: "{{ route('form16.get.employees.list') }}",
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
</script>
<script>
    function printDiv(divName) 
    {
        var divToPrint = document.getElementById(divName).innerHTML;
        var popupWin = window.open('', '_blank', 'width=300,height=300');
        popupWin.document.open();
        popupWin.document.write('<html>');
        popupWin.document.write('<head><style>body{margin:0;padding:0}</style></head>');
        popupWin.document.write('<body>');
        popupWin.document.write('<div id="' + divName + '">' + divToPrint + '</div>');
        popupWin.document.write('</body></html>');
        popupWin.document.close();

        // Wait for content to load before printing
        popupWin.onload = function() {
            setTimeout(function() {
                popupWin.print();
            }, 1000);
        };
    }
</script>
@include('includes.footer')
