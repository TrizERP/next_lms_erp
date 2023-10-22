{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Create Leaderboard Master</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Create Leaderboard Master</li>
                    </ol>
                </nav>
            </div>
            <div class="col-md-3 mb-4 text-md-right">
                <a href="{{ route('lb_master.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Master</a>
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
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                            <table id="subject_list" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Sr. No.</th>
                                        <th>Academic Section</th>
                                        <th>Standard</th>
                                        <th>Module Name</th>
                                        <th>Points</th>
                                        <th>Icon</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    @if(isset($data['data']) && count($data['data']) > 0)
                                    @php $i = 1; @endphp
                                    @foreach($data['data'] as $key => $lbdata)
                                    <tr>
                                        <td>@php echo $i++;@endphp</td>
                                        <td>{{$lbdata->title}}</td>
                                        <td>{{$lbdata->name}}</td>
                                        <td>{{$lbdata->module_name}}</td>
                                        <td>{{$lbdata->points}}</td>
                                        <td class="fa">&#{{$lbdata->icon}};</td>
                                        <td>{{ucwords($lbdata->description)}}</td>
                                        <td>
                                            @if($lbdata->status == 1)
                                            Show
                                            @else
                                            Hide
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($lbdata->id))
                                            <div class="d-flex align-items-center justify-content-end">
                                                <a class="btn btn-outline-success" href="{{ route('lb_master.edit',$lbdata->id)}}">
                                                    <i class="ti-pencil-alt"></i>
                                                </a>
                                                <form class="d-inline" action="{{ route('lb_master.destroy', $lbdata->id)}}" method="post">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button onclick="return confirmDelete();" type="submit" class="btn btn-outline-danger"><i class="ti-trash"></i></button>
                                                </form>
                                            </div>
                                            @endif
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
</div>

@include('includes.lmsfooterJs')
<script>
$( document ).ready(function() {
    $("#standard").change(function(){
        var std_id = $("#standard").val();
        var path = "{{ route('ajax_StandardwiseSubject') }}";
        $('#subject').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
        $.ajax({url: path,data:'std_id='+std_id, success: function(result){
            for(var i=0;i < result.length;i++){
                $("#subject").append($("<option></option>").val(result[i]['subject_id']).html(result[i]['display_name']));
            }
        }
        });
    })
});
</script>
@include('includes.footer')
@endsection
