@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Lesson Plan Report</h4>
            </div>            
        </div>        
        <div class="card">                
                @if ($sessionData = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
                @endif
                
                <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                    <table id="subject_list" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Standard</th>
                                <th>Division</th>
                                <th>Subject</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Reason</th>
                                <th>Execution Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['data'] as $key => $data)
                            <tr>    
                                <td>{{date('d-m-Y',strtotime($data->school_date))}}</td>
                                <td>{{$data->standard_name}}</td> 
                                <td>{{$data->division_name}}</td>     
                                <td>{{$data->subject_name}} ({{$data->subject_code}})</td>     
                                <td>{{$data->title}}</td>     
                                <td>{{$data->description}}</td>     
                                <td>{{$data->lessonplan_status}}</td>     
                                <td>{{$data->lessonplan_reason}}</td>     
                                <td>{{date('d-m-Y',strtotime($data->lessonplan_date))}}</td>                                     
                            </tr>
                            @endforeach

                        </tbody>

                    </table>

                </div>

            </div>
        </div>
    </div>


@include('includes.footerJs')
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function () {
    $('#subject_list').DataTable({});
});

</script>
@include('includes.footer')
