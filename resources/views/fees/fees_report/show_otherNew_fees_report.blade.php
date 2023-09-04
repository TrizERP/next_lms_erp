{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<style type="text/css">
    #overlay {
      position: fixed; /* Sit on top of the page content */
      display: none; /* Hidden by default */
      width: 100%; /* Full width (cover the whole page) */
      height: 100%; /* Full height (cover the whole page) */
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0,0,0,0.5); /* Black background with opacity */
      z-index: 2; /* Specify a stack order in case you're using a different order for other elements */
      cursor: pointer; /* Add a pointer on hover */
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Other Fees Report</h4> </div>
            </div>
        @php
            $grade_id = $standard_id = $division_id = $enrollment_no = $from_date = $to_date = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
                $from_date = $data['from_date'];
                $to_date = $data['to_date'];
            }
            if(isset($data['from_date'])){
                $from_date = $data['from_date'];
                $to_date = $data['to_date'];
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
                <form action="{{ route('otherNew_fees_report.create') }}" enctype="multipart/form-data" class="row">
                    @csrf
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}

                    <div class="col-md-4 form-group">
                        <label>From Date</label>
                        <input type="text" id="from_date" name="from_date" value="{{$from_date}}" class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>To Date</label>
                        <input type="text" id="to_date" name="to_date" value="{{$to_date}}" class="form-control mydatepicker" autocomplete="off">
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Other Fees Head</label>
                        <select id="otherfeeshead" name="otherfeeshead"class="form-control">
                            <option value="">Select</option>
                            @if(isset($data['feesOtherHead_data']))
                                @foreach($data['feesOtherHead_data'] as $key => $val)
                                    @php
                                    $selected = "";
                                    if(isset($data['otherfeeshead']) && $data['otherfeeshead'] == $val['id'])
                                    {
                                        $selected = "selected";
                                    }
                                    @endphp
                                    <option {{$selected}} value={{$val['id']}}>{{$val['display_name']}}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>

                </form>
            </div>
        @if(isset($data['other_feesData']))
        @php
            if(isset($data['other_feesData'])){
                $other_feesData = $data['other_feesData'];
            }

        @endphp
        <div class="card">
            <div class="table-responsive">
                <table id="example" class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>{{ App\Helpers\get_string('grno','request')}}</th>
                            <th>{{ App\Helpers\get_string('studentname','request')}}</th>
                            <th>{{ App\Helpers\get_string('standard','request')}}</th>
                            <th>{{ App\Helpers\get_string('division','request')}}</th>
                            <th>Mobile No.</th>
                            <th>Fees Head</th>
                            <th>Receipt No.</th>
                            <th>Remark</th>
                            <th>Payment Mode</th>
                            <th>Received Date</th>
                            <th>Paid Amount</th>
                            <th>Ledger</th>
                        </tr>
                    </thead>
                    <tbody>
                    @php
                    $j=1;
                    $grand_total = 0;
                    if(isset($data['other_fee_title']))
                    {
                        foreach($data['other_fee_title'] as $key => $otherhead)
                        {
                            $grand_total[$otherhead->fees_title] = 0;
                        }
                    }
                    @endphp

                    @if(isset($data['other_feesData']))
                        @foreach($other_feesData as $key => $fees_value)
                        @php $student_total = 0; @endphp
                        <tr>
                            <td>{{$j}}</td>
                            <td>{{$fees_value['enrollment_no']}}</td>
                            <td>{{$fees_value['student_name']}}</td>
                            <td>{{$fees_value['standard_name']}}</td>
                            <td>{{$fees_value['division_name']}}</td>
                            <td>{{$fees_value['mobile']}}</td>
                            <td>{{$fees_value['fees_head']}}</td>
                            <td>{{$fees_value['receipt_id']}}</td>
                            <td>
                                @if($fees_value['deduction_remarks'] != "")
                                {{$fees_value['deduction_remarks']}}
                                @else -
                                @endif
                            </td>
                            <td>{{$fees_value['payment_mode']}}</td>
                            <td>{{date('d-m-Y',strtotime($fees_value['deduction_date']))}}</td>
                            <td>{{$fees_value['deduction_amount']}}</td>
                            <td>
                                <button type="button" class="btn btn-info float-right" data-toggle="modal" onclick="javascript:save_data({{$fees_value['student_id']}});">View Ledger</button>
                            </td>
                        </tr>
                        @php
                        $j++;
                        $grand_total += $fees_value['deduction_amount'];
                        @endphp
                        @endforeach
                        <tr class="font-weight-bold">
                            <td>{{$j++}}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td>Total</td>
                            <td>{{$grand_total}}</td>
                            <td></td>
                        </tr>

                    @endif
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>
</div>

