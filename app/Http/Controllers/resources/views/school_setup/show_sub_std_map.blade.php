@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title mb-20">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Subject Standard Mapping</h4>
            </div>            
        </div>
        
        <div class="card">    
            @if ($sessionData = Session::get('data'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3 mb-30">
                    <a href="{{ route('sub_std_map.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>            
                <div class="col-lg-12 col-sm-12 col-xs-12 mb-30">
                    <div class="table-responsive">
                        <table id="list" class="table table-striped" width="100%">
                            <thead>
                                <tr>
                                    <th>Subject Name</th>
                                    <th>Standard Name</th>
                                    <th>Display Name</th>
                                    <th>Allow Grades</th>
                                    <th>Optional Subject</th>
                                    <th>Sort Order</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $key => $data)
                                <tr>    
                                    <td>{{$data->subject_name}} ({{$data->subject_code}})</td>
                                    <td>{{$data->name}}</td>                 
                                    <td>{{$data->display_name}}</td>                 
                                    <td>
                                    @if($data->allow_grades != "")
                                        {{$data->allow_grades}}
                                    @else
                                        {{"-"}}
                                    @endif
                                    </td>     
                                    <td>
                                    @if($data->elective_subject != "")
                                        {{$data->elective_subject}}
                                    @else
                                        {{"-"}}
                                    @endif
                                    </td>     
                                    <td>
                                    @if($data->sort_order != "")
                                        {{$data->sort_order}}
                                    @else
                                        {{"-"}}
                                    @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('sub_std_map.edit',$data->id)}}" class="btn btn-outline-success"><i class="ti-pencil-alt"></i></a>                                                                        
                                        
                                        <form action="{{ route('sub_std_map.destroy', $data->id)}}" method="post" class="d-inline" onsubmit="return delete_sub_std_mapping({{$data->id}});">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger"><i class="ti-trash"></i></button>
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
<script>
$(document).ready(function () {   

    var table = $('#list').DataTable({
        select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Subject Standard Mapping',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Subject Standard Mapping'},
                {extend: 'excel', text: ' EXCEL', title: 'Subject Standard Mapping'},
                {extend: 'print', text: ' PRINT', title: 'Subject Standard Mapping'},
                'pageLength'
            ],
        });

    $('#list thead tr').clone(true).appendTo('#list thead');
        $('#list thead tr:eq(1) th').each(function(i) {
            var title = $(this).text();
            $(this).html('<input type="text" size="5" style="color:black !important;" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function() {
                if (table.column(i).search() !== this.value) {
                    table
                        .column(i)
                        .search(this.value)
                        .draw();
                }
            });
    });
        
});

function delete_sub_std_mapping(id)
{
    if(confirm('Are you sure?'))
    {            
        var error = 1;
        var path = "{{ route('ajax_subStdMappingDependencies') }}";
        $.ajax({
            url: path,
            data: "id="+id,
            async: false,
            success: function(result){  

                if(result > 0)
                {
                    alert("You cannot delete Mapping.Mapping is having dependencies in Other Module");
                    error = 1;
                }
                else{
                    error = 0;
                } 
            },
            failure:function(er)
            {
                alert('error'+er);
                error = 1;
            }
        });            
    }
    else
    {
        error = 1;
    }

    if(error == 1)
    {
        return false;             
    }else{
        return true;             
    }   
}

</script>
@include('includes.footer')
