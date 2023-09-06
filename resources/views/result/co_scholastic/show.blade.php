@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="card">
            @if(!empty($data['message']))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('co_scholastic.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                        
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Term Name</th>
                                    <th>Parent Name</th>
                                    <th>Title</th>
                                    <th>Standard</th>                                    
                                    <th>Sort Order</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $key => $data)

                                <tr>    
                                    <td>{{$data->term_name}}</td>
                                    <td>{{$data->parent_name}}</td>
                                    <td>{{$data->title}}</td>
                                    <td>{{$data->standard}}</td>                                    
                                    <td>{{$data->sort_order}}</td>

                                    <td>
                                        <div class="d-inline">                                            
                                            <a href="{{ route('co_scholastic.edit',$data->id)}}" class="btn btn-outline-success ">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>
                                        <form action="{{ route('co_scholastic.destroy', $data->id)}}" method="post" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger" onclick="return confirmDelete();" type="submit"><i class="ti-trash"></i></button>
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
