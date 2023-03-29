@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">       
            <div class="card">
                @if(!empty($data['message']))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif

                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                    <table id="example" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Syear</th>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Text</th>
                                <th>Module</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data as $key => $arr)
                            <tr>    
                                <td>{{$arr['syear']}}</td>
                                <td>{{$arr['name']}}</td>
                                <td>{{$arr['sms_no']}}</td>
                                <td>{{$arr['sms_text']}}</td>                 
                                <td>{{$arr['module_name']}}</td>                                                  
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
                title: 'Send SMS Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Send SMS Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Send SMS Report'}, 
            { extend: 'print', text: ' PRINT', title: 'Send SMS Report'}, 
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
