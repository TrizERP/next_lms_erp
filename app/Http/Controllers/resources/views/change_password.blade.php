@include('includes.headcss')
<link href="../plugins/bower_components/switchery/dist/switchery.min.css" rel="stylesheet" />
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Change Password</h4>
            </div>
        </div>
        <div class="card">
            <div class="col-md-12 mb-5">
                @if(Session::get('user_profile_name') == 'Admin')
                    <a href="#" class="btn btn-info pull-right m-l-20 hidden-xs hidden-sm waves-effect waves-light">
                        Your
                        Id @if(isset($data['userdata']['user_name'])){{str_pad($data['userdata']['id'], 5, '0', STR_PAD_LEFT)}}@endif
                    </a>
                @endif
            </div>
            <div id="errorbox" style="display:none;">
                <div class="alert alert-danger alert-block">
                    <strong>Password and confirm password does not match.</strong>
                </div>
            </div>

            @if (isset($data['message']) && $data['message'] == 'Password Change Successfully')
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
            @endif

            <form action="{{ route('change_password.store') }}" enctype="multipart/form-data" method="post"
                  onSubmit="return checkPassword();">
                @csrf
                <div class="row">
                    @if(Session::get('user_profile_name') == 'Admin')
                        <div class="col-md-4 form-group">
                            <label>School Name </label>
                            <input type="text" id='school_name'
                                   @if(isset($data['schooldata']['SchoolName'])) value="{{$data['schooldata']['SchoolName']}}"
                                   @endif required name='school_name' readonly="readonly" class="form-control">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>User Name </label>
                            <input type="text" id='contact_person'
                                   @if(isset($data['userdata']['user_name'])) value="{{$data['userdata']['user_name']}}"
                                   @endif required name='contact_person' readonly="readonly" class="form-control">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Mobile </label>
                        <input type="text" @if(isset($data['userdata']['mobile'])) value="{{$data['userdata']['mobile']}}" @endif  readonly="readonly" id='mobile' required name='mobile' class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Email </label>
                        <input type="text" id='email' @if(isset($data['userdata']['email'])) value="{{$data['userdata']['email']}}" @endif  required name='email' class="form-control" readonly="readonly">
                    </div>
                    @endif
                    <div class="col-md-4 form-group">
                        <label>Password </label>
                        <input type="password" id='password' required name='password' class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Confirm Password </label>
                        <input type="password" id='confirmpassword' required name='confirmpassword'
                               class="form-control">
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Change Password" class="btn btn-success">
                        </center>
                    </div>
                </div>
            </form>
        </div>
        @if(Session::get('user_profile_name') == 'Admin')
            <div class="card">
                <h3 class="box-title">Account Settings</h3>
                <div class="list-group tickets">
                    <div class="list-group-item">
                        <span class="sp-setting-left">
                            <strong>Weekly Summary</strong>. Receive a quick summary for the past one week via email. Default is ON.
                            Receive it every:
                                <select name="updateDate" id="updateDate" class="form-control form-material-input"
                                        style="width:350px;display:inline-block;font-size: 14px;padding: 6px;">
                                    <option value="0">Sunday (For the prior week ending Saturday)</option>
                                    <option value="1">Monday (For the prior week ending Sunday)</option>
                                    <option value="2" selected="">Tuesday (For the prior week ending Monday)</option>
                                    <option value="3">Wednesday (For the prior week ending Tuesday)</option>
                                    <option value="4">Thursday (For the prior week ending Wednesday)</option>
                                    <option value="5">Friday (For the prior week ending Thursday)</option>
                                    <option value="6">Saturday (For the prior week ending Friday)</option>
                                </select>

                        </span>
                        <span class="sp-setting-right pull-right"><input type="checkbox" checked class="js-switch" data-size="small" data-color="#13dafe" /></span>
                    </div>
                    <div class="list-group-item">
                        <span class="sp-setting-left"><strong>Product &amp; Service Updates.</strong> Receive important product and service availability updates from the Triz team via email. Default is ON.</span>
                        <span class="sp-setting-right pull-right"><input type="checkbox" checked class="js-switch" data-size="small" data-color="#13dafe" /></span>
                    </div>
                    <div class="list-group-item">
                        <span class="sp-setting-left"><strong>Receive Triz Reminders via Email.</strong> Receive important assignment reminders,  and user sent messages via email. Default is ON.</span>
                        <span class="sp-setting-right pull-right"><input type="checkbox" checked class="js-switch" data-size="small" data-color="#13dafe" /></span>
                    </div>
                    <div class="list-group-item">
                        <span class="sp-setting-left"><strong>Time Sensitive Help Videos and Instructions.</strong> Receive how-to videos and insightful instructions to get the best out of Triz features. Default is ON.</span>
                        <span class="sp-setting-right pull-right">
                            <input type="checkbox" data-size="small" checked class="js-switch" data-color="#13dafe" />
                        </span>
                    </div>
                </div>
            </div>
        @endif
        </div>
    </div>
</div>

@include('includes.footerJs')

<script src="../plugins/bower_components/switchery/dist/switchery.min.js"></script>

<script>
        $(function() {
            // Switchery
            var elems = Array.prototype.slice.call(document.querySelectorAll('.js-switch'));
            $('.js-switch').each(function() {
                new Switchery($(this)[0], $(this).data());
            });
            // For select 2
            $(".select2").select2();
            $('.selectpicker').selectpicker();
            //Bootstrap-TouchSpin
            $(".vertical-spin").TouchSpin({
                verticalbuttons: true
            });
            var vspinTrue = $(".vertical-spin").TouchSpin({
                verticalbuttons: true
            });
            if (vspinTrue) {
                $('.vertical-spin').prev('.bootstrap-touchspin-prefix').remove();
            }
            $("input[name='tch1']").TouchSpin({
                min: 0,
                max: 100,
                step: 0.1,
                decimals: 2,
                boostat: 5,
                maxboostedstep: 10,
                postfix: '%'
            });
            $("input[name='tch2']").TouchSpin({
                min: -1000000000,
                max: 1000000000,
                stepinterval: 50,
                maxboostedstep: 10000000,
                prefix: '$'
            });
            $("input[name='tch3']").TouchSpin();
            $("input[name='tch3_22']").TouchSpin({
                initval: 40
            });
            $("input[name='tch5']").TouchSpin({
                prefix: "pre",
                postfix: "post"
            });
            // For multiselect
            $('#pre-selected-options').multiSelect();
            $('#optgroup').multiSelect({
                selectableOptgroup: true
            });
            $('#public-methods').multiSelect();
            $('#select-all').click(function() {
                $('#public-methods').multiSelect('select_all');
                return false;
            });
            $('#deselect-all').click(function() {
                $('#public-methods').multiSelect('deselect_all');
                return false;
            });
            $('#refresh').on('click', function() {
                $('#public-methods').multiSelect('refresh');
                return false;
            });
            $('#add-option').on('click', function() {
                $('#public-methods').multiSelect('addOption', {
                    value: 42,
                    text: 'test 42',
                    index: 0
                });
                return false;
            });
        });
        </script>

<script>
    function checkPassword()
    {
        var password = document.getElementById('password').value;
        var confirmpassword = document.getElementById('confirmpassword').value;

        if(password != confirmpassword)
        {
            document.getElementById('errorbox').style.display = "block";
            return false;
        }else{
            document.getElementById('errorbox').style.display = "none";
            return true;
        }
    }
</script>
@include('includes.footer')
