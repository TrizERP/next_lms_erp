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
                    <form action="{{ route('result_book_master.update', $data['id']) }}" enctype="multipart/form-data" method="post">
                        {{ method_field("PUT") }}
                        {{csrf_field()}}
                        
                        <div class="row">
                        
                            {{ App\Helpers\SearchChain('6','multiple','grade,std',$data['grade'],$data['standard']) }}                        

                            <div class="col-md-6 form-group">
                                <label>CCE Line 1 : </label>
                                <textarea name="line1"  rows="4" cols="20" class="form-control">{{ $data['line1'] }}</textarea>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>CCE Line 2 : </label>
                                <textarea name="line2" rows="4" cols="20" class="form-control">{{ $data['line2'] }}</textarea>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>CCE Line 3 : </label>
                                <textarea name="line3" rows="4" cols="20" class="form-control">{{ $data['line3'] }}</textarea>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>CCE Line 4 : </label>
                                <textarea name="line4" rows="4" cols="20" class="form-control">{{ $data['line4'] }}</textarea>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Left Logo :</label>
                                <input type="file" name="left_logo" class="dropify" data-default-file="/storage/result/left_logo/{{ $data['left_logo'] }}" accept="image/*">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Right Logo :</label>
                                <input type="file" name="right_logo" class="dropify" data-default-file="/storage/result/right_logo/{{ $data['right_logo'] }}" accept="image/*">
                            </div>
                            <div class="col-md-4 form-group" style="margin-top: 25px">
                                <label>Status : </label>
                                <input type="radio" name="status" value="Y" {{ ($data['status']=="Y")? "checked" : "" }}> Yes
                                <input type="radio" name="status" value="N" {{ ($data['status']=="N")? "checked" : "" }}> No
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
