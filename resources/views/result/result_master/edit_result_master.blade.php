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
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('result_master.update', $data['id']) }}" enctype="multipart/form-data" method="post">
                        {{ method_field("PUT") }}
                        {{csrf_field()}}
                        <div class="row">
                            {{ App\Helpers\TermDD($data['term']) }}                        
                            {{ App\Helpers\SearchChain('4','single','grade,std',$data['grade'],$data['standard_id']) }}
                            
                            <div class="col-md-4 form-group">
                                <label>Result Date</label>
                                <input type="text" id='result_date' required name="result_date" value="{{ $data['result_date'] }}" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>School Re-Open Date</label>
                                <input type="text" id='reopen_date' required name="reopen_date" value="{{ $data['reopen_date'] }}" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Vaction Start Date</label>
                                <input type="text" id='vaction_start_date' required name="vaction_start_date" value="{{ $data['vaction_start_date'] }}" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Vaction End Date</label>
                                <input type="text" id='vaction_end_date' required name="vaction_end_date" value="{{ $data['vaction_end_date'] }}" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Remove Fail Percentage : </label>
                                <input type="radio" name="remove_fail_per" value="y" {{ ($data['remove_fail_per']=="y")? "checked" : "" }}> Yes
                                <input type="radio" name="remove_fail_per" value="n" {{ ($data['remove_fail_per']=="n")? "checked" : "" }}> No
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Display Option Subject In Mark Sheet : </label>
                                <input type="radio" name="optional_subject_display" value="y" {{ ($data['optional_subject_display']=="y")? "checked" : "" }}> Yes
                                <input type="radio" name="optional_subject_display" value="n" {{ ($data['optional_subject_display']=="n")? "checked" : "" }}> No
                            </div>
                            <div class="col-md-12 form-group">
                                <label>Result Remark : </label>
                                <input type="radio" name="result_remark" value="grade_master" {{ ($data['result_remark']=="grade_master")? "checked" : "" }}> From Grade Master
                                <input type="radio" name="result_remark" value="individual" {{ ($data['result_remark']=="individual")? "checked" : "" }}> Individual Student Wise
                            </div>
                            
                            <div class="col-md-4 form-group">
                                <label>Class Teacher Signature</label>
                                <input type="file" name="teacher_sign" id="teacher_sign" data-default-file="/storage/result/teacher_sign/{{ $data['teacher_sign'] }}" accept="image/*" class="dropify">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Principle Sign</label>
                                <input type="file" name="principal_sign" id="principal_sign" data-default-file="/storage/result/principal_sign/{{ $data['principal_sign'] }}" accept="image/*" class="dropify">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Director Signature</label>
                                <input type="file" name="director_signatiure" id="director_signatiure"  data-default-file="/storage/result/director_sign/{{ $data['director_signatiure'] }}" accept="image/*" class="dropify">
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

@include('includes.footer')
