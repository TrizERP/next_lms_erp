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
                <h4 class="page-title">PNL Report</h4> 
            </div>
        </div>        
        @if(isset($data['pnl_bazar_report']))
            @php
                if(isset($data['pnl_bazar_report']))
                {
                    $pnl_bazar_reports = $data['pnl_bazar_report'];
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
                                    <th>Name</th>
                                    <th>Gross</th>
                                    <th>Exp</th>
                                    <th>Other Exp</th>
                                    <th>Gross Total</th>
                                    <th>Interest</th>
                                    <th>Net Total</th>
                                    <th>Created Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pnl_bazar_reports as $pnl_bazar_report)                                 
                                    <tr>
                                        <td>{{ $pnl_bazar_report['upload_date'] }}</td>
                                        <td>{{ $pnl_bazar_report['code'] }}</td>
                                        <td>{{ $pnl_bazar_report['name'] }}</td>
                                        <td>{{ $pnl_bazar_report['gross'] }}</td>
                                        <td>{{ $pnl_bazar_report['exp'] }}</td>
                                        <td>{{ $pnl_bazar_report['other_exp'] }}</td>
                                        <td>{{ $pnl_bazar_report['gross_total'] }}</td>
                                        <td>{{ $pnl_bazar_report['intrest'] }}</td>
                                        <td>{{ $pnl_bazar_report['net_total'] }}</td>
                                        <td>{{ $pnl_bazar_report['created_at'] }}</td>
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
                title: 'PNL Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'PNL Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'PNL Report' }, 
            { extend: 'print', text: ' PRINT', title: 'PNL Report' }, 
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
