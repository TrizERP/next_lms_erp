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
                <h4 class="page-title">Teacher Daily Report</h4>
            </div>            
        </div>      
        
            <div class="card">
                @if ($message = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('ajax_getTeacherDailyReport') }}" enctype="multipart/form-data" method="post">
                        
                        <div class="row">
                            <div class="col-md-4 form-group">
                            <label>Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input value="@if(isset($data['date_selected'])){{ $data['date_selected'] }}@endif" type="text" required class="form-control mydatepicker" placeholder="YYYY/MM/DD" name="date" id="date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span> 
                                </div>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Status</label>
                                <select id='status' name="status" class="form-control">
                                    <option>--Select Status--</option>
                                    <option value="Y" @if(isset($data['status'])) @if($data['status'] == 'Y') selected @endif @endif>Yes</option>
                                    <option value="N" @if(isset($data['status'])) @if($data['status'] == 'N') selected @endif @endif>No</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group mt-4">                            
                                <br>
                                <input type="submit" name="submit" value="Submit" class="btn btn-success">     
                            </div>
                        </div>
                    </form>                    
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="alert alert-danger alert-dismissable" id='showerr' style="display:none;">                                   
                        <div id='err'></div>
                    </div>
                </div>
            </div>
        
       
            <div class="card">       
                @if( isset($data['data']) )
                <div class="col-lg-12 col-sm-12 col-xs-12">
				<div class="table-responsive">
                    <table id="proxy_list" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th>Teacher</th>
                                <th>Attedance</th>
                                <th>Homework Assigned</th>
                                <th>Homework Checked</th>
                                <th>Parent Communication</th>
                                <th>Student Leave Apporval</th>                           
                            </tr>
                        </thead>
                        <tbody>
                       @php $i=1; @endphp
                        @foreach($data['data'] as $key =>$val)                                    
                            <tr>                                
                                <td>{{$i++}}</td>
                                <td>{{$val->TEACHER}}</td>
                                <td><b><a href="{{ route('ajax_getTeacherDailyDetailsReport', ['teacher_id'=>$val->TEACHER_ID, 'date'=>$data['date_selected'],'action'=>'attedance']) }}" target="_blank">{{$val->STUDENT_ATTE}}</a></b></td>
                                <td><b><a href="{{ route('ajax_getTeacherDailyDetailsReport', ['teacher_id'=>$val->TEACHER_ID, 'date'=>$data['date_selected'],'action'=>'homework_assign']) }}" target="_blank">{{$val->HOMEWORK_ASSIGN}}</a></b></td>
                                <td><b><a href="{{ route('ajax_getTeacherDailyDetailsReport', ['teacher_id'=>$val->TEACHER_ID, 'date'=>$data['date_selected'],'action'=>'homework_check']) }}" target="_blank">{{$val->HOMEWORK_CHECK}}</a></b></td>
                                <td><b><a href="{{ route('ajax_getTeacherDailyDetailsReport', ['teacher_id'=>$val->TEACHER_ID, 'date'=>$data['date_selected'],'action'=>'parent_comm']) }}" target="_blank">{{$val->PARENT_COMM}}</a></b></td>
                                <td><b><b><a href="{{ route('ajax_getTeacherDailyDetailsReport', ['teacher_id'=>$val->TEACHER_ID, 'date'=>$data['date_selected'],'action'=>'student_leave']) }}" target="_blank">{{$val->STUDENT_LEAVE}}</a></b></td>
                            </tr>                                    
                        @endforeach     
                        </tbody>
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
     var table = $('#proxy_list').DataTable( {
         select: true,          
         lengthMenu: [ 
                        [100, 500, 1000, -1], 
                        ['100', '500', '1000', 'Show All'] 
        ],
        dom: 'Bfrtip', 
        buttons: [ 
            { 
                extend: 'pdfHtml5',
                title: 'Teacher Daily Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Teacher Daily Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Teacher Daily Report'}, 
            { extend: 'print', text: ' PRINT', title: 'Teacher Daily Report'}, 
            'pageLength' 
        ], 
        }); 

        $('#proxy_list thead tr').clone(true).appendTo( '#proxy_list thead' );
        $('#proxy_list thead tr:eq(1) th').each( function (i) {
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
