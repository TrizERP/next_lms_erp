@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Create Topic</h4>
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
                <div class="col-lg-3 col-sm-3 col-xs-3">				
                    <a href="{{ route('topic_master.create',['chapter_id'=>$data['chapter_id']]) }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New Topic</a>
                </div>
                <br><br><br>
                <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                    <table id="subject_list" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th>Chapter Name</th>
                                <th>Topic Name</th>
                                <th>Description</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['data'] as $key => $data)
                            <tr>    
                                <td>{{$data->chapter_name}}</td>
                                <td>{{$data->name}}</td> 
                                <td>{{$data->description}}</td>     
                                <td style="display: inline-flex;">
                                    <a href="{{ route('topic_master.edit',['id'=>$data->id,'std_id'=>$data->standard_id])}}"><button type="button" class="btn btn-info btn-outline btn-circle btn m-r-5"><i class="ti-pencil-alt"></i></button></a>                                                                        
                                    
                                    <form action="{{ route('topic_master.destroy', $data->id)}}" method="post">
                                    @csrf
                                    @method('DELETE')
                                    <button onclick="return confirmDelete();" type="submit" class="btn btn-info btn-outline btn-circle btn m-r-5"><i class="ti-trash"></i></button>
									
									<a href="{{ route('chapter_master.addtopic',['id'=>$data->id])}}">
									<button type="button" class="btn btn-info">Add Topic</button>
									</a>                                                                        
                                    </form>                                    
                                </td> 
                            </tr>
                            @endforeach

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
$(document).ready(function () {

	$('#subject_list thead tr').clone(true).appendTo( '#subject_list thead' );
    $('#subject_list thead tr:eq(1) th').each( function (i) {
        var title = $(this).text();
        $(this).html( '<input type="text" size="4" placeholder="Search '+title+'" />' );
 
        $( 'input', this ).on( 'keyup change', function () {
            if ( table.column(i).search() !== this.value ) {
                table
                    .column(i)
                    .search( this.value )
                    .draw();
            }
        });
    });
	    
    $('#subject_list').DataTable({});
});

</script>
@include('includes.footer')
