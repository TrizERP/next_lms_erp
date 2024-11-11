{{--@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')--}}
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Update Fees Structurefdasf</h4>
            </div>
        </div>
        <div class="row" style=" margin-top: 25px;">
            <div class="panel-body white-box">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('fees_breackoff.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        @csrf
                        <div class="col-md-12 form-group">
                            {{ App\Helpers\SearchChain('4','multiple','grade,std,div') }}
                        </div>


                        @foreach ($data['data']['ddMonth'] as $id => $val)
                            <div class="col-md-3 form-group">
                                <input name="month[{{$id}}]" type="checkbox">
                                <label>{{$val}}</label>
                            </div>

                        @endforeach

                        <div class="col-md-12 form-group">
                            <input type="submit" name="submit" value="Add Structure" class="btn btn-success" >
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
