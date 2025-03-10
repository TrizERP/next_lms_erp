@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Datewise Fees Report</h4>
            </div>
        </div>
        @php
        $from_date = $to_date = '';
        if(isset($data['selFromDate'])){
            $from_date = $data['selFromDate'];
        }
        if(isset($data['selToDate'])){
            $to_date = $data['selToDate'];
        }
        @endphp
        <form action="{{route('fees_report_datewise.index')}}" method="get">
            @csrf
            <div class="card">
                <div class="row">
                    <div class="col-md-3">
                        <label for="">Select Institute</label>
                        <select name="institute" id="institute" class="form-control" required>
                            <option value="">Select Institute</option>
                            @foreach($data['institutes'] as $key=>$institute)
                         <option value="{{$institute}}" @if($data['selInstitute']==$institute) selected @endif>{{$institute}}</option>
                        @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="">From Date</label>
                        <input type="text" name="from_date" id="from_date" class="form-control mydatepicker" value="{{$from_date}}" required>
                    </div>
                    <div class="col-md-3">
                        <label for="">To Date</label>
                        <input type="text" name="to_date" id="to_date" class="form-control mydatepicker" value="{{$to_date}}" required>
                    </div>
                    <div class="col-md-3">
                        <label for="">Payment Mode</label>
                        <select name="payment_mode" id="payment_mode" class="form-control" required>
                        <option value="">Select Mode</option>
                        @foreach($data['payment_mode'] as $key=>$mode)
                         <option value="{{$mode}}" @if($data['selPaymentMode']==$mode) selected @endif>{{$mode}}</option>
                        @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mt-2" >
                        <label for="">Fees Head</label>
                        <select name="fees_head[]" id="fees_head" class="form-control resizable" multiple required>
                        <option value="">Select Head</option>
                        @foreach($data['feesHead'] as $title=>$head)
                         <option value="{{$title}}" @if(in_array($title,$data['selfeesHead'])) selected @endif>{{$head}}</option>
                        @endforeach
                        </select>
                    </div>

                    <div class="col-md-12">
                        <center>
                            <input type="submit" value="search" name="search" class="btn btn-success">
                        </center>
                    </div>

                </div>
            </div>
        </form>
        @if(isset($data['datewiseData']))
        <div class="card">
            <div class="row" id="printDiv" style="width:100%">
            @php $grandTotal = 0; @endphp
            @foreach($data['datewiseData'] as $date_pay=>$values)
                @php 
                    $explode = explode('||',$date_pay);
                    $date = $explode[0] ?? '-';
                    $pay_mode = $explode[1] ?? '-';
                    $colspan = (8+count($data['selfeesHead']))
                @endphp
                <div class="table-responsive" style="margin-bottom:20px;width:100%;">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th colspan="{{$colspan}}" style="text-align:left;"><b><span>DATE : {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</span><span style="padding-left:70px;">PAYMENT MODE : {{strtoupper($pay_mode)}}</span><b></th>
                        </tr>
                        <tr>
                            <th><b>REC.NO</b></th>
                            <th><b>NAME</b></th>
                            <th><b>STD</b></th>
                            <th><b>BANK NAME</b></th>
                            <th><b>CHEQUE NO.</b></th>
                            <th><b>RECIEVED BY</b></th>
                            <th><b>REMARKS</b></th>
                            @foreach($data['selfeesHead'] as $key=>$title)
                                @if(isset($data['feesHead'][$title]))
                                <th><b>{{strtoupper($data['feesHead'][$title])}}</b></th>
                                @endif
                            @endforeach
                            <th><b>AMOUNT</b></th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(!empty($values))
                            @php 
                                $total_amt =0;
                            @endphp
                            @foreach($values as $key=>$value)
                            @php 
                                $total_amt+=$value->amount;
                                $grandTotal+=$value->amount;
                            @endphp
                            <tr>
                                <td style="text-align:center;">{{$value->receipt_no}}</td>
                                <td>{{$value->student_name}}</td>
                                <td>{{$value->std_name}}</td>
                                <td>{{$value->cheque_bank_name}}</td>
                                <td>{{$value->cheque_no}}</td>
                                <td>{{$value->user_name}}</td>
                                <td>{{$value->remarks}}</td>
                                @foreach($data['selfeesHead'] as $key=>$title)
                                <td>
                                @if(isset($value->{'total_'.$title}))
                                    {{ $value->{'total_'.$title} }}
                                @endif
                                </td>
                            @endforeach
                                <td>{{$value->amount}}</td>
                            </tr>
                            @endforeach
                        <tr>
                            <td colspan="{{$colspan}}" style="text-align:right"><span>DATE WISE TOTAL FEES : {{$total_amt}}</span></td>
                        </tr>
                        @endif
                    </tbody>
                </table>
                </div>
            @endforeach 
                <div class="mt-4" style="display:inline-grid;justify-content:center;width:100%" id="paymentDetails">
					<div class="table-responsive">
						<table class="table table-bordered">
                            <tr>
                                <th style="text-align:center"><b>SR NO.</b></th>
                                <th style="text-align:center"><b>PAYMENT MODE</b></th>
                                <th style="text-align:center"><b>AMOUNT</b></th>
                            </tr>
                            <tr>
                                <th style="text-align:center"> 1</th>
                                <th style="text-align:center"><b>{{$data['selPaymentMode']}}</b></th>
                                <th style="text-align:center">{{$grandTotal}}</th>
                            </tr>
                            <tr>
                                <th colspan="2" style="text-align:right"><b>Total</b></th>
                                <th><b>{{$grandTotal}}</b></th>
                            </tr>
                        </table>
            </div>
                 
                </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <center>
                    <button class="btn btn-primary mt-4" id="printButton">Print</button>
                    <button class="btn btn-primary mt-4" id="printExcel">Excel</button>
                </center>
            </div>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("printButton").addEventListener("click", function () {
        var printContents = document.getElementById("printDiv").innerHTML;
        var printWindow = window.open("", "", "width=800,height=600");

        printWindow.document.write('<html><head><title>Print</title>');
        printWindow.document.write('<style>@page{margin:0}@media print{ table{border:0.8px solid #ddd;width:100%;} th,td{border:0.8px solid #ddd;padding:4px;}}</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(printContents);
        printWindow.document.write('</body></html>');
        printWindow.document.close(); // Close document to finish writing

        printWindow.focus(); // Ensure the window is in focus

        printWindow.onload = function () {
            printWindow.print();
            printWindow.onafterprint = function () {
                printWindow.close(); // Close window after printing
            };
        };
    });

          // EXCEL EXPORT FUNCTION - Merge All Tables into One Sheet
          document.getElementById("printExcel").addEventListener("click", function () {
            let tables = document.querySelectorAll("#printDiv .table"); // Select all tables inside #printDiv
            let wb = XLSX.utils.book_new(); // Create new workbook
            let allData = [];

            tables.forEach((table, index) => {
                let ws = XLSX.utils.table_to_sheet(table, { raw: true }); // Convert table to sheet format
                let tableData = XLSX.utils.sheet_to_json(ws, { header: 1 }); // Convert sheet to array format

                if (index !== 0) {
                    allData.push([""]); // Add empty row for separation
                }
                allData.push(...tableData); // Append table data to allData
            });

            let finalSheet = XLSX.utils.aoa_to_sheet(allData); // Convert merged data into a single sheet
            XLSX.utils.book_append_sheet(wb, finalSheet, "All Tables"); // Add to workbook

            // Auto-adjust column widths
            let range = XLSX.utils.decode_range(finalSheet["!ref"]);
            for (let C = range.s.c; C <= range.e.c; ++C) {
                let maxWidth = 10;
                for (let R = range.s.r; R <= range.e.r; ++R) {
                    let cell_address = XLSX.utils.encode_cell({ r: R, c: C });
                    if (finalSheet[cell_address] && finalSheet[cell_address].v) {
                        maxWidth = Math.max(maxWidth, finalSheet[cell_address].v.toString().length);
                    }
                }
                if (!finalSheet["!cols"]) finalSheet["!cols"] = [];
                finalSheet["!cols"][C] = { width: maxWidth + 2 };
            }

            // Save as Excel file
            XLSX.writeFile(wb, "report.xlsx");
        });

});

</script>
@include('includes.footer')
@endsection
