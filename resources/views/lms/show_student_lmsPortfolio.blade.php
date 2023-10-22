{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Create Portfolio</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item">Portfolio</li>
                        <li class="breadcrumb-item active" aria-current="page">Create New Portfolio</li>
                    </ol>
                </nav>
            </div>
            <div class="col-md-6 mb-4 text-md-right">
                <a href="{{ route('lmsPortfolio.create',['action'=>'personal']) }}" class="btn btn-info add-new"><i
                        class="fa fa-plus"></i>Add Portfolio</a>

                <a href="{{ route('lmsPortfolio.create',['action'=>'coursewise']) }}" class="btn btn-info add-new"><i
                        class="fa fa-plus"></i>Add From Course</a>

                <a href="{{ route('lmsDoubt.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i>Add
                    Doubts</a>

                <a target="_blank" href="{{ route('lmsPortfolio.show','1') }}" class="btn btn-info add-new">View All</a>
            </div>

        </div>

        <div class="card">
            <div class="card-body">
                @if ($sessionData = Session::get('data'))
                    <div
                        class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                @endif
                <div class="row">
                    <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                        <div class="table-responsive">
                            <table id="subject_list" class="table table-striped table-bordered">
                                <thead>
                                <tr>
                                        <th>Sr. No.</th>
                                        <th>Title</th>
                                        <th>Description</th>
                                        <th>Type</th>
                                        <th>Attached File</th>
                                        <th>Created On</th>
                                    <th>Feedback</th>
                                    <th>Action</th>

                                </tr>
                                </thead>
                                <tbody>
                                @if(count($data['data']) > 0)
                                    @php $i = 1;
                                    @endphp
                                    @foreach($data['data'] as $key => $portfolio)
                                        <tr>
                                            <td>@php echo $i++;@endphp</td>
                                            <td>{{$portfolio['title']}}</td>
                                            <td>{{$portfolio['description']}}</td>
                                            <td>{{$portfolio['type']}}</td>
                                            <td>@if($portfolio['file_name'] != ""){{$portfolio['file_name']}}@else
                                                    - @endif</td>
                                            <td>{{$portfolio['created_at']}}</td>
                                            <td>{{$portfolio['feedback']}} - {{$portfolio['teacher_name']}}</td>
                                            <td>
                                                <a class="btn btn-outline-success btn-sm"
                                                   href="{{ route('lmsPortfolio.edit',['lmsPortfolio'=>$portfolio['id']]) }}">
                                                    <i class="ti-pencil-alt"></i>
                                                </a>
                                                <form action="{{ route('lmsPortfolio.destroy', $portfolio['id']) }}"
                                                      method="post" class="btn btn-outline-danger btn-sm">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button onclick="return confirmDelete();" type="submit"
                                                            class="border-0 bg-transparent"><i class="ti-trash"></i>
                                                    </button>
                                                </form>

                                            </td>

                                        </tr>
                                    @endforeach
                                    @else
                                        <tr><td colspan="20"><center>No records</center></td></tr>
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@include('includes.lmsfooterJs')
@include('includes.footer')
@endsection
