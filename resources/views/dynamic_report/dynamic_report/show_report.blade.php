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
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('dynamic_report.index') }}" class="btn btn-info add-new">Back To Reports</a>
                </div>
                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    @foreach($data['tbl_heading'] as $key => $val)
                                    <th>{!! $val !!}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>

                                 @foreach($data['result'] as $id => $arr)
                                    
                                    <tr>
                                        @foreach($arr as $ind => $val)
                                            <td>
                                                @if($ind == "chapter_name")
                                                  @php 
                                                    $explodedValues = explode(',', $val);
                                                    @endphp
                                                         @foreach($explodedValues as $explodedVal)
                                                            {{$explodedVal}}<br>
                                                        @endforeach
                                                @elseif($ind == "sub_contents" || $ind == "content_type") {{-- Assuming the fourth column is at index 3 --}}
                                                    @php $i=1; 
                                                        $explodedValues = explode(',', $val);
                                                    @endphp
                                                        @foreach($explodedValues as $explodedVal)
                                                            {{$i.")  ".$explodedVal}}<br>
                                                            @php $i++; @endphp
                                                    @endforeach
                                                @elseif($ind == "icard_icon")
                                                @php
                                                    $path = 'storage/app/public/driver/' . $val;
                                                @endphp
                                                <img src="{{ asset($path) }}" height="30%" width="30%">                                        
                                                @else
                                                    {{$val}}
                                                @endif
                                            </td>
                                        @endforeach
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
<!-- <script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script> -->
<!-- <script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script> -->
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
                title: 'Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Report'}, 
            { extend: 'print', text: ' PRINT', title: 'Report'}, 
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