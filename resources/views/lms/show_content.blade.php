{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Create Content</h4>
            </div>
        </div>

    <div class="row" style=" margin-top: 25px;">
        <div class="white-box">
            <div class="panel-body">
                @if ($sessionData = Session::get('data'))
                <div class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
                @endif

                <div class="col-lg-12 col-sm-3 col-xs-3">
                    <a href="{{ route('content_master.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Content</a>
                </div>
                <br><br>

                <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                    <table id="subject_list" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th>Academic Section</th>
                                <th>Standard</th>
                                <th>Subject</th>
                                <th>Chapter Name</th>
                                <th>Topic Name</th>
                                <th>Sub Topic Name</th>
                                <th>Content Title</th>
                                <th>Content Category</th>
                                <th>Content Link</th>
                                <th>Show/Hide</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(count($data['data']) > 0)
                            @php $i = 1;@endphp
                            @foreach($data['data'] as $key => $chdata)
                            <tr>
                                <td>@php echo $i++;@endphp</td>
                                <td>{{$chdata->grade_name}}</td>
                                <td>{{$chdata->standard_name}}</td>
                                <td>{{$chdata->subject_name}}</td>
                                <td>{{$chdata->chapter_name}}</td>
                                <td>@if(isset($chdata->topic_name)){{$chdata->topic_name}}@else - @endif</td>
                                <td>@if(isset($chdata->sub_topic_name)){{$chdata->sub_topic_name}}@else - @endif</td>
                                <td>{{$chdata->title}}</td>
                                <td>{{$chdata->content_category}}</td>
                                <td><a target="_blank" href="{{ Storage::disk('digitalocean')->url('public'.$chdata->file_folder.'/'.$chdata->filename)}}">{{$chdata->filename}}</a></td>
                                <td>
                                    @if($chdata->show_hide == 1)
                                    Show
                                    @else
                                    Hide
                                    @endif
                                </td>
                                <td style="display: inline-flex;">
                                    <a href="{{ route('content_master.edit',['id'=>$chdata->id,'std_id'=>$chdata->standard_id])}}"><button type="button" class="btn btn-info btn-outline btn-circle btn m-r-5"><i class="ti-pencil-alt"></i></button></a>

                                    <form action="{{ route('content_master.destroy', $chdata->id)}}" method="post">
                                    @csrf
                                    @method('DELETE')
                                    <button onclick="return confirmDelete();" type="submit" class="btn btn-info btn-outline btn-circle btn m-r-5"><i class="ti-trash"></i></button>
                                    </form>
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

@include('includes.footerJs')
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
