@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit Tasks</h4> </div>
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
                    <form action="{{ route('task.update',$data['ID']) }}" enctype="multipart/form-data" method="post">

                        {{ method_field("PUT") }}
                        @csrf

                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Title </label>
                                <input type="text" id='TASK_TITLE' value="@if(isset($data['TASK_TITLE'])){{ $data['TASK_TITLE'] }}@endif" required name='TASK_TITLE' class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Description </label>
                                <input type="text" id='TASK_DESCRIPTION' value="@if(isset($data['TASK_DESCRIPTION'])){{ $data['TASK_DESCRIPTION'] }}@endif"  required name='TASK_DESCRIPTION' class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Date </label>
                                <input type="text" required name='TASK_DATE' value="@if(isset($data['TASK_DATE'])){{ $data['TASK_DATE'] }}@endif" class="form-control mydatepicker">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>TASK ALLOCATED </label>                                
                                <select id="TASK_ALLOCATED" name="TASK_ALLOCATED" class="form-control" readonly>
                                    @if(isset($data))
                                        <option value="{{$data['TASK_ALLOCATED']}}"> {{$data['ALLOCATOR']}} </option>
                                    @endif
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>TASK ALLOCATED TO </label>                                
                                <select id="TASK_ALLOCATED_TO" name="TASK_ALLOCATED_TO" class="form-control" readonly>
                                    @if(isset($data))
                                        <option value="{{$data['TASK_ALLOCATED_TO']}}"> {{$data['ALLOCATED_TO']}} </option>
                                    @endif
                                </select>
                            </div> 

                            <!-- <div class="col-md-4 form-group">
                                <label>User </label>
                                <input type="text" id='TASK_ALLOCATED_TO' value="@if(isset($data['ALLOCATED_TO'])){{ $data['ALLOCATED_TO'] . ' - '. $data['TASK_ALLOCATED_TO'] }}@endif" list="userAllocatedList" name="TASK_ALLOCATED_TO" class="form-control">
                                <datalist id="userAllocatedList">
                                    @if(isset($userList))
                                        @foreach($userList as $key => $value)
                                            <option value="{{$value['first_name'].' '.$value['last_name'] . ' - '. $value['id']}}"> {{$value['first_name']." ".$value['last_name']}} </option>
                                        @endforeach
                                    @endif
                                </datalist>
                            </div> -->

                            <div class="col-md-4 form-group">
                                <label>Task Status </label>
                                <select name='STATUS' class="form-control">
                                    <option value=""> Select Task Status </option>
                                    @foreach($taskStatus as $key => $value)
                                        <option value="{{$value['TITLE']}}" @if(isset($data['STATUS'])) @if($data['STATUS'] == $value['TITLE']) selected="selected" @endif  @endif >{{$value['TITLE']}}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label for="input-file-now">Task Attachment</label>
                                <input type="file" @if(isset($data['TASK_ATTACHMENT'])) data-default-file="/storage/frontdesk/{{ $data['TASK_ATTACHMENT'] }}" @endif name="TASK_ATTACHMENT" id="input-file-now" class="dropify" />
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Reply </label>
                                <textarea type="text" id='reply' required name='reply' class="form-control">{{ $data['reply'] }}
                                </textarea>
                            </div>
                            <div class="col-md-12 form-group">
                                    <input type="submit" name="submit" value="Update" class="btn btn-success" >
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
