     @include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Bank List</h4>
            </div>            
        </div>
            
        <div class="card">               
                @if ($sessionData = Session::get('data'))
                <div class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">              
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
                @endif

                <div class="col-lg-12 col-sm-3 col-xs-3">                                        
                    <a href="{{ route('bank_master.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Bank</a>
                </div> 
                <br><br>
                               
                <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                    <div class="table-responsive">
                    <table id="subject_list" class="table">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th>Bank Name</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(count($data['bank_data']) > 0)
                            @php $i = 1;@endphp
                            @foreach($data['bank_data'] as $key => $chdata)
                            <tr>    
                                <td>@php echo $i++;@endphp</td>
                                <td>{{$chdata['bank_name']}}</td>
                                <td>
                                    <div class="d-inline">
                                    <a href="{{ route('bank_master.edit',$chdata['id'])}}" class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>                                                                        
                                    </div>
                                    <form class="d-inline" action="{{ route('bank_master.destroy', $chdata['id'])}}" method="post">
                                    @csrf
                                    @method('DELETE')
                                    <button onclick="return confirmDelete();" type="submit" class="btn btn-info btn-outline-danger"><i class="ti-trash"></i></button>                                                                                                                                          
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
