@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit Complain</h4> </div>
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
                        <form action="{{ route('complaint.update',$data['ID']) }}" enctype="multipart/form-data" method="post">

                        {{ method_field("PUT") }}
                        @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Title </label>
                                <input type="text" id='TITLE' value="@if(isset($data['TITLE'])){{ $data['TITLE'] }}@endif" required name='TITLE' class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Description </label>
                                <input type="text" id='DESCRIPTION' value="@if(isset($data['DESCRIPTION'])){{ $data['DESCRIPTION'] }}@endif"  required name='DESCRIPTION' class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Date </label>
                                <input type="text" required name='DATE' value="@if(isset($data['DATE'])){{ $data['DATE'] }}@endif" class="form-control mydatepicker">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Complaint Solution </label>
                                <select name='COMPLAINT_SOLUTION' class="form-control">
                                    <option value=""> Select Complaint Solution </option>
                                    @foreach($complaint_status as $key => $value)
                                        <option value="{{$value['TITLE']}}" @if(isset($data['COMPLAINT_SOLUTION'])) @if($data['COMPLAINT_SOLUTION'] == $value['TITLE']) selected="selected" @endif  @endif >{{$value['TITLE']}}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Complaint Solution By</label>
                                <select name='COMPLAINT_SOLUTION_BY' class="form-control">
                                    <option value=""> Select User </option>
                                    @foreach($userList as $key => $value)
                                        <option value="{{$value['id']}}" @if(isset($data['COMPLAINT_SOLUTION_BY'])) @if($data['COMPLAINT_SOLUTION_BY'] == $value['id']) selected="selected" @endif  @endif >{{$value['first_name']." ".$value['last_name']}}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label for="input-file-now">Task Attachment</label>
                                <input type="file" @if(isset($data['ATTACHEMENT'])) data-default-file="/storage/frontdesk/{{ $data['ATTACHEMENT'] }}" @endif name="ATTACHEMENT" id="input-file-now" class="dropify" />
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
