@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Mapping</h4>
            </div>
        </div>
        <div class="row" style=" margin-top: 25px;">
            <div class="panel-body white-box">
                <div class="col-lg-12 col-sm-12 col-xs-12">

                    <form action="{{ route('fees_title.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        @csrf


                        <div class="col-md-12 form-group">
                            <label>
                                <input type="radio" name="payment_gatway_name" value="hdfc" checked>
                                <img style="height:100px;" src="{{url('/online_fees_logo/hdfcpayment.jpg')}}">
                            </label>
                            
                                
                        </div>
                        <div class="col-md-12 form-group">
                            <label>
                                <input type="radio" name="payment_gatway_name" value="agree" checked>
                                <img style="height:64px;" src="{{url('/online_fees_logo/aggre_pay.jpg')}}">
                            </label>
                        </div>
                        <div class="col-md-12 form-group">
                            <label>
                                <input type="radio" name="payment_gatway_name" value="axis" checked>
                                <img style="height:64px;" src="{{url('/online_fees_logo/axis.jpg')}}">
                            </label>
                        </div>

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Continue" class="btn btn-success">
                            </center>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


@include('includes.footerJs')
@include('includes.footer')