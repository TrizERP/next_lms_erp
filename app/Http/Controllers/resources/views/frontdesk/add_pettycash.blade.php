@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                @if(!isset($data['period_data']))
                Add Petty Cash
                @else
                Edit Petty Cash
                @endif
                </h4>
                </div>            
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
                        <form action="@if (isset($data['petty_data']))
                              {{ route('pettycash.update', $data['petty_data']['id']) }}
                              @else
                              {{ route('pettycash.store') }}
                              @endif" enctype="multipart/form-data" method="post">
                              @if(!isset($data['petty_data']))
                            {{ method_field("POST") }}
                            @else
                            {{ method_field("PUT") }}
                            @endif
                            @csrf
                            <div class="row">
                                <div class="col-sm-4 ol-md-4 col-xs-12 form-group">
                                <label>Date</label>
                                    <div class="input-daterange input-group" id="date-range">
                                        <input type="text" required class="form-control mydatepicker" placeholder="YYYY/MM/DD" name="created_on" id="created_on" autocomplete="off">
                                        <span class="input-group-addon"><i class="icon-calender"></i></span> 
                                    </div>
                                </div>
                                <div class="col-sm-4 ol-md-4 col-xs-12 form-group">
                                    <label>Title</label>
                                    <select required id="title_id" name="title_id" class="selectpicker form-control">
                                        <option value="">Select Title</option>
                                        @foreach($data['Title_Arr'] as $key => $val)
                                        <option value="{{$val->id}}">{{$val->title}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-4 ol-md-4 col-xs-12 form-group">
                                    <label>Amount</label>
                                    <input type="number" id='amount' required name="amount" class="form-control">
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
                                 
                              <div class="col-lg-4 ol-md-4 col-xs-12 form-group ml-0 mr-0">
                                <label>Description</label>
                                <textarea id='description' name="description" class="form-control"></textarea>
                            </div> 
                            <div class="col-sm-4 ol-md-4 col-xs-12 form-group ml-0">                         
                                <label for="input-file-now">Image</label>
                                <input type="file" accept="image/*" name="bill_image" id="input-file-now" class="dropify" /> 
                            </div>                                                                                
                            <div class="col-md-12 form-group">                        
                                <input type="submit" name="submit" value="Save" class="btn btn-success">                        
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
