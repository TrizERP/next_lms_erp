@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit Frontdesk</h4> </div>
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
                    <form action="{{ route('frontdesk.update',$data['ID']) }}" enctype="multipart/form-data" method="post">

                        {{ method_field("PUT") }}

                            @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Visitor Type </label>
                                <select required name='VISITOR_TYPE' class="form-control">
                                    <option value=""> Select Visitor Type </option>
                                    <option value="parents" @if(isset($data['VISITOR_TYPE'])) @if($data['VISITOR_TYPE'] == 'parents') SELECTED @endif @endif> Parents </option>
                                    <option value="other" @if(isset($data['VISITOR_TYPE'])) @if($data['VISITOR_TYPE'] == 'other') SELECTED @endif @endif> Other </option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Title </label>
                                <input type="text" id='TITLE' required name='TITLE' value="@if(isset($data['TITLE'])){{ $data['TITLE'] }}@endif" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Description </label>
                                <input type="text" id='DESCRIPTION' required name='DESCRIPTION' value="@if(isset($data['DESCRIPTION'])){{ $data['DESCRIPTION'] }}@endif" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Student </label>
                                <input type="text" oninput="onInput()" value="@if(isset($data['student_name'])){{ $data['student_name'] . ' - '. $data['STUDENT_ID'] }}@endif" id='student' list="studentSearchList" name="STUDENT_ID" onkeyup="getStudents(this.value);" class="form-control">
                                <datalist id="studentSearchList">
                                </datalist>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>User </label>
                                <input type="text" id='TO_WHOM_MEET' value="@if(isset($data['user_name'])){{ $data['user_name'] . ' - '. $data['TO_WHOM_MEET'] }}@endif" list="userSearchList" name="TO_WHOM_MEET" class="form-control">
                                <datalist id="userSearchList">
                                    @if(isset($userList))
                                        @foreach($userList as $key => $value)
                                            <option value="{{$value['first_name'].' '.$value['last_name'] . ' - '. $value['id']}}" @if(isset($data['TO_WHOM_MEET'])) @if($data['TO_WHOM_MEET'] == $value['id']) SELECTED @endif @endif> {{$value['first_name']." ".$value['last_name']}} </option>
                                        @endforeach
                                    @endif
                                </datalist>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Date </label>
                                <input type="text" required name='DATE' value="@if(isset($data['DATE'])){{ $data['DATE'] }}@endif" class="form-control mydatepicker">
                            </div>

                            <div class="col-md-4 form-group">
                                <label for="input-file-now">Visitor Photo</label>
                                <input type="file" accept="image/*" @if(isset($data['VISITOR_PHOTO'])) data-default-file="/storage/frontdesk/{{ $data['VISITOR_PHOTO'] }}" @endif name="VISITOR_PHOTO" id="input-file-now" class="dropify" />
                            </div>                            

                            <div class="col-md-4 form-group">
                                <label>In Time </label>
                                <div class="input-group clockpicker " data-placement="bottom" data-align="top" data-autoclose="true">
                                    <input type="text" id="in_time" required="" name="IN_TIME" class="form-control" value="@if(isset($data['IN_TIME'])){{ $data['IN_TIME'] }}@endif">
                                    <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                                </div>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Out Time </label>
                                <div class="input-group clockpicker " data-placement="bottom" data-align="top" data-autoclose="true">
                                    <input type="text" id="in_time" required="" name="OUT_TIME" class="form-control" value="@if(isset($data['OUT_TIME'])){{ $data['OUT_TIME'] }}@endif">
                                    <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                                </div>
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
