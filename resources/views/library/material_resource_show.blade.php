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
                <h4 class="page-title">Material Resource Report</h4> 
            </div>
        </div>        
        @if(isset($data['material_resources']))
            @php
                if(isset($data['material_resources']))
                {
                    $material_resources = $data['material_resources'];
                }
            @endphp 
            <div class="card">  
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">                       
                             <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Title</th>
                                    <th>Sub Title</th>
                                    <th>Material Resource Type</th>
                                    <th>Edition</th>
                                    <th>Tags</th>
                                    <th>No. of Items</th>
                                    <th>Author/Editor Name</th>
                                    <th>ISBN/ISSN</th>
                                    <th>Classification</th>
                                    <th>Publisher Name</th>
                                    <th>Publish Year</th>
                                    <th>Publishing Place</th>
                                    <th>Book Size/ No. page</th>
                                    <th>Series Title</th>
                                    <th>Call Number</th>
                                    <th>Language</th>
                                    <th>Source</th>
                                    <th>Subject</th>
                                    <th>Price</th>
                                    <th>Notes</th>
                                    <th>Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $number = 1; @endphp
                                @foreach($material_resources as $key => $material_resource)                                 
                                    <tr>
                                        <td>{{ $number++ }}</td>
                                        <td>{{$material_resource->title}}</td>
                                        <td>{{$material_resource->sub_title}}</td>    
                                        <td>{{$material_resource->material_resource_type}}</td>
                                        <td>{{$material_resource->edition}}</td>
                                        <td>{{$material_resource->tags}}</td>
                                        <td>{{$material_resource->vol_no}}</td>
                                        <td>{{$material_resource->author_name}}</td>
                                        <td>{{$material_resource->isbn_issn}}</td>
                                        <td>{{$material_resource->classification}}</td>
                                        <td>{{$material_resource->publisher_name}}</td>
                                        <td>{{$material_resource->publish_year}}</td>
                                        <td>{{$material_resource->publish_place}}</td>
                                        <td>{{$material_resource->pages}}</td>
                                        <td>{{$material_resource->series_title}}</td>
                                        <td>{{$material_resource->call_number}}</td>
                                        <td>{{$material_resource->language}}</td>
                                        <td>{{$material_resource->source}}</td>
                                        <td>{{$material_resource->subject}}</td>
                                        <td>{{$material_resource->price}}</td>
                                        <td>{{$material_resource->notes}}</td>
                                        <td>{{$material_resource->review}}</td>
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
                title: 'Inward Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Classwise Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Classwise Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Classwise Report' }, 
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
