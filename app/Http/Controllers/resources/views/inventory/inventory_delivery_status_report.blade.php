@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Item Delivery Status Report</h4>
            </div>
        </div>
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
            <form action="{{ route('inventory_delivery_status_report.create') }}" enctype="multipart/form-data">
            @csrf  
                <div class="row">                    
                    <div class="col-md-4 form-group">
                        <label>From Date</label>
                        <input type="text" name="from_date" class="form-control mydatepicker" placeholder="Please select from date." value="@if(isset($data['from_date'])) {{$data['from_date']}} @endif" autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>To Date</label>
                        <input type="text" name="to_date" class="form-control mydatepicker" placeholder="Please select to date." value="@if(isset($data['to_date'])) {{$data['to_date']}} @endif" autocomplete="off">
                    </div>
                     <div class="col-md-4 form-group">
                        <label for="requisition_by">Requisition By</label>
                        <select name="requisition_by" id="requisition_by" class="form-control">
                            <option value="">Select Requisition By</option>
                            @foreach($data['user'] as $k => $v  )
                                <option value="{{$v['id']}}"
                                @if(isset($data['requisition_by']))
                                    @if($data['requisition_by'] == $v['id'])
                                    selected='selected'
                                    @endif
                                @endif
                                >{{ $v['first_name'] }} {{ $v['middle_name'] }} {{ $v['last_name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success" >
                        </center>
                    </div>
                </div>                  
            </form>
        </div>

        @if(isset($data['result_report']))
        @php
        $j = 1;
            if(isset($data['result_report'])){
                $result_report = $data['result_report'];
            }
        @endphp
        <div class="card">            
            <div class="table-responsive">
                <table id="example" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>SR NO</th>
                            <th>Requisition By</th>
                            <th>Requisition Date</th>
                            <th>Requisition No.</th>
                            <th>Item</th>
                            <th>Item Qty</th>
                            <th>Unit</th>
                            <th>Expected Delivery Time</th>
                            <th>Remarks (if any)</th>
                            <th>Requisition Status</th>
                            <th>Requisition Approved By</th>
                            <th>Requisition Approved Remarks</th>
                            <th>Requisition Approved Date</th>
                            <th>Delivery Status</th>
                            <th>Delivery Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($result_report as $key => $value)
                            <tr>
                                <td>{{$j++}}</td>
                                <td>{{$value->REQUISITION_BY_NAME}} </td>
                                <td>{{date('d-m-Y',strtotime($value->REQUISITION_DATE))}}</td>
                                <td>{{$value->REQUISITION_NO}}</td>
                                <td>{{$value->ITEM_NAME}}</td>
                                <td>{{$value->ITEM_QTY}}</td>
                                <td>{{$value->ITEM_UNIT}}</td>
                                <td>{{$value->EXPECTED_DELIVERY_TIME}}</td>
                                <td>{{$value->REMARKS}}</td>
                                <td>{{$value->REQUISITION_STATUS}}</td>
                                <td>{{$value->REQUISITION_APPROVED_BY}}</td>
                                <td>{{$value->REQUISITION_APPROVED_REMARKS}}</td>
                                <td>{{date('d-m-Y',strtotime($value->REQUISITION_APPROVED_DATE))}}</td>
                                <td>{{$value->delivery_status}}</td>
                                <td>{{date('d-m-Y',strtotime($value->DELIVERY_DATE))}}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
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
                title: 'Item Delivery Status Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                  
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Item Delivery Status Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Item Delivery Status Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Item Delivery Status Report' }, 
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

