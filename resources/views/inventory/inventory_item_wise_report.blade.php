@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Item Wise Report</h4>
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
            <form action="{{ route('inventory_item_wise_report.create') }}" enctype="multipart/form-data">
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
                        <label for="item_id">Item</label>
                        <select name="item_id" id="item_id" class="form-control">
                            <option value="">Select Item</option>
                            @foreach($data['item'] as $k => $v  )
                                <option value="{{$v['id']}}"
                                @if(isset($data['item_id']))
                                    @if($data['item_id'] == $v['id'])
                                    selected='selected'
                                    @endif
                                @endif
                                >{{ $v['title'] }}</option>
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
                            <th>Name of Staff</th>
                            <th>Taken On</th>
                            <th>Requisition No.</th>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Approved Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($result_report as $key => $value)
                            <tr>
                                <td>{{$j++}}</td>
                                <td> {{$value->REQUISITION_BY_NAME}} </td>
                                <td> {{$value->REQUISITION_DATE}} </td>
                                <td> {{$value->REQUISITION_NO}} </td>
                                <td> {{$value->ITEM_NAME}} </td>
                                <td> {{$value->ITEM_QTY}} </td>
                                <td> {{$value->APPROVED_QTY}} </td>
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
                title: 'Item Wise Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Item Wise Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Item Wise Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Item Wise Report' }, 
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

