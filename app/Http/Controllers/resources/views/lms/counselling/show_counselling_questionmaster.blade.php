{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Create Counselling Question Bank</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">Counselling</a></li>
                        <li class="breadcrumb-item">{{$data['breadcrum_data']->course_title}}</li>
                        <li class="breadcrumb-item active" aria-current="page">Create Counselling Question Bank</li>
                    </ol>
                </nav>
            </div>
            <div class="col-md-3 mb-4 text-md-right">
                <a href="{{ route('lmsCounsellingQuestion.create',['course_id'=>$data['breadcrum_data']->course_id]) }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Counselling Question</a>
            </div>
        </div>

    <div class="row">
        <div class="white-box">
            <div class="panel-body">
                @if ($sessionData = Session::get('data'))
                <div class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                    <div class="card">
                        <div class="card-body">
                            <table id="subject_list" class="table table-striped table-bordered" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Sr. No.</th>
                                        <th>Course</th>
                                        <th>Question</th>
                                        <th>Question Type</th>
                                        <th>Question Points</th>
                                        <th>Multiple Answer</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if(count($data['data']) > 0)
                                    @php $i = 1;@endphp
                                    @foreach($data['data'] as $key => $quesdata)
                                    <tr>
                                        <td>@php echo $i++;@endphp</td>
                                        <td>{{$quesdata->course_title}}</td>
                                        <td>{{$quesdata->question_title}}</td>
                                        <td>{{ucwords($quesdata->question_type)}}</td>
                                        <td>{{ucwords($quesdata->points)}}</td>
                                        <td>
                                            @if($quesdata->multiple_answer == 1)
                                            Yes
                                            @else
                                            No
                                            @endif
                                        </td>
                                        <td>
                                            @if($quesdata->status == 1)
                                            Show
                                            @else
                                            Hide
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center justify-content-end">
                                                <a class="btn btn-outline-success" href="{{ route('lmsCounsellingQuestion.edit',['id'=>$quesdata->id])}}">
                                                    <i class="ti-pencil-alt"></i>
                                                </a>
                                                <form class="d-inline" action="{{ route('lmsCounsellingQuestion.destroy', $quesdata->id)}}" method="post">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button onclick="return confirmDelete();" type="submit" class="btn btn-outline-danger"><i class="ti-trash"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                    @else
                                        <tr><td colspan="10"><center>No records</center></td></tr>
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
</div>

@include('includes.lmsfooterJs')
@include('includes.footer')
@endsection
