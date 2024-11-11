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
                    {{-- <a href="{{ route('lo_master.create') }}" class="btn btn-info add-new"><i
                        class="fa fa-plus"></i> Add New</a> --}}
                </div>
            </div>
            <form action="{{ route('lo_marks_greport.store') }}" enctype="multipart/form-data" method="post">
                @csrf
                <input type="hidden" name="medium" value="{{ $data['medium'] }}">
                <input type="hidden" name="std" value="{{ $data['std'] }}">
                <input type="hidden" name="div" value="{{ $data['div'] }}">
                <div class="row">
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                <tr>
                                    <th>Sr No.</th>
                                    <th>Roll No</th>
                                    <th>Name</th>
                                    <th>Standard</th>
                                    <th>View</th>
                                </tr>
                                </thead>
                                <tbody>
                                    @php
                                    $i=1;
                                    @endphp
                                    @foreach($data['stud'] as $key => $datas)
                                    <tr>
                                        <td>{{$i}}</td>
                                        <td>{{$datas->roll_no}}</td>
                                        <td>{{$datas->name}}</td>
                                        <td>{{$datas->STD}}</td>
                                        <td><a target="_blank" href="{{ route('lo_marks_greport.store', ['id' => $datas->student_id]) }}">VIEW</a></td>
                                    </tr>
                                    @php
                                    $i++;
                                    @endphp
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
    $(document).ready(function () {
        var table = $('#example').DataTable({});
    });

</script>
@include('includes.footer')
@endsection
