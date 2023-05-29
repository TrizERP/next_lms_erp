@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Create Class Teacher</h4>
            </div>            
        </div>
        <div class="card"> 

            @if (Session::get('data') != null && $sessionData = Session::get('data'))
             <div class="alert alert-block {{ $sessionData['class'] ?? '' }}">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <form action="@if (isset($data['id']))
            {{ route('classteacher.update', $data['id']) }}
            @else
            {{ route('classteacher.store') }}
            @endif" enctype="multipart/form-data" method="post">
                @if(!isset($data['id']))
                    {{ method_field("POST") }}
                @else
                    {{ method_field("PUT") }}
                @endif
                @csrf       
                <div class="row">                                                                     
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$data['academic_section_id'],$data['standard_id'],$data['division_id']) }}                              
                    <div class="col-md-4 form-group"> 
                        <label>Select Teacher</label>                                                                         
                        <select class="form-control" name="teacher_id" id="teacher_id" required>
                            <option value="">Select Teacher</option>                        
                            @if(isset($data['teacher_data']))
                            @foreach($data['teacher_data'] as $key =>$val)
                                @php 
                                $selected = '';
                                if( isset($data['teacher_id']) && $data['teacher_id'] == $val->id )
                                {
                                    $selected = 'selected';
                                }
                                @endphp                                                                                                                                                                            
                                <option {{$selected}} value="{{$val->id}}">{{$val->teacher_name}}</option>                            
                            @endforeach                       
                            @endif                                                                                                 
                        </select>                        
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
                                    <th>Class Teacher</th>
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
                                    <td>{{$data->teacher_name}}</td>     
                                    <td>
                                        <div class="d-inline">                                        
                                            <a href="{{ route('classteacher.edit',$data->id)}}" class="btn btn-info btn-outline">
                                                <i class="ti-pencil-alt"></i>
                                            </a>                                                                                          
                                        </div>
                                        <form action="{{ route('classteacher.destroy', $data->id)}}" method="post" class="d-inline">
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
<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<script>
$(document).ready(function () {
    $('#classteacher_list').DataTable({});
});

</script>
@include('includes.footer')
