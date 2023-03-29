@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<link href="{{ asset("/plugins/bower_components/dropzone-master/dist/dropzone.css") }}" rel="stylesheet">
<link href="{{ asset("/plugins/bower_components/summernote/dist/summernote.css") }}" rel="stylesheet">
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Send Email Other</h4>
            </div>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-lg-6">
                    @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                    @endif
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
                    <form action="{{ route('send_other_mail') }}" enctype="multipart/form-data" method="post" onsubmit="return postForm();">
                            {{ method_field("POST") }}
                            {{csrf_field()}}
                            <?php
                                // $all_emails = implode(',',$data);
                            ?>
                        <h4>To Email</h4>
                        <div class="form-group">
                            <input type="text" id="all_email" name="all_email" class="form-control" placeholder="To email">
                        </div>
                        {{-- <input type="hidden" name="all_email" value= > --}}
                        <h4>Subject</h4>
                        <div class="form-group">
                            <input type="text" id="example-subject" name="example-subject" class="form-control" placeholder="Subject">
                        </div>
                        <h4>Attachment</h4>
                        <input type="file" name="fileToUpload" id="fileToUpload" style="margin-bottom: 33px;"><br>
                        <h4>Mail Body</h4>
                        <textarea id="summernote" name="content"></textarea>

                        <button type="submit" class="btn btn-success mt-3"><i class="far fa-envelope"></i> Send</button>
                        <button type="submit" class="btn btn-dark mt-3">Discard</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


@include('includes.footerJs')
<script src="{{ asset("/plugins/bower_components/summernote/dist/summernote.min.js") }}"></script>
<!-- <script src="{{ asset("/plugins/bower_components/dropzone-master/dist/dropzone.js") }}"></script> -->

<script>
// var myDropzone = new Dropzone("#dzid", {
//   url: "/file/post",
//   acceptedFiles: accept,
//   uploadMultiple: false,
//   createImageThumbnails: false,
//   addRemoveLinks: true,
//   maxFiles: 3,
//   maxfilesexceeded: function(file) {
//     this.removeAllFiles();
//     this.addFile(file);
//   },
//   init: function() {
//     this.on('error', function(file, errorMessage) {
//       if (errorMessage.indexOf('Error 404') !== -1) {
//         var errorDisplay = document.querySelectorAll('[data-dz-errormessage]');
//         errorDisplay[errorDisplay.length - 1].innerHTML = 'Error 404: The upload page was not found on the server';
//       }
//     });
//   }
// });

$('#summernote').summernote({
        placeholder: 'Type your email Here',
        tabsize: 2,
        height: 250
    });
    var postForm = function() {
        // alert("herer");
        // alert($('#summernote').summernote('code'));
	var content = $('textarea[name="content"]').html($('#summernote').summernote('code'));
    // alert($('textarea[name="content"]').html());

}
    // $("#dzid").dropzone({ url: "/temp_file_upload/file_upload.php" });
</script>
<script>
    $(function () {
        var $tblChkBox = $("input:checkbox");
        $("#ckbCheckAll").on("click", function () {
            $($tblChkBox).prop('checked', $(this).prop('checked'));
        });
    });
</script>
@include('includes.footer')
