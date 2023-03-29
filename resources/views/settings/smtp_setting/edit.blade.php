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
                    <form action="{{ route('smtp_setting.update', $data->id) }}" enctype="multipart/form-data" method="post">
                        {{ method_field("PUT") }}
                        {{csrf_field()}}

                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Email</label>
                                <input type="text" id='url' required name="email" value="{{ $data->gmail }}" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Password</label>
                                <input type="text" id='parameter' required name="password" value="{{ $data->password }}" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Server Address</label>
                                <input type="text" id='mobile_var' required name="server_address" value="{{ $data->server_address }}" class="form-control">
                            </div>
                            <div class="col-md-4 form-group ml-0 mr-0">
                                <label>Port</label>
                                <input type="text" id='text_var' required name="port" value="{{ $data->port }}" class="form-control">
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


@include('includes.footer')
