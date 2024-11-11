@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style>
.title{
    font-weight:200;
}
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit Proxy</h4>
            </div>            
        </div>
        <div class="card"> 
            @if ($message = Session::get('data'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message['message'] }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                            
                        <form action="{{ route('proxy_master.update', $data['proxydata']['id']) }}" name="proxy" id="proxy" enctype="multipart/form-data" method="post">
                            @if(isset($data['proxydata']))
                            {{ method_field("PUT") }}                               
                            @endif
                            @csrf 
                            @if( isset($data['proxydata']) )
                            <div class="table-responsive">                                
                                <table id="proxy_list" class="table table-striped">
                                    <thead>
                                        <tr>                                       
                                            <th>Date</th>                                        
                                            <th>Week Day</th>                                        
                                            <th>Standard</th>
                                            <th>Division</th>
                                            <th>Batch</th>
                                            <th>Period</th>
                                            <th>Subject</th>
                                            <th>Proxy Teacher</th>
                                        </tr>
                                    </thead>
                                    <tbody>                                                                  
                                        <tr>                                        
                                            <td>{{$data['proxydata']['proxy_date']}}</td>
                                            <td>{{$data['proxydata']['week_day']}}</td>
                                            <td>{{$data['proxydata']['standard_name']}}</td>
                                            <td>{{$data['proxydata']['division_name']}}</td>
                                            <td>{{$data['proxydata']['batch_name']}}</td>
                                            <td>{{$data['proxydata']['period_name']}}</td>
                                            <td>{{$data['proxydata']['subject_name']}}</td>
                                            <td>
                                                <select class="selectpicker form-control" name="proxy_teacher_id" id="proxy_teacher_id">
                                                    <option value="">Select Teacher</option> 
                                                    @if(isset($data['teacher_data']))
                                                    @foreach($data['teacher_data'] as $key1 =>$val1) 
                                                        @php
                                                        $selected = "";
                                                        if($data['proxydata']['proxy_teacher_id'] == $val1->id)
                                                        {
                                                            $selected = "selected";
                                                        } 
                                                        @endphp                    
                                                        <option {{$selected}} value="{{$val1->id}}">{{$val1->teacher_name}}</option>
                                                    @endforeach                       
                                                    @endif
                                                </select>
                                            </td>                                         
                                        </tr>                                                                    
                                    </tbody>
                                    <tr align="center">
                                        <td colspan="9">
                                            <center>                                        
                                                <input onclick="return validate_data();" type="submit" name="Update" value="Update" class="btn btn-success" >
                                            </center>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </form>
                        @endif
                    </div>                                                           
                </div>                    
            </div>                
            @if (count($errors) > 0)
            <div class="alert alert-danger">
                <strong>Whoops!</strong> There were some problems with your input.<br><br>
                <ul>
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>
function validate_data()
{   
    var tval = document.getElementById("proxy_teacher_id").value;   
    if(tval == ""){
        alert("Please Select Teacher");
        return false;
    }
    else{
        return true;
    }   
}

</script>
@include('includes.footer')
