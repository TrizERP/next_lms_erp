@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Visitor Details Report</h4>
                </div>
        </div>        
                <div class="card">
                    @if(!empty($data['message']))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $data['message'] }}</strong>
                    </div>
                    @endif
                        <div class="table-responsive">
                        <table id="example" class="table table-striped">                           
                             <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Coming From</th>
                                    <th>To Meet</th>
                                    <th>Relation With</th>
                                    <th>Date</th>
                                    <th>Check In Time</th>
                                    <th>Check Out Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $key => $data)
                                <tr>    
                                    <td>{{$data->name}}</td>
                                    <td>{{$data->contact}}</td>
                                    <td>{{$data->email}}</td>
                                    <td>{{$data->coming_from}}</td>
                                    <td>{{$data->to_meet}}</td>
                                    <td>{{$data->relation}}</td>
                                    <td>{{date('d-m-Y',strtotime($data->meet_date))}}</td>
                                    <td>{{$data->in_time}}</td>
                                    <td>{{$data->out_time}}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>     
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
                title: 'Visitor Details Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Visitor Details Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Visitor Details Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Visitor Details Report' },
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
