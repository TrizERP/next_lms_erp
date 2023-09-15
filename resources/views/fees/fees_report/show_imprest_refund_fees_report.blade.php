@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Imprest Refund Report</h4> </div>
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
                <form action="{{ route('imprest_refund_report.create') }}" enctype="multipart/form-data" class="row">                    
                    @csrf                                    
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}

                    <div class="col-md-4 form-group ml-0 mr-0">
                        <label>From Date</label>
                        <input type="text" id="from_date" name="from_date" value="{{$from_date}}" class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group ml-0">
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
        @if(isset($data['refund_feesData']))
        @php
            if(isset($data['refund_feesData'])){
                $refund_feesData = $data['refund_feesData'];
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
                            <th>Receipt No.</th>
                            <th>Cancel Type</th>                               
                            <th>Remark</th>                                                           
                            <th>Cancelled Date</th>                               
                            <th>Cancelled By</th>                               
                            <th>Amount</th>                                                               
                        </tr>
                    </thead>
                    <tbody>
                    @php
                    $j=1; 
                    $grand_total = 0;
                                      
                    @endphp
                                           
                    @if(isset($data['refund_feesData']))
                        @foreach($refund_feesData as $key => $fees_value)                  
                        @php $student_total = 0; @endphp
                        <tr>
                            <td>{{$j}}</td>
                            <td>{{$fees_value['enrollment_no']}}</td>
                            <td>{{$fees_value['student_name']}}</td>
                            <td>{{$fees_value['standard_name']}}</td>
                            <td>{{$fees_value['division_name']}}</td>
                            <td>{{$fees_value['mobile']}}</td>                                                        
                            <td>{{$fees_value['reciept_id']}}</td>
                            <td>{{$fees_value['cancel_type']}}</td>                                                                                
                            <td>
                                @if($fees_value['cancel_remark'] != "")
                                    {{$fees_value['cancel_remark']}}
                                @else - 
                                @endif</td>                                                                                                                                                                                    
                            <td>{{date('d-m-Y',strtotime($fees_value['cancel_date']))}}</td>                                                                                
                            <td>{{$fees_value['cancelled_by']}}</td>                                                                                                                                                                                    
                            <td>{{$fees_value['cancel_amount']}}</td>                                                                                                                                                                                    
                        </tr>
                        @php
                        $j++;                       
                        $grand_total += $fees_value['cancel_amount'];                       
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
                            <td>Total</td>                                                        
                            <td>{{$grand_total}}</td>                             
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

@include('includes.footerJs')
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
