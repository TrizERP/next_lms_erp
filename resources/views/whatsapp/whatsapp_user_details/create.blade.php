@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('includes.header')
@include('includes.sideNavigation')

<style>
    .email_error {
        width: 80%;
        height: 35px;
        font-size: 1.1em;
        color: #D83D5A;
        font-weight: bold;
    }

    .email_success {
        width: 80%;
        height: 35px;
        font-size: 1.1em;
        color: green;
        font-weight: bold;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Whatsapp User Details</h4>
            </div>
        </div>
        <div class="card">
            <!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
            @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
            @endif
            <form action="{{ route('whatsapp_user_details.store') }}" enctype="multipart/form-data" method="post">
                {{ method_field("POST") }}
                @csrf

                <div class="col-md-4 form-group">
                    <label>User whatsapp No </label>
                    <input type="text" id='user_whatsapp_no' required name="user_whatsapp_no" class="form-control"
                           value="{{$data['user_whatsapp_no']}}">
                    @error('user_whatsapp_no')
                    <span style="color: red">{{$message}}</span>
                    @enderror
                </div>
                <div class="col-md-4 form-group">
                    <label>User Whatsapp Sid</label>
                    <input type="text" id='user_whatsapp_sid' required name="user_whatsapp_sid" class="form-control"
                           value="{{$data['user_whatsapp_sid']}}">
                    @error('user_whatsapp_sid')
                    <span style="color: red">{{$message}}</span>
                    @enderror
                </div>
                <div class="col-md-4 form-group">
                    <label>User Whatsapp Token</label>
                    <input type="text" id='user_whatsapp_token' required name="user_whatsapp_token" class="form-control"
                           value="{{$data['user_whatsapp_token']}}">
                    @error('user_whatsapp_token')
                    <span style="color: red">{{$message}}</span>
                    @enderror
                </div>
                <input type="hidden" name="id" value="{{$data['id']}}">
                <div class="col-md-12 form-group">
                    <center>
                        <input type="submit" name="submit" id="Submit" value="Save" class="btn btn-success">
                    </center>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="../../../admin_dep/js/cbpFWTabs.js"></script>
<script type="text/javascript">
    (function () {
        [].slice.call(document.querySelectorAll('.sttabs')).forEach(function (el) {
            new CBPFWTabs(el);
        });
    })();
</script>
<script src="../../../plugins/bower_components/dropify/dist/js/drsopify.min.js"></script>
<script>
    var select = document.getElementById('amount_type');
    select.addEventListener('change', function () {
        var type = document.getElementById('amount_type').value;
        if (type == 2) {
            $('#payroll_per').removeClass('d-none');
        } else {
            $('#payroll_per').addClass('d-none');
        }
        //window.location.href = window.location.origin +'/payroll-deduction?type=' + type;
    }, false);
    $(document).ready(function () {
        $("#total_lecture_div").css("display", "none");

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
    function getUsername() {
        var first_name = document.getElementById("first_name").value;
        var last_name = document.getElementById("last_name").value;
        var username = first_name.toLowerCase() + "_" + last_name.toLowerCase();
        document.getElementById("user_name").value = username;
    }


    //START Unique Email Validation
    var email_state = false;
    $("#email").on("blur", function (event) {
        email_val = this.value;
        var path = "{{ route('ajax_checkEmailExist') }}";
        $.ajax({
            url: path,
            data: 'email=' + email_val,
            success: function (result) {
                if (result == 1) {
                    $("#email_error_span").removeClass().addClass("email_error").text('Email already taken');
                    email_state = true;
                } else {
                    $("#email_error_span").removeClass().addClass("email_success").text('Email available');
                    email_state = false;
                }
            }
        });
    });
    //END Unique Email Validation

    $("#user_profile_id").on("change", function (event) {
        var val1 = $.trim($("#user_profile_id").find("option:selected").text());

        if (val1 == 'Teacher' || val1 == 'TEACHER') {
            $("#total_lecture_div").css("display", "block");
        } else {
            $("#total_lecture_div").css("display", "none");
        }
    });

    $('#Submit').on('click', function () {

        if (email_state == true) {
            alert('Fix the errors in the form first');
            return false;
        }

    });


</script>
@include('includes.footer')