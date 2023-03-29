@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">  
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Send Sms Parents</h4>
            </div>
        </div>          
        <div class="card">
            @if(!empty($data['message']))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('manage_sms_api.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                          
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>URL</th>
                                    <th>Parameter</th>
                                    <th>Mobile Variable</th>
                                    <th>Text Variable</th>
                                    <th>Last Variable</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $key => $data)
                                <tr>    
                                    <td>{{$data->url}}</td>
                                    <td>{{$data->pram}}</td>
                                    <td>{{$data->mobile_var}}</td>
                                    <td>{{$data->text_var}}</td>                 
                                    <td>{{$data->last_var}}</td>                 
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ route('manage_sms_api.edit',$data->id)}}" class="btn btn-info btn-outline">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>
                                        <form class="d-inline" action="{{ route('manage_sms_api.destroy', $data->id)}}" method="post">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-info btn-outline-danger" onclick="return confirmDelete();" type="submit"><i class="ti-trash"></i></button>
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
    $('#example').DataTable({
        
    });
});

</script>
@include('includes.footer')
