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
                    <h4 class="page-title">Outward Report</h4> </div>
        </div>      
                <div class="card">
                    @if(!empty($data['message']))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $data['message'] }}</strong>
                    </div>
                    @endif
                    <br><br><br>
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                        <table id="example" class="table table-striped">
                           
                             <thead>
                                <tr>
                                    <th>Year</th>
                                    <th>From Place</th>
                                    <th>Outward No.</th>
                                    <th>Subject</th>
                                    <th>Description</th>
                                    <th>File Name</th>
                                    <th>File Location</th>
                                    <!--<th>Academic Year</th>-->
                                    <th>Outward Date</th>
                                    <th>Attachment</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $key => $data)
                                <tr>    
                                    <td>{{$data->syear}}</td>
                                    <td>{{$data->place_id}}</td>
                                    <td>{{$data->outward_number}}</td>
                                    <td>{{$data->title}}</td>  
                                    <td>{{$data->description}}</td> 
                                    <td>{{$data->file_name}}</td> 
                                    <td>{{$data->file_location_id}}</td> 
                                    <!--<td>{{$data->acedemic_year}}</td> -->
                                    <td>{{date('d-m-Y',strtotime($data->outward_date))}}</td> 
                                    <td><a target="blank" href="/storage/outward/{{$data->attachment}}">{{$data->attachment}}</a> </td> 
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>     
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
                title: 'Outward Report',
                orientation: 'portrait',
                pageSize: 'A4',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Outward Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Outward Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Outward Report' }, 
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
