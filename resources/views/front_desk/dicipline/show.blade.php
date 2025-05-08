{{-- @include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation') --}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">       
            <div class="card">
                @if(!empty($data['message']))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    
                </div>
                @endif
                @if(session()->get('sub_institute_id') == 195)
                <div class="row mb-2">
                    <div class="col-md-12 text-right">
                        <a href="{{ route('dicipline-Master.index') }}" class="btn btn-primary" target="_blank">Add Master</a>
                    </div>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('dicipline.create') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("GET") }}
                        {{csrf_field()}}

                        <div class="row">
                            {{ App\Helpers\SearchChain('4','single','grade,std,div') }}

                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
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
