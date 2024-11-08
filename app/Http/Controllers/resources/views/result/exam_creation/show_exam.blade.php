{{-- @include('includes.headcss') @include('includes.header') @include('includes.sideNavigation') --}} 
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">        
            <div class="card">
                @if(!empty($data['message']))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('exam_creation.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>
                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <table id="example" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Term</th>
                                <th>Medium</th>
                                <th>Exam Type</th>
                                <th>App Status</th>
                                <th>Standard</th>
                                <th>Subject</th>
                                <th>Exam Name</th>
                                <th>Points</th>
                                <th>Mark Type</th>
                                <th>Report Card Status</th>
                                <th>Sort Order</th>
                                <th>Exam Date</th>
                                <th class="text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['data'] as $key => $data)
                            <tr>    
                                <td>{{$data->term_name}}</td>
                                <td>{{$data->medium}}</td>
                                <td>{{$data->exam_type}}</td>
                                <td>{{$data->app_disp_status}}</td>
                                <td>{{$data->std_name}}</td>
                                <td>{{$data->sub_name}}</td>
                                <td>{{$data->exam_name}}</td>
                                <td>{{$data->points}}</td>
                                <td>{{$data->marks_type}}</td>
                                <td>{{$data->report_card_status}}</td>
                                <td>{{$data->sort_order}}</td>
                                <td>{{$data->exam_date}}</td>
                                <td>
                                    <div class="d-inline">
                                        <a href="{{ route('exam_creation.edit',$data->id)}}" class="btn btn-info btn-outline">
                                            <i class="ti-pencil-alt"></i>
                                        </a>
                                    </div>
                                    <form class="d-inline" action="{{ route('exam_creation.destroy', $data->id)}}" method="post">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-info btn-outline-danger" onclick="return confirmDelete();" type="submit"><i class="ti-trash"></i></button>
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

@include('includes.footerJs')
<script>
    $(document).ready(function() {
     var table = $('#example').DataTable( {
            
        }); 

        $('#example thead tr').clone(true).appendTo( '#example thead' );
        $('#example thead tr:eq(1) th').each( function (i) {
            var title = $(this).text();
            $(this).html( '<input type="text" placeholder="Search '+title+'" />' );

            $( 'input', this ).on( 'keyup change', function () {
                if ( table.column(i).search() !== this.value ) {
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
@endsection