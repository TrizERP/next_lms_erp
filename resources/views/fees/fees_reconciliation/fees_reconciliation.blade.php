@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Reconciliation Upload</h4>
            </div>
        </div>
        <div class="card">
            @if(session('message'))
                @if(session('status_code') == 1)
                    <div class="alert alert-success alert-block">
                @else
                    <div class="alert alert-danger alert-block">
                @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ session('message') }}</strong>
                </div>
            @endif
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <form action="{{ route('store_fees_reconciliation_sheet_data') }}" method="POST" enctype="multipart/form-data">
                    {{csrf_field()}}
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Upload file</label>
                            <input type="file" class="form-control" name="attachment" id="attachment" required>
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Upload" class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        @php
            $get_fees_reconciliations = $data['get_fees_reconciliations'] ?? []; 
        @endphp
        
        @if(isset($data['get_fees_reconciliations']))
        <div class="card">
            <div class="table-responsive">
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>ICID</th>
                            <th>Reference No</th>
                            <th>SubMerchant Id</th>
                            <th>PGAmount</th>
                            <th>Student Name</th>
                            <th>Mobile No</th>
                            <th>Email Id</th>
                            <th>CN Student</th>
                            <th>Sports Type</th>
                            <th>Tenure</th>									
                            <th>Batch</th>									
                            <th>UPIVPA</th>									
                            <th>TRANID</th>									
                            <th>Merchant Opted Mode</th>									
                            <th>Tran Date</th>									
                            <th>Tran Amount</th>									
                            <th>PROCESSINGFEE</th>									
                            <th>SERVICETAX</th>									
                            <th>Payer Opted Mode</th>									
                            <th>Recon Date</th>									
                            <th>Settlement Date</th>									
                            <th>Transaction Process</th>									
                            <th>UNIQUE REF NUMBER</th>									
                            <th>VAN NUMBER</th>									
                            <th>Student Id</th>									
                            <th>Term Id</th>									
                            <th>Receipt No</th>									
                            <th>Standard Id</th>									
                            <th>Payment Mode</th>									
                            <th>Bank Name</th>									
                            <th>Amount</th>									
                            <th>Sub Institute Id</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $j=1; @endphp
                        @foreach($get_fees_reconciliations as $key => $get_fees_reconciliation)
                            <tr>
                                <td>{{$j}}</td>
                                <td>{{$get_fees_reconciliation->icid}}</td>
                                <td>{{$get_fees_reconciliation->reference_no}}</td>
                                <td>{{$get_fees_reconciliation->subMerchant_id}}</td>
                                <td>{{$get_fees_reconciliation->PGAmount}}</td>
                                <td>{{$get_fees_reconciliation->student_name}}</td>
                                <td>{{$get_fees_reconciliation->mobile_no}}</td>
                                <td>{{$get_fees_reconciliation->email_iD}}</td>
                                <td>{{$get_fees_reconciliation->CN_student}}</td>
                                <td>{{$get_fees_reconciliation->sports_type}}</td>
                                <td>{{$get_fees_reconciliation->tenure}}</td>
                                <td>{{$get_fees_reconciliation->batch}}</td>
                                <td>{{$get_fees_reconciliation->UPIVPA}}</td>
                                <td>{{$get_fees_reconciliation->TRANID}}</td>
                                <td>{{$get_fees_reconciliation->merchant_opted_mode}}</td>
                                <td>{{$get_fees_reconciliation->tran_date}}</td>
                                <td>{{$get_fees_reconciliation->tran_amount}}</td>
                                <td>{{$get_fees_reconciliation->PROCESSINGFEE}}</td>
                                <td>{{$get_fees_reconciliation->SERVICETAX}}</td>
                                <td>{{$get_fees_reconciliation->payer_opted_mode}}</td>
                                <td>{{$get_fees_reconciliation->recon_date}}</td>
                                <td>{{$get_fees_reconciliation->settlement_date}}</td>
                                <td>{{$get_fees_reconciliation->transaction_process}}</td>
                                <td>{{$get_fees_reconciliation->UNIQUE_REF_NUMBER}}</td>
                                <td>{{$get_fees_reconciliation->VAN_NUMBER}}</td>
                                <td>{{$get_fees_reconciliation->student_id}}</td>
                                <td>{{$get_fees_reconciliation->term_id}}</td>
                                <td>{{$get_fees_reconciliation->recept_no}}</td>
                                <td>{{$get_fees_reconciliation->standard_id}}</td>
                                <td>{{$get_fees_reconciliation->paymode}}</td>
                                <td>{{$get_fees_reconciliation->bank_detals}}</td>
                                <td>{{$get_fees_reconciliation->amount}}</td>
                                <td>{{$get_fees_reconciliation->sub_institute_id}}</td>
                            </tr>
                        @php
                        $j++;
                        @endphp
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4" style="display:inline-grid;justify-content:center;width:100%">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tr>
                            <th style="font-weight: 600;">Total Records</th>
                            <th style="font-weight: 600;">Success Reconsilation</th>
                            <th style="font-weight: 600;">Not found in ERP</th>
                        </tr>
                        @php 
                            $total_records = count($get_fees_reconciliations);
                            
                            $get_success_reconsilations = DB::table('fees_collect')->where(['sub_institute_id' => session()->get('sub_institute_id'), 'syear' => session()->get('syear'), 'conciliation' => '1'])->get()->toArray();
                            $success_reconsilations = count($get_success_reconsilations);

                            $not_found_success_reconsilations = ($total_records) - ($success_reconsilations);
                        @endphp
                        <tr>
                            <td style="text-align: center;">{{ $total_records }}</td>
                            <td style="text-align: center;">{{ $success_reconsilations }}</td>
                            <td style="text-align: center;">{{ $not_found_success_reconsilations }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@include('includes.footerJs')
<script>
    $(document).ready(function() 
    {
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
                title: 'Fees Overall Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',
                pageSize: 'A0',
                exportOptions: {
                     columns: ':visible'
                },
            },
            { extend: 'csv', text: ' CSV', title: 'Fees Overall Report' },
            { extend: 'excel', text: ' EXCEL', title: 'Fees Overall Report' },
            { extend: 'print', text: ' PRINT', title: 'Fees Overall Report' },
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
            });
        });
    });
</script>
@include('includes.footer')
