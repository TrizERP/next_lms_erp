@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                Document Standard Mapping
                </h4>
            </div>            
        </div>
        <div class="row bg-title">
            <div class="col-md-3 d-flex">
               <h4 class="page-title">
                    <a href="{{ route('document_standard_mapping.create') }}" class="btn btn-info add-new">Map Document</a>
                </h4>
            </div>
        </div>   

        <div class="card" style=" margin-top: 25px;">
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
            <!-- table start  -->
            @if(!empty($data['mappedData']))
            <div class="card">
                <div class="table-responsive">                        
                    <table id="example" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Sr No.</th>
                                <th>Document Type</th>
                                <th>{{App\Helpers\get_string('searchsection')}}</th>
                                <th>{{App\Helpers\get_string('searchstandard')}}</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($data['mappedData'] as $key=>$value)
                            <tr>
                                <td>{{$key+1}}</td>
                                <td>{{$value->document_type}}</td>
                                <td>{{$value->grade_name}}</td>
                                <td>{{$value->standard}}</td>
                                <td>
                                    <div class="d-inline">                                        
                                        <a href="{{ route('document_standard_mapping.edit',$value->document_type_id)}}" class="btn btn-info btn-outline">
                                            <i class="ti-pencil-alt"></i>
                                        </a>                                                                                          
                                    </div>
                                    <form action="{{ route('document_standard_mapping.destroy', $value->document_type_id)}}" method="post" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                        <button onclick="return confirmDelete();" type="submit" class="btn btn-info btn-outline-danger">
                                            <i class="ti-trash"></i>
                                        </button>
                                    </form>  
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
            <!-- table end  -->
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
                title: 'Document Standard Map Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Document Standard Map Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Document Standard Map Report'}, 
            {
                extend: 'print',
                text: ' PRINT',
                title: 'Student Report',
                customize: function (win) {
                    $(win.document.body).prepend(`{!! App\Helpers\get_school_details() !!}`);
                    $(win.document.body).append(`<div style="text-align: right;margin-top:20px">Printed on: {{date('d-m-Y H:i:s')}}</div>`);
                }
            },
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
@endsection