@include('../includes.headcss')
    <link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Biomatrix Setting</h4>
            </div>
        </div>
        <div class="row" style=" margin-top: 25px;">
            <div class="panel-body white-box">
                <div class="col-lg-12 col-sm-12 col-xs-12">

                    <form action="{{ route('biomatrix.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        @csrf

                        <div class="col-md-6 form-group">
                            <label>Biomatrix Id</label>
                            <input type="text" id='biomatrix_id' required name="biomatrix_id" class="form-control">
                        </div>

                        
                        <div class="col-md-12 form-group">
                            <input type="submit" name="submit" value="Save" class="btn btn-success" >
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


@include('includes.footerJs')


@include('includes.footer')
