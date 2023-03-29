@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Register Parents Report</h4> </div>
            </div>
        @php
            $mobile_no = $from_date = $to_date = '';

            if(isset($data['mobile_no']))
            {
                $mobile_no = $data['mobile_no'];
            }
            if(isset($data['from_date']))
            {
                $from_date = $data['from_date'];
            }
            if(isset($data['to_date']))
            {
                $to_date = $data['to_date'];
            }
        @endphp
        <div class="card">
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
                <form action="{{ route('register_parents_report.create') }}" enctype="multipart/form-data" class="row">
                    @csrf
                    <div class="col-md-4 form-group">
                        <label>From Date</label>
                        <input type="text" id="from_date" name="from_date" value="{{$from_date}}" class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>To Date</label>
                        <input type="text" id="to_date" name="to_date" value="{{$to_date}}" class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Mobile No.</label>
                        <input type="text" id="mobile_no" value="{{$mobile_no}}" name="mobile_no" class="form-control">
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>

                </form>
            </div>
        @if(isset($data['data']))
        @php
            if(isset($data['data'])){
                $data = $data['data'];
            }
            
        @endphp
        <div class="card">
            <div class="table-responsive">
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sr.No.</th>
                            <th>Student Name</th>
                            <th>Gr.No.</th>
                            <th>Academic Section</th>
                            <th>Standard</th>
                            <th>Division</th>
                            <th>IMEI No.</th>
                            <th>Mobile</th>
                            <th>Current Version</th>
                            <th>New Version</th>
                            <th>Creadted On</th>                                                         
                        </tr>
                    </thead>
                    <tbody>
                        @php
                        $j=1;
                        @endphp
                        @if(isset($data))                        
                        @foreach($data as $key => $val)
                        <tr>
                            <td>{{$j}}</td>
                            <td>{{$val['stu_name']}}</td>
                            <td>{{$val['enrollment_no']}}</td>
                            <td>{{$val['aca_sec']}}</td>  
                            <td>{{$val['std_name']}}</td> 
                            <td>{{$val['div_name']}}</td> 
                            <td>{{$val['imei_no']}}</td> 
                            <td>{{$val['mobile_no']}}</td> 
                            <td>{{$val['curr_version']}}</td> 
                            <td>{{$val['new_version']}}</td>
                            <td>{{$val['CREATED_ON']}}</td>
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
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>
    $(document).ready(function() {
    // Setup - add a text input to each footer cell    

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
                title: 'Register Parent Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Register Parent Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Register Parent Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Register Parent Report' },
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
