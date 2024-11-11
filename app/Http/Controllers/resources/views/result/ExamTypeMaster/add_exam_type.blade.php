{{-- @include('includes.headcss') @include('includes.header') @include('includes.sideNavigation') --}} 
@extends('layout')
@section('container')

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
                        <form action="
                              @if (isset($data['Id']))
                              {{ route('exam_type_master.update', $data->Id) }}
                              @else
                              {{ route('exam_type_master.store') }}
                              @endif" enctype="multipart/form-data" method="post">

                            @if(!isset($data['Id']))
                            {{ method_field("POST") }}
                            @else
                            {{ method_field("PUT") }}
                            @endif

                            {{csrf_field()}}
                            
                            <div class="row">
                              <div class="col-md-6 form-group">
                                  <label>Code </label>
                                  <input type="text" id='Code' required name="Code" 
                                         value="@if(isset($data->Code)){{ $data->Code }}@else{{ $data['Code'] }}@endif" 
                                         class="form-control">
                              </div>
                              <div class="col-md-6 form-group">
                                  <label>Exam Type</label>
                                  <input type="text" id='ExamType' required name="ExamType" value="@if(isset($data->ExamType)) {{ $data->ExamType }} @endif" class="form-control">
                              </div>
                              <div class="col-md-6 form-group">
                                  <label>Short Name</label>
                                  <input type="text" id='ShortName' name="ShortName" value="@if(isset($data->ShortName)) {{ $data->ShortName }} @endif" class="form-control">
                              </div>
                              <div class="col-md-6 form-group">
                                  <label>Sort Order</label>
                                  <input type="text" id='SortOrder' required name="SortOrder" 
                                      value="@if(isset($data->SortOrder)){{ $data->SortOrder}}@else{{ $data['SortOrder']}}@endif" 
                                      class="form-control">
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
@endsection