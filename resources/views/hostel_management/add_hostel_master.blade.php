@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">       
            <div class="white-box">
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
                          {{ route('add_hostel_master.update',['$data->id']) }}
                          @else
                          {{ route('add_hostel_master.store') }}
                          @endif" method="post">

                        @if(!isset($data))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif


                        {{csrf_field()}}
                        
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    @csrf
                                    <label>Code </label>
                                    <input type="text" id='code' required name="code" value="@if(isset($data->code)) {{ $data->code }} @endif" class="form-control">
                                </div>
                            
                                <div class="col-md-4 form-group">
                                    <label>Name </label>
                                    <input type="text" id='name' required name="name" value="@if(isset($data->name)) {{ $data->name }} @endif" class="form-control">
                                </div>
                           
                                <div class="col-md-4 form-group">                   
                                    <label class="control-label">Hostel Type</label>                                    
                                    <select class="form-control" required name="hostel_type_id">
                                        <option value="">Select Hostel Type</option>
                                    @if(!empty($data['menu']))  
                                    @foreach($data['menu'] as $key => $value)
                                        <option value="{{ $value['id'] }}" @if(isset($data->hostel_type_id)) {{ $data->hostel_type_id == $value['id'] ? 'selected' : '' }} @endif> {{ $value['hostel_type'] }} </option>
                                    @endforeach
                                    @endif
                                    </select>
                                    
                                </div>
                            
                                <div class="col-md-4 form-group">
                                    <label>Description </label>
                                    <textarea class="form-control" rows="2" id='description' required name="description">@if(isset($data->description)) {{ $data->description }} @endif</textarea>  
                                </div>

                                <div class="col-md-4 form-group">
                                    <label>Warden </label>
                                    <input type="text" id='warden' required name="warden" value="@if(isset($data->warden)) {{ $data->warden }} @endif" class="form-control">
                                </div>
                                    @if(isset($data['custom_fields']))
                
                                    @foreach($data['custom_fields'] as $key => $value)
                                    <div class="col-md-4 form-group">
                                        <label>{{ $value['field_label'] }}</label>
                                        @if($value['field_type'] == 'file')
                                        <input type="{{ $value['field_type'] }}" accept="image/*" id="input-file-now" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" class="dropify">
                                        @elseif($value['field_type'] == 'date')
                                        <div class="input-daterange input-group" >
                                        <input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy" autocomplete="off" id="{{ $value['field_name'] }}" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" class="form-control"><span class="input-group-addon"><i class="icon-calender"></i></span>
                                        </div>
                                        @elseif($value['field_type'] == 'checkbox')
                                        <div class="checkbox-list">
                                            @if(isset($data['data_fields'][$value['id']]))
                                            @foreach($data['data_fields'][$value['id']] as $keyData => $valueData )
                                                <label class="checkbox-inline">
                                                    <div class="checkbox checkbox-success">
                                                        <input type="checkbox" name="{{ $value['field_name'] }}[]" value="{{ $valueData['display_value'] }}" id="{{ $valueData['display_value'] }}" @if($value['required'] == 1) required @endif>
                                                        <label for="{{ $valueData['display_value'] }}">{{ $valueData['display_text'] }}</label>
                                                    </div>
                                                </label>
                                                @endforeach
                                            @endif
                                        </div>
                                        @elseif($value['field_type'] == 'dropdown')
                                                <select name="{{ $value['field_name'] }}" class="form-control" @if($value['required'] == 1) required @endif id="{{ $value['field_name'] }}">
                                                    <option value=""> SELECT {{ strtoupper($value['field_label']) }} </option>
                                                @if(isset($data['data_fields'][$value['id']]))
                                                    @foreach($data['data_fields'][$value['id']] as $keyData => $valueData)
                                                    <option value="{{ $valueData['display_value'] }}"> {{ $valueData['display_text'] }} </option>
                                                    @endforeach
                                                @endif
                                                </select>
                                        @elseif($value['field_type'] == 'textarea')
                                        <textarea id="{{ $value['field_name'] }}" class="form-control" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" placeholder="{{ $value['field_message'] }}">
                                        </textarea>
                                        @else
                                        <input type="{{ $value['field_type'] }}" id="{{ $value['field_name'] }}" placeholder="{{ $value['field_message'] }}" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" class="form-control">
                                        @endif
                                    </div>
                                    @endforeach

                                    @endif

                                <div class="col-md-4 form-group">
                                    <label>Warden Contact </label>
                                    <input type="text" id='warden_contact' required name="warden_contact" value="@if(isset($data->warden_contact)) {{ $data->warden_contact }} @endif" class="form-control">
                                </div>
                            
                                <div class="col-md-12 form-group">
                                    <center>
                                        <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                    </center>
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
@include('includes.footer')
