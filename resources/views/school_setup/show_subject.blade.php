@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Create Subject</h4>
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
                    <a href="{{ route('subject_master.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="subject_list" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Subject Name</th>
                                    <th>Subject Code</th>
                                    <th>Subject Type</th>
                                    <th>Short Name</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $key => $data)
                                <tr>    
                                    <td>{{$data->subject_name}}</td>
                                    <td>{{$data->subject_code}}</td> 
                                    <td>@if($data->subject_type != "")
                                        {{$data->subject_type}}
                                        @else
                                        {{'-'}}
                                        @endif
                                    </td>                                                                 
                                    <td>{{$data->short_name}}</td>     
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ route('subject_master.edit',$data->id)}}" class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>                                                                        
                                        </div>                                        
                                        <form class="d-inline" action="{{ route('subject_master.destroy', $data->id)}}" method="post">
                                        @csrf
                                        @method('DELETE')
                                            <button onclick="return confirmDelete();" type="submit" class="btn btn-info btn-outline-danger"><i class="ti-trash"></i></button>
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
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function () {
    $('#subject_list').DataTable({});
});

</script>
@include('includes.footer')
