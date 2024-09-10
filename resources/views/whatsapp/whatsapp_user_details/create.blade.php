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
                    <label>User Whatsapp SID</label>
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

@include('includes.footer')
