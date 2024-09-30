@extends('layout')
@section('container')
<div id="page-wrapper">
<div class="container-fluid">
   <div class="row bg-title">
      <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
         <h4 class="page-title">Payroll Deduction</h4>
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
            @php 
                $deductionTypeArr = [1=>'Allowance',2=>'Deduction'];
            @endphp
            <form action="{{route('payroll_deduction.index')}}" enctype="multipart/form-data">
               @csrf
               <div class="row">
                  <div class="col-md-3 form-group">
                     <label>Deduction Type</label>
                     <select id="deduction_type" name="deduction_type" class="form-control" required>
                        <option value="">Select Type</option>
                        @foreach($deductionTypeArr as $key=>$value)
                        <option value="{{$key}}" @if(isset($data['selDeduction']) && $data['selDeduction']==$key) Selected @endif>{{$value}}</option>
                        @endforeach
                     </select>
                  </div>
                  <div class="col-md-3 form-group">
                     <label>Payroll Name</label>
                     <select id='payroll_type' name="payroll_type" class="form-control" required>
                        <option value="">Select Name</option>
                       
                     </select>
                  </div>
                  <div class="col-md-2 form-group">
                     <label>Select Month</label>
                     <select id='month' name="month" class="form-control">
                        @foreach($data['months'] as $month)
                            <option @if(isset($data['selMonth']) && $data['selMonth'] == $month) selected @endif>{{$month}}</option>
                        @endforeach
                     </select>
                  </div>
                  <div class="col-md-2 form-group">
                     <label>Select Year</label>
                     <select id='year' name="year" class="form-control">
                        @foreach($data['years'] as $year)
                            <option @if(isset($data['selYear']) && $data['selYear'] == $year) selected @endif>{{$year}}</option>
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

      <!-- table card start  -->
      @if(isset($data['all_emp']))
      <div class="card">
        <div class="card-body">
            <form action="{{route('payroll_deduction.store')}}" method="post">
            @csrf
            <input type="hidden" value="{{$data['selType']}}" name="payroll_type" id="payroll_type">
            <input type="hidden" value="{{$data['selMonth']}}" name="month" id="month">
            <input type="hidden" value="{{$data['selYear']}}" name="year" id="year">

            <div class="table-responsive">
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>Employee Code</th>
                            <th>Employee Name</th>
                            <th>Department</th>
                            <th class="text-left">Deduction Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($data['all_emp'] as $key => $value)
                        <tr>
                            <td>{{$key+1}}</td>
                            <td>{{$value['employee_no']}}</td>
                            <td>{{$value['full_name']}}</td>
                            <td>{{$value['department']}}</td>
                            <td class="text-left">
                                <input type="number" name="deductAmt[{{$value['id']}}]" id="deductAmt" class="form-control" @if(isset($data['deductionArr'][$value['id']])) value="{{$data['deductionArr'][$value['id']]}}" @endif>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                </table>
            <div>
                <div class="row">
                    <div class="col-md-12">
                        <center>
                            <input type="submit" name="save" value="save" class="btn btn-primary">
                        </center>
                    </div>
                </div>
            </form>
        </div>
      </div>
      @endif
      <!-- table card end  -->

   </div>
</div>
@include('includes.footerJs')
<script>
   
    $(document).ready(function(){
        $('#deduction_type').on('change',function(){
            getPayrollType();
        })

        @if(isset($data['selDeduction'])) 
            getPayrollType({{$data['selType']}});
        @endif 
    })

    function getPayrollType(selId=''){
        var deductionType = $('#deduction_type').val();
        var payrollTypes = @json($data['payrollTypes']);

        $('#payroll_type').empty();

        if (payrollTypes[deductionType]) {
            var selectedData = payrollTypes[deductionType];
            // console.log(selectedData);
            selectedData.forEach(element => {
                if(selId===element.id){
                    var selected = 'Selected';
                }else{
                    var selected = '';
                }
                $('#payroll_type').append(`<option value='${element.id}' ${selected}>${element.payroll_name}</option>`);
           });
        } else {
            console.log('No data found for the selected deduction type.');
        }
    }
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
                        title: 'payroll-Deduct Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'payroll-Deduct Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'payroll-Deduct Report'},
                    {extend: 'print', text: ' PRINT', title: 'payroll-Deduct Report'},
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
@endsection