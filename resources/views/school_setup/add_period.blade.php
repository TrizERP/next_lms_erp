@include('includes.headcss')
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                @if(!isset($data['period_data']))
                Add Period
                @else
                Edit Period
                @endif
                </h4>
            </div>            
        </div>
        <div class="card">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="@if (isset($data['period_data']))
                          {{ route('period_master.update', $data['period_data']['id']) }}
                          @else
                          {{ route('period_master.store') }}
                          @endif" enctype="multipart/form-data" method="post">
                            @if(!isset($data['period_data']))
                            {{ method_field("POST") }}
                            @else
                            {{ method_field("PUT") }}
                            @endif
                            @csrf
                        <div class="row">                            
                            <div class="col-md-6 form-group">
                                <label>Title</label>
                                <input type="text" id='title' value="@if(isset($data['period_data']['title'])){{$data['period_data']['title']}}@endif" required name="title" class="form-control">
                            </div>                                                                       
                            <div class="col-md-6 form-group">
                                <label>Short Name</label>
                                <input type="text" id='short_name' value="@if(isset($data['period_data']['short_name'])){{$data['period_data']['short_name']}}@endif" required name="short_name" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Sort Order</label>
                                <input type="text" id='sort_order' value="@if(isset($data['period_data']['sort_order'])){{$data['period_data']['sort_order']}}@endif" required name="sort_order" class="form-control">
                            </div>                                            
                            <!-- <div class="col-md-6 form-group">
                                <label>Academic Section</label>                            
                                <select class="form-control" data-style="form-control" name="academic_section_id" id="academic_section_id">
                                    @foreach($data['academic_section_data'] as $key =>$val)
                                        @php 
                                            $selected = '';
                                            if( isset($data['period_data']['academic_section_id']) && $data['period_data']['academic_section_id'] == $val->id )
                                            {
                                                $selected = 'selected';
                                            }
                                        @endphp
                                        <option {{$selected}} value="{{$val->id}}">{{$val->title}}</option>
                                    @endforeach                                
                                </select>
                            </div> -->
                            <div class="col-md-6 form-group">
                                <label>Academic Year</label>                            
                                <select class="form-control" data-style="form-control" name="academic_year_id" id="academic_year_id">
                                    @foreach($data['academic_year_data'] as $key =>$val)
                                        @php 
                                            $selected = '';
                                            if( isset($data['period_data']['academic_year_id']) && $data['period_data']['academic_year_id'] == $val->id )
                                            {
                                                $selected = 'selected';
                                            }                                        
                                        @endphp
                                        <option {{$selected}} value="{{$val->id}}">{{$val->title}}</option>
                                    @endforeach                                
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Start Time</label>
                                <div class="input-group clockpicker " data-placement="bottom" data-align="top" data-autoclose="true">
                                    <input type="text" id='start_time' name="start_time" class="form-control" value="@if(isset($data['period_data']->start_time)) {{ $data['period_data']->start_time }} @endif"> 
                                    <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>End Time</label>
                                <div class="input-group clockpicker " data-placement="bottom" data-align="top" data-autoclose="true">
                                    <input type="text" id='end_time' name="end_time" class="form-control" value="@if(isset($data['period_data']->end_time)) {{ $data['period_data']->end_time }} @endif"> 
                                    <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                                </div>
                            </div> 
                            <div class="col-md-6 form-group">
                                <div class="checkbox checkbox-info checkbox-circle">
                                    <br><input @if(isset($data['period_data']['used_for_attendance']) && $data['period_data']['used_for_attendance'] == "Yes"){{'checked'}}@endif type="checkbox" id="used_for_attendance" name="used_for_attendance" value="Yes">
                                    <label for="used_for_attendance"><b>Used for Attendance</b></label>
                                </div> 
                            </div>                       
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" onclick="return validate();">
                                </center>
                            </div>
                        </div>    
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>
function validate(){
    var start_time = $("#start_time").val();
    var end_time = $("#end_time").val();    
    if(Date.parse('1/1/2011 '+start_time) < Date.parse('1/1/2011 '+end_time)){
        return true;
    }else{
        alert("Please select Proper Start Time and End Time");
        return false;    
    }    
}
</script>
@include('includes.footer')
