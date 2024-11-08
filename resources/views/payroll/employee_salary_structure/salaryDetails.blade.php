@if(!empty($salary_deposit))
<button type="button" class="btn btn-outline-info" data-toggle="modal" data-target="#exampleModalSalary" style="margin-bottom:16px">
Salary Deposit
</button>
@endif 

<div class="modal fade" id="exampleModalSalary" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        @if(!empty($salary_deposit))
            <table class="table">
                <thead>
                    <tr>
                        <th colspan="3" class="mainHead text-left"><b>Salary Deposit</b></th>
                    </tr>
                    <tr>
                        <th class="subHead">Year</th>
                        <th class="subHead">Moth</th>
                        <th class="subHead text-left">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($salary_deposit as $key => $value)
                    <tr>
                        <td>{{$value['year']}}</td>
                        <td>{{$value['month']}}</td>
                        <td>{{$value['amount']}}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
      </div>
     
    </div>
  </div>
</div>
<!-- salary structure table -->
<table class="table">
    <thead>
        <tr>
            <th class="mainHead text-left" colspan="{{count($payrollTypes) + 1}}"><b>Employee Salary History</b></th>
        </tr>
        <tr>
            <th class="subHead">Syear</th>
            @foreach($payrollTypes as $k=>$v)
            <th class="subHead">{{$v->payroll_name}}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($SalaryStructure as $key=>$val)
        <tr>
            <td>{{$val->year}}</td>
            <!-- decode json salary data -->
            @php $decodedData = json_decode($val->employee_salary_data,true); @endphp
            @foreach($payrollTypes as $k=>$v)
            <td>
                @if(isset($decodedData[$v->id]) && $decodedData[$v->id]!=null)
                {{$decodedData[$v->id]}}
                @else
                0
                @endif
            </td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>