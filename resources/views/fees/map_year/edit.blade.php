{{--
@include('../includes.headcss')
--}}
@extends('layout')
@section('container')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
{{--@include('../includes.header')
@include('../includes.sideNavigation')--}}


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Map Year</h4>
            </div>
        </div>
            <div class="card">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('map_year.update', $data['id']) }}" enctype="multipart/form-data" method="post">
                        {{ method_field("PUT") }}
                        {{csrf_field()}}

                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="fee_interval">Select Fee Type:</label>
                                <select name="fee_type" id="fee_type" class="form-control" required>
                                    <option selected>Select Type</option>
                                    <option value="yearly_fees" @if($data['type'] == 'yearly_fees') selected @endif>Yearly Fees</option>
                                    <option value="half_year_fees" @if($data['type'] == 'half_year_fees') selected @endif>Half Year Fees</option>
                                    <option value="quarterly_fees" @if($data['type'] == 'quarterly_fees') selected @endif>Quarterly Fees</option>
                                    <option value="monthly_fees" @if($data['type'] == 'half_year_fees') selected @endif>Monthly Fees</option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group ml-0 mr-0">
                                <label>From Month</label>
                                <select name="start_month" id="title" class="form-control van" required>
                                    <option value="">--Select--</option>
                                    <?php
                                    foreach ($data['data']['ddMonth'] as $id => $arr) {
                                        if ($data['from_month'] == $id) {
                                            $selected = "selected=selected";
                                        } else {
                                            $selected = "";
                                        }
                                        echo "<option $selected value='$id'>$arr</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3 form-group ml-0">
                                <label>To Month</label>
                                <select name="end_month" id="title" class="form-control van" required>
                                    <option value="">--Select--</option>
                                    <?php
                                    foreach ($data['data']['ddMonth'] as $id => $arr) {
                                        if ($data['to_month'] == $id) {
                                            $selected = "selected=selected";
                                        } else {
                                            $selected = "";
                                        }
                                        echo "<option $selected value='$id'>$arr</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-12 form-group ml-0">
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
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
@endsection
