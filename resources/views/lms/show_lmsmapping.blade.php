{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Create LMS Mappings
                    @if(isset($data['chapter_topic_data']['chapter_topic_name']))
                        <span
                            style="color:#26dad2;"><b>for {{$data['chapter_topic_data']['chapter_topic_name']}}</b></span>
                    @endif
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Create LMS Mappings</li>
                    </ol>
                </nav>
            </div>
            @php
                if(isset($_REQUEST['preload_lms'])){
                    $preload_lms = "preload_lms=preload_lms";
                    $readonly="pointer-events: none";
                }
            @endphp
            <div class="col-md-3 mb-4 text-md-right">
                @if(isset($data['chapter_topic_data']['chapter_topic_id']) && $data['chapter_topic_data']['action'] == 'chapter')
                    <a href="{{ route('lmsmapping.create',['chapter_id'=>$data['chapter_topic_data']['chapter_topic_id'],$preload_lms ?? '']) }}"
                       class="btn btn-info add-new"><i class="fa fa-plus"></i> Add LMS Mapping</a>
                @elseif(isset($data['chapter_topic_data']['chapter_topic_id']) && $data['chapter_topic_data']['action'] == 'topic')
                    <a href="{{ route('lmsmapping.create',['topic_id'=>$data['chapter_topic_data']['chapter_topic_id'],$preload_lms ?? '']) }}"
                       class="btn btn-info add-new"><i class="fa fa-plus"></i> Add LMS Mapping</a>
                @else
                    <a href="{{ route('lmsmapping.create',[$preload_lms ?? '']) }}" class="btn btn-info add-new"><i class="fa fa-plus"></i>
                        Add LMS Mapping</a>
                @endif
            </div>
        </div>

        <div class="row">
            @if ($sessionData = Session::get('data'))
                <div
                    class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
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
                                <th>LMS Mapping Name</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if(count($data['data']) > 0)
                                @php $i = 1;
                                @endphp

                                @foreach($data['data'] as $key => $lmsdata)

                                    <tr>
                                        <td>@php echo $i++;@endphp</td>
                                        <td><h4>{{$lmsdata['name']}}</h4></td>
                                        <td>
                                            <a href="{{ route('lmsmapping.edit',[$lmsdata['id'],$preload_lms ?? '' ])}}"
                                               class="btn btn-outline-success btn-sm"><i class="ti-pencil-alt"></i></a>

                                            @if( !isset($lmsdata['CHILD_ARR']) )
                                                <form action="{{ route('lmsmapping.destroy', $lmsdata['id'])}}"
                                                      method="post" class="btn btn-outline-danger btn-sm">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button onclick="return confirmDelete();" type="submit"
                                                            class="border-0 bg-transparent" style="{{$readonly ?? ''}}"><i class="ti-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @if( isset($lmsdata['CHILD_ARR']) )
                                        @foreach($lmsdata['CHILD_ARR'] as $childkey => $child_data)
                                            <tr>
                                                <td></td>
                                                <td>{{$child_data['name']}}</td>
                                                <td>
                                                    <a href="{{ route('lmsmapping.edit',[$child_data['id'],$preload_lms ?? ''])}}"
                                                       class="btn btn-outline-success btn-sm"><i
                                                            class="ti-pencil-alt"></i></a>

                                                    <form action="{{ route('lmsmapping.destroy', $child_data['id'])}}"
                                                          method="post" class="btn btn-outline-danger btn-sm">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button onclick="return confirmDelete();" type="submit"
                                                                class="border-0 bg-transparent" style="{{$readonly ?? ''}}"><i class="ti-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="10">
                                        <center>No records</center>
                                    </td>
                                </tr>
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
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
// $(document).ready(function () {

// 	$('#subject_list thead tr').clone(true).appendTo( '#subject_list thead' );
//     $('#subject_list thead tr:eq(1) th').each( function (i) {
//         var title = $(this).text();
//         $(this).html( '<input type="text" size="4" placeholder="Search '+title+'" />' );

//         $( 'input', this ).on( 'keyup change', function () {
//             if ( table.column(i).search() !== this.value ) {
//                 table
//                     .column(i)
//                     .search( this.value )
//                     .draw();
//             }
//         });
//     });

//     $('#subject_list').DataTable({});
// });

</script>
<script>
    $( document ).ready(function() {
        $("#standard").change(function(){
            var std_id = $("#standard").val();
            var path = "{{ route('ajax_StandardwiseSubject') }}";
            $('#subject').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
            $.ajax({
                url: path, data: 'std_id=' + std_id, success: function (result) {
                    for (var i = 0; i < result.length; i++) {
                        $("#subject").append($("<option></option>").val(result[i]['subject_id']).html(result[i]['display_name']));
                    }
                }
            });
        })
    });
</script>
@include('includes.footer')
@endsection
