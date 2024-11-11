@include('includes.headcss')
<style>
    tfoot input {
        width: 100%;
        padding: 3px;
        box-sizing: border-box;
    }
    tfoot {
     display: table-header-group;
    }
</style>
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Position Report</h4> 
            </div>
        </div>        
        @if(isset($data['position_bazar_report']))
            @php
                if(isset($data['position_bazar_report']))
                {
                    $position_bazar_reports = $data['position_bazar_report'];
                }
            @endphp 
            <div class="card">  
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">                       
                             <thead>
                                <tr>
                                    <th>Upload Date</th>
                                    <th>Date</th>
                                    <th>Client Id</th>
                                    <th>Exchange</th>
                                    <th>ScriptName</th>
                                    <th>B.F. Qty</th>
                                    <th>B.F. Rate</th>
                                    <th>B.F. Value</th>
                                    <th>BuyQty</th>
                                    <th>BuyRate</th>
                                    <th>BuyAmount</th>
                                    <th>SaleQty</th>
                                    <th>SaleRate</th>
                                    <th>SaleAmount</th>
                                    <th>NetQty</th>
                                    <th>NetRate</th>
                                    <th>NetAmount</th>
                                    <th>ClosingPrice</th>
                                    <th>Booked</th>
                                    <th>Notional</th>
                                    <th>Total</th>
                                    <th>Created Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($position_bazar_reports as $position_bazar_report)                                 
                                    <tr>
                                        <td>{{ $position_bazar_report['upload_date'] }}</td>
                                        <td>{{ $position_bazar_report['date'] }}</td>
                                        <td>{{ $position_bazar_report['client_id'] }}</td>
                                        <td>{{ $position_bazar_report['exchange'] }}</td>
                                        <td>{{ $position_bazar_report['ScriptName'] }}</td>
                                        <td>{{ $position_bazar_report['b_f_qty'] }}</td>
                                        <td>{{ $position_bazar_report['b_f_rate'] }}</td>
                                        <td>{{ $position_bazar_report['b_f_value'] }}</td>
                                        <td>{{ $position_bazar_report['buy_qty'] }}</td>
                                        <td>{{ $position_bazar_report['buy_rate'] }}</td>
                                        <td>{{ $position_bazar_report['buy_amount'] }}</td>
                                        <td>{{ $position_bazar_report['sale_qty'] }}</td>
                                        <td>{{ $position_bazar_report['sale_rate'] }}</td>
                                        <td>{{ $position_bazar_report['sale_amount'] }}</td>
                                        <td>{{ $position_bazar_report['net_qty'] }}</td>
                                        <td>{{ $position_bazar_report['net_rate'] }}</td>
                                        <td>{{ $position_bazar_report['net_amount'] }}</td>
                                        <td>{{ $position_bazar_report['closing_price'] }}</td>
                                        <td>{{ $position_bazar_report['booked'] }}</td>
                                        <td>{{ $position_bazar_report['notional'] }}</td>
                                        <td>{{ $position_bazar_report['total'] }}</td>
                                        <td>{{ $position_bazar_report['created_at'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>    
                        </table>
                    </div>     
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
                title: 'Position Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Position Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Position Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Position Report' }, 
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