<!--Modal: Add ChapterModal-->
<div id="printThis">
    <div class="modal fade right modal-scrolling" id="ChapterModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="display: none;" aria-hidden="true">
        <div class="modal-dialog modal-side modal-bottom-right modal-notify modal-info" role="document" style="min-width: 75%;">
            <!--Content-->
            <div class="modal-content">
                <!--Header-->
                <div class="modal-header">
                    <center>
                        <h5 class="modal-title" id="heading">Student Ledger Report</h5>
                    </center>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">x</span>
                    </button>
                </div>
                <!--Body-->
                <div class="modal-body">
                    <div class="row">
                        <div class="panel-body" style="min-width: 100% !important;">
                            <div class="col-lg-12 col-sm-12 col-xs-12">
                                <input type="hidden" name="student_id" id="student_id" value="">
                                <input type="hidden" name="action" id="action" value="imprest_ledger_view">
                                <div id="reprint_receipt_html">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Footer-->
                <div class="modal-footer" style="justify-content: center !important;">
                    <div id="overlay" style="display:none;"><center><p style="margin-top: 273px;color:red;font-weight: 700;">Please do not refresh the page, while the process is going on.</p><img src="http://dev.triz.co.in/admin_dep/images/loader.gif"></center></div>
                    <!-- <button class="btn" data-dismiss="modal" aria-hidden="true">Close</button> -->
                    <center>
                        <button id="btnPrint" type="button" class="btn btn-primary">Print</button>
                        <input type="button" value="Send Email" class="btn btn-primary" id="ajax_sendEmail" />
                    </center>
                </div>
            </div>
            <!--/.Content-->
        </div>
    </div>
</div>
<!--Modal: Add ChapterModal-->

<style type="text/css">
    @media print{
        table td, table th, table tr {
            border:1px solid #b71313 !important;
        }
    }
</style>

@include('includes.footerJs')
<script>

    document.getElementById("btnPrint").onclick = function()
    {
        PrintDiv("reprint_receipt_html");
    }
    function PrintDiv(divName)
    {
        var divToPrint = document.getElementById(divName);
        var popupWin = window.open('', '_blank', 'width=300,height=300');
        popupWin.document.open();
        popupWin.document.write('<html>');
        popupWin.document.write('<body onload="window.print()">'+'<style type="text/css">' +
                                                                    'table th, table td, table tr {' +
                                                                    'border:1px solid #000 !important;' +
                                                                    'padding:0.3em !important;' +
                                                                    '}' +
                                                                '</style>' + divToPrint.innerHTML + '</html>');
        popupWin.document.close();
    }

    function save_data(val)
    {
        var path = "{{ route('ajax_ledgerData') }}";
        $.ajax({
                url: path,
                data:'student_id='+val,
                success: function(result){
                    $('#reprint_receipt_html').html(result);
                    $('#student_id').val(val);
                    $('#ChapterModal').modal('show');
                }
        });
    }

    $(document).ready(function() {
     var table = $('#example').DataTable( {
         select: true,
         lengthMenu: [
                        [100, 500, 1000, -1],
                        ['100', '500', '1000', 'Show All']
        ],
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'pdfHtml5',
                title: 'Other Fees Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',
                pageSize: 'A0',
                exportOptions: {
                     columns: ':visible'
                },
            },
            { extend: 'csv', text: ' CSV', title: 'Other Fees Report' },
            { extend: 'excel', text: ' EXCEL', title: 'Other Fees Report'},
            { extend: 'print', text: ' PRINT', title: 'Other Fees Report'},
            'pageLength'
        ],
        });

        $('#example thead tr').clone(true).appendTo( '#example thead' );
        $('#example thead tr:eq(1) th').each( function (i) {
            var title = $(this).text();
            $(this).html( '<input type="text" placeholder="Search '+title+'" />' );

            $( 'input', this ).on( 'keyup change', function () {
                if ( table.column(i).search() !== this.value ) {
                    table
                        .column(i)
                        .search( this.value )
                        .draw();
                }
            } );
        } );
    } );
</script>
@include('includes.footer')
@endsection
