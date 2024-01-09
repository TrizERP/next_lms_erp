{{-- @include('includes.headcss') @include('includes.header') @include('includes.sideNavigation') --}} 
@extends('layout')
@section('container')
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
                    <a href="{{ route('working_day_master.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                   <div class="table-responsive">                       
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Term Name</th>
                                    <th>Standard</th>
                                    <th>Total Working Day</th>
                                    <th class="text-align">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $key => $data1)

                                <tr>    

                                    <td>{{$data1['term_name']}}</td>
                                    <td>{{$data1['standard_name']}}</td>
                                    <td>{{$data1['total_working_day']}}</td>
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ route('working_day_master.edit',$data1['id'])}}" class="btn btn-outline-success">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>
                                        <form action="{{ route('working_day_master.destroy', $data1['id'])}}" method="post" class="d-inline">
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
@endsection