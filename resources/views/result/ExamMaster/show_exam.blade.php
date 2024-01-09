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
                    <div class="col-lg-3 col-sm-3 col-xs-3">
                        <a href="{{ route('exam_master.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                    </div>
                  
                    <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">                        
                        <table id="example" class="table table-striped" >
                            <thead>
                                <tr>
                                    <th>SrNo.</th>
                                    <th>Exam Type</th>  
                                    <th>Standard</th>                                                                      
                                    <th>Term</th>
                                    <th>Weightage</th>                                    
                                    <th>Sort Order</th>
                                    <th class="text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $key => $data)
                                <tr>    
                                    <td>{{$data->SrNo}}</td>
                                    <td>{{$data->ExamTitle}}</td>                                    
                                    <td>{{$data->std_name}}</td>
                                    <td>{{$data->term}}</td>                                    
                                    <td>{{$data->weightage}}</td>
                                    <td>{{$data->SortOrder}}</td>                 
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ route('exam_master.edit',$data->Id)}}" class="btn btn-info btn-outline">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>
                                        @if($data->total_count == 0)
                                        <form class="d-inline" action="{{ route('exam_master.destroy', $data->Id)}}" method="post">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-info btn-outline-danger" onclick="return confirmDelete();" type="submit"><i class="ti-trash"></i></button>
                                        </form>
                                        @endif
                                    </td> 
                                </tr>
                                @endforeach

                            </tbody>

                        </table>

                    </div>

                </div>
            </div>       
</div>

@include('includes.footerJs')
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
    $(document).ready(function () {
        var table = $('#example').DataTable({
        });
    });
</script>
@include('includes.footer')
@endsection
