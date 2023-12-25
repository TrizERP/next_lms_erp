@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Result Activity Master</h4>
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
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('result_activity_master.create') }}" class="btn btn-info add-new">
                        <i class="fa fa-plus"></i> Add Result Activity Master
                    </a>
                </div>                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Id</th>
                                    <th>Title</th>
                                    <th>Skill Name</th>
                                    <th>Sort Order</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                            $j=1;
                            @endphp
                            @if(isset($data['result_activity_masters']))
                                @foreach($data['result_activity_masters'] as $key => $result_activity_master)
                                <tr>    
                                    <td>{{$j}}</td>
                                    <td>{{$result_activity_master->title}}</td>
                                    <td>{{$result_activity_master->result_main_title}} ({{ $result_activity_master->result_title }})</td>
                                    <td>{{$result_activity_master->sort_order}}</td>
                                    <td>
                                        <div class="d-inline">                                            
                                            <a href="{{ route('result_activity_master.edit',$result_activity_master->id)}}" class="btn btn-info btn-outline">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>
                                        <form action="{{ route('result_activity_master.destroy', $result_activity_master->id)}}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                            <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger"><i class="ti-trash"></i></button>
                                        </form>
                                    </td>  
                                </tr>
                            @php
                            $j++;
                            @endphp
                                @endforeach
                            @endif
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
    $('#example').DataTable();
});

</script>
@include('includes.footer')
