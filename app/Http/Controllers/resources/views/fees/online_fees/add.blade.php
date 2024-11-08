{{--@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Select Bank</h4>
            </div>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('online_fees.create') }}" enctype="multipart/form-data" method="get">
                    {{ method_field("GET") }}
                    @csrf
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>
                                    {{-- <input type="radio" name="payment_gatway_name" value="hdfc" checked> --}}
                                    <a href="{{ route('hdfcpayment') }}">
                                        <img style="height:50px;"
                                            src="{{url('/online_fees_logo/hdfcpayment.jpg')}}">
                                    </a>
                                </label>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>
                                    {{-- <input type="radio" name="payment_gatway_name" value="agree" checked> --}}
                                    <a href="{{ route('aggre_pay') }}">
                                        <img style="height:54px;" src="{{url('/online_fees_logo/aggre_pay.jpg')}}">
                                    </a>
                                </label>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>
                                    {{-- <input type="radio" name="payment_gatway_name" value="axis" checked> --}}
                                    <a href="{{ route('axis') }}">
                                        <img style="height:50px;" src="{{url('/online_fees_logo/axis.jpg')}}">
                                    </a>
                                </label>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>
                                    {{-- <input type="radio" name="payment_gatway_name" value="icici" checked> --}}
                                    <a href="{{ route('icici') }}">
                                        <img style="height:40px;" src="{{url('/online_fees_logo/icici.png')}}">
                                    </a>
                                </label>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>
                                    {{-- <input type="radio" name="payment_gatway_name" value="icici" checked> --}}
                                    <a href="{{ route('razorpay') }}">
                                        <img style="height:80px;" src="{{url('/online_fees_logo/Razorpay.png')}}">
                                    </a>
                                </label>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>
                                    {{-- <input type="radio" name="payment_gatway_name" value="payphi" checked> --}}
                                    <a href="{{ route('payphi') }}">
                                        <img style="height:80px;" src="{{url('/online_fees_logo/PayPhi.jpg')}}">
                                    </a>
                                </label>
                            </div>

                            <div class="col-md-6 form-group">


                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


@include('includes.footerJs')
@include('includes.footer')
@endsection
