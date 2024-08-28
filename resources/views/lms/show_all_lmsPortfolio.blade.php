{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">View All Portfolio</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item">Portfolio</li>
                        <li class="breadcrumb-item active" aria-current="page">View All Portfolio</li>
                    </ol>
                </nav>
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
                            <form action="{{ route('ajax_lmsPortfolio_feedback') }}" method="post">
                                @csrf
                                <table id="subject_list" class="table table-striped table-bordered">
                                    <thead>
                                    <tr>
                                        <th>Sr. No.</th>
                                        <th>Student Name</th>
                                        <th>Standard</th>
                                        <th>Title</th>
                                        <th>Description</th>
                                        <th>Type</th>
                                        <th>Attached File</th>
                                        <th>Created On</th>
                                        <th>Feedback</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(count($data['data']) > 0)
                                        @php $i = 1;
                                        @endphp
                                        @foreach($data['data'] as $key => $portfolio)
                                            <tr>
                                                <td>@php echo $i++;@endphp</td>
                                                <td>{{$portfolio['student_name']}}</td>
                                                <td>{{$portfolio['standard_name']}}</td>
                                                <td>{{$portfolio['title']}}</td>
                                                <td data-toggle="popover"
                                                    data-content="{{$portfolio['description']}}">{{substr($portfolio['description'],0,30)}}
                                                    <span
                                                        style="font-size: 28px;color: black;font-weight: bolder;">...</span>
                                                </td>
                                                <td>{{$portfolio['type']}}</td>
                                                <td>@if($portfolio['file_name'] != "")<a href="{{ Storage::disk('digitalocean')->url('public/lms_portfolio/'.$portfolio['file_name'])}}" target="_blank">{{$portfolio['file_name']}}</a>@else
                                                        - @endif</td>
                                                <td>{{$portfolio['created_at']}}</td>
                                                <td>
                                                    @if($portfolio['type'] == "coursewise")
                                                        <textarea class="form-control"
                                                                  name="feedback[{{$portfolio['id']}}]">{{$portfolio['feedback']}}</textarea>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="20">
                                                <center>No records</center>
                                            </td>
                                        </tr>
                                    @endif
                                    </tbody>
                                </table>
                                @if(count($data['data']) > 0)
                                    <center>
                                        <input type="submit" name="submit" value="Save" class="btn btn-success">
                                    </center>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@include('includes.lmsfooterJs')
<script>
    $(document).ready(function () {
        $('[data-toggle="popover"]').popover({title: "", html: true});
        $('[data-toggle="popover"]').on('click', function (e) {
            $('[data-toggle="popover"]').not(this).popover('hide');
        });
    });
</script>
@include('includes.footer')
@endsection
