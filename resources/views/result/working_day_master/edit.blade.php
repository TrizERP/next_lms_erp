@include('../includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="card">            
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('working_day_master.update', $data['id']) }}" enctype="multipart/form-data" method="post">
                        {{ method_field("PUT") }}
                        {{csrf_field()}}

                        <div class="row">
                        
                            {{ App\Helpers\SearchChain('4','single','grade,std',$data['grade_id'],$data['standard_id']) }}
                            {{ App\Helpers\TermDD($data['term_id']) }}

                            <div class="col-md-4 form-group ml-0 mr-0">
                                <label>Total Working Days: </label>
                                <input type="text" name="total_working_day" value="{{$data['total_working_day']}}" class="form-control" />
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


@include('includes.footerJs')
<script src="../../../plugins/bower_components/dropify/dist/js/dropify.min.js"></script>
<script>
$(document).ready(function () {
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
    drEvent.on('dropify.beforeClear', function (event, element) {
        return confirm("Do you really want to delete \"" + element.file.name + "\" ?");
    });
    drEvent.on('dropify.afterClear', function (event, element) {
        alert('File deleted');
    });
    drEvent.on('dropify.errors', function (event, element) {
        console.log('Has Errors');
    });
    var drDestroy = $('#input-file-to-destroy').dropify();
    drDestroy = drDestroy.data('dropify')
    $('#toggleDropify').on('click', function (e) {
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
    CKEDITOR.replace('line1');
    CKEDITOR.replace('line2');
    CKEDITOR.replace('line3');
    CKEDITOR.replace('line4');
</script>

@include('includes.footer')
