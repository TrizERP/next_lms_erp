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
                    <a href="{{ route('result_book_master.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                        
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Line 1</th>
                                    <th>Line 2</th>
                                    <th>Line 3</th>
                                    <th>Line 4</th>
                                    <th>Grade</th>
                                    <th>Standard</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $key => $data)
           
                                <tr>    
                                    <td>{!!$data['line1']!!}</td>
                                    <td>{!!$data['line2']!!}</td>
                                    <td>{!!$data['line3']!!}</td>
                                    <td>{!!$data['line4']!!}</td>
                                    <td>{{$data['grade_name']}}</td>
                                    <td>{{$data['standard_name']}}</td>
                                    <td>{{$data['status']}}</td>
                                    <td>
                                        <div class="d-inline">                                        
                                            <a href="{{ route('result_book_master.edit',$data['id'])}}" class="btn btn-outline-success">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>
                                        <form action="{{ route('result_book_master.destroy', $data['id'])}}" method="post" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger" onclick="return confirmDelete();" type="submit">
                                                <i class="ti-trash"></i>
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
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable({
        
    });
});

</script>
@include('includes.footer')
