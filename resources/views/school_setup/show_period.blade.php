@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Create Period</h4>
            </div>            
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
            <div class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('period_master.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                        
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Short Name</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Academic Year</th>
                                    <th>Academic Section</th>
                                    <th>Used for Attendance</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $key => $data)
                                <tr>    
                                    <td>{{$data->title}}</td>
                                    <td>{{$data->short_name}}</td>                 
                                    <td>{{$data->start_time}}</td>                 
                                    <td>{{$data->end_time}}</td>     
                                    <td>{{$data->academic_year_name}}</td>                                         
                                    <td>
                                    @if($data->academic_section_name != "")
                                        {{$data->academic_section_name}}
                                    @else
                                        {{"-"}}
                                    @endif
                                    </td>
                                    <td>
                                    @if($data->used_for_attendance != "")
                                        {{$data->used_for_attendance}}
                                    @else
                                        {{"-"}}
                                    @endif
                                    </td>
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ route('period_master.edit',$data->id)}}" class="btn btn-info btn-outline">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>                                                    @php
                                        $timetable = DB::table("timetable")
                                            ->where("period_id", $data->id)
                                            ->get()
                                            ->toArray();
                                        @endphp

                                        @if(empty($timetable))
                                        <form action="{{ route('period_master.destroy', $data->id)}}" method="post" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button onclick="return confirmDelete();" type="submit" class="btn btn-info btn-outline-danger">
                                                <i class="ti-trash"></i>
                                            </button>
                                        </form>
                                        @endif                                     
                                    </td> 
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>            
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable({});
});

</script>
@include('includes.footer')
