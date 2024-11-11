{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Create LO Indicator</h4>
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
                    <a href="{{ route('lo_indicator.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add LO Indicator</a>
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
                                <th>Chapter</th>
                                <th>LO Master Title</th>
                                <th>LO Indicator</th>
                                <th>Availability</th>
                                <th>Sort Order</th>
                                <th>Show/Hide</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(count($data['data']) > 0)
                            @php $i = 1;@endphp
                            @foreach($data['data'] as $key => $lodata)
                            <tr>
                                <td>@php echo $i++;@endphp</td>
                                <td>{{$lodata->grade_name}}</td>
                                <td>{{$lodata->standard_name}}</td>
                                <td>{{$lodata->subject_name}}</td>
                                <td>{{$lodata->chapter_name}}</td>
                                <td>{{$lodata->lomaster_title}}</td>
                                <td>{{$lodata->indicator}}</td>
                                <td>
                                    @if($lodata->availability == 1)
                                    Available
                                    @else
                                    Not Available
                                    @endif
                                </td>
                                <td>{{$lodata->sort_order}}</td>
                                <td>
                                    @if($lodata->show_hide == 1)
                                    Show
                                    @else
                                    Hide
                                    @endif
                                </td>
                                <td style="display: inline-flex;">
                                    <a href="{{ route('lo_indicator.edit',['id'=>$lodata->id])}}"><button type="button" class="btn btn-info btn-outline btn-circle btn m-r-5"><i class="ti-pencil-alt"></i></button></a>

                                    <form action="{{ route('lo_indicator.destroy', $lodata->id)}}" method="post">
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
