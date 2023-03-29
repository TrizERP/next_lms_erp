@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">      
        <div class="card">
            <div class="panel-body">
                @if(!empty($data['message']))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route("add_place_master.create") }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Place Master</a>
                </div>
                <br><br><br>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <table id="example" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['data'] as $key => $data)
                            <tr>    
                                <td>{{$data->title}}</td>
                                <td>{{$data->description}}</td>
                                <td>
                                    <div class="d-inline">
                                        <a href="{{ route('add_place_master.edit',$data->id)}}" class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>
                                    </div>
                                    <form action="{{ route('add_place_master.destroy', $data->id)}}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-info btn-outline-danger" type="submit"><i class="ti-trash"></i></button>
                                    </form>
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
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable({
        "scrollX": true
    });
});

</script>
@include('includes.footer')
