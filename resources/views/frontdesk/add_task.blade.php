@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Tasks</h4> </div>
        </div>
        
        <div class="card">
            <div class="row">
                <!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
                    @if ($sessionData = Session::get('data'))
                    @if($sessionData['status_code'] == 1)
                    <div class="alert alert-success alert-block">
                    @else
                    <div class="alert alert-danger alert-block">
                    @endif
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif

                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('task.store') }}" enctype="multipart/form-data" method="post">

                        {{ method_field("POST") }}
                        @csrf

                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label>Title </label>
                                <input type="text" id='TASK_TITLE' required name='TASK_TITLE' class="form-control">
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Description </label>
                                <input type="text" id='TASK_DESCRIPTION' required name='TASK_DESCRIPTION' class="form-control">
                            </div>                            

                            <div class="col-md-3 form-group">
                                <label>Date </label>
                                <input type="text" required name='TASK_DATE' class="form-control mydatepicker" autocomplete="off">
                            </div>                        

                            <div class="col-md-3 form-group">
                                <label>User </label>                                
                                <select id="TASK_ALLOCATED_TO[]" name="TASK_ALLOCATED_TO[]" class="form-control" multiple="multiple">
                                    @if(isset($data['userList']))
                                        @foreach($data['userList'] as $key => $value)
                                            <option value="{{$value['id']}}"> {{$value['first_name']." ".$value['last_name']}} </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div> 

                            <!-- <div class="col-md-3 form-group">
                                <label>User </label>
                                <input type="text" id='TASK_ALLOCATED_TO' list="userAllocatedList" name="TASK_ALLOCATED_TO" class="form-control">
                                <datalist id="userAllocatedList">
                                    @if(isset($data['userList']))
                                        @foreach($data['userList'] as $key => $value)
                                            <option value="{{$value['first_name'].' '.$value['last_name'] . ' - '. $value['id']}}"> {{$value['first_name']." ".$value['last_name']}} </option>
                                        @endforeach
                                    @endif
                                </datalist>
                            </div>  -->                                       
                        
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
                            <div class="col-md-3 form-group ml-0 mr-auto">
                                <label for="input-file-now">Task Attachment</label>
                                <input type="file" name="TASK_ATTACHMENT" id="input-file-now" class="dropify" />
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Reply </label>
                                <textarea type="text" id='reply' required name='reply' class="form-control">
                                </textarea>
                            </div>
                            <div class="col-md-12 form-group">
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>


@include('includes.footerJs')

<script type="text/javascript">
    function getStudents(value)
    {
        var URL = "{{route('search_student_name')}}";
        // var data = value;

        if(value.length > 2)
        {

            $.post(URL,
          {
            'value': value
          },
          function(result, status){
            $('#studentSearchList').find('option').remove().end();
            for(var i=0;i < result.length;i++){

                    $("#studentSearchList").append($("<option></option>").val(result[i]['student'] + ' - ' + result[i]['id']).html(result[i]['student']));
            }
          });
        }
    }


</script>
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
