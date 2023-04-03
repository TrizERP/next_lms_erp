@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Create Batch</h4>
            </div>
        </div>
        <div class="card">
            <div class="panel-body">
                @if ($sessionData = Session::get('data'))
                <div class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('batch_master.create') }}" class="btn btn-info add-new mb-4"><i
                            class="fa fa-plus"></i> Add New</a>
                </div>
                <br><br><br>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <table id="batch_list" class="table table-striped table-bordered">
                        <thead>
                        <tr>
                            <th>Title</th>
                            <th>Standard</th>
                            <th>Division</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($data['data'] as $key => $data)
                            <tr>
                                <td>{{$data->titles}}</td>
                                <td>{{$data->standard_name}}</td>
                                <td>{{$data->division_name}}</td>
                                <td>
                                    <div class="d-inline">
                                        <a href="{{ route('batch_master.edit',['batch_master'=>$data->standard_id,'div_id'=>$data->division_id])}}"
                                           class="btn btn-outline-success"><i class="ti-pencil-alt"></i></a>
                                    </div>
                                    <form
                                        action="{{ route('batch_master.destroy', ['batch_master'=>$data->standard_id,'div_id'=>$data->division_id])}}"
                                        class="d-inline" method="post">
                                        @csrf
                                        @method('DELETE')
                                        <button onclick="return confirmDelete();" type="submit"
                                                class="btn btn-outline-danger"><i class="ti-trash"></i></button>
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

@include('includes.footerJs')
<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<script>
$(document).ready(function () {
    $('#batch_list').DataTable({});
    $('#batch_list').removeClass('table-hover');
});

</script>
@include('includes.footer')
