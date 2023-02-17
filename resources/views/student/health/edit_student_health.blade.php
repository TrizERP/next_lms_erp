@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit Student Health</h4> 
            </div>
        </div>
        <div class="card">
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
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('student_health.update',$data['id']) }}" enctype="multipart/form-data" method="post">
                    {{ method_field("PUT") }}
                    @csrf
                        <div class="row">                            
                            <div class="col-md-4 form-group">
                                <label>Student </label>
                                <input type="text" id='student' value="@if(isset($data['student_name'])){{ $data['student_name'] . ' - '. $data['student_id'] }}@endif" list="studentSearchList" name="student_id" onkeyup="getStudents(this.value);" required="required" class="form-control">
                                <datalist id="studentSearchList">
                                </datalist>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Doctor Name </label>
                                <input type="text" id='doctor_name' value="@if(isset($data['doctor_name'])){{ $data['doctor_name'] }}@endif" name="doctor_name" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Doctor Contact </label>
                                <input type="text" id='doctor_contact' value="@if(isset($data['doctor_contact'])){{ $data['doctor_contact'] }}@endif" name="doctor_contact" class="form-control">
                            </div>
                            <div class="col-md-4 form-group ml-0 mr-0">
                                <label>Date</label>
                                <input type="text" id='date' value="@if(isset($data['date'])){{ $data['date'] }}@endif" name="date" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-4 form-group ml-0">
                                <label for="input-file-now">File</label>
    							<input type="file" data-default-file="/storage/frontdesk/{{ $data['file'] }}" name="file" id="input-file-now" class="dropify" />
                            </div>
                            <div class="col-md-12 form-group">
                                <center>                                    
                                    <input type="submit" name="submit" value="Update" class="btn btn-success" >
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
