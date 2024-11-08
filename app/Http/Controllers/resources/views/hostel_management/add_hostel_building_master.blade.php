@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Hostel Building</h4> </div>
            </div>       
            <div class="card">
                <div class="panel-body">
                    @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                    @endif
                       
                    <div class="col-lg-12 col-sm-12 col-xs-12">  
                        <form action="
                          @if (isset($data))
                          {{ route('add_hostel_building_master.update', $data->id) }}
                          @else
                          {{ route('add_hostel_building_master.store') }}
                          @endif

                          " method="post">
                          @csrf

                        @if(!isset($data))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif


                        {{csrf_field()}}

                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label class="control-label">Hostel Type</label>
                                    <select class="form-control" id="hostel_type_id" onchange="getHostels();" required name="hostel_type_id">
                                        <option value=""> Select Hostel Type </option>
                                    @if(!empty($hosteltype))  
                                    @foreach($hosteltype as $key => $value)
                                        <option value="{{ $value['id'] }}" @if(isset($data->hostel_type_id)) {{ $data->hostel_type_id == $value['id'] ? 'selected' : '' }} @endif> {{ $value['hostel_type'] }} </option>
                                    @endforeach
                                    @endif
                                    </select>
                                </div>

                                <div class="col-md-4 form-group">
                                    <label class="control-label">Hostels</label>
                                    <select class="form-control" required id="hostel_id" name="hostel_id">
                                        <option value=""> Select Hostel </option>
                                        <option value=""> Boys Hostel </option>
                                       <option value=""> Girls Hostel </option>
                                    </select>
                                </div>
                            
                                <div class="col-md-4 form-group">
                                    <label>Building Name </label>
                                    <input type="text" id='building_name' required name="building_name" placeholder="Please enter building name." value="@if(isset($data->building_name)) {{ $data->building_name }} @endif" class="form-control">
                                </div>
                            
                                <div class="col-md-2 form-group">
                                        <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                </div>
                            </div>

                        </form>
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
</div>

@include('includes.footerJs')
<script>
    function getHostels(hostel_id = null){
        
        var hostel_type_id = document.getElementById("hostel_type_id").value;

        $('#hostel_id').find('option').remove().end().append('<option value=""> Select Hostel  </option>').val('');

        var path = "{{ route('hostelList') }}";
        $.ajax({
        type:'POST',
        url:path,
        data:{hostel_type_id:hostel_type_id},
            success:function(data){
                for(var i=0;i < data.length;i++){   
                    if(data[i]['id'] == hostel_id){
                        $("#hostel_id").append($("<option value=''> Select Hostel </option>").val(data[i]['id']).html(data[i]['name']).attr("selected","selected"));  
                    }else{
                        $("#hostel_id").append($("<option value=''> Select Hostel </option>").val(data[i]['id']).html(data[i]['name']));  
                    }
                } 
        }
        });
    }

    $( document ).ready(function() {
        var hostel_id = @if(isset($data->hostel_id)) {{$data->hostel_id}} @else '0' @endif;
        if(hostel_id != 0)
        {
            getHostels(hostel_id);
        }
    });
</script>
@include('includes.footer')
