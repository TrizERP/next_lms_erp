@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add User</h4> </div>
        </div>
        <div class="row" style=" margin-top: 25px;">
            <div class="white-box">
            <div class="panel-body">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
				@endif
				<div class="row">
                    <div class="col-lg-2 col-sm-4 col-xs-12">
                        <a href="{{ route('add_user.create') }}"><button class="btn btn-block btn-default btn-rounded">User Information</button></a>
                    </div>
                    <div class="col-lg-2 col-sm-4 col-xs-12">
                        <a href="{{ route('add_user_past_education.index') }}"><button class="btn btn-block btn-info btn-rounded">Past Education</button></a>
                    </div>
                    <!-- <div class="col-lg-2 col-sm-4 col-xs-12">
                        <button class="btn btn-block btn-default btn-rounded">Primary</button>
                    </div>
                    <div class="col-lg-2 col-sm-4 col-xs-12">
                        <button class="btn btn-block btn-default btn-rounded">Success</button>
                    </div>
                    <div class="col-lg-2 col-sm-4 col-xs-12">
                        <button class="btn btn-block btn-default btn-rounded">Danger</button>
                    </div> -->
                </div>
                <br>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                            <form action="{{ route('past_education.store') }}" enctype="multipart/form-data" method="post">
                            {{ method_field("POST") }}
                                @csrf  
                                                <div class="col-md-2 form-group">
                                                    <label>Course </label>
                                                    <input type="text" id='course' required name="course" class="form-control">
                                                </div>
                                                <div class="col-md-2 form-group">
                                                    <label>Medium </label>
                                                    <input type="text" id='medium' required name="medium" class="form-control">
                                                </div>
                                                <div class="col-md-2 form-group">
                                                    <label>Name of board </label>
                                                    <input type="text" id='name_of_board' required name="name_of_board" class="form-control">
                                                </div>
                                                <div class="col-md-2 form-group">
                                                    <label>Year of passing </label>
                                                    <input type="text" id='year_of_passing' required name="year_of_passing" class="form-control">
                                                </div>
                                                <div class="col-md-2 form-group">
                                                    <label>Percentage </label>
                                                    <input type="text" id='percentage' required name="percentage" class="form-control">
                                                </div>
                                                <div class="col-md-2 form-group">
                                                    <label>School Name </label>
                                                    <input type="text" id='school_name' required name="school_name" class="form-control">
                                                </div>
                                                <div class="col-md-2 form-group">
                                                    <label>Place </label>
                                                    <input type="text" id='place' required name="place" class="form-control">
                                                </div>
                                                <div class="col-md-2 form-group">
                                                    <label>Trial </label>
                                                    <input type="text" id='trial' required name="trial" class="form-control">
                                                </div>
                                                <div class="col-md-12 form-group">
                                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                                </div>
                                            </form>
            </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="../../../admin_dep/js/cbpFWTabs.js"></script>
<script type="text/javascript">
    (function() {
        [].slice.call(document.querySelectorAll('.sttabs')).forEach(function(el) {
            new CBPFWTabs(el);
        });
    })();
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
    <script>
        function getUsername(){
            var first_name = document.getElementById("first_name").value;
            var last_name = document.getElementById("last_name").value;
            var username = first_name.toLowerCase()+"_"+last_name.toLowerCase();
            document.getElementById("user_name").value = username;
        }
    </script>
@include('includes.footer')
