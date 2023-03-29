@include('../includes.headcss')
    <link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">SMTP Setting</h4>
            </div>
        </div>       
            <div class="card">
                <div class="col-lg-12 col-sm-12 col-xs-12">

                    <form action="{{ route('smtp_setting.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        @csrf

                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Email</label>
                                <input type="text" id='gmail' required name="email" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Password</label>
                                <div class="checkbox checkbox-info">
                                    <input type="text" id='password' required name="password" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Server Address</label>
                                <div class="checkbox checkbox-info">
                                    <input type="text" id='server_address' required name="server_address" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4 form-group ml-0 mr-0">
                                <label>Port</label>
                                <div class="checkbox checkbox-info">
                                    <input type="text" id='port' required name="port" class="form-control">
                                </div>
                            </div>

                            <div class="col-md-12 form-group">
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>   
</div>


@include('includes.footerJs')


@include('includes.footer')
