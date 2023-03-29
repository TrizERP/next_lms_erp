@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
    		<div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Floor Room</h4> </div>
            </div>        
        <div class="card">
            <div class="panel-body">
                @if(!empty($data['message']))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route("add_hostel_room_master.create") }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Floor Room</a>
                </div>
                <br><br><br>
                <div class="table-responsive">
                    <table id="example" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Sr.No.</th>
                                <th>Room Title</th>
                                <th>Floor Title</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        @php
                        $j=1;
                        @endphp    
                            @foreach($data['data'] as $key => $data)
                            <tr>    
                                <td>{{$j}}</td>
                                <td>{{$data->room_name}}</td>
                                <td>{{$data->floor_id}}</td>
                                <td>
                                    <div class="d-inline">
                                        <a href="{{ route('add_hostel_room_master.edit',$data->id)}}" class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>
                                    </div>
                                    <form class="d-inline" action="{{ route('add_hostel_room_master.destroy', $data->id)}}" method="post">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-info btn-outline-danger" type="submit"><i class="ti-trash"></i></button>
                                    </form>
                            </tr>
                        @php
                        $j++;
                        @endphp    
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
    $('#example').DataTable();
});
</script>
@include('includes.footer')
