@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Outward</h4>
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
                    <form action="@if (isset($data->id))
                          {{ route('add_outward.update', $data->id) }}
                          @else
                          {{ route('add_outward.store') }}
                          @endif" enctype="multipart/form-data" method="post">
                          @if(!isset($data->id))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif
                            @csrf
                        <div class="row">    
                            <div class="col-md-4 form-group">                   
                                <label class="control-label">To Place</label>
                                <select class="form-control" required name="place_id">
                                    <option value="">Select To Place</option>
                                @if(!empty($data['menu']))  
                                @foreach($data['menu'] as $key => $value)
                                    <option value="{{$value['id']}}" @if(isset($data->place_id)){{$data->place_id == $value['id'] ? 'selected' : '' }} @endif>{{$value['title']}}</option>
                                @endforeach
                                @endif
                                </select>
                            </div>
<!--                                
                            <div class="col-md-4 form-group">
                                <label>Academic Year</label>
                                <select name="acedemic_year" id="acedemic_year" class="form-control" required>
                                    <option>Select Academic Year</option>
                                    <option value="2013-2014" @if(isset($data->acedemic_year))@if("2013-2014" == $data->acedemic_year) selected @endif  @endif> 2013-2014 </option>
                                    <option value="2014-2015" @if(isset($data->acedemic_year))@if("2014-2015" == $data->acedemic_year) selected @endif  @endif> 2014-2015 </option>
                                    <option value="2015-2016" @if(isset($data->acedemic_year))@if("2015-2016" == $data->acedemic_year) selected @endif  @endif> 2015-2016 </option>
                                    <option value="2016-2017" @if(isset($data->acedemic_year))@if("2016-2017" == $data->acedemic_year) selected @endif  @endif> 2016-2017 </option>
                                    <option value="2017-2018" @if(isset($data->acedemic_year))@if("2017-2018" == $data->acedemic_year) selected @endif  @endif> 2017-2018 </option>
                                    <option value="2018-2019" @if(isset($data->acedemic_year))@if("2018-2019" == $data->acedemic_year) selected @endif  @endif> 2018-2019 </option>
                                    <option value="2019-2020" @if(isset($data->acedemic_year))@if("2019-2020" == $data->acedemic_year) selected @endif  @endif> 2019-2020 </option>
                                    <option value="2020-2021" selected="selected" @if(isset($data->acedemic_year))@if("2020-2021" == $data->acedemic_year) selected @endif  @endif> 2020-2021 </option>
                                    <option value="2020-2021" selected="selected" @if(isset($data->acedemic_year))@if("2021-2022" == $data->acedemic_year) selected @endif  @endif> 2021-2022 </option> 
                                    <option value="2020-2021" selected="selected" @if(isset($data->acedemic_year))@if("2022-2023" == $data->acedemic_year) selected @endif  @endif> 2022-2023 </option>    
                                </select>
                            </div>    
-->                             
                            <div class="col-md-4 form-group">
                                <label>Outward Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input type="text" required class="form-control mydatepicker" placeholder="dd/mm/yyyy" value="@if(isset($data->outward_date)) {{$data->outward_date}} @else {{ date('Y-m-d') }} @endif" name="outward_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span> 
                                </div>
                            </div>
                                
                            <div class="col-md-4 form-group">
                                <label>Outward Number </label>
                                <input type="text" value="{{$outward_no}}" id='outward_number' required name="outward_number" class="form-control" readonly="readonly">
                            </div>
                                
                            <div class="col-md-4 form-group">
                                <label>Subject </label>
                                <input type="text" id='title' value="@if(isset($data->title)){{$data->title}}@endif" required name="title" class="form-control">
                            </div>
                          
                            <div class="col-md-4 form-group">
                                <label>Description</label>
                                <textarea class="form-control" required name="description">@if(isset($data->description)){{$data->description}}@endif</textarea>
                            </div>
                            
                            <div class="col-md-4 form-group ml-0 mr-0">                   
                                <label class="control-label">File Name</label>
                                <select class="form-control" required name="file_location_id">
                                    <option value="">Select File Name</option>
                                @if(!empty($data['menu1']))  
                                @foreach($data['menu1'] as $key => $value)
                                    <option value="{{$value['id']}}" @if(isset($data->file_location_id)){{$data->file_location_id == $value['id'] ? 'selected' : '' }}@endif>{{$value['title']}} </option>
                                @endforeach
                                @endif
                                </select>
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
                                             

                       
                            
                            <div class="col-sm-4 ol-md-4 col-xs-12">                         
                                <label for="input-file-now">Upload File</label>
                                <input type="file" accept="image/*" name="attachment" @if(isset($data->attachment))data-default-file="/storage/outward/{{$data->attachment}}" @else required @endif id="input-file-now" class="dropify" /> 
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
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
<script src="../../../plugins/bower_components/dropify/dist/js/dropify.min.js"></script>
    <script>
    $(document).ready(function() {
        // Basic
        $('.dropify').dropify();
        // Translated
        $('.dropify-fr').dropify({
            messages: {
                default: 'Glissez-déposez un fichier ici ou cliquez',
                replace: 'Glissez-déposez un fichier ou cliquez pour remplacer',
                remove: 'Supprimer',
                error: 'Désolé, le fichier trop volumineux'
            }
        });
        // Used events
        var drEvent = $('#input-file-events').dropify();
        drEvent.on('dropify.beforeClear', function(event, element) {
            return confirm("Do you really want to delete \"" + element.file.name + "\" ?");
        });
        drEvent.on('dropify.afterClear', function(event, element) {
            alert('File deleted');
        });
        drEvent.on('dropify.errors', function(event, element) {
            console.log('Has Errors');
        });
        var drDestroy = $('#input-file-to-destroy').dropify();
        drDestroy = drDestroy.data('dropify')
        $('#toggleDropify').on('click', function(e) {
            e.preventDefault();
            if (drDestroy.isDropified()) {
                drDestroy.destroy();
            } else {
                drDestroy.init();
            }
        })
    });
    </script>
@include('includes.footer')
