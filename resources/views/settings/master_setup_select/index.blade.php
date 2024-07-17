@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Master Setup Selects</h4>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-3 col-sm-3 col-xs-3 mb-20">
                <a href="{{ route('master_setup.create') }}" class="btn btn-info add-new">
                    <i class="fa fa-plus"></i> Add New
                </a>
            </div>
        </div>
            
            <div class="card">
                <div class="row">
                    <div class="col-md-12">
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
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <table id="example" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Sr No.</th>
                                            <th>Type</th>
                                            <th>Field Name</th>
                                            <th>Field Values</th>
                                            <th class="text-left">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($data['masterData'] as $key=>$value)
                                        <tr>
                                            <td>{{$key+1}}</td>
                                            <td>{{$value->type}}</td>
                                            <td>{{$value->fieldname}}</td>
                                            <td>{{$value->allvalues}}</td>
                                            <td class="d-flex">
                                                <a href="{{ route('master_setup.edit',$value->type)}}"
                                                class="btn btn-outline-success btn-sm mx-1"><i class="mdi mdi-pencil-outline"></i></a>
                                                <form action="{{ route('master_setup.destroy',$value->type)}}" method="post">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button onclick="return confirmDelete();" type="submit"
                                                            class="btn btn-outline-danger btn-sm mx-1" style="{{$readonly ?? ''}}">
                                                        <i class="mdi mdi-delete-outline"></i></button>
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
</div>

@include('includes.footerJs')
@include('includes.footer')
@endsection