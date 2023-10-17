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
                <h4 class="page-title">Margin Report</h4> 
            </div>
        </div>        
        @if(isset($data['margin_bazar_report']))
            @php
                if(isset($data['margin_bazar_report']))
                {
                    $margin_bazar_reports = $data['margin_bazar_report'];
                }
            @endphp 
            <div class="card">  
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">                       
                             <thead>
                                <tr>
                                    <th>Upload Date</th>
                                    <th>Code</th>
                                    <th>Exchange</th>
                                    <th>Script</th>
                                    <th>Qty</th>
                                    <th>Span</th>
                                    <th>Exposure</th>
                                    <th>Delivery Margin</th>
                                    <th>Additional Margin</th>
                                    <th>Ex.%</th>
                                    <th>Total</th>
                                    <th>Created Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($margin_bazar_reports as $margin_bazar_report)                                 
                                    <tr>
                                        <td>{{ $margin_bazar_report['upload_date'] }}</td>
                                        <td>{{ $margin_bazar_report['Code'] }}</td>
                                        <td>{{ $margin_bazar_report['exchange'] }}</td>
                                        <td>{{ $margin_bazar_report['script'] }}</td>
                                        <td>{{ $margin_bazar_report['qty'] }}</td>
                                        <td>{{ $margin_bazar_report['span'] }}</td>
                                        <td>{{ $margin_bazar_report['exposure'] }}</td>
                                        <td>{{ $margin_bazar_report['delivery_margin'] }}</td>
                                        <td>{{ $margin_bazar_report['additional_margin'] }}</td>
                                        <td>{{ $margin_bazar_report['ex_%'] }}</td>
                                        <td>{{ $margin_bazar_report['total'] }}</td>
                                        <td>{{ $margin_bazar_report['created_at'] }}</td>
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
                title: 'Margin Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Margin Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Margin Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Margin Report' }, 
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
