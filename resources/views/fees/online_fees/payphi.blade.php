{{--@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Payment Mapping</h4>
            </div>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('online_fees.store') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("POST") }}
                    @csrf
                        <input type="hidden" name="map_company" value="payphi">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Merchant ID</label>
                                <input type="text" id='merchant_id' required name="merchant_id" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Key</label>
                                <input type="text" id='key' required name="key" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Fees Collect Type</label>
                                <div class="radio radio-info mt-2">
                                    <input type="radio" name="fees_type" value="fix" checked><label for="Fix"> Fix </label>
                                    <input type="radio" name="fees_type" value="dynamic"><label for="Dynamic"> Dynamic </label>
                                </div>
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                </center>
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
