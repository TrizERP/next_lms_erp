@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Request Report</h4>
            </div>
        </div>        
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
            <form action="{{ route('student_request_report.create') }}" enctype="multipart/form-data">                
                @csrf  
                <div class="row">                    
                    <div class="col-md-4 form-group">
                        <label>From Date</label>
                        <input type="text" name="from_date" class="form-control mydatepicker" placeholder="Please select from date." autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>To Date</label>
                        <input type="text" name="to_date" class="form-control mydatepicker" placeholder="Please select to date." autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group mt-3">
                        <input type="submit" name="submit" value="Search" class="btn btn-success" >                     
                    </div>
                </div>              
            </form>
        </div>

        @if(isset($data['result_report']))
        @php
        $j = 1;
            if(isset($data['result_report'])){
                $result_report = $data['result_report'];
            }
        @endphp
        <div class="card">            
            <div class="table-responsive">
                {!! App\Helpers\get_school_details("","","") !!}
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>SR NO</th>
                            <th>{{App\Helpers\get_string('grno','request')}}</th>
                            <th>{{App\Helpers\get_string('studentname','request')}}</th>
                            <th>{{App\Helpers\get_string('standard','request')}}</th>
                            <th>{{App\Helpers\get_string('division','request')}}</th>
                            <th>Request</th>
                            <th>Reason</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($result_report as $key => $value)
                            <tr>
                                <td>{{$j++}}</td>
                                <td> {{$value->enrollment_no}} </td>
                                <td> {{$value->student_name}} </td>
                                <td> {{$value->standard}} </td>
                                <td> {{$value->division}} </td>
                                <td> {{$value->REQUEST}} </td>
                                <td> {{$value->REASON}} </td>
                                <td> {{$value->DESCRIPTION}} </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>    
        @endif
    </div>
</div>

<script>
    function checkAll(ele,name) {
         var checkboxes = document.getElementsByClassName(name);
         if (ele.checked) {
             for (var i = 0; i < checkboxes.length; i++) {
                 if (checkboxes[i].type == 'checkbox') {
                     checkboxes[i].checked = true;
                 }
             }
         } else {
             for (var i = 0; i < checkboxes.length; i++) {
                 console.log(i)
                 if (checkboxes[i].type == 'checkbox') {
                     checkboxes[i].checked = false;
                 }
             }
         }
    }
</script>

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
                title: 'Student Request Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Student Request Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Student Request Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Student Request Report' }, 
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

