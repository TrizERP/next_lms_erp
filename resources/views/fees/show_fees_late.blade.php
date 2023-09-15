@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Late Master</h4>
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
                <div class="col-lg-3 col-sm-3 col-xs-3 mb-30">
                    <a href="{{ route('fees_late_master.create') }}" class="btn btn-info add-new">
                        <i class="fa fa-plus"></i> Add New Fees Late</a>
                </div>                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Id</th>
                                    <th>{{ App\Helpers\get_string('standard','request')}}</th>
                                    <th>Late Fees Date</th>
                                    <th>Created By</th>
                                    <th>Created On</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                            $j=1;
                            @endphp
                            @if(isset($data['data']))
                                @foreach($data['data'] as $key => $data)
                                <tr>    
                                    <td>{{$j}}</td>
                                    <td>{{$data->standard}}</td>
                                    <td>{{date('d-m-Y',strtotime($data->late_date))}}</td>
                                    <td>{{$data->user}}</td>  
                                    <td>{{date('d-m-Y h:i:s',strtotime($data->created_on))}}</td>
                                    <td>
                                        <div class="d-inline">                                            
                                            <a href="{{ route('fees_late_master.edit',$data->id)}}" class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>
                                        </div>
                                        <form action="{{ route('fees_late_master.destroy', $data->id)}}" method="post" class="d-inline">
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
