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
            $stu_name = isset($data['stu_name']) ? $data['stu_name'] : '';
            $mobile = isset($data['mobile']) ? $data['mobile'] : '';
            $grno = isset($data['grno']) ? $data['grno'] : '';
            $uniqueid = isset($data['uniqueid']) ? $data['uniqueid'] : '';
        @endphp
        <div class="card">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            
                    @php
                        $fromDate = request('from_date');
                        $toDate = request('to_date');
                    @endphp
                    <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('dicipline_report.create') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("GET") }}
                        {{csrf_field()}}
                        <div class="row">                            
                            {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                            <div class="col-md-4 form-group">
                        <label>{{App\Helpers\get_string('studentname')}}<i class="mdi mdi-lead-pencil"></i></label>
                        <input type="text" id="stu_name" placeholder="{{App\Helpers\get_string('studentname')}}" name="stu_name" class="form-control" @if(isset($data['stu_name'])) value="{{$data['stu_name']}}" @endif>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>{{App\Helpers\get_string('uniqueid')}}<i class="mdi mdi-lead-pencil"></i></label>
                        <input type="text" id="uniqueid" placeholder="{{App\Helpers\get_string('uniqueid')}}" name="uniqueid" class="form-control" @if(isset($data['uniqueid'])) value="{{$data['uniqueid']}}" @endif>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Mobile</label>
                        <input type="text" id="mobile" placeholder="Mobile" name="mobile" class="form-control" @if(isset($data['mobile'])) value="{{$data['mobile']}}" @endif>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>{{App\Helpers\get_string('grno')}}<i class="mdi mdi-lead-pencil"></i></label>
                        <input type="text" id="grno" placeholder="{{App\Helpers\get_string('grno')}}" name="grno" class="form-control" @if(isset($data['grno'])) value="{{$data['grno']}}" @endif>
                    </div>
                            <div class="col-md-4 form-group mr-0 ml-0">
                                <label>From Date</label>
                                <input type="text" name="from_date" class="form-control mydatepicker"  value="{{$fromDate}}">
                            </div>
                            <div class="col-md-4 form-group ml-0">
                                <label>To Date</label>
                                <input type="text" name="to_date" class="form-control mydatepicker" value="{{$toDate}}" >
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
@if(!empty($data['data']))
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
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
                                        <th class="text-left">Name</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>                                    
                                    @foreach ($data['data'] as $id=>$col_arr)
                                    <tr>
                                        <td>{{ $id+1 }}</td>
                                        <td>{{ $col_arr['first_name'].' '.$col_arr['middle_name'].' '.$col_arr['last_name'] }}</td>
                                        <td>{{ $col_arr['standard_name'] }}</td>
                                        <td>{{ $col_arr['division_name'] }}</td>
                                        <td>{{ $col_arr['mobile'] }}</td>
                                        <td>{{ $col_arr['dicipline'] }}</td>
                                        <td>{{ $col_arr['message'] }}</td>
                                        <td>{{ $col_arr['name'] }}</td>
                                        <td>{{ $col_arr['date_'] }}</td>
                                    </tr>
                                    @endforeach
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
    @endif
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
            {
                extend: 'print',
                text: ' PRINT',
                title: 'Student Discipline Report',
                customize: function (win) {
                    $(win.document.body).prepend(`{!! App\Helpers\get_school_details("$grade_id", "$standard_id", "$division_id") !!}`);
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
