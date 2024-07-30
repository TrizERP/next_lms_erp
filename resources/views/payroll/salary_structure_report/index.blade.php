@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Salary Structure Reports</h4>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
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
                            <form action="" enctype="multipart/form-data"
                                  method="post">
                                @csrf
                                <div class="row">
                                @php 
                                    $dep_id = $emp_id = '';
                                    if(isset($data['department_id'])){
                                        $dep_id = $data['department_id'];
                                    }

                                    if(isset($data['selected_emp'])){
                                        $emp_id = $data['selected_emp'];
                                    }
                                @endphp

                                {!! App\Helpers\HrmsDepartments("","multiple",$dep_id,"multiple",$emp_id,"") !!}
                                    <div class="col-md-3 form-group">
                                        <label>Select Year</label>
                                        <select id='year' name="year" class="form-control">
                                          @foreach($data['years'] as $key => $value)
                                                <option value="{{$key}}" @if(isset($data['year']) && $key==$data['year']) selected @elseif($key==date('Y')) selected @endif>{{$value}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3 col-sm-offset-4 text-center form-group">
                                        <input type="submit" name="submit" value="Search" class="btn btn-success">
                                    </div>
                                </div>
                                <!-- Modal -->
                                <div class="modal fade bd-example-modal-lg" id="exampleModal" tabindex="-1"
                                     role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="exampleModalLabel">Choose Field</h5>
                                                <button type="button" class="close" data-dismiss="modal"
                                                        aria-label="Close">
                                                    <span aria-hidden="true">x</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
            </div>
            @if(isset($data['salaryStructure']))
                <div class="card">
                    <div class="table-responsive mt-20 tz-report-table">
                        <table id="example" class="table table-striped">
                            <thead>
                            <tr>
                                <th>Emp No</th>
                                <th>Employee Name</th>
                                <th>Department</th>
                                <th>Year</th>
                                @foreach($data['headers'] as $hkey => $header)
                                    <th class="text-left"> {{$header}} </th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($data['salaryStructure'] as $key => $value)
                                <tr>
                                    <td>{{$value['employee_no']}}</td>
                                    <td>{{$value['employee_name']}}</td>
                                    <td>{{$value['department']}}</td>
                                    <td>{{$value['year']}}</td>
                                    @php $jsonData = json_decode($value['employee_salary_data'],true); @endphp
                                    @foreach($data['headers'] as $hkey => $header)
                                        @if(isset($jsonData[$hkey]))
                                            <td>{{$jsonData[$hkey]}}</td>
                                        @else 
                                            <td>0</td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
        </div>
        @endif
    </div>

    @include('includes.footerJs')
    <script>
        $(document).ready(function () {
            var table = $('#example').DataTable({
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
@include('includes.footer')
