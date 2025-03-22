@extends('layout')
@section('container')
<style>
    .schoolData{
        margin-left:12px;
    }
</style>
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

        @if (isset($data['status']) && $data['status']==0)
            <div class="alert alert-danger alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ isset($data['message']) ? $data['message'] : 'Something wen wrong!' }}</strong>
            </div>
        @endif
        <!-- controller is fees/fees_report/feesReportController  -->
        <form action="{{route('fees_report_datewise.index')}}" method="get">
            @csrf
            <div class="card">
                <div class="row">
                    <div class="col-md-4">
                        <label for="">Select Institute</label>
                        <select name="receipt_title" id="receipt_title" class="form-control" required>
                            <option value="">Select Institute</option>
                            @foreach($data['receipt_title'] as $key=>$value)
                                @php 
                                $instituteName = $value->receipt_line_2;
                                if($value->sort_order==2 && session()->get('sub_institute_id')==76){
                                    $instituteName = 'SHRI SWAMINARAYAN MISSION';
                                }
                                @endphp 
                            <option value="{{$value->sort_order}}" @if($data['selreceipt_title']==$value->sort_order) selected @endif data-standard="{{$value->standards}}" data-heads="{{$value->heads}}">{{$instituteName}}</option>
                            @endforeach
                        </select>
                    </div> 
                    <div class="col-md-4 mt-2" required>
                        <label for="">Fees Head</label>
                        <select name="fees_head[]" id="fees_head" class="form-control resizableVarticle" multiple required>
                       
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="">Payment Mode</label>
                        <select name="payment_mode" id="payment_mode" class="form-control" required>
                        <option value="">Select Mode</option>
                        @foreach($data['payment_mode'] as $key=>$mode)
                         <option value="{{$key}}" @if($data['selPaymentMode']==$key) selected @endif>{{$mode}}</option>
                        @endforeach 
                        </select>
                    </div>
                    
                    
                    <div class="col-md-4">
                        <label for="">From Date</label>
                        <input type="text" name="from_date" id="from_date" class="form-control mydatepicker" value="{{$from_date}}" >
                    </div>
                    <div class="col-md-4">
                        <label for="">To Date</label>
                        <input type="text" name="to_date" id="to_date" class="form-control mydatepicker" value="{{$to_date}}">
                    </div>
                   
                    <div class="col-md-12 mt-2">
                        <center>
                            <input type="submit" value="search" name="search" class="btn btn-success">
                        </center>
                    </div>

                </div>
            </div>
        </form>
        @if(isset($data['datewiseData']) && !empty($data['datewiseData']))
        <div class="card">
            <div class="row" id="printDiv" style="width:100%">
            @if(isset($data['school_details']->id))
                @php 
                    $instituteName = $data['school_details']->receipt_line_2;
                    if($data['school_details']->sort_order==2 && session()->get('sub_institute_id')==76){
                        $instituteName = 'SHRI SWAMINARAYAN MISSION';
                    }
                    $academicYear = session()->get('syear').'-'.(session()->get('syear')+1);
                @endphp 
            <div style="width:100%;display:flex;justify-content:center;flex-wrap:wrap;text-align:center;margin-bottom:8px">
                    <div><img style="height:50px;" src="https://erp.triz.co.in/storage/fees/{{$data['school_details']->receipt_logo}}"></div>
                    <div class="schoolData" style="text-align:left;">
                        <p style="margin:0px;"><b>{{$instituteName}}</b></p>
                        <p style="margin:0px;"><b>{{$data['school_details']->receipt_line_3}}</b></p>
                        <p style="margin:0px;">
                        <b> {{--DATE : {{date('d-m-y',strtotime($from_date))}} to {{date('d-m-y',strtotime($to_date))}}--}}
                            ACADEMIC YEAR : {{$academicYear}}
                        </b></p>
                    </div>
                </div>
            @endif
            @php $grandTotal = 0; @endphp
            @foreach($data['datewiseData'] as $date_pay=>$values)
                @php 
                    $explode = explode('||',$date_pay);
                    $date = $explode[0] ?? '-';
                    $pay_mode = $explode[1] ?? '-';
                    $colspan = 9;
                @endphp
                <div class="table-responsive" style="margin-bottom:20px;width:100%;">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th colspan="{{$colspan}}" style="text-align:left;"><b><span>DATE : {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</span><span style="padding-left:70px;">PAYMENT MODE : {{strtoupper($pay_mode)}}</span><b></th>
                        </tr>
                        <tr>
                            <th style="text-align:center;"><b>Sr.NO</b></th>
                            <th style="text-align:center;"><b>REC.NO</b></th>
                            <th style="text-align:center;"><b>NAME</b></th>
                            <th style="text-align:center;"><b>STD.</b></th>
                            @if($data['selPaymentMode']!='CASH')
                            <th><b>BANK NAME</b></th>
                            <th style="text-align:center;"><b>CHEQUE NO.</b></th>
                            @endif
                            <!-- <th><b>RECIEVED BY</b></th> -->
                            <th style="text-align:center;"><b>REMARKS</b></th>
                            {{--  @php $colspan += count($data['selTitle']); @endphp
                                @foreach($data['selTitle'] as $key=>$title)
                                <th><b>{{strtoupper($title)}}</b></th>
                            @endforeach --}}
                            <th style="text-align:center;"><b>AMOUNT</b></th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(!empty($values))
                            @php 
                                $total_amt =0;
                            @endphp
                            @foreach($values as $key=>$value)
                            @php 
                                $total_amt+=$value->total_amount;
                                $grandTotal+=$value->total_amount;
                            @endphp
                            <tr>
                                <td>{{$key+1}}</td>
                                <td>{{$value->receipt_no}}</td>
                                <td>{{$value->student_name}}</td>
                                <td>{{$value->short_standard_name}} - {{$value->div_name}}</td>
                                @if($data['selPaymentMode']!='CASH')
                                <td>{{$value->cheque_bank_name}}</td>
                                <td>{{$value->cheque_no}}</td>
                                @endif
                                {{--<td>{{$value->user_name}}</td>--}}
                                <td>{{$value->remarks}}</td>
                                {{--@foreach($data['selTitle'] as $key=>$title)
                                <td>
                                @if(isset($value->{'total_'.$title}))
                                    {{ $value->{'total_'.$title} }}
                                @endif
                                </td>
                                @endforeach --}} 
                                <td>{{$value->total_amount}}</td>
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
                    <button class="btn btn-primary mt-4 hide" id="printExcel">Excel</button>
                </center>
            </div>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
    $(document).ready(function(){
        @if(isset($data['selreceipt_title']) && $data['selreceipt_title']!='')
            var selectedOption = $('#receipt_title').find(':selected');
            var standard = selectedOption.data('standard');
            var heads = selectedOption.data('heads');
            console.log(@json($data['selfeesHead']));
            getHeads(heads,@json($data['selfeesHead']));
        @endif

        $('#receipt_title').on('change', function() {
            // alert('function called');
            var selectedOption = $(this).find(':selected');
            var standard = selectedOption.data('standard');
            var heads = selectedOption.data('heads');
            getHeads(heads);
        });

    })

function getHeads(heads,selVals = ''){
    var selValsInt = (selVals!='') ? selVals.map(Number) : [];
        $('#fees_head').empty();
            $.ajax({
                url : '{{route("getFeesTitle")}}',
                data : {heads:heads},
                type: 'GET',
                success : function(result){
                    $('#fees_head').empty();
                    var options = '';
                   
                    $.each(result, function(index, item) {
                        var selected = ''; 
                        if (selVals.length === 0 || selValsInt.includes(parseInt(item.id))) {
                            selected = 'selected';
                        }
                        options += '<option value="' + item.id + '" '+selected+'>' + item.display_name + '</option>';
                    });

                    $('#fees_head').html(options);
                }
            })
}
document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("printButton").addEventListener("click", function () {
        var printContents = document.getElementById("printDiv").innerHTML;
        var printWindow = window.open("", "", "width=800,height=600");

        printWindow.document.write('<html><head><title>Print</title>');
        printWindow.document.write('<style>@page{margin:0}@media print{ table{border:0.8px solid #ddd;width:100%;} th,td{border:0.8px solid #ddd;padding:4px;} .schoolData{margin-left:8px} }</style>');
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
            XLSX.writeFile(wb, "date wise fees report.xlsx");
        });

});

</script>
@include('includes.footer')
@endsection
