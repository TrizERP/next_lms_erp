@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Result Activity Master</h4>
            </div>
        </div>
        <div class="card">          
            @if ($sessionData = Session::get('data'))
                @if($sessionData['status_code'] == 1)
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
                @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('result_activity_master.create') }}" class="btn btn-info add-new">
                        <i class="fa fa-plus"></i> Add Result Activity Master
                    </a>
                </div>                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr No.</th>
                                    <th>Standard</th>
                                    <th>Title</th>
                                    <th>Skill Name</th>
                                    <th>Sub Activities</th>
                                    <th>Sort Order</th>
                                    <th class="text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                            $j=1;
                            @endphp
                            @if(isset($data['result_activity_masters']))
                                @foreach($data['result_activity_masters'] as $key => $result_activity_master)
                                <tr>    
                                    <td>{{$j}}</td>
                                    <td>{{$result_activity_master->standard}}</td>
                                    <td>{{$result_activity_master->title}}</td>
                                    <td>{{$result_activity_master->result_main_title}} ({{ $result_activity_master->result_title }})</td>
                                    <td>@php 
                                        if(isset($result_activity_master->sub_activity)){
                                            $explodVal = explode('|||',$result_activity_master->sub_activity);
                                            foreach($explodVal as $k=>$val){
                                                echo ($k+1).')&nbsp;'.$val.'<br>';
                                            }
                                        }else{
                                            echo "-";
                                        }
                                    @endphp</td>
                                    <td>{{$result_activity_master->sort_order}}</td>
                                    <td>
                                        <div class="d-inline">                                            
                                            <a href="{{ route('result_activity_master.edit',$result_activity_master->id)}}" class="btn btn-info btn-outline">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>
                                        <form action="{{ route('result_activity_master.destroy', $result_activity_master->id)}}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                            <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger"><i class="ti-trash"></i></button>
                                        </form>
                                    </td>  
                                </tr>
                            @php
                            $j++;
                            @endphp
                                @endforeach
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
<script>
	 $(document).ready(function () {
        var table = $('#example').DataTable({
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'HPC Activities Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'HPC Activities Report'},
                {extend: 'excel', text: ' EXCEL', title: 'HPC Activities Report'},
                {extend: 'print', text: ' PRINT', title: 'HPC Activities Report'},
                'pageLength'
            ],
        });

        $('#example thead tr').clone(true).appendTo('#example thead');
        $('#example thead tr:eq(1) th').each(function (i) {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function () {
                if (table.column(i).search() !== this.value) {
                    table
                        .column(i)
                        .search( this.value )
                        .draw();
                }
            } );
        } );
    } );
</script>
@include('includes.footer')
