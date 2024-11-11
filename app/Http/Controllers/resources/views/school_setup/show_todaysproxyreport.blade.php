@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style>
.title{
    font-weight:200;
}
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Todays Proxy Report</h4>
            </div>            
        </div>            
            <div class="card">
                @if ($message = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message['message'] }}</strong>
                </div>
                @endif
     
                @if( isset($data['proxydata']) )
                <div class="col-lg-12 col-sm-12 col-xs-12">
				<div class="table-responsive">
                    {!! App\Helpers\get_school_details("","","") !!}
                    <table id="example" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th>Date</th>
                                <th>Standard</th>
                                <th>Division</th>
                                <th>Absent Teacher</th>
                                <th>Proxy Teacher</th>
                                <th>Period</th>
                                <th>Subject</th>                                
                            </tr>
                        </thead>
                        <tbody>
                       @php $i=1; @endphp
                        @foreach($data['proxydata'] as $key =>$val)                                    
                            <tr>                                
                                <td>{{$i++}}</td>
                                <td>{{$val->proxy_date}}</td>
                                <td>{{$val->standard_name}}</td>
                                <td>{{$val->division_name}}</td>
                                <td>{{$val->teacher_name}}</td>
                                <td>{{$val->proxy_teacher_name}}</td>
                                <td>{{$val->period_name}}</td>
                                <td>{{$val->sub_name}}</td>                                                                
                            </tr>                                    
                        @endforeach     
                        </tbody>
                        @if( count($data['proxydata']) == 0 )
                        <tr align="center">
                            <td colspan="10">
                                No Records Found!
                            </td>
                        </tr>
                        @endif
                    </table>
					</div>
                </div>
                @endif               

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
                title: 'Todays Proxy Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Todays Proxy Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Todays Proxy Report'}, 
            {
                extend: 'print',
                text: ' PRINT',
                title: 'Student Report',
                customize: function (win) {
                    $(win.document.body).prepend(`{!! App\Helpers\get_school_details("", "", "") !!}`);
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


function validate_dates(){
    var from_date = $("#from_date").val();
    var to_date = $("#to_date").val();
   
    if(Date.parse(from_date) < Date.parse(to_date)){
        return true;
    }else{
        $("#showerr").css("display", "block");        
        $("#err").html("Please select Proper Dates");
        //alert("Please select Proper Dates");
        return false;    
    }
}

</script>

@include('includes.footer')
