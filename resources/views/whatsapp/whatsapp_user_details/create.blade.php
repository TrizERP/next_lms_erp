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
                    <input type="number" id='user_whatsapp_no' required name="user_whatsapp_no" class="form-control"
                           value="{{$data['user_whatsapp_no']}}">
                    @error('user_whatsapp_no')
                    <span style="color: red">{{$message}}</span>
                    @enderror
                </div>
                <div class="col-md-4 form-group">
                    <label>API Type</label>
                    <select id='api_type' name="api_type" class="form-control" required onchange="toggleApiFields(this.value)">
                        <option value="twilio" {{(isset($data['api_type']) && $data['api_type']=='twilio')?'selected':''}}>Twilio</option>
                        <option value="cloud_api" {{(isset($data['api_type']) && $data['api_type']=='cloud_api')?'selected':''}}>WhatsApp Cloud API</option>
                    </select>
                    @error('api_type')
                    <span style="color: red">{{$message}}</span>
                    @enderror
                </div>
                <div class="col-md-4 form-group twilio-field">
                    <label>User Whatsapp SID</label>
                    <input type="text" id='user_whatsapp_sid' name="user_whatsapp_sid" class="form-control"
                           value="{{$data['user_whatsapp_sid']}}">
                    @error('user_whatsapp_sid')
                    <span style="color: red">{{$message}}</span>
                    @enderror
                </div>
                <div class="col-md-4 form-group twilio-field">
                    <label>User Whatsapp Token</label>
                    <input type="text" id='user_whatsapp_token' name="user_whatsapp_token" class="form-control"
                           value="{{$data['user_whatsapp_token']}}">
                    @error('user_whatsapp_token')
                    <span style="color: red">{{$message}}</span>
                    @enderror
                </div>
                <div class="col-md-4 form-group cloud-api-field" style="display:none">
                    <label>Access Token</label>
                    <input type="text" id='cloud_api_access_token' name="cloud_api_access_token" class="form-control"
                           value="{{$data['cloud_api_access_token'] ?? ''}}">
                    @error('cloud_api_access_token')
                    <span style="color: red">{{$message}}</span>
                    @enderror
                </div>
                <div class="col-md-4 form-group cloud-api-field" style="display:none">
                    <label>Phone Number ID</label>
                    <input type="text" id='cloud_api_phone_number_id' name="cloud_api_phone_number_id" class="form-control"
                           value="{{$data['cloud_api_phone_number_id'] ?? ''}}">
                    @error('cloud_api_phone_number_id')
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

<script>
function toggleApiFields(type) {
    if (type === 'cloud_api') {
        document.querySelectorAll('.twilio-field').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.cloud-api-field').forEach(el => el.style.display = 'block');
    } else {
        document.querySelectorAll('.twilio-field').forEach(el => el.style.display = 'block');
        document.querySelectorAll('.cloud-api-field').forEach(el => el.style.display = 'none');
    }
}
document.addEventListener('DOMContentLoaded', function() {
    toggleApiFields(document.getElementById('api_type')?.value || 'twilio');
});
</script>

@include('includes.footer')
