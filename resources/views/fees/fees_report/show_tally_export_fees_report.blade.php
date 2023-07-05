@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Tally Export Fees Report</h4> </div>
            </div>
        @php
            $grade_id = $standard_id = $division_id = $enrollment_no = $first_name = $last_name = $mobile_no = $from_date = $to_date = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }

            if(isset($data['first_name']))
            {
                $first_name = $data['first_name'];
            }
            if(isset($data['last_name']))
            {
                $last_name = $data['last_name'];
            }
            if(isset($data['enrollment_no']))
            {
                $enrollment_no = $data['enrollment_no'];
            }
            if(isset($data['mobile_no']))
            {
                $mobile_no = $data['mobile_no'];
            }
            if(isset($data['from_date']))
            {
                $from_date = $data['from_date'];
            }
            if(isset($data['to_date']))
            {
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
                <form action="{{ route('tally_export_fees_report.create') }}" enctype="multipart/form-data" class="row">
                    @csrf
                    <div class="col-md-4 form-group">
                        <label>First Name</label>
                        <input type="text" id="first_name" value="{{$first_name}}" name="first_name" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Last Name</label>
                        <input type="text" id="last_name" value="{{$last_name}}" name="last_name" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>{{ App\Helpers\get_string('grno','request')}}</label>
                        <input type="text" id="enrollment_no" name="enrollment_no" value="{{$enrollment_no}}" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Mobile No.</label>
                        <input type="text" id="mobile_no" value="{{$mobile_no}}" name="mobile_no" class="form-control">
                    </div>                    
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                    <div class="col-md-4 form-group">
                        <label>From Date</label>
                        <input type="text" id="from_date" name="from_date" value="{{$from_date}}" class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>To Date</label>
                        <input type="text" id="to_date" name="to_date" value="{{$to_date}}" class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>

                </form>
            </div>
        @if(isset($data['fees_data']))
        @php
            if(isset($data['fees_data'])){
                $fees_data = $data['fees_data'];
            }
            
        @endphp
        <div class="card">
            <div class="table-responsive">
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Voucher Type</th>
                            <th>Vch No</th>
                            <th>Date</th>
                            <th>Ledger</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Inst</th>
                            <th>Inst. Date</th>
                            <th>Bank Name</th>
                            <th>Branch</th>                                                           
                            <th>Narration</th>                                                           
                        </tr>
                    </thead>
                    <tbody>                     
                    @if(isset($data['fees_data']))
                        @foreach($fees_data as $key => $fees_value)
                        @php

                             $finalFees = ' (' .$fees_value['annual_fees_narration'] .$fees_value['tuition_fees_narration'] . ') ';
                             $finalFees = rtrim($finalFees,' , )');
                             $finalFees .= ')';

                            $showLedger = 0;
                            $finalAmount = 0;
                            $showNarration = 0;
                        @endphp    
                            @if($fees_value['annual_fees'] != '' && $fees_value['annual_fees'] > 0)
                                @php  
                                    $showLedger = 1;
                                @endphp    
                                <tr>
                                    <td>Receipt</td>
                                    <td>{{$fees_value['vchno']}}</td>
                                    <td>{{date('d-M-y', strtotime($fees_value['receiptdate']))}}</td>
                                    <td>Annual Fees</td>
                                    <td>{{$fees_value['annual_fees']}}</td>
                                    <td></td>
                                    <td>{{$fees_value['cheque_no']}}</td>
                                    <td>{{date('d-M-y', strtotime($fees_value['cheque_date']))}}</td>
                                    <td>{{$fees_value['bank_name']}}</td>
                                    <td>{{$fees_value['bank_branch']}}</td>
                                    <td>
                                        {{$fees_value['student_name']}}-{{$fees_value['std_name']}}-{{$fees_value['div_name']}}-{{$finalFees}}
                                    </td>
                                @php    
                                    $showNarration = 1;
                                    $finalAmount = $finalAmount + $fees_value['annual_fees'];
                                @endphp    
                                </tr> 
                            @endif
                                
                            @if($fees_value['tuition_fees'] != '' && $fees_value['tuition_fees'] > 0)
                                @php 
                                    $showLedger = 1;  
                                @endphp
                                    <tr>
                                        <td>Receipt</td>
                                        <td>{{$fees_value['vchno']}}</td>
                                        <td>{{date('d-M-y', strtotime($fees_value['receiptdate']))}}</td>
                                        <td>tuition Fees</td>
                                        <td>{{$fees_value['tuition_fees']}}</td>
                                        <td></td>
                                        <td>{{$fees_value['cheque_no']}}</td>
                                        <td>{{date('d-M-y', strtotime($fees_value['cheque_date']))}}</td>
                                        <td>{{$fees_value['bank_name']}}</td>
                                        <td>{{$fees_value['bank_branch']}}</td>
                                    @if($showNarration == 0)
                            <td>{{$fees_value['student_name']}}-{{$fees_value['std_name']}}-{{$fees_value['div_name']}}-{{$finalFees}}</td>
                                    @else
                                <td></td>    
                                    @endif
                                </tr>
                                @php
                                    $finalAmount = $finalAmount + $fees_value['tuition_fees'];
                                @endphp    
                            @endif

                            @if($fees_value['tot_fees_discount'] != '' && $fees_value['tot_fees_discount'] < 0)
                                @php
                                    $showLedger = 1;  
                                @endphp    
                                <tr>
                                    <td>Receipt</td>
                                    <td>{{$fees_value['vchno']}}</td>
                                    <td>{{date('d-M-y', strtotime($fees_value['receiptdate']))}}</td>
                                    <td>Discount On Fees</td>
                                    <td></td>
                                    <td>{{abs($fees_value['tot_fees_discount'])}}</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                @php
                                    $finalAmount = $finalAmount + $fees_value['tot_fees_discount'];
                                @endphp    
                            @endif 

                            @if($showLedger == 1)
                                <tr>
                                    <td>Receipt</td>
                                    <td>{{$fees_value['vchno']}}</td>
                                    <td>{{date('d-M-y', strtotime($fees_value['receiptdate']))}}</td>
                                    <td> AXIS BANK LTD.-259712 </td>
                                    <td></td>
                                    <td>{{$finalAmount}}</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            @endif   
                        @endforeach
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>

    function checkAll(ele) {
         var checkboxes = document.getElementsByTagName('input');
         if (ele.checked) {
             for (var i = 0; i < checkboxes.length; i++) {
                 if (checkboxes[i].type == 'checkbox') {
                     checkboxes[i].checked = true;
                 }
             }
         } else {
             for (var i = 0; i < checkboxes.length; i++) {
                 console.log(i)
                 if (checkboxes[i].type == 'checkbox') {
                     checkboxes[i].checked = false;
                 }
             }
         }
    }
</script>
<script>
    $(document).ready(function() {
    // Setup - add a text input to each footer cell    

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
                title: 'Tally Export Fees Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Tally Export Fees Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Tally Export Fees Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Tally Export Fees Report' },
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
