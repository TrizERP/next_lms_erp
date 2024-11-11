@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-md-3 d-flex">
               <h4 class="page-title">
                    <a href="{{ route('add_cast.create') }}" class="btn btn-info add-new">Add Cast</a>
                </h4>
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
                <!-- table start -->
                <div class="table-responsive">
                    <table id="example" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Sr No.</th>
                                <th>Cast</th>
                                <th>Sort Order</th>
                                <th class="text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['castData'] as $key=>$value)
                            <tr>
                                <td>{{$key+1}}</td>
                                <th>{{$value->title}}</th>
                                <th>{{$value->sort_order}}</th>
                                <td>
                                    <div class="d-inline">
                                        <a href="{{ route('add_cast.edit',$value->id)}}" class="btn btn-info btn-outline">
                                            <i class="ti-pencil-alt"></i>
                                        </a>
                                    </div>
                                    <form action="{{ route('add_cast.destroy', $value->id)}}" method="post" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                        <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger">
                                            <i class="ti-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- table  end  -->
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
@endsection
