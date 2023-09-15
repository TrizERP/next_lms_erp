@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Student Discipline Report</h4>            
            </div>                    
        </div>
        @php
            $grade_id = $standard_id = $division_id = '';
            
            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }
        @endphp
        <div class="card">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    @php
                    if(isset($data['data'])){
                    @endphp
                    @php
                        $fromDate = request('from_date');
                        $toDate = request('to_date');
                    @endphp
                    <form action="{{ route('dicipline_report.index') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <div class="table-responsive">
                            @php
                                echo App\Helpers\get_school_details("$grade_id","$standard_id","$division_id");
                                echo '<br><center><span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important">From Date : '.$fromDate .' - </span><span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important">To Date : '.$toDate .'</span></center><br>';
                            @endphp
                                                    
                            <table class="table table-stripped" id="example">
                                <thead>                                    
                                    <tr>
                                        <th>No</th>
                                        <th>Student Name</th>
                                        <th>Std</th>
                                        <th>Div</th>
                                        <th>Mobile</th>
                                        <th>Dicipline</th>
                                        <th>Message</th>
                                        <th>Name</th>
                                    </tr>
                                </thead>
                                <tbody>                                    
                                    @php
                                    $arr = $data['data'];
                                    foreach ($arr as $id=>$col_arr){
                                    @endphp
                                    <tr>
                                        <td>@php echo $id+1; @endphp</td>
                                        <td>@php echo $col_arr->first_name.' '.$col_arr->middle_name.' '.$col_arr->last_name; @endphp</td>
                                        <td>@php echo $col_arr->standard_name; @endphp</td>
                                        <td>@php echo $col_arr->division_name; @endphp</td>
                                        <td>@php echo $col_arr->mobile; @endphp</td>
                                        <td>@php echo $col_arr->dicipline; @endphp</td>
                                        <td>@php echo $col_arr->message; @endphp</td>
                                        <td>@php echo $col_arr->name; @endphp</td>                                        
                                    </tr>
                                    @php
                                    }
                                    @endphp
                                </tbody>
                            </table>
                        </div>
                    </form>
                    @php
                    }else{
                    echo "No Student Found.";
                    }
                    @endphp
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
    $(function () {
        var $tblChkBox = $("input:checkbox");
        $("#ckbCheckAll").on("click", function () {
            $($tblChkBox).prop('checked', $(this).prop('checked'));
        });
    });
</script>
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
                title: 'Student Discipline Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Student Discipline Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Student Discipline Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Student Discipline Report' }, 
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
