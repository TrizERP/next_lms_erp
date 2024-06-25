@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
<div class="container-fluid">
<div class="row bg-title">
   <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
      <h4 class="page-title">Monthly Pay Roll Reports</h4>
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
            <form action="{{route('monthly_payroll.create')}}">
                @csrf
                <div class="row">
                @php 
                $currentMonth = date('M');
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
                    <label>Select Month</label>
                    <select id='month' name="month" class="form-control">
                        <option value="0">Select Month</option>
                        @foreach($data['months'] as $month)
                        <option @if(isset($data['selMonth']) && $data['selMonth'] == $month) selected @elseif($currentMonth == $month) Selected @endif>{{$month}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label>Select Year</label>
                    <select id='year' name="year" class="form-control">
                        <option value="0">Select Year</option>
                        @foreach($data['years'] as $year)
                        <option @if(isset($data['selYear']) && $data['selYear'] == $year) selected @elseif(date('Y')==$year) selected @endif>{{$year}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-offset-4 text-center form-group">
                    <input type="submit" name="submit" value="Search" class="btn btn-success">
                </div>
                </div>
            </form>
        </div>
        <!-- table data  card -->
        @if(isset($data['header']))
        <div class="card">
            <form action="{{route('monthly_payroll.store')}}" method="post">
            @csrf
            <input type="hidden" name="month" @if(isset($data['selMonth'])) value="{{$data['selMonth']}}" @endif>
            <input type="hidden" name="year" @if(isset($data['selYear'])) value="{{$data['selYear']}}" @endif>

            <div class="table-responsive mt-20 tz-report-table">
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>SR No.</th>
                            <th>Emp No</th>
                            <th>Employee Name</th>
                            <th>Total Day</th>
                            @foreach($data['header'] as $hkey => $col)
                                <th class="text-left">{{$col}} </th>
                            @endforeach
                            <th>PDF Link</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['employeeDetails'] as $key => $value)
                        <tr>
                            <td>{{$key+1}}</td>
                            <td>{{$value['employee_no']}}</td>
                            <td>{{$value['full_name'] ?? '-' .'('.$value['user_profile'] ?? '-' .')'}}</td>
                            <td><input type="number" name="payrollVal[{{$value['id']}}][total_day]" onkeyup="getData(this,{{$value['id']}})" class="form-control" @if(isset($value['monthlyData']->total_day)) value="{{$value['monthlyData']->total_day}}" @endif></td>
                            @foreach($data['header'] as $hkey => $col)
                                @if(!empty($value['monthlyData']))
                                    @php 
                                        $salaryStructure = json_decode($value['monthlyData']->employee_salary_data);
                                    @endphp 
                                   
                                @if($hkey=="total_deduction")
                                    <td id="{{$value['id'].'_'.$hkey}}">{{$value['monthlyData']->total_deduction ?? 0}}</td>
                                    <input type="hidden" name="payrollVal[{{$value['id']}}][{{$hkey}}]" id="input_{{$value['id'].'_'.$hkey}}" @if(isset($value['monthlyData']->total_deduction)) value="{{$value['monthlyData']->total_deduction}}" @endif>
                                @elseif($hkey=="total_payment")
                                    <td id="{{$value['id'].'_'.$hkey}}">{{$value['monthlyData']->total_payment ?? 0}}</td>
                                    <input type="hidden" name="payrollVal[{{$value['id']}}][{{$hkey}}]" id="input_{{$value['id'].'_'.$hkey}}" @if(isset($value['monthlyData']->total_payment)) value="{{$value['monthlyData']->total_payment}}" @endif>
                                @elseif($hkey=="received_by")
                                    <td id="{{$value['id'].'_'.$hkey}}">{{$value['monthlyData']->received_by ?? '' }}</td>
                                    <input type="hidden" name="payrollVal[{{$value['id']}}][{{$hkey}}]" id="input_{{$value['id'].'_'.$hkey}}" @if(isset($value['monthlyData']->received_by)) value="{{$value['monthlyData']->received_by}}" @endif>
                                @else
                                <td id="{{$value['id'].'_'.$hkey}}">{{$salaryStructure->$hkey ?? 0}}</td>
                                <input type="hidden" name="payrollVal[{{$value['id']}}][payrollHead][{{$hkey}}]" id="input_{{$value['id'].'_'.$hkey}}" @if(isset($salaryStructure->$hkey)) value="{{$salaryStructure->$hkey}}" @endif>
                                @endif

                            @else 
                                @php 
                                    $name = "payrollVal[".$value['id']."][payrollHead][".$hkey."]";
                                 if(in_array($hkey,["total_deduction","total_payment","received_by"])){
                                    $name="payrollVal[".$value['id']."][".$hkey."]";
                                 }
                                @endphp
                                <td id="{{$value['id'].'_'.$hkey}}">0</td>
                                <input type="hidden" name="{{$name}}" id="input_{{$value['id'].'_'.$hkey}}">
                            @endif
                            @endforeach

                            <td>@if(isset($value['monthlyData']->total_day))<a href="{{ env('APP_URL')."monthly-payroll-report/pdf/".$value['id']."/".$data['selMonth'].'/'.$data['selYear'] }}" class="btn btn-primary">PDF</a> @else - @endif </td>
                          
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="row">
                <div class="col-m-12 form-group">
                    <input type="submit" class="btn btn-success" value="Save" name="Save">
                </div>
            </div>
            </form>
        </div>
        @endif
    <!-- end table card  -->
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
   
   function getData(inputElement, emp_id) {
        var inputValue = inputElement.value;
        var month = $('#month').val();
        var year = $('#year').val();

        $.ajax({
            url: "{{ route('getMonthlyData') }}",
            data: { totalDay: inputValue, emp_id: emp_id, month:month, year:year},
            type: 'GET',
            success: function (response) {
                // Check if salaryData is not empty
                if (response.salaryData && Object.keys(response.salaryData).length > 0) {
                    // console.log(response.salaryData);
                    Object.entries(response.salaryData).forEach(([index, element]) => {
                        $('#' + emp_id + '_' + index).text(element);
                        $('#input_' + emp_id + '_' + index).val(element);
                    });
                    console.log(response.salaryData);
                } else {
                    console.log('salaryData is empty');
                }
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
    }
</script>
@include('includes.footer')