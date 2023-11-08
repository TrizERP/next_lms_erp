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
        content: "(" counter(c2, lower-latin)") ";
        color: black;
        font-family: "Times New Roman", serif;
        font-style: normal;
        font-weight: normal;
        text-decoration: none;
        font-size: 11.5pt;
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
        vertical-align: -6pt;
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
                                    <div class="col-md-3 form-group">
                                        <label>Employee List</label>
                                        <select id='employee_id' name="employee_id" class="form-control">
                                            <option value="0">Select Employee</option>
                                            @foreach($data['employeeDetails'] as $employee)
                                                <option
                                                    value="{{$employee->employee_id}}">{{ $employee->getUser->first_name ?? '' }}
                                                    {{ ' ' }}
                                                    {{ $employee->getUser->last_name ?? '' }}
                                                    </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Select Year</label>
                                        <select id='year' name="year" class="form-control">
                                            <option>Select Year</option>
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
                                            <input type="checkbox" name="allowance[]" value="{{$payrollType->id}}"> {{$payrollType->payroll_name}}
                                        @endforeach

                                    </div>
                                    <div class="col-md-3 form-group">
                                        <h4>Deduction</h4>
                                        @foreach($data['deduction'] as $payrollType)
                                            <input type="checkbox" name="deduction[]" value="{{$payrollType->id}}"> {{$payrollType->payroll_name}}
                                        @endforeach
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
        </div>

        <table style="border-collapse:collapse;margin-left:6.07943pt" cellspacing="0">
            <tr style="height:16pt">
                <td style="width:600pt;border-top-style:solid;border-top-width:2pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    colspan="4">
                    <p class="s1" style="padding-left: 69pt;padding-right: 68pt;text-indent: 0pt;text-align: center;">FORM
                        NO. 16</p>
                </td>
            </tr>
            <tr style="height:30pt">
                <td style="width:600pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    colspan="4">
                    <p class="s1"
                       style="padding-top: 7pt;padding-left: 69pt;padding-right: 68pt;text-indent: 0pt;text-align: center;">
                        PART A</p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td style="width:600pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    colspan="4">
                    <p class="s1" style="padding-left: 69pt;padding-right: 68pt;text-indent: 0pt;text-align: center;">
                        Certificate under section 203 of the income-tax Act, 1961 for tax deducted at source on salary.</p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:300pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s1" style="padding-left: 63pt;text-indent: 0pt;text-align: left;">Name and Address of the
                        Employer</p>
                </td>
                <td style="width:300pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    colspan="3">
                    <p class="s1" style="padding-left: 63pt;text-indent: 0pt;text-align: left;">Name and Address of the
                        Employee</p>
                </td>
            </tr>
            <tr style="height:45pt">
                <td
                    style="width:300pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-left: 3pt;text-indent: 0pt;text-align: left;">Muljibhai Mehta International
                        school</p>
                    <p class="s2" style="padding-top: 9pt;padding-left: 3pt;text-indent: 0pt;text-align: left;">Gokul
                        TownShip, Bolinj Road Virar ( W )</p>
                </td>
                <td style="width:300pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    colspan="3">
                    <p class="s2" style="padding-left: 4pt;text-indent: 0pt;text-align: left;">KIRTANE AMRUTA AJIT</p>
                    <p class="s2" style="padding-top: 3pt;padding-left: 4pt;text-indent: 0pt;text-align: left;">423,
                        Gurukrupa, Dr. P. N. Kirtane lean,Near Old Jain Mandir, Chalpeth, Aagashi,Virar,401301</p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:150pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s1" style="padding-left: 23pt;text-indent: 0pt;text-align: left;">PAN of the Deductor</p>
                </td>
                <td
                    style="width:150pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s1" style="padding-left: 23pt;text-indent: 0pt;text-align: left;">TAN of the Deductor</p>
                </td>
                <td style="width:300pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    colspan="2">
                    <p class="s1" style="padding-left: 98pt;text-indent: 0pt;text-align: left;">PAN of the Employee</p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:150pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:150pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td style="width:300pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    colspan="2">
                    <p class="s1" style="padding-left: 4pt;text-indent: 0pt;text-align: left;">BWHPS6252A</p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:300pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s1" style="padding-left: 124pt;padding-right: 123pt;text-indent: 0pt;text-align: center;">
                        CIT(TDS)</p>
                </td>
                <td style="width:119pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    rowspan="2">
                    <p class="s1" style="padding-top: 9pt;padding-left: 19pt;text-indent: 0pt;text-align: left;">Assessment
                        Year</p>
                </td>
                <td style="width:181pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    colspan="2" rowspan="2">
                    <p class="s1"
                       style="padding-top: 9pt;padding-left: 73pt;padding-right: 72pt;text-indent: 0pt;text-align: center;">
                        Period</p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:300pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s1" style="padding-left: 3pt;text-indent: 0pt;text-align: left;">Address</p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:300pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td style="width:119pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    rowspan="2">
                    <p class="s1" style="padding-top: 9pt;padding-left: 35pt;text-indent: 0pt;text-align: left;">2023-2024
                    </p>
                </td>
                <td
                    style="width:90pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s1" style="padding-left: 30pt;padding-right: 30pt;text-indent: 0pt;text-align: center;">From
                    </p>
                </td>
                <td
                    style="width:91pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s1" style="padding-left: 14pt;padding-right: 14pt;text-indent: 0pt;text-align: center;">To</p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:300pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-left: 3pt;text-indent: 0pt;text-align: left;">City Pin code</p>
                </td>
                <td
                    style="width:90pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-left: 15pt;text-indent: 0pt;text-align: left;">01/Apr/2022</p>
                </td>
                <td
                    style="width:91pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-left: 14pt;padding-right: 14pt;text-indent: 0pt;text-align: center;">
                        31/Mar/2023</p>
                </td>
            </tr>
            <tr style="height:30pt">
                <td style="width:600pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    colspan="4">
                    <p class="s1"
                       style="padding-top: 7pt;padding-left: 69pt;padding-right: 68pt;text-indent: 0pt;text-align: center;">
                        PART B (Annexure)</p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td style="width:600pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    colspan="4">
                    <p class="s2" style="padding-left: 69pt;padding-right: 68pt;text-indent: 0pt;text-align: center;">
                        Details of Salary paid and any other income and tax deducted</p>
                </td>
            </tr>
            <tr style="height:500pt">
                <td
                    style="width:244pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <ol id="l1">
                        <li data-list-text="1">
                            <p class="s2" style="padding-left: 22pt;text-indent: -15pt;text-align: left;">Gross Salary</p>
                            <ol id="l2">
                                <li data-list-text="(a)">
                                    <p class="s2"
                                       style="padding-top: 5pt;padding-left: 45pt;padding-right: 8pt;text-indent: -18pt;line-height: 92%;text-align: left;">
                                        Salary as per provisions contained in sec. 17(1)</p>
                                </li>
                                <li data-list-text="(b)">
                                    <p class="s2"
                                       style="padding-top: 7pt;padding-left: 49pt;padding-right: 17pt;text-indent: -21pt;text-align: left;">
                                        Value of perquisites u/s 17(2) (as per Form No. 12BA, wherever applicable)</p>
                                </li>
                                <li data-list-text="(c)">
                                    <p class="s2"
                                       style="padding-top: 8pt;padding-left: 45pt;padding-right: 13pt;text-indent: -18pt;line-height: 94%;text-align: left;">
                                        Profits in lieu of salary under section 17(3) (as per Form No. 12BA, wherever
                                        applicable)</p>
                                </li>
                                <li data-list-text="(d)">
                                    <p class="s2"
                                       style="padding-top: 7pt;padding-left: 49pt;text-indent: -21pt;text-align: left;">
                                        Total</p>
                                </li>
                            </ol>
                        </li>
                        <li data-list-text="2">
                            <p class="s2"
                               style="padding-top: 6pt;padding-left: 22pt;padding-right: 17pt;text-indent: -15pt;line-height: 65%;text-align: left;">
                                Less : Allowance to the extent exempt under section 10</p>
                            <p style="text-indent: 0pt;text-align: left;"><br /></p>
                        </li>
                        <li data-list-text="3">
                            <p class="s2" style="padding-left: 22pt;text-indent: -15pt;text-align: left;">Balance (1 - 2)
                            </p>
                        </li>
                        <li data-list-text="4">
                            <p class="s2" style="padding-top: 5pt;padding-left: 22pt;text-indent: -15pt;text-align: left;">
                                Deductions :</p>
                            <ol id="l3">
                                <li data-list-text="(a)">
                                    <p class="s2"
                                       style="padding-top: 3pt;padding-left: 37pt;text-indent: -15pt;text-align: left;">
                                        Entertainment allowance</p>
                                </li>
                                <li data-list-text="(b)">
                                    <p class="s2"
                                       style="padding-top: 5pt;padding-left: 38pt;text-indent: -16pt;text-align: left;">Tax
                                        on employment</p>
                                </li>
                            </ol>
                        </li>
                        <li data-list-text="5">
                            <p class="s2" style="padding-top: 5pt;padding-left: 22pt;text-indent: -15pt;text-align: left;">
                                Aggregate of 4 (a) and (b)</p>
                        </li>
                        <li data-list-text="6">
                            <p class="s2"
                               style="padding-top: 5pt;padding-left: 22pt;padding-right: 5pt;text-indent: -15pt;line-height: 65%;text-align: left;">
                                Income chargeable under the head &#39;Salaries&#39; (3-5)</p>
                        </li>
                        <li data-list-text="7">
                            <p class="s2"
                               style="padding-top: 4pt;padding-left: 22pt;padding-right: 38pt;text-indent: -15pt;line-height: 65%;text-align: left;">
                                Add : Any other income reported by the employee</p>
                            <p style="text-indent: 0pt;text-align: left;"><br /></p>
                        </li>
                        <li data-list-text="8">
                            <p class="s2" style="padding-left: 22pt;text-indent: -15pt;text-align: left;">Gross total income
                                (6 + 7)</p>
                            <p style="text-indent: 0pt;text-align: left;"><br /></p>
                        </li>
                        <li data-list-text="9">
                            <p class="s2" style="padding-left: 22pt;text-indent: -15pt;text-align: left;">Deductions under
                                Chapter VIA</p>
                        </li>
                    </ol>
                </td>
                <td
                    style="width:118pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s2" style="padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 189789</p>
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s2" style="padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s2" style="padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s2" style="padding-top: 9pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                    <p class="s2" style="padding-top: 5pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
                <td
                    style="width:120pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s2" style="padding-top: 7pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 189789
                    </p>
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s2" style="padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 0</p>
                    <p class="s2" style="padding-top: 8pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 189789
                    </p>
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s2" style="padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 0</p>
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s2" style="padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 0</p>
                </td>
                <td
                    style="width:118pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s2" style="padding-left: 4pt;text-indent: 0pt;text-align: left;">Rs. 189789</p>
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s2" style="padding-top: 8pt;padding-left: 4pt;text-indent: 0pt;text-align: left;">Rs. 189789
                    </p>
                </td>
            </tr>
        </table>
        <table style="border-collapse:collapse" cellspacing="0">
            <tr style="height:16pt">
                <td
                    style="width:130pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-left: 40pt;text-indent: 0pt;text-align: left;">Allowance</p>
                </td>
                <td
                    style="width:87pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-left: 35pt;padding-right: 34pt;text-indent: 0pt;text-align: center;">Rs.
                    </p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:130pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-left: 3pt;text-indent: 0pt;text-align: left;">u/s. 10</p>
                </td>
                <td
                    style="width:87pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-right: 3pt;text-indent: 0pt;text-align: right;">0</p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:130pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:87pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:130pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:87pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
            </tr>
        </table>
        <p style="text-indent: 0pt;text-align: left;" />
        <table style="border-collapse:collapse" cellspacing="0">
            <tr style="height:16pt">
                <td
                    style="width:130pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-left: 46pt;padding-right: 46pt;text-indent: 0pt;text-align: center;">Income
                    </p>
                </td>
                <td
                    style="width:87pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-left: 35pt;padding-right: 34pt;text-indent: 0pt;text-align: center;">Rs.
                    </p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:130pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:87pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:130pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:87pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
            </tr>
        </table>
        <p style="text-indent: 0pt;text-align: left;" />
        <table style="border-collapse:collapse;margin-left:6.07943pt" cellspacing="0">
            <tr style="height:33pt">
                <td style="width:244pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:dotted;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    rowspan="7">
                    <ol id="l4">
                        <li data-list-text="(A)">
                            <p class="s2" style="padding-left: 41pt;text-indent: -18pt;text-align: left;">Sections 80C,
                                80CCC and 80 CCD</p>
                            <ol id="l5">
                                <li data-list-text="(a)">
                                    <p class="s2"
                                       style="padding-top: 2pt;padding-left: 52pt;text-indent: -29pt;text-align: left;">
                                        Section 80C</p>
                                    <ol id="l6">
                                        <li data-list-text="(i)">
                                            <p class="s2"
                                               style="padding-top: 5pt;padding-bottom: 1pt;padding-left: 50pt;text-indent: -19pt;text-align: left;">
                                                Standard Deduction</p>
                                            <p
                                                style="padding-left: 46pt;text-indent: 0pt;line-height: 1pt;text-align: left;" />
                                        </li>
                                        <li data-list-text="(ii)">
                                            <p class="s2"
                                               style="padding-top: 5pt;padding-bottom: 1pt;padding-left: 50pt;text-indent: -22pt;text-align: left;">
                                                PF</p>
                                            <p
                                                style="padding-left: 46pt;text-indent: 0pt;line-height: 1pt;text-align: left;" />
                                        </li>
                                        <li data-list-text="(iii)">
                                            <p class="s2"
                                               style="padding-top: 5pt;padding-bottom: 1pt;padding-left: 53pt;text-indent: -25pt;text-align: left;">
                                                ProfTax</p>
                                            <p
                                                style="padding-left: 49pt;text-indent: 0pt;line-height: 1pt;text-align: left;" />
                                        </li>
                                        <li data-list-text="(iv)">
                                            <p class="s2"
                                               style="padding-top: 6pt;padding-left: 48pt;text-indent: -21pt;text-align: left;">
                                                <u>&nbsp;
                                                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                                    &nbsp;</u></p>
                                        </li>
                                        <li data-list-text="(v)">
                                            <p class="s2"
                                               style="padding-top: 8pt;padding-left: 46pt;text-indent: -18pt;text-align: left;">
                                                <u>&nbsp;
                                                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                                    &nbsp;</u></p>
                                        </li>
                                        <li data-list-text="(vi)">
                                            <p
                                                style="padding-top: 7pt;padding-left: 40pt;text-indent: -16pt;text-align: left;">
                                                <br /></p>
                                        </li>
                                    </ol>
                                </li>
                            </ol>
                        </li>
                    </ol>
                </td>
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td style="width:119pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:2pt;border-right-style:solid;border-right-width:1pt"
                    rowspan="28">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s1" style="padding-left: 6pt;padding-right: 5pt;text-indent: 0pt;text-align: center;">Gross
                        Amount</p>
                    <p class="s2"
                       style="padding-top: 7pt;padding-left: 6pt;padding-right: 5pt;text-indent: 0pt;text-align: center;">
                        Rs. 999</p>
                    <p class="s2"
                       style="padding-top: 8pt;padding-left: 6pt;padding-right: 5pt;text-indent: 0pt;text-align: center;">
                        Rs. 16867</p>
                    <p class="s2"
                       style="padding-top: 8pt;padding-left: 6pt;padding-right: 5pt;text-indent: 0pt;text-align: center;">
                        Rs. 2500</p>
                    <p class="s2" style="padding-top: 8pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                    <p class="s2" style="padding-top: 8pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                    <p class="s2" style="padding-top: 6pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                    <p class="s2" style="padding-top: 8pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                    <p class="s2"
                       style="padding-top: 8pt;padding-left: 6pt;padding-right: 5pt;text-indent: 0pt;text-align: center;">
                        Rs. 20366</p>
                    <p class="s2" style="padding-top: 5pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                    <p class="s2" style="padding-top: 5pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s1" style="padding-left: 6pt;padding-right: 5pt;text-indent: 0pt;text-align: center;">Rs.<span
                            class="s2"> 20366</span></p>
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s2" style="padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                    <p class="s1"
                       style="padding-top: 10pt;padding-left: 6pt;padding-right: 5pt;text-indent: 0pt;text-align: center;">
                        Qualifying Amount</p>
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s2" style="padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                    <p class="s2" style="padding-top: 8pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                    <p class="s2" style="padding-top: 8pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                    <p class="s2" style="padding-top: 8pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                    <p class="s2" style="padding-top: 8pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s1" style="padding-left: 11pt;text-indent: 0pt;text-align: left;">Deductible Amount</p>
                </td>
            </tr>
            <tr style="height:20pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 2pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 999</p>
                </td>
            </tr>
            <tr style="height:21pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 3pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 16867
                    </p>
                </td>
            </tr>
            <tr style="height:21pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 3pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 2500</p>
                </td>
            </tr>
            <tr style="height:21pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 3pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
            </tr>
            <tr style="height:20pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 3pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 1pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
            </tr>
            <tr style="height:24pt">
                <td style="width:244pt;border-top-style:dotted;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:2pt;border-right-style:solid;border-right-width:1pt"
                    rowspan="21">
                    <p class="s2" style="padding-top: 4pt;padding-left: 27pt;text-indent: 0pt;text-align: left;">(vii)
                        <u>&nbsp;</u></p>
                    <p class="s2" style="padding-top: 8pt;padding-left: 50pt;text-indent: 0pt;text-align: left;">Total of
                        Section 80C</p>
                    <ol id="l7">
                        <li data-list-text="(b)">
                            <p class="s2" style="padding-top: 6pt;padding-left: 52pt;text-indent: -30pt;text-align: left;">
                                Section 80CCC</p>
                        </li>
                        <li data-list-text="(c)">
                            <p class="s2" style="padding-top: 5pt;padding-left: 52pt;text-indent: -29pt;text-align: left;">
                                Section 80CCD</p>
                            <p class="s1"
                               style="padding-top: 7pt;padding-left: 50pt;padding-right: 16pt;text-indent: 0pt;text-align: left;">
                                Aggregate amount deductible under the three section, i.e., 80C, 80CCC and 80CCD</p>
                            <p class="s2"
                               style="padding-top: 8pt;padding-left: 27pt;text-indent: 0pt;line-height: 13pt;text-align: left;">
                                Note: Aggregate amount deductible under</p>
                            <ol id="l8">
                                <li data-list-text="1">
                                    <p class="s2"
                                       style="padding-left: 61pt;padding-right: 11pt;text-indent: -14pt;text-align: left;">
                                        section 80C shall not exceed 1.5 lakh rupees.</p>
                                </li>
                                <li data-list-text="2">
                                    <p class="s2"
                                       style="padding-top: 4pt;padding-left: 61pt;padding-right: 11pt;text-indent: -14pt;text-align: left;">
                                        Aggregate amount deductible under the three sections, i.e., 80C, 80CCC and 80CCD
                                        shall not exceed 1.5 lakh rupees</p>
                                </li>
                            </ol>
                        </li>
                        <li data-list-text="(d)">
                            <p class="s2" style="padding-top: 6pt;padding-left: 52pt;text-indent: -30pt;text-align: left;">
                                Section 80CCD (1B)</p>
                        </li>
                    </ol>
                    <p class="s2" style="padding-top: 4pt;padding-left: 22pt;text-indent: 0pt;text-align: left;">(B) Other
                        sections (e.g. 80E, 80G, 80TTA,etc.) under Chapter VI-A</p>
                    <p class="s2" style="padding-top: 6pt;padding-left: 30pt;text-indent: 0pt;text-align: left;">(i)
                        <u>&nbsp;</u></p>
                    <p class="s2" style="padding-top: 8pt;padding-left: 27pt;text-indent: 0pt;text-align: left;">(ii)
                        <u>&nbsp;</u></p>
                    <p class="s2" style="padding-top: 8pt;padding-left: 27pt;text-indent: 0pt;text-align: left;">(iii)
                        <u>&nbsp;</u></p>
                    <p class="s2" style="padding-top: 8pt;padding-left: 27pt;text-indent: 0pt;text-align: left;">(iv)
                        <u>&nbsp;</u></p>
                    <p class="s2" style="padding-top: 8pt;padding-left: 28pt;text-indent: 0pt;text-align: left;">(v)
                        <u>&nbsp;</u></p>
                    <ol id="l9">
                        <li data-list-text="10">
                            <p class="s2"
                               style="padding-top: 6pt;padding-left: 22pt;padding-right: 39pt;text-indent: -18pt;line-height: 65%;text-align: left;">
                                Aggregate of deductible amounts under Chapter VIA</p>
                            <p style="text-indent: 0pt;text-align: left;"><br /></p>
                        </li>
                        <li data-list-text="11">
                            <p class="s2" style="padding-left: 22pt;text-indent: -18pt;text-align: left;">Total income
                                (8-10)</p>
                        </li>
                        <li data-list-text="12">
                            <p class="s2" style="padding-top: 5pt;padding-left: 22pt;text-indent: -18pt;text-align: left;">
                                Tax on total income</p>
                        </li>
                        <li data-list-text="13">
                            <p class="s2"
                               style="padding-top: 4pt;padding-left: 22pt;padding-right: 15pt;text-indent: -18pt;text-align: left;">
                                Education cess @ 3% (on tax computed at S. No. 12)</p>
                        </li>
                        <li data-list-text="14">
                            <p class="s2" style="padding-top: 4pt;padding-left: 22pt;text-indent: -18pt;text-align: left;">
                                Tax Payable (12+13)</p>
                        </li>
                        <li data-list-text="15">
                            <p class="s2" style="padding-top: 5pt;padding-left: 22pt;text-indent: -18pt;text-align: left;">
                                Less: Relief under section 89 (attach details)</p>
                        </li>
                        <li data-list-text="16">
                            <p class="s2" style="padding-top: 5pt;padding-left: 22pt;text-indent: -18pt;text-align: left;">
                                Tax payable (14-15)</p>
                        </li>
                        <li data-list-text="17">
                            <p class="s2" style="padding-top: 5pt;padding-left: 22pt;text-indent: -18pt;text-align: left;">
                                Tax deducted at source u/s 192</p>
                        </li>
                        <li data-list-text="18">
                            <p class="s2" style="padding-top: 5pt;padding-left: 22pt;text-indent: -18pt;text-align: left;">
                                Balance (16-17)</p>
                        </li>
                    </ol>
                </td>
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 6pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
            </tr>
            <tr style="height:19pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 2pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 20366
                    </p>
                </td>
            </tr>
            <tr style="height:17pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 1pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
            </tr>
            <tr style="height:32pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 1pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
            </tr>
            <tr style="height:83pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s1" style="padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.<span class="s2">
                        20366</span></p>
                </td>
            </tr>
            <tr style="height:71pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s2" style="padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 0</p>
                </td>
            </tr>
            <tr style="height:24pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s1" style="padding-top: 3pt;padding-left: 23pt;text-indent: 0pt;text-align: left;">Gross
                        Amount</p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s1" style="padding-top: 3pt;padding-left: 11pt;text-indent: 0pt;text-align: left;">Deductible
                        Amount</p>
                </td>
            </tr>
            <tr style="height:24pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 5pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 5pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
            </tr>
            <tr style="height:21pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 3pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 3pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
            </tr>
            <tr style="height:21pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 3pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 3pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
            </tr>
            <tr style="height:21pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 3pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 3pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
            </tr>
            <tr style="height:24pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 3pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 3pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs.</p>
                </td>
            </tr>
            <tr style="height:30pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 6pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 20366
                    </p>
                </td>
            </tr>
            <tr style="height:25pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 9pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 169423
                    </p>
                </td>
            </tr>
            <tr style="height:22pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 1pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 0</p>
                </td>
            </tr>
            <tr style="height:22pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 6pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 0</p>
                </td>
            </tr>
            <tr style="height:17pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 1pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 0</p>
                </td>
            </tr>
            <tr style="height:17pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 1pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 0</p>
                </td>
            </tr>
            <tr style="height:17pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 1pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 0</p>
                </td>
            </tr>
            <tr style="height:17pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 1pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 0</p>
                </td>
            </tr>
            <tr style="height:18pt">
                <td
                    style="width:118pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:2pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td
                    style="width:119pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:2pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-top: 1pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Rs. 0</p>
                </td>
            </tr>
            <tr style="height:27pt">
                <td style="width:600pt;border-top-style:solid;border-top-width:2pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    colspan="4">
                    <p class="s4"
                       style="padding-top: 5pt;padding-left: 270pt;padding-right: 270pt;text-indent: 0pt;text-align: center;">
                        Verification</p>
                </td>
            </tr>
        </table>
        <table style="border-collapse:collapse;margin-left:6.07943pt" cellspacing="0">
            <tr style="height:42pt">
                <td style="width:600pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    colspan="3">
                    <p class="s2" style="padding-left: 3pt;text-indent: 0pt;line-height: 13pt;text-align: left;">I
                        <b>CHIMANLAL MEHTA </b>, son/daughter of MEHTA working in the capacity of Trustee Department
                        (designation) do hereby</p>
                    <p class="s2" style="padding-left: 3pt;text-indent: 0pt;text-align: left;">certify that the information
                        given above is true, complete and correct and is based on the books of account, documents, and other
                        available records.</p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:116pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-left: 3pt;text-indent: 0pt;text-align: left;">Place:</p>
                </td>
                <td
                    style="width:150pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                </td>
                <td style="width:334pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"
                    rowspan="2">
                    <p style="text-indent: 0pt;text-align: left;"><br /></p>
                    <p class="s2" style="padding-left: 3pt;text-indent: 0pt;text-align: left;">(Signature of person
                        responsible for deduction of tax)</p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:116pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-left: 3pt;text-indent: 0pt;text-align: left;">Date:</p>
                </td>
                <td
                    style="width:150pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-right: 3pt;text-indent: 0pt;text-align: right;">13-05-2023</p>
                </td>
            </tr>
            <tr style="height:16pt">
                <td
                    style="width:116pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-left: 3pt;text-indent: 0pt;text-align: left;">Designation:</p>
                </td>
                <td
                    style="width:150pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-right: 3pt;text-indent: 0pt;text-align: right;">Trustee Department</p>
                </td>
                <td
                    style="width:334pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                    <p class="s2" style="padding-left: 3pt;text-indent: 0pt;text-align: left;">Full Name: CHIMANLAL MEHTA
                    </p>
                </td>
            </tr>
        </table>
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
@include('includes.footer')
