@include('includes.headcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
    		<div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Visitor Management</h4> </div>
            </div>
        
            <div class="card">
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    @if(!empty($data['message']))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $data['message'] }}</strong>
                    </div>
                    @endif
                </div>				

                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route("add_visitor_master.create") }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Visitor</a>
                </div>
                
				<div class="col-lg-12 col-sm-12 col-xs-12">
                <div class="table-responsive">
                    <table id="example" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th data-toggle="tooltip" title="Action">Action</th>
                                <th data-toggle="tooltip" title="Date">Date</th>
                                <th data-toggle="tooltip" title="Check In Time">Check In Time</th>
                                <th data-toggle="tooltip" title="Check Out Time">Check Out Time</th>                                
                                <th data-toggle="tooltip" title="Appointment Type">Appointment Type</th>
                                <th data-toggle="tooltip" title="Visitor Type">Visitor Type</th>
                                <th data-toggle="tooltip" title="Visitor Name">Visitor Name</th>
                                <th data-toggle="tooltip" title="Visitor Contact">Visitor Contact</th>
                                <th data-toggle="tooltip" title="Visitor Email">Visitor Email</th>
                                <th data-toggle="tooltip" title="Visitor Photo">Visitor Photo</th>
                                <th data-toggle="tooltip" title="Visitor ID Card">Visitor ID Card</th>
                                <th data-toggle="tooltip" title="Coming From">Coming From</th>
                                <th data-toggle="tooltip" title="To Meet">To Meet</th>
                                <th data-toggle="tooltip" title="Relation">Relation</th>
								<th data-toggle="tooltip" title="Purpose">Purpose</th>                                
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['data'] as $key => $data)
                            <tr style="background-color:{{$data->status}}">                                    
                                 <td>
                                    <div class="d-inline">
                                    <a href="{{ route('add_visitor_master.edit',$data->id)}}" class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>
                                    </div>  
                                    <!-- <form action="{{ route('add_visitor_master.destroy', $data->id)}}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger" type="submit"><i class="ti-trash"></i></button>
                                    </form> -->
                                </td>
                                <td>{{date('d-m-Y',strtotime($data->meet_date))}}</td>
                                <td>{{$data->in_time}}</td>
                                <td>{{$data->out_time}}</td>
                                <td>{{$data->appointment_type}} Appointment</td>
                                <td>{{$data->visitor_type_name}}</td>
                                <td>{{$data->name}}</td>
                                <td>{{$data->contact}}</td>
                                <td>{{$data->email}}</td>
                                <td><a target="_blank" href="/storage/visitor_photo/{{$data->photo}}">{{$data->photo}}</a></td>
                                <td>{{$data->visitor_idcard}}</td>
                                <td>{{$data->coming_from}}</td>
                                <td>{{$data->staff_name}}</td>
                                <td>{{$data->relation}}</td>
                                <td>{{$data->purpose}}</td>                                
                            </tr>
                            @endforeach

                        </tbody>

                    </table>
				</div>
                </div>

            </div>
            </div>
        </div>
    </div>

@include('includes.footerJs')

<script type="text/javascript">
$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip();   
});
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
                title: 'Visitor Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Visitor Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Visitor Report'}, 
            { extend: 'print', text: ' PRINT', title: 'Visitor Report'}, 
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
