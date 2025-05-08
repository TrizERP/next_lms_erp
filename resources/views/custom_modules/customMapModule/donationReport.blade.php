@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Donation Collection Report</h4>
            </div>
        </div>
        @if (isset($data['status']) && $data['status']==0)
            <div class="alert alert-danger alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ isset($data['message']) ? $data['message'] : 'Something wen wrong!' }}</strong>
            </div>
        @endif
        <div class="card">
            <form method="GET" action="{{ route('donation_report.index') }}" class="row">
                <div class="col-md-4 mt-2">
                    <label for="from_date">From Date:</label>
                    <input type="text" class="form-control mydatepicker" id="from_date" name="from_date" value="{{ $data['from_date'] }}">
                </div>
                <div class="col-md-4 mt-2">
                    <label for="to_date">To Date:</label>
                    <input type="text" class="form-control mydatepicker" id="to_date" name="to_date" value="{{ $data['to_date'] }}">
                </div>
<!--               
                <div class="col-md-4 mt-2">
                    <label for="name">Name</label>
                    <input type="text" class="form-control" name="full_name" list="donarLists" placeholder="Enter Full Name" autocomplete="off" value="{{ $data['full_name'] ?? '' }}">
                    <datalist id="donarLists">
                        @foreach($data['donarLists'] as $donar)
                            <option>{{ $donar->full_name }}</option>
                        @endforeach
                    </datalist>
                </div>
                <div class="col-md-4 mt-2">
                    <label for="mobile">Mobile Number</label>
                    <input type="text" class="form-control" pattern="[1-9]{1}[0-9]{9}" name="mobile_number" placeholder="Enter Mobile Number" autocomplete="off" value="{{ $data['mobile_number'] ?? '' }}">
                </div>
-->
                    <div class="col-md-4">
                        <label for="">Payment Mode</label>
                        <select name="payment_mode" id="payment_mode" class="form-control" required>
                        <option value="">Select Mode</option>
                        @foreach($data['payment_mode'] as $key=>$mode)
                         <option value="{{$key}}" @if($data['selPaymentMode']==$key) selected @endif>{{$mode}}</option>
                        @endforeach 
                        </select>
                    </div>                
                <div class="col-md-12 mt-2">
                    <center>
                        <input type="submit" value="Search" class="btn btn-primary" name="Search" id="Search">
                    </center>
                </div>
            </form>
    </div>
<!--
        @if(isset($data['reportData']) && !empty($data['reportData']))
        <div class="card">
            <div class="row">
                <div class="white-box">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="example">
                            <thead>
                                <tr>
                                    <th>Sr No.</th>
                                    <th>Donor Name</th>
                                    <th>Mobile</th>
                                    <th>PAN Number</th>
                                    <th>Paid Date</th>
                                    <th>Amount</th>
                                    <th>Payment Mode</th>
                                    <th>Cheque Details</th>
                                    <th>Bank Name</th>
                                    <th class="text-left">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['reportData'] as $key => $donation)
                                <tr>
                                    <td>{{ $key+1 }}</td>
                                    <td>{{ $donation->full_name }}</td>
                                    <td>{{ $donation->mobile_number }}</td>
                                    <td>{{ $donation->pan_number }}</td>
                                    <td>{{ \Carbon\Carbon::parse($donation->paid_date)->format('d-m-Y') }}</td>
                                    <td>{{ $donation->donation_amount }}</td>
                                    <td>{{ $donation->payment_mode }}</td>
                                    <td>{{ $donation->cheque_number }} / {{\Carbon\Carbon::parse($donation->cheque_date)->format('d-m-Y')}}</td>
                                    <td>{{ $donation->bank_name }}</td>
                                    <td>{{ $donation->remarks }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
@endif
-->

        @if(isset($data['datewiseData']) && !empty($data['datewiseData']))
        <div class="card">
            <div class="row" id="printDiv" style="width:100%">
            @if(isset($data['school_details']->id))
                @php 
                    $academicYear = session()->get('syear').'-'.(session()->get('syear')+1);
                @endphp 
            <div style="width:100%;display:flex;justify-content:center;flex-wrap:wrap;text-align:center;margin-bottom:8px;margin-top:15px">
                    <div><img style="height:50px;" src="https://erp.triz.co.in/storage/fees/{{$data['school_details']->receipt_logo}}"></div>
                    <div class="schoolData" style="text-align:left;">
                        <h4 style="margin:0px;"><b>SHRI SWAMINARAYAN MISSION</b></h4>
                        <p style="margin:0px;"><b>{{$data['school_details']->receipt_line_3}}</b></p>
                        <p style="margin:0px;"><b>ACADEMIC YEAR : {{$academicYear}}</b></p>
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
                <div class="table-responsive" style="margin:auto;width:85%">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th colspan="{{$colspan}}" style="text-align:left;"><b><span>DATE : {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</span><span style="padding-left:70px;">PAYMENT MODE : {{strtoupper($pay_mode)}}</span><b></th>
                        </tr>
                        <tr>
                            <th style="text-align:center;"><b>Sr.NO</b></th>
                            <th style="text-align:center;"><b>REC.NO</b></th>
                            <th style="text-align:center;"><b>NAME</b></th>
                            <th style="text-align:center;"><b>MOBILE</b></th>
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
                                $total_amt+=$value->donation_amount;
                                $grandTotal+=$value->donation_amount;
                            @endphp
                            <tr>
                                <td>{{$key+1}}</td>
                                <td>{{$value->reciept_no}}</td>
                                <td>{{ strtoupper($value->full_name)}}</td>
                                <td>{{ strtoupper($value->mobile_number)}}</td>
                                @if($data['selPaymentMode']!='CASH')
                                <td>{{$value->bank_name}}</td>
                                <td>{{$value->cheque_number}}</td>
                                @endif
                                {{--<td>{{$value->user_name}}</td>--}}
                                <td>{{$value->remarks}}</td>
                                <td>{{$value->donation_amount}}</td>
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
            <br/><br/>
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
                </center>
            </div>
        </div>
        @endif


    </div>
</div>

@include('includes.footerJs')
<script>
    $(document).ready(function() {
        $('#to_date').change(function() {
            const fromDate = new Date($('#from_date').val());
            const toDate = new Date($(this).val());
            if (toDate < fromDate) {
                alert('To Date should be greater than or equal to From Date.');
                $(this).val(''); // Clear the invalid date
            }
        });
    });
document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("printButton").addEventListener("click", function () {
        var printContents = document.getElementById("printDiv").innerHTML;
        var printWindow = window.open("", "", "width=800,height=600");

        printWindow.document.write('<html><head><title>Print</title>');
        printWindow.document.write('<style>@page{margin:20px}@media print{ table{border:0.8px solid #ddd;width:100%;} th,td{border:0.8px solid #ddd;padding:0px;font-size:14px;} .schoolData{margin-left:8px;} }</style>');
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

});
    
</script>
@include('includes.footer')
@endsection
