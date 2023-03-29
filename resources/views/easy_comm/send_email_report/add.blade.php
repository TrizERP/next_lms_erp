@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Send Email Report</h4> </div>
            </div>      
            <div class="card">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif               
                <div class="card">
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <table id="example" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Sr No.</th>
                                            <th>Email Id</th>
                                            <th>Subject</th>
                                            <th>Attachment</th>
                                            <th>IP</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                    @if(isset($data['all_data']))
                                        @foreach($data['all_data'] as $key => $value)
                                        <tr>
                                            <td>{{$j}}</td>
                                            <td>{{$value->EMAIL}}</td>
                                            <td>{{$value->SUBJECT}}</td>
                                            <?php
                                                $attechment = "-";
                                                if($value->ATTECHMENT != ""){
                                                    $attechment_val = "/storage/email/";
                                                    $att_arr = explode('/',$value->ATTECHMENT);
                                                    $attechment_val .= $att_arr[count($att_arr)-1];
                                                    // foreach($att_arr as $id=>$val){
                                                    //     if($val == "")
                                                    // }
                                                    $attechment = "<a href='$attechment_val'> Attachment </a>";
                                                }
                                            ?>
                                            <td>{!!$attechment!!}</td>
                                            <td>{{$value->IP}}</td>
                                            <td>{{ date('d-M-y', strtotime($value->CREATED_ON)) }}</td>
                                        </tr>
                                    @php
                                    $j++;
                                    @endphp
                                        @endforeach
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>                    
                @if (count($errors) > 0)
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> There were some problems with your input.<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
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
                title: 'Send Email Report ',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Send Email Report ' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Send Email Report '}, 
            { extend: 'print', text: ' PRINT', title: 'Send Email Report '}, 
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
