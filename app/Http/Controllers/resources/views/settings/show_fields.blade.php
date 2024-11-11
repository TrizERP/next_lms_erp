@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Custom Fields</h4> </div>
            </div>       
            <div class="card">               
                    @if ($sessionData = Session::get('data'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif
                    <div class="row">
                        <div class="col-lg-3 col-sm-3 col-xs-3">
                            <a href="{{ route('add_fields.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New Custom Field </a>
                        </div>
                        <br><br><br>
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <table id="example" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Id</th>
                                            <th>Module Name</th>
                                            <th>Field Name</th>
                                            <th>Field Label</th>
                                            <th>Field Type</th>
                                            <th>Field Message</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                        @foreach($data['data'] as $key => $data)
                                        <tr>    
                                            <td>{{$j}}</td>
                                            <td>{{$data->table_name}}</td>
                                            <td>{{$data->field_name}}</td>
                                            <td>{{$data->field_label}}</td>  
                                            <td>{{$data->field_type}}</td> 
                                            <td>{{$data->field_message}}</td> 
                                            <td>
                                            <!-- <a href="{{ route('add_fields.edit',$data->id)}}"><button style="float:left;" type="button" class="btn btn-info btn-outline btn-circle btn m-r-5"><i class="ti-pencil-alt"></i></button></a> -->
                                            <form action="{{ route('add_fields.destroy', $data->id)}}" method="post">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-info btn-outline btn-circle btn m-r-5"><i class="ti-trash"></i></button>
                                            </form>
                                            </td>  
                                        </tr>
                                        @php
                                    $j++;
                                    @endphp
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

<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable();
});

</script>
@include('includes.footer')
