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
                <h4 class="page-title">Teacher Daily Details Report</h4>
            </div>            
        </div>
        <div class="row" style=" margin-top: 25px;">
         <div class="white-box">   
            <div class="panel-body">   
                @if($data['request_action'] == 'homework_assign')
                <div class="col-lg-12 col-sm-12 col-xs-12">
				<div class="table-responsive">
                    <table id="example" class="table table-striped table-bordered table-responsive" style="width:100%">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th>Std-Div</th>
                                <th>Subject</th>
                                <th>Title</th>
                                <th>Attechment</th>
                                <th>Teacher Name</th>                           
                                <th>Date</th>                           
                            </tr>
                        </thead>
                        <tbody>
                       @php $i=1; @endphp
                        @foreach($data['data'] as $key =>$val)                                    
                            <tr>                                
                                <td>{{$i++}}</td>
                                <td>{{$val->STD}} - {{$val->div_name}}</td>
                                <td>{{$val->SUBJECT}}</td>
                                <td>{{$val->description}}</td>
                                <td>{{$val->ATTACHMENT}}</td>
                                <td>{{$val->teacher}}</td>
                                <td>{{$val->homework_date}}</td>
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

            <div class="panel-body">   
                @if($data['request_action'] == 'homework_check')
                <div class="col-lg-12 col-sm-12 col-xs-12">
                <div class="table-responsive">
                    <table id="example" class="table table-striped table-bordered table-responsive" style="width:100%">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th>Std-Div</th>
                                <th>Subject</th>
                                <th>Title</th>
                                <th>Attechment</th>
                                <th>Teacher Name</th>                           
                                <th>Date</th>                           
                                <th>Submission Date</th>                           
                                <th>Submission Remark</th>                           
                                <th>Submission Status</th>                           
                            </tr>
                        </thead>
                        <tbody>
                       @php $i=1; @endphp
                        @foreach($data['data'] as $key =>$val)                                    
                            <tr>                                
                                <td>{{$i++}}</td>
                                <td>{{$val->STD}} - {{$val->div_name}}</td>
                                <td>{{$val->SUBJECT}}</td>
                                <td>{{$val->description}}</td>
                                <td>{{$val->ATTACHMENT}}</td>
                                <td>{{$val->teacher}}</td>
                                <td>{{$val->homework_date}}</td>
                                <td>{{$val->submission_date}}</td>
                                <td>{{$val->submission_remarks}}</td>
                                <td>{{$val->completion_status}}</td>
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

            <div class="panel-body">   
                @if($data['request_action'] == 'attedance')
                <div class="col-lg-12 col-sm-12 col-xs-12">
                <div class="table-responsive">
                    <table id="example" class="table table-striped table-bordered table-responsive" style="width:100%">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th>Std-Div</th>
                                <th>Teacher Name</th>                           
                                <th>Created Date</th>                           
                                <th>Attendance Code</th>                           
                                <th>Attendance Date</th>                           
                            </tr>
                        </thead>
                        <tbody>
                       @php $i=1; @endphp
                        @foreach($data['data'] as $key =>$val)                                    
                            <tr>                                
                                <td>{{$i++}}</td>
                                <td>{{$val->STD}} - {{$val->div_name}}</td>
                                <td>{{$val->teacher}}</td>
                                <td>{{$val->created_date}}</td>
                                <td>{{$val->attendance_code}}</td>
                                <td>{{$val->attendance_date}}</td>
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

            <div class="panel-body">   
                @if($data['request_action'] == 'parent_comm')
                <div class="col-lg-12 col-sm-12 col-xs-12">
                <div class="table-responsive">
                    <table id="example" class="table table-striped table-bordered table-responsive" style="width:100%">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th>Std-Div</th>
                                <th>Teacher Name</th>                           
                                <th>Message</th>                           
                                <th>Reply</th>                           
                                <th>Created Date</th>                           
                                <th>Reply Date</th>                           
                            </tr>
                        </thead>
                        <tbody>
                       @php $i=1; @endphp
                        @foreach($data['data'] as $key =>$val)                                    
                            <tr>                                
                                <td>{{$i++}}</td>
                                <td>{{$val->STD}} - {{$val->div_name}}</td>
                                <td>{{$val->teacher}}</td>
                                <td>{{$val->message}}</td>
                                <td>{{$val->reply}}</td>
                                <td>{{$val->created_date}}</td>
                                <td>{{$val->reply_date}}</td>
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

            <div class="panel-body">   
                @if($data['request_action'] == 'student_leave')
                <div class="col-lg-12 col-sm-12 col-xs-12">
                <div class="table-responsive">
                    <table id="example" class="table table-striped table-bordered table-responsive" style="width:100%">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th>Std-Div</th>
                                <th>Teacher Name</th>                           
                                <th>Message</th>                           
                                <th>Status</th>                           
                                <th>File</th>                           
                                <th>Applied Date</th>                           
                            </tr>
                        </thead>
                        <tbody>
                       @php $i=1; @endphp
                        @foreach($data['data'] as $key =>$val)                                    
                            <tr>                                
                                <td>{{$i++}}</td>
                                <td>{{$val->STD}} - {{$val->div_name}}</td>
                                <td>{{$val->teacher}}</td>
                                <td>{{$val->message}}</td>
                                <td>{{$val->status}}</td>
                                <td>{{$val->files}}</td>
                                <td>{{$val->apply_date}}</td>
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
    </div>
</div>

@include('includes.footerJs')

<script>
$(document).ready(function () {
    var table = $('#example').DataTable({
		orderCellsTop: true,
        fixedHeader: true,
        dom: 'Bfrtip',
        buttons: [
            'copyHtml5',
            'excelHtml5',
            // 'csvHtml5',
            'pdfHtml5'
        ]
    } );
	
});

</script>

@include('includes.footer')
