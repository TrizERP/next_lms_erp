@include('includes.lmsheadcss')

@include('includes.header')
{{-- @include('includes.sideNavigation') --}}
<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="row">
        <div class="col-md-6">
            <h1 class="h4 mb-3">             
            Add Lesson Plan            
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>                                 
                    <li class="breadcrumb-item">Lesson Plan</li>                                 
                    <li class="breadcrumb-item active" aria-current="page">Add Lesson Plan</li>
                </ol>
            </nav>
        </div>        
    </div>

    <div class="container-fluid mb-5">
        <div class="card border-0">
            <div class="card-body">
                @php
                    // echo "<pre>"; print_r($data); exit;
                @endphp
                <table id="example" class="table table-striped">
                    <tbody>
                        @foreach ( $data['data'] as $key => $value )

                        @if ( $key == 'header' )
                            <tr>
                                <td colspan="2" class="text-center"><h2>{{ $value }}</h2></td>
                            </tr>
                            @continue
                        @endif
                        <tr>
                            <td>{{ $key }}</td>
                            <td class="text-left">{!! $value !!}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@include('includes.lmsfooterJs')
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
                title: 'Admission Enquiry Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Admission Enquiry Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Admission Enquiry Report'}, 
            { extend: 'print', text: ' PRINT', title: 'Admission Enquiry Report'}, 
            'pageLength' 
        ], 
        }); 

        // $('#example thead tr').clone(true).appendTo( '#example thead' );
        // $('#example thead tr:eq(1) th').each( function (i) {
        //     var title = $(this).text();
        //     $(this).html( '<input type="text" placeholder="Search '+title+'" />' );

        //     $( 'input', this ).on( 'keyup change', function () {
        //         if ( table.column(i).search() !== this.value ) {
        //             table
        //                 .column(i)
        //                 .search( this.value )
        //                 .draw();
        //         }
        //     } );
        // } );
    } );
</script>

@include('includes.footer')
