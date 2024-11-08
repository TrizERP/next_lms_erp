{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Create Content Category</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Create Content Category</li>
                    </ol>
                </nav>
            </div>
            <div class="col-md-3 mb-4 text-md-right">
                <a href="{{ route('lms_content_category.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Content Category</a>
            </div>
        </div>

            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    @if ($sessionData = Session::get('data'))
                    <div class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="card">
                        <div class="table-responsive">
                            <table id="subject_list" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Sr. No.</th>
                                        <th>Content Category</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if(count($data['data']) > 0)
                                    @php $i = 1; @endphp
                                    @foreach($data['data'] as $key => $cc_data)
                                    <tr>
                                        <td>@php echo $i++;@endphp</td>
                                        <td>{{$cc_data['category_name']}}</td>
                                        @if($cc_data['sub_institute_id'] != 0)
                                        <td>
                                            <div>
                                                <a class="btn btn-outline-success" href="{{ route('lms_content_category.edit',['id'=>$cc_data['id']])}}">
                                                    <i class="ti-pencil-alt"></i>
                                                </a>
                                                <form class="d-inline" action="{{ route('lms_content_category.destroy', $cc_data['id'])}}" method="post">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button onclick="return confirmDelete();" type="submit" class="btn btn-outline-danger"><i class="ti-trash"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                        @else
                                            <td> - </td>
                                        @endif
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
