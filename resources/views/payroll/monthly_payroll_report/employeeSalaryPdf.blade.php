<table style="border-collapse: collapse;" border="1" width="100%">
    <tr>
        <td colspan=4 align="center">
            <h2>{{ $employeeData['school_name']->ReceiptHeader }}</h2>
            <h4>Pay Slip For The Month Of : {{ $employeeData['month'] }} {{ $employeeData['year'] }}</h4>
        </td>
    </tr>
    <tr>
        <td>Salary Slip of</td>
        <td>{{$employeeData['name']}}</td>
        <td>Emp Code</td>
        <td>{{$employeeData['emp_code']}}</td>
    </tr>
    <tr>
        <td>Designation</td>
        <td>{{ $employeeData['designation'] }}</td>
        <td>Date Of Joining</td>
        <td>{{ $employeeData['join_date'] }}</td>
    </tr>
    <tr>
        <td>Bank A/C No</td>
        <td>{{ isset($employeeData['bank_ac_no']) ? $employeeData['bank_ac_no'] : '-'}}</td>
        <td>Days Present</td>
        <td>{{$employeeData['total_day']}}</td>
    </tr>
    <tr>
        <td>P. F. A/C</td>
        <td>{{ isset($employeeData['pf_no']) ? $employeeData['pf_no'] : '-'}}</td>
        <td>Leave without pay</td>
        <td>{{$employeeData['leave_without_pay']}}</td>
    </tr>
</table>
<table style="border-collapse: collapse;" border="1" width="100%">
    <tr>
        <td colspan="3" align="center"><b>Earning</b></td>
        <td colspan="2" align="center"><b>Deduction</b></td>
    </tr>
    <tr>
        <td align="center"><b>Particulars</b></td>
        <td align="center" colspan="2"><b>Amount</b></td>
        <td align="center"><b>Particulars</b></td>
        <td align="center"><b>Amount</b></td>
    </tr>
    <tr>
        <td></td>
        <td align="center"><b>Actual</b></td>
        <td align="center"><b>Current</b></td>
        <td></td>
        <td></td>
    </tr>
    <?php
    $actualSalary = 0;
    $currentSalary = 0;
    $deduction = 0;
    ?>
    @foreach($employeeData['salary_data'] as $key =>$employeeSalary)
        <tr>
            <td>&nbsp;&nbsp;{{ $employeeSalary[0][3] == 'allowance' ? $employeeSalary[0][0] : '-'}}</td>
            <td align="right">{{ $employeeSalary[0][3] == 'allowance' ? $employeeSalary[0][1]: '-'}}</td>
            <td align="right">{{$employeeSalary[0][3] == 'allowance' ? $employeeSalary[0][2] : '-'}}</td>
            @if(isset($employeeSalary[1]))
                @if(isset($employeeSalary[1][3]) && $employeeSalary[1][3] == 'allowance')
                    <td>&nbsp;&nbsp;-</td>
                    <td align="right">-</td>
                    </tr>
                    <tr>
                    <td>&nbsp;&nbsp;{{$employeeSalary[1][3] == 'allowance' ? $employeeSalary[1][0] : '-'}}</td>
                    <td align="right">{{$employeeSalary[1][3] == 'allowance' ? $employeeSalary[1][1] : '-'}}</td>
                    <td align="right">{{$employeeSalary[1][3] == 'allowance' ? $employeeSalary[1][2] : '-'}}</td>
                    <td>&nbsp;&nbsp;-</td>
                    <td align="right">-</td>
                    </tr>
                @else
                    <td>&nbsp;&nbsp;{{ isset($employeeSalary[1][3]) && $employeeSalary[1][3] == 'deduction' ? $employeeSalary[1][0] : '-'}}</td>
                    <td align="right">{{ isset($employeeSalary[1][3]) && $employeeSalary[1][3] == 'deduction' ? $employeeSalary[1][2] : '-'}}</td>
                @endif
            @else
                <td>&nbsp;&nbsp;-</td>
                <td align="right">-</td>
            @endif
        </tr>
    @endforeach
    <tr>
        <td>&nbsp;&nbsp;<b>Gross Salary</b></td>
        <td align="right"><b>{{$employeeData['total_actual_payment']}}</b></td>
        <td align="right"><b>{{$employeeData['total_payment']}}</b></td>
        <td>&nbsp;&nbsp;<b>Total Deduction</b></td>
        <td align="right"><b>{{$employeeData['deduction']}}</b></td>
    </tr>
    <tr>
        <td colspan="5">
            &nbsp;&nbsp;
            <b>Net Salary (in figs.) Rs. {{$employeeData['net_salary']}} /-
                <br>
                &nbsp;&nbsp;
                Net Salary (in words) {{$employeeData['ruppee_in_word']}}
            </b>
        </td>
    </tr>
</table>
<table style="border-collapse: collapse;" border="1" width="100%">
    <tr>
    {{-- <td colspan="4" align="center"><b>Leave Record</b></td> --}}
        <td align="center" {{-- rowspan=4 --}}>
            <b>From {{ $employeeData['school_name']->ReceiptHeader }}</b>
            <br>
            
            <br>
            <br>
            <br>
            <b>Authorised Signatory</b>
        </td>
    </tr>
    {{-- <tr>
        <td align="center"><b>Leave</b></td>
        <td align="center"><b>Granted</b></td>
        <td align="center"><b>Availed</b></td>
        <td align="center"><b>Balance</b></td>
    </tr>
    <tr>
        <td>&nbsp;&nbsp;<b>Casual Leave</b></td>
        <td align="center">10.00</td>
        <td align="center">1.00</td>
        <td align="center">9</td>
    </tr>
    <tr>
        <td>&nbsp;&nbsp;<b>Earn Leave</b></td>
        <td align="center">15.00</td>
        <td align="center">1.00</td>
        <td align="center">14</td>
    </tr>
    <tr>
        <td>&nbsp;&nbsp;<b>Maternity Leave</b></td>
        <td align="center">60.00</td>
        <td align="center">0.00</td>
        <td align="center">60</td>
    </tr>--}}   
</table> 
<h4>
    <center><font color="red">This is a computer generated payslip and does not require signature.</font>
        <center>
</h4><br><br><br>