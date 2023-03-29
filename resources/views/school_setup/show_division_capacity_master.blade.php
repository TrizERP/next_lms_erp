@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Division Capacity Master</h4>
            </div>            
        </div>
        <div class="card"> 
            @if ($sessionData = Session::get('data'))
             <div class="alert alert-block {{ $sessionData['class'] }}">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <form action="@if (isset($data['id']))
            {{ route('division_capacity_master.update', $data['id']) }}
            @else
            {{ route('division_capacity_master.store') }}
            @endif" method="post">
                @if(!isset($data['id']))
                    {{ method_field("POST") }}
                @else
                    {{ method_field("PUT") }}
                @endif
                @csrf       
                <div class="row">                                                                     
                    {{ App\Helpers\SearchChain('3','single','grade,std,div',$data['grade_id'],$data['standard_id'],$data['division_id']) }}                              
                    <div class="col-md-3 form-group"> 
                        <label>Capacity</label>                                                                         
                        <input type="number" name="capacity" id="capacity" class="form-control" required="required" placeholder="Enter total number of capacity" autocomplete="off" value="@if(isset($data['capacity'])){{$data['capacity']}}@endif">                        
                    </div>                            
                    <div class="col-md-12 form-group">                                                        
                        <center>                        
                            <input type="submit" name="{{$data['button']}}" value="{{$data['button']}}" class="btn btn-success">
                        </center>
                    </div>
                </div>         
            </form> 
        </div>  
        
        <div class="card">
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                        
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr. No.</th>
                                    <th>Academic Section</th>
                                    <th>Standard</th>
                                    <th>Division</th>
                                    <th>Capacity</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $i=1; @endphp
                                @foreach($data['data'] as $key => $data)
                                <tr>    
                                    <td>{{$i++}}</td>
                                    <td>{{$data->academic_section_name}}</td>                 
                                    <td>{{$data->standard_name}}</td>                 
                                    <td>{{$data->division_name}}</td>     
                                    <td>{{$data->capacity}}</td>     
                                    <td>
                                        <div class="d-inline">                                        
                                            <a href="{{ route('division_capacity_master.edit',$data->id)}}" class="btn btn-info btn-outline">
                                                <i class="ti-pencil-alt"></i>
                                            </a>                                                                                          
                                        </div>
                                        <form action="{{ route('division_capacity_master.destroy', $data->id)}}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                            <button onclick="return confirmDelete();" type="submit" class="btn btn-info btn-outline-danger">
                                                <i class="ti-trash"></i>
                                            </button>
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
    $('#example').DataTable({});
    $('#grade').attr('required',true);
    $('#standard').attr('required',true);
    $('#division').attr('required',true);
});
</script>
@include('includes.footer')
