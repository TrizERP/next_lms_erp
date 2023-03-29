@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Proxy Management</h4>
            </div>            
        </div>
        <div class="card">  
            @if ($sessionData = Session::get('data'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('proxy_master.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                        
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Standard</th>
                                    <th>Division</th>
                                    <th>Absent Teacher</th>
                                    <th>Proxy Teacher</th>
                                    <th>Period</th>
                                    <th>Subject</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>                       
                            @foreach($data['data'] as $key =>$val)                                    
                                <tr>                                
                                    <td>{{$val->proxy_date}}</td>
                                    <td>{{$val->standard_name}}</td>
                                    <td>{{$val->division_name}}</td>
                                    <td>{{$val->teacher_name}}</td>
                                    <td>{{$val->proxy_teacher_name}}</td>
                                    <td>{{$val->period_name}}</td>
                                    <td>{{$val->sub_name}}</td>
                                    <td>
                                        <div class="d-inline">                                            
                                            <a href="{{ route('proxy_master.edit',$val->id)}}" class="btn btn-info btn-outline">
                                                <i class="ti-pencil-alt"></i>
                                            </a>                                                                                        
                                        </div>
                                        <form action="{{ route('proxy_master.destroy', $val->id)}}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                            <button onclick="return confirmDelete();" type="submit" class="btn btn-info btn-outline-danger">    <i class="ti-trash"></i>
                                            </button>
                                        </form>                                    
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
