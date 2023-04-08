@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Fees Collection Report</h4> </div>
            </div>
        @php
        $grade_id = $standard_id = $division_id = $enrollment_no = $receipt_no = $from_date = $to_date = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }
            if(isset($data['enrollment_no']))
            {
                $enrollment_no = $data['enrollment_no'];
            }
            if(isset($data['receipt_no']))
            {
                $receipt_no = $data['receipt_no'];
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
                <form action="{{ route('show_fees_collection_report') }}" enctype="multipart/form-data" class="row" method="post">
                    {{ method_field("POST") }}
                    @csrf
                    <div class="col-md-4 form-group">
                        <label>GR No</label>
                        <input type="text" id="enrollment_no" name="enrollment_no" value="{{$enrollment_no}}" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Receipt No</label>
                        <input type="text" id="receipt_no" value="{{$receipt_no}}" name="receipt_no" class="form-control">
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
                            <input type="submit" name="submit" value="Search" class="btn btn-success" >
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
                            <th>Sr No.</th>
                            <th>GR No.</th>
                            <th>Student Name</th>
                            <th>Std-Div</th>
                            <th>Uniqueid</th>
                            <th>Month</th>
                            <th>Receipt No</th>
                            <th>Payment Mode</th>
                            <th>Bank Details</th>
                            <!--<th>Cheque Date</th>-->
                            <th>Receipt Date</th>
                            <th>Collected By</th>
                            <!--<th>Created On</th>-->
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    @php
                    $j=1;
                    $amount = 0;

                    @endphp
                    @if(isset($data['fees_data']))
                        @foreach($fees_data as $key => $value)
                        @php
                            if($value['cheque_date'] != '' && $value['cheque_date'] != '0000-00-00')
                            {
                                $cheque_date = date('d-m-Y',strtotime($value['cheque_date']));
                            }else{
                                $cheque_date = '';
                            }
                        @endphp
                        <tr>
                            <td>{{$j}}</td>
                            <td>{{$value['enrollment_no']}}</td>
                            <td>{{$value['student_name']}}</td>
                            <td>{{$value['standard_name']}} - {{$value['division_name']}}</td>
                            <td>{{$value['uniqueid']}}</td>
                            <td>{{$data['months'][$value['term_id']]}}</td>
                            <td>{{$value['receipt_no']}}</td>
                            <td>{{$value['payment_mode']}}</td>
                            <td>{{$value['cheque_no']}} {{$value['cheque_bank_name']}} {{$value['bank_branch']}}</td>
                            <!--<td>{{$cheque_date}}</td>-->
                            <td>{{date('d-m-Y',strtotime($value['receiptdate']))}}</td>
                            <td>{{$value['user_name']}}</td>
                            <!--<td>{{date('d-m-Y h:i:s',strtotime($value['created_date']))}}</td>-->
                            @php
                            if(isset($value['actual_amountpaid']) &&  $value['actual_amountpaid'] != '')
                            {
                                $fees_collect_amt = ($amount + $value['actual_amountpaid']);
                            }else{
                                $fees_collect_amt = $value['actual_amountpaid'];
                            }
                            @endphp
                            <td>{{$fees_collect_amt}}</td>
                        </tr>
                    @php
                    $amount += $fees_collect_amt;
                    $j++;
                    @endphp
                        @endforeach
                        <tr>
                            <th>Total</th>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <!--<td></td>-->
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <!--<td></td>-->
                            <th>{{$amount}}</th>
                        </tr>
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
                title: 'Fees Collection Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Fees Collection Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Fees Collection Report'}, 
            { extend: 'print', text: ' PRINT', title: 'Fees Collection Report'}, 
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
