@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-md-3 d-flex">
               <h4 class="page-title">
                    Add Cast
                </h4>
            </div>
        </div>   
            <div class="card">
                @if(!empty($data['message']))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif
                <!-- table start -->
                @if(isset($data['editData']) && $data['editData']->id)
                <form action="{{route('add_cast.update',$data['editData']->id)}}" method="post">
                {{ method_field("PUT") }}
                @else
                <form action="{{route('add_cast.store')}}" method="post">
                @endif
                @csrf 
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="title">Cast</label>
                        <input type="text" class="form-control" name="title" id="title" required @if(isset($data['editData']) && $data['editData']->id) value="{{$data['editData']->title}}" @endif>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="sort_order">Sort Order</label>
                        @if(isset($data['editData']) && $data['editData']->id)
                        <input type="number" class="form-control" name="sort_order" id="sort_order" value="{{$data['editData']->sort_order}}" required>
                        
                        @else
                        <input type="number" class="form-control" name="sort_order" id="sort_order" value="{{ ($data['lastData']->sort_order) ? ($data['lastData']->sort_order + 1) : 1 }}" required>
                        @endif
                    </div>
                    <div class="col-md-12">
                        <center>
                            <input type="submit" value="Add" name="add" class="btn btn-primary">
                        </center>
                    </div>
                </div>
                </form>
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
