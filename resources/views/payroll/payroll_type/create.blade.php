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
                <h4 class="page-title">Payroll Type</h4>
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
            <form action="{{ route('payroll_type.store') }}" enctype="multipart/form-data" method="post">
                {{ method_field("POST") }}
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Payroll Type: </label>
                        @if($payrollType['payroll_type'] == 1)
                        <input type="radio" name="type" value="1" checked> Allowance
                            <input type="radio" name="type" value="2"> Deduction
                        @elseif($payrollType['payroll_type'] == 2)
                            <input type="radio" name="type" value="1" > Allowance
                        <input type="radio" name="type" value="2" checked> Deduction
                        @endif
                        @error('type')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Payroll Type Name </label>
                        <input type="text" id='payroll_name' required name="payroll_name" class="form-control" value="{{$payrollType['payroll_name']}}" required>
                        @error('payroll_name')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>
                    @php
                    $class = 'Flat';
                    if(isset($payrollType['amount_type']) && $payrollType['amount_type'] == 2) {
                        $class = 'Percentage';
                    }
                    @endphp
                    <div class="col-md-4 form-group" id="payroll_per">
                        <label id="typeName"> {{$class}} </label>
                        <input type="text" id='payroll_percentage' name="payroll_percentage" class="form-control" value="{{$payrollType['payroll_percentage']}}" autocomplete="off">
                        @error('payroll_percentage')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Amount Type</label>
                        <select name="amount_type" id="amount_type" class="form-control" require>
                            @if($payrollType['amount_type'] == 1)
                            <option value="1" selected> Flat </option>
                            <option value="2"> Percentage </option>
                            @elseif($payrollType['amount_type'] == 2)
                                <option value="1"> Flat </option>
                                <option value="2" selected> Percentage </option>
                            @endif
                        </select>
                        @error('amount_type')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Status</label>
                        <select name="status" id="status" class="form-control" require>
                            @if($payrollType['status'] == 1)
                            <option value="0"> Disable </option>
                            <option value="1" selected> Enable </option>
                            @else
                                <option value="0" selected> Disable </option>
                                <option value="1"> Enable </option>
                            @endif
                        </select>
                        @error('status')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Day Wise Count: </label>
                        @if($payrollType['day_count'] == 0)
                            <input type="radio" name="day_count" value="0" checked> Yes
                            <input type="radio" name="day_count" value="1"> No
                        @elseif($payrollType['day_count'] == 1)
                            <input type="radio" name="day_count" value="0" > Yes
                            <input type="radio" name="day_count" value="1" checked> No
                        @endif
                        
                    </div>

                    <input type="hidden" name="id" value="{{$payrollType['id']}}">
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" id="Submit" value="Save" class="btn btn-success" >
                        </center>
                    </div>
                </div>
            </form>
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
<script src="../../../plugins/bower_components/dropify/dist/js/drsopify.min.js"></script>
<script>
    var select = document.getElementById('amount_type');
    select.addEventListener('change', function () {
        var type = document.getElementById('amount_type').value;
        if(type == 2) {
            $('#typeName').text('Percentage');
        } else {
            $('#typeName').text('Flat');
        }
        //window.location.href = window.location.origin +'/payroll-deduction?type=' + type;
    }, false);
    $(document).ready(function() {
        $("#total_lecture_div").css("display","none");

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


    //START Unique Email Validation
    var email_state = false;
    $("#email").on( "blur", function( event ) {
        email_val = this.value;
        var path = "{{ route('ajax_checkEmailExist') }}";
        $.ajax({
            url:path,
            data:'email='+email_val,
            success:function(result){
                if(result == 1)
                {
                    $("#email_error_span").removeClass().addClass("email_error").text('Email already taken');
                    email_state = true;
                }
                else
                {
                    $("#email_error_span").removeClass().addClass("email_success").text('Email available');
                    email_state = false;
                }
            }
        });
    });
    //END Unique Email Validation

    $("#user_profile_id").on( "change", function( event ) {
        var val1 = $.trim($("#user_profile_id").find("option:selected").text());

        if(val1 == 'Teacher' || val1 == 'TEACHER')
        {
            $("#total_lecture_div").css("display","block");
        }
        else
        {
            $("#total_lecture_div").css("display","none");
        }
    });

    $('#Submit').on('click', function(){

        if(email_state == true)
        {
            alert('Fix the errors in the form first');
            return false;
        }

    });


</script>
@include('includes.footer')
