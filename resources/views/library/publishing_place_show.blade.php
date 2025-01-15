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
                <h4 class="page-title">Publishing Place Report</h4> 
            </div>
        </div>        
        @if(isset($data['publishing_places']))
            @php
                if(isset($data['publishing_places']))
                {
                    $publishing_places = $data['publishing_places'];
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
                                @foreach($publishing_places as $key => $publishing_place)                                 
                                    <tr>
                                        <td>{{ $number++ }}</td>
                                        <td>{{$publishing_place->title}}</td>
                                        <td>{{$publishing_place->sub_title}}</td>    
                                        <td>{{$publishing_place->material_resource_type}}</td>
                                        <td>{{$publishing_place->edition}}</td>
                                        <td>{{$publishing_place->tags}}</td>
                                        <td>{{$publishing_place->vol_no}}</td>
                                        <td>{{$publishing_place->author_name}}</td>
                                        <td>{{$publishing_place->isbn_issn}}</td>
                                        <td>{{$publishing_place->classification}}</td>
                                        <td>{{$publishing_place->publisher_name}}</td>
                                        <td>{{$publishing_place->publish_year}}</td>
                                        <td>{{$publishing_place->publish_place}}</td>
                                        <td>{{$publishing_place->pages}}</td>
                                        <td>{{$publishing_place->series_title}}</td>
                                        <td>{{$publishing_place->call_number}}</td>
                                        <td>{{$publishing_place->language}}</td>
                                        <td>{{$publishing_place->source}}</td>
                                        <td>{{$publishing_place->subject}}</td>
                                        <td>{{$publishing_place->price}}</td>
                                        <td>{{$publishing_place->notes}}</td>
                                        <td>{{$publishing_place->review}}</td>
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
