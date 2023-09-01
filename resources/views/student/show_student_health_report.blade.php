@include('includes.headcss')

@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Health Report</h4>
            </div>
        </div>
        @php
        $grade_id = $standard_id = $division_id = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }
        @endphp
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
                        <form action="{{ route('show_student_health_report') }}" enctype="multipart/form-data"
                              method="post">
                            @csrf
                            <div class="row">
                                {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                                <div class="col-md-4 form-group">
                                    <label> Health </label>
                                    <select name="health_type" class="form-control" required="required">
                                        <option value="">--Select Health--</option>
                                        <option value="student_infirmary"
                                                @if(isset($data['health_type'])) @if($data['health_type'] == 'student_infirmary') selected @endif @endif>
                                            Infirmary Info
                                        </option>
                                        <option value="student_vaccination"
                                                @if(isset($data['health_type'])) @if($data['health_type'] == 'student_vaccination') selected @endif @endif>
                                            Vaccination Info
                                        </option>
                                        <option value="student_height_weight"
                                                @if(isset($data['health_type'])) @if($data['health_type'] == 'student_height_weight') selected @endif @endif>
                                            Height &amp; Weight Info
                                        </option>
                                        <option value="student_health"
                                                @if(isset($data['health_type'])) @if($data['health_type'] == 'student_health') selected @endif @endif>
                                            Health Info
                                        </option>
                                    </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>From Date </label>
                        <input type="text" id='from_date' value="@if(isset($data['from_date'])) {{$data['from_date']}} @endif" required name='from_date' class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>To Date </label>
                        <input type="text" id='to_date' value="@if(isset($data['to_date'])) {{$data['to_date']}} @endif"
                               required name='to_date' class="form-control mydatepicker" autocomplete="off">
                    </div>

                                <div class="col-md-12 form-group">
                                    <center>
                                        <input type="submit" name="submit" value="Search" class="btn btn-success">
                                    </center>
                                </div>
                            </div>

                        </form>
                    </div>

                    @if(isset($data['health_data']))
        @php
            if(isset($data['health_data'])){
                $health_data = $data['health_data'];
            }
        @endphp
                        <div class="card">
                            <div class="table-responsive">
                            @php
                                echo App\Helpers\get_school_details($grade_id, $standard_id, $division_id);
                                echo '<br><center><span style="font-size: 14px; font-weight: 600; font-family: Arial, Helvetica, sans-serif !important">';
                                echo 'Health : ' . (isset($data['health_type']) ? $data['health_type'] : '');
                                echo '</span> <span style="font-size: 14px; font-weight: 600; font-family: Arial, Helvetica, sans-serif !important">';
                                echo 'From Date : ' . (isset($data['from_date']) ? date('d-m-Y', strtotime($data['from_date'])) : '') . ' - ';
                                echo '</span><span style="font-size: 14px; font-weight: 600; font-family: Arial, Helvetica, sans-serif !important">';
                                echo 'To Date : ' . (isset($data['to_date']) ? date('d-m-Y', strtotime($data['to_date'])) : '') . '</span></center><br>';
                            @endphp
                                <table id="example" class="table table-striped">
                                    <thead>
                                    <tr>
                                        @foreach($data['headers'] as $hkey => $header)
                                <th> {{$header}} </th>
                            @endforeach
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($health_data as $key => $value)
                                        <tr>
                                            @foreach($data['headers'] as $hkey => $header)
                                <td> {{$value->$hkey}} </td>
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
                        title: 'Student Health Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'Student Health Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'Student Health Report'},
                    {
                        extend: 'print',
                        text: ' PRINT',
                        title: 'Student Health Report',
                        customize: function (win) {
                            $(win.document.body).prepend(`{!! App\Helpers\get_school_details("$grade_id", "$standard_id", "$division_id") !!}`);
                        }
                    },
                    'pageLength'
                ],
            });
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
            } );
        } );
    } );
</script>

@include('includes.footer')
