@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style type="text/css">
    * {
        margin: 0;
        padding: 0;
        text-indent: 0;
    }

    .s1 {
        color: black;
        font-family: "Times New Roman", serif;
        font-style: normal;
        font-weight: bold;
        text-decoration: none;
        font-size: 11.5pt;
    }

    .s2 {
        color: black;
        font-family: "Times New Roman", serif;
        font-style: normal;
        font-weight: normal;
        text-decoration: none;
        font-size: 11.5pt;
    }

    .s4 {
        color: black;
        font-family: "Times New Roman", serif;
        font-style: italic;
        font-weight: bold;
        text-decoration: none;
        font-size: 11.5pt;
    }

    li {
        display: block;
    }

    #l1 {
        padding-left: 0pt;
        counter-reset: c1 1;
    }

    #l1>li>*:first-child:before {
        counter-increment: c1;
        content: counter(c1, decimal)" ";
        color: black;
        font-family: "Times New Roman", serif;
        font-style: normal;
        font-weight: normal;
        text-decoration: none;
        font-size: 11.5pt;
    }

    #l1>li:first-child>*:first-child:before {
        counter-increment: c1 0;
    }

    #l2 {
        padding-left: 0pt;
        counter-reset: c2 1;
    }

    #l2>li>*:first-child:before {
        counter-increment: c2;
        content: "(" counter(c2, decimal)") ";
        color: black;
        font-family: "Times New Roman", serif;
        font-style: normal;
        font-weight: normal;
        text-decoration: none;
        font-size: 11.5pt;
        /* vertical-align: -6pt; */
    }

    #l2>li:first-child>*:first-child:before {
        counter-increment: c2 0;
    }

    #l3 {
        padding-left: 0pt;
        counter-reset: c2 1;
    }

    #l3>li>*:first-child:before {
        counter-increment: c2;
        content: "(" counter(c2, lower-latin)") ";
        color: black;
        font-family: "Times New Roman", serif;
        font-style: normal;
        font-weight: normal;
        text-decoration: none;
        font-size: 11.5pt;
    }

    #l3>li:first-child>*:first-child:before {
        counter-increment: c2 0;
    }

    li {
        display: block;
    }

    #l4 {
        padding-left: 0pt;
        counter-reset: d1 1;
    }

    #l4>li>*:first-child:before {
        counter-increment: d1;
        content: "(" counter(d1, upper-latin)") ";
        color: black;
        font-family: "Times New Roman", serif;
        font-style: normal;
        font-weight: normal;
        text-decoration: none;
        font-size: 11.5pt;
    }

    #l4>li:first-child>*:first-child:before {
        counter-increment: d1 0;
    }

    #l5 {
        padding-left: 0pt;
        counter-reset: d2 1;
    }

    #l5>li>*:first-child:before {
        counter-increment: d2;
        content: "(" counter(d2, lower-latin)") ";
        color: black;
        font-family: "Times New Roman", serif;
        font-style: normal;
        font-weight: normal;
        text-decoration: none;
        font-size: 11.5pt;
    }

    #l5>li:first-child>*:first-child:before {
        counter-increment: d2 0;
    }

    #l6 {
        padding-left: 0pt;
        counter-reset: d3 1;
    }

    #l6>li>*:first-child:before {
        counter-increment: d3;
        content: "(" counter(d3, lower-roman)") ";
        color: black;
        font-family: "Times New Roman", serif;
        font-style: normal;
        font-weight: normal;
        text-decoration: none;
        font-size: 11.5pt;
    }

    #l6>li:first-child>*:first-child:before {
        counter-increment: d3 0;
    }

    li {
        display: block;
    }

    #l7 {
        padding-left: 0pt;
        counter-reset: e1 2;
    }

    #l7>li>*:first-child:before {
        counter-increment: e1;
        content: "(" counter(e1, lower-latin)") ";
        color: black;
        font-family: "Times New Roman", serif;
        font-style: normal;
        font-weight: normal;
        text-decoration: none;
        font-size: 11.5pt;
    }

    #l7>li:first-child>*:first-child:before {
        counter-increment: e1 0;
    }

    #l8 {
        padding-left: 0pt;
        counter-reset: e2 1;
    }

    #l8>li>*:first-child:before {
        counter-increment: e2;
        content: counter(e2, decimal)" ";
        color: black;
        font-family: "Times New Roman", serif;
        font-style: normal;
        font-weight: normal;
        text-decoration: none;
        font-size: 11.5pt;
    }

    #l8>li:first-child>*:first-child:before {
        counter-increment: e2 0;
    }

    li {
        display: block;
    }

    #l9 {
        padding-left: 0pt;
        counter-reset: f1 10;
    }

    #l9>li>*:first-child:before {
        counter-increment: f1;
        content: counter(f1, decimal)" ";
        color: black;
        font-family: "Times New Roman", serif;
        font-style: normal;
        font-weight: normal;
        text-decoration: none;
        font-size: 11.5pt;
        vertical-align: 0pt;
    }

    #l9>li:first-child>*:first-child:before {
        counter-increment: f1 0;
    }

    table,
    tbody {
        vertical-align: top;
        overflow: visible;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Form 16</h4>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                @if (isset($data['status_code']))
                    @if($data['status_code'] == 1)
                        <div class="alert alert-success alert-block">
                    @else
                        <div class="alert alert-danger alert-block">
                    @endif
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{{ $data['message'] }}</strong>
                        </div>
                @endif
                <form action="{{ route('form16.report') }}" enctype="multipart/form-data" method="post">
                @csrf
                    <div class="row">
                        @php 
                            $dep_id = $emp_id='';
                            if(isset($data['department_id'])){
                                $dep_id=$data['department_id'];
                            }
                            if(isset($data['employee_id'])){
                                $emp_id=$data['employee_id'];
                            }
                        @endphp 
                    {!! App\Helpers\HrmsDepartments("","",$dep_id,"",$emp_id,"") !!}
                        <div class="col-md-3 form-group">
                            <label>Select Year</label>
                            <select id='year' name="year" class="form-control">
                                @foreach($data['years'] as $key => $value)
                                <option value="{{$key}}" @if(isset($data['year']) && $data['year'] == $key) selected @elseif($data['DefaultYear']==$key) selected @endif>{{$value}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-offset-4 text-center form-group">
                            <input type="submit" name="submit" value="Submit" class="btn btn-success">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <h4>Allowance</h4>
                            @foreach($data['allowance'] as $payrollType)
                                <input type="checkbox" name="allowance[]" value="{{$payrollType->id}}" checked> {{$payrollType->payroll_name}}
                            @endforeach

                        </div>
                        <div class="col-md-3 form-group">
                            <h4>Deduction</h4>
                            @foreach($data['deduction'] as $payrollType)
                                @if($payrollType->payroll_name == "PT")
                                    <input type="checkbox" name="deduction[]" value="{{$payrollType->id}}" checked> {{$payrollType->payroll_name}}
                                @else
                                    <input type="checkbox" name="deduction[]" value="{{$payrollType->id}}"> {{$payrollType->payroll_name}}
                                @endif
                            @endforeach
                        </div>
                    </div>
                    
                </form>
            </div>
        </div>
    </div>
    @if(isset($data['search']) && $data['search']==1)
        <table style="border-collapse:collapse;" id="table_60" width="100%" cellspacing="0" cellpadding="0">
            <tbody><tr>
                <td>
						<table style="width:100%; border-collapse:collapse; border-color:#000; display:table;" width="800px" cellspacing="0" cellpadding="0" border="1">
					<tbody>
			
						<tr>
                            <th colspan="5" style="padding-left: 69pt;padding-right: 68pt;text-indent: 0pt;text-align: center;">FORM NO. 16</th>
                        </tr>
                        <tr>
                            <th colspan="5" style="padding-left: 69pt;padding-right: 68pt;text-indent: 0pt;text-align: center;"><h4 style="margin:10px 0;">PART A</h4></th>
                        </tr>
                        <tr>
                            <th colspan="5" style="padding-left: 69pt;padding-right: 68pt;text-indent: 0pt;text-align: center;"><span>Certificate under section 203 of the income-tax Act, 1961 for tax deducted at source on salary.</span></th>
                        </tr>
						<tr>
							<th colspan="2" style="padding:2px 5px;text-align: center;" width="50%">Name and Address of the Employer</th>
							<th colspan="3" style="padding:2px 5px;text-align: center;" width="50%">Name and Address of the Employee</th>
						</tr>
                        
						<tr>
							<td colspan="2" style="padding:2px 5px; border-bottom: 1px solid transparent !important;">{{ $data['get_school_detail']->ReceiptHeader ?? '' }}</td>
							<td colspan="3" style="padding:2px 5px; border-bottom: 1px solid transparent !important;">{{ $data['get_employee_detail']->first_name ?? '' }} {{ $data['get_employee_detail']->middle_name ?? '' }} {{ $data['get_employee_detail']->last_name ?? '' }}</td>
						</tr>
						<tr>
							<td colspan="2" style="padding:2px 5px;">{{ $data['get_school_detail']->ReceiptAddress ?? '' }}</td>
							<td colspan="3" style="padding:2px 5px;">{{ $data['get_employee_detail']->address ?? ''}}</td>
						</tr>
						<tr>
							<td style="padding:2px 5px;" align="center"><b>PAN of the Deductor</b></td>
							<td style="padding:2px 5px;" align="center"><b>TAN of the Deductor</b></td>
							<td colspan="3" style="padding:2px 5px;" align="center"><b>PAN of the Employee</b></td>
						</tr>
						<tr>
							<td style="padding:2px 5px;" align="center"><b><b></b></b></td>
							<td style="padding:2px 5px;" align="center"><b><b></b></b></td>
							<td colspan="3" style="padding:2px 5px;" align="left"><b>{{ $data['get_employee_detail']->pancard ?? '' }}</b></td>
						</tr>
						<tr>
							<td colspan="2" style="padding:2px 5px;" align="center"><b>CIT(TDS)</b></td>
							<td rowspan="2" style="padding:2px 5px;" align="center"><b>Assessment Year</b></td>
							<td colspan="2" rowspan="2" style="padding:2px 5px;" align="center"><b>Period</b></td>
						</tr>
						<tr>
							<td colspan="2" style="padding:2px 5px;"><b>Address</b></td>
						</tr>
						<tr>
							<td style="padding:2px 5px;" colspan="2"></td>
							<td rowspan="2" style="padding:2px 5px;" align="center"><b>
                               {{ $data['years'][$data['year']] ?? '-'}}
                               </b></td>
							<td style="padding:2px 5px;" align="center"><b>From</b></td>
							<td style="padding:2px 5px;" align="center"><b>To</b></td>
						</tr>
						<tr>
							<td colspan="2" style="padding:2px 5px;"><span>City</span>     <span>Pin code </span>   </td>
							<td style="padding:2px 5px;" align="center">{{ $data['from_date'] ?? '' }}</td>
							<td style="padding:2px 5px;" align="center">{{ $data['to_date'] ?? '' }}</td>
						</tr>
						</tbody>
					</table>
                </td>
            </tr>
            @php 
                $amount = !empty($data['get_employee_salary']) ? json_decode($data['get_employee_salary']->employee_salary_data, true) : [];
                $total = $total_allowances = $tot_pf = $total_deductions = $total_ps = $total_pt = 0;
                foreach($data['selected_allowances'] as $key => $allowances)
                {
                    $total +=  $amount[$allowances] ?? 0;

                    $total_allowances = $total * 12;
                }
                $deductions_titles = $total_deductions = [];

                foreach($data['selected_deductions'] as $key => $deductions)
                {
                    $get_payroll_names = DB::table('payroll_types')->where('id', $deductions)->first(['payroll_name']);
                    $deductions_titles[] = $get_payroll_names->payroll_name;
                    if($deductions == 1)
                    {
                        $tot_pf += $amount[$deductions] ?? 0;

                        $total_deductions[] = $tot_pf * 12;
                    }

                    if($deductions == 2)
                    {
                        $total_deductions[] = $amount[$deductions] ?? 0;

                        $total_pt = $amount[$deductions] ?? 0;
                    }
                }
            @endphp
            <tr>
                <td style="padding:0;">
                    <table style="border-collapse:collapse; border-color:#000; border-left:1px solid; border-right:1px solid; border-bottom:1px solid;" width="100%" cellspacing="0" cellpadding="0">

                        <tbody><tr>
							<td colspan="5" style="padding:2px 5px;" valign="middle" align="center"><h4 style="margin:10px 0;">PART B (Annexure)</h4></td>
						</tr>
                        <tr>
                            <td colspan="5" style="padding:2px 5px; border:1px solid;" align="center">
                               Details of Salary paid and any other income and tax deducted
                            </td>	
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">1</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">Gross Salary</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">(a) </td>
                                        <td style="border-right:none;">Salary as per provisions contained in sec. 17(1)</td>
                                    </tr>
                                </tbody></table>
                            </td>
                            <td style="border-right: 1px solid;" width="20%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ $total_allowances }}</td>
                                    </tr>
                                </tbody></table>
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">(b) </td>
                                        <td style="border-right:none; padding:2px 5px;">Value of perquisites u/s 17(2) (as per Form No. 12BA, wherever applicable)</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 2px 0; border-right: 1px solid;" width="20%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right"></td>
                                    </tr>
                                </tbody></table>
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">(c) </td>
                                        <td style="border-right:none;">Profits in lieu of salary under section 17(3) (as per Form No. 12BA, wherever applicable)</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 2px 0; border-right: 1px solid;" width="20%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none;" align="right"></td>
                                    </tr>
                                </tbody></table>
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">(d) </td>
                                        <td style="border-right:none; padding:2px 5px;">Total</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 2px 0; border-right: 1px solid;" width="20%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ $total_allowances }}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">2</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%"> Less : Allowance to the extent exempt under section 10 </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="150px">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="150px">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="150px">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%"></td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%"> 
                                <table style="width:100%; border-collapse:collapse; border-color:#000;" width="350px" cellspacing="0" cellpadding="0" border="1">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="60%" align="center">Allowance</td>
                                        <td class="border-right-none" style="padding:2px 5px;" width="40%" align="center">Rs.</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:2px 5px;">u/s. 10</td>
                                        <td class="border-right-none" style="padding:2px 5px;" align="right">0</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:2px 5px;">&nbsp;</td>
                                        <td class="border-right-none" style="padding:2px 5px;">&nbsp;</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:2px 5px;">&nbsp;</td>
                                        <td class="border-right-none" style="padding:2px 5px;">&nbsp;</td>
                                    </tr>
                                </tbody></table>
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid; border-bottom-color:#fff;" width="20%">&nbsp;</td>
                            <td style="padding: 2px 0; border-right: 1px solid; border-bottom-color:#fff;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">0</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">3</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">Balance (1 - 2) </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 0px; border-right: 1px solid;" width="20%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ $total_allowances }}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">4</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">Deductions : </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">(a) Entertainment allowance</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">(b) Tax on employment</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">5</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">Aggregate of 4 (a) and (b)</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">0</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">6</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">Income chargeable under the head 'Salaries' (3-5)</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ $total_allowances }}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">7</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">Add : Any other income reported by the employee</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table style="width:100%; border-collapse:collapse; border-color:#000;" width="350px" cellspacing="0" cellpadding="0" border="1">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="60%" align="center">Income</td>
                                        <td class="border-right-none" style="padding:2px 5px;" width="40%" align="center">Rs.</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:2px 5px;">&nbsp;</td>
                                        <td class="border-right-none" style="padding:2px 5px;">&nbsp;</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:2px 5px;">&nbsp;</td>
                                        <td class="border-right-none" style="padding:2px 5px;">&nbsp;</td>
                                    </tr>
                                </tbody></table>
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid; border-bottom-color:#fff;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid; border-bottom-color:#fff;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">0</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">8</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">Gross total income (6 + 7)</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ $total_allowances }}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">9</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">Deductions under Chapter VIA</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">(A) Sections 80C, 80CCC and 80 CCD</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%" align="center">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%"></td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">(a) &nbsp;&nbsp;&nbsp;&nbsp; Section 80C</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%" align="center"><b>Gross Amount</b></td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%" align="center"><b>Deductible Amount</b></td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="10%" align="right"> (i) </td>
                                        <td style="border-right:none;border-bottom: 1px dotted #000; padding:2px 5px;">{{ isset($deductions_titles[0]) ? $deductions_titles[0] : ''}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ isset($total_deductions[0]) ? $total_deductions[0] : 0}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ isset($total_deductions[0]) ? $total_deductions[0] : 0}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="10%" align="right"> (ii) </td>
                                        <td style="border-right:none;border-bottom: 1px dotted #000; padding:2px 5px;">{{ isset($deductions_titles[1]) ? $deductions_titles[1] : ''}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ isset($total_deductions[1]) ? $total_deductions[1] : 0}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ isset($total_deductions[1]) ? $total_deductions[1] : 0}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="10%" align="right"> (iii) </td>
                                        <td style="border-right:none;border-bottom: 1px dotted #000; padding:2px 5px;">{{ isset($deductions_titles[2]) ? $deductions_titles[2] : ''}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ isset($total_deductions[2]) ? $total_deductions[2] : 0}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ isset($total_deductions[2]) ? $total_deductions[2] : 0}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
						<tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="10%" align="right"> (iv) </td>
                                        <td style="border-right:none;border-bottom: 1px dotted #000; padding:2px 5px;">{{ isset($deductions_titles[3]) ? $deductions_titles[3] : ''}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ isset($total_deductions[3]) ? $total_deductions[3] : 0}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ isset($total_deductions[3]) ? $total_deductions[3] : 0}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
						<tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="10%" align="right"> (v) </td>
                                        <td style="border-right:none;border-bottom: 1px dotted #000; padding:2px 5px;">{{ isset($deductions_titles[4]) ? $deductions_titles[4] : ''}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ isset($total_deductions[4]) ? $total_deductions[4] : 0}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ isset($total_deductions[4]) ? $total_deductions[4] : 0}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
						<tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="10%" align="right"> (vi) </td>
                                        <td style="border-right:none;border-bottom: 1px dotted #000; padding:2px 5px;">{{ isset($deductions_titles[5]) ? $deductions_titles[5] : ''}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ isset($total_deductions[5]) ? $total_deductions[5] : 0}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ isset($total_deductions[5]) ? $total_deductions[5] : 0}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
						<tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="10%" align="right"> (vii) </td>
                                        <td style="border-right:none;border-bottom: 1px dotted #000; padding:2px 5px;">{{ isset($deductions_titles[6]) ? $deductions_titles[6] : ''}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ isset($total_deductions[6]) ? $total_deductions[6] : 0}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ isset($total_deductions[6]) ? $total_deductions[6] : 0}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        @php
                            $total_deductions_sum = array_sum($total_deductions);
                        @endphp
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%"></td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="10%" align="right">&nbsp;</td>
                                        <td style="border-right:none; padding:2px 5px;">Total of Section 80C</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ $total_deductions_sum }}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ $total_deductions_sum }}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%"></td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">(b) &nbsp;&nbsp;&nbsp;&nbsp; Section 80CCC</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%"></td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">(c) &nbsp;&nbsp;&nbsp;&nbsp; Section 80CCD</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%"></td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="10%" align="right">&nbsp;</td>
                                        <td style="border-right:none; padding:2px 5px;"><b>Aggregate amount deductible under the three section, i.e., 80C, 80CCC and 80CCD</b></td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top"><b>Rs. </b></td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{$total_deductions_sum}}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top"><b>Rs. </b></td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ $total_deductions_sum }}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%"></td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="10%" valign="top" align="right">Note: 1</td>
                                        <td style="border-right:none; padding:2px 5px;">Aggregate amount deductible under section 80C shall not exceed 1.5 lakh rupees.</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:2px 5px;" width="10%" valign="top" align="right">2</td>
                                        <td style="border-right:none; padding:2px 5px;">Aggregate amount deductible under the three sections, i.e., 80C, 80CCC and 80CCD shall not exceed 1.5 lakh rupees</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%"></td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">(d) &nbsp;&nbsp;&nbsp;&nbsp; Section 80CCD (1B)</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">0</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">(B) Other sections (e.g. 80E, 80G, 80TTA,etc.) under Chapter VI-A</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%" align="center"><b>Gross Amount</b></td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%" align="center"><b>Qualifying Amount</b></td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%" align="center"><b>Deductible Amount</b></td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="10%" align="right"> (i) </td>
                                        <td style="border-right:none;border-bottom: 1px dotted #000; padding:2px 5px;">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="10%" align="right"> (ii) </td>
                                        <td style="border-right:none;border-bottom: 1px dotted #000; padding:2px 5px;">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="10%" align="right"> (iii) </td>
                                        <td style="border-right:none;border-bottom: 1px dotted #000; padding:2px 5px;">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="10%" align="right"> (iv) </td>
                                        <td style="border-right:none;border-bottom: 1px dotted #000; padding:2px 5px;">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="10%" align="right"> (v) </td>
                                        <td style="border-right:none;border-bottom: 1px dotted #000; padding:2px 5px;">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">&nbsp;</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">10</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">Aggregate of deductible amounts under Chapter VIA</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ $total_deductions_sum }}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                        </tr>
                        @php 
                            $total_amount = $total_allowances - $total_deductions_sum;
                        @endphp
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">11</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">Total income (8-10)</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">{{ $total_amount }}</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">12</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">Tax on total income</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">0</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" valign="top" align="center">13</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">Education cess @ 3% (on tax computed at S. No. 12)</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">0</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">14</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">Tax Payable (12+13)</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">0</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">15</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">Less: Relief under section 89 (attach details)</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">0</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">16</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%">Tax payable (14-15)</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">0</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid;" width="2%" align="center">17</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="38%"> Tax deducted at source u/s 192</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">0</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:2px 5px; border-left: 1px solid; border-bottom:1px solid;" width="2%" align="center">18</td>
                            <td style="padding:2px 5px; border-right: 1px solid; border-bottom:1px solid;" width="38%">Balance (16-17)</td>
                            <td style="padding:2px 5px; border-right: 1px solid; border-bottom:1px solid;" width="20%">&nbsp;</td>
                            <td style="padding:2px 5px; border-right: 1px solid; border-bottom:1px solid;" width="20%">&nbsp;</td>
                            <td style="padding: 0 0; border-right: 1px solid; border-bottom:1px solid;" width="20%" valign="bottom">
                                <table width="100%">
                                    <tbody><tr>
                                        <td style="padding:2px 5px;" width="5%" valign="top">Rs. </td>
                                        <td style="border-right:none; padding:2px 5px;" align="right">0</td>
                                    </tr>
                                </tbody></table> 
                            </td>
                        </tr>
                    </tbody></table>
                </td>
            </tr>
			<tr>
				<td>
					<table style="border-collapse:collapse; border-color:#000; display: table; width:100%; " cellspacing="0" cellpadding="0" border="1">
						<tbody><tr>
						 <td colspan="3" style="padding-left:5px; font-weight:bold; padding:10px 5px;" align="center"><i>Verification</i></td>
						</tr>
						<tr>
						<td colspan="3" style="padding:2px 5px;" align="left">
										I <b>{{ strtoupper($data['get_employee_detail']->first_name ?? '') }} {{ strtoupper($data['get_employee_detail']->last_name ?? '') }}</b> , son/daughter of {{ strtoupper($data['get_employee_detail']->last_name ?? '') }} working in the capacity of {{ $data['department_name']->department_name ?? ''}} (designation) do hereby certify that the information given above is true, complete and correct and is based on the books of account, documents, and other available records.
						  </td>
						</tr>
					  <tr>
						<td style="padding:2px 5px;" align="left">Place:</td>
						<td style="1px solid #000; padding:2px 5px;" align="right"></td>
						<td rowspan="2" style="padding:2px 5px;" valign="bottom">(Signature of person responsible for deduction of tax)</td>
					  </tr>
					  <tr>
						<td style="padding:2px 5px;" align="left">Date:</td>
						<td style="padding:2px 5px;" align="right">{{ date('d-m-Y') }}</td>
					  </tr>
					   <tr>
						<td style="padding:2px 5px;" align="left">Designation:</td>
						<td style="padding:2px 5px;" align="right">{{ $data['department_name']->department_name ?? '' }}</td>
						<td style="padding:2px 5px;" align="left">Full Name: {{ strtoupper($data['get_employee_detail']->first_name ?? '') }} {{ strtoupper($data['get_employee_detail']->middle_name ?? '') }} {{ strtoupper($data['get_employee_detail']->last_name ?? '') }}</td>
					  </tr>
					</tbody></table>
					
				</td>
			</tr>
        </tbody>
    </table>
    <br />
    <input class="btn btn-warning mb-4" type="button" onclick="printDiv('table_60');" value="Print" style="margin-left: 520px;"/>
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
<script>
    @if(isset($data['department_id']) && $data['department_id']!=='')
        var departmentId = "{{$data['department_id']}}";
        getEmpList(departmentId);
    @endif

    $(document).on("change", "#department_id", function(e) {
        var departmentId = $(this).val();
        getEmpLists(departmentId);
    });

    function getEmpLists(departmentId){
        $('#employee_id').empty();
        $.ajax({
            type: "post",
            url: "{{ route('form16.get.employees.list') }}",
            data: { department_id: departmentId },
            success: function(data) {
                var options = '';
                $.each(data.employees, function(index, employee) {
                    options += '<option value="' + employee.id + '" >' + employee.first_name + ' ' + employee.last_name + '  ('+employee.user_profile+')</option>';
                });
                $('#employee_id').append(options);
            },
            error: function(xhr) {
                console.error(xhr.responseText);
            }
        });
    }
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
