@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Skill</h4>
            </div>
        </div>
            <div class="card">
                <form class="row" method="POST" action="{{route('skill_library.store')}}">
                    @csrf
                    <div class="col-md-4">
                        <label for="categroy">Select Category <span class="mdi mdi-asterisk" style="color:red"></span></label>
                        <select name="category" id="category" class="form-control" required>
                            @foreach($data['categoryArr'] as $key=>$value)
                            <option value="{{$value}}">{{$value}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="sub categroy">Select Sub Category</label>
                        <select name="sub_category" id="sub_category" class="form-control">
                            <option value="">select sub skills</option>
                            @foreach($data['subCategoryArr'] as $key=>$value)
                            <option value="{{$value}}">{{$value}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="title">Skill Name <span class="mdi mdi-asterisk" style="color:red"></span></label>
                        <input type="text" class="form-control" name="title" id="title" required>
                    </div>
                    <div class="col-md-4">
                        <label for="description">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="5" style="resize:vertical"></textarea>
                    </div>
                    <div class="col-md-12">
                        <center>
                            <input type="submit" value="Add Skill" name="submit" class="btn btn-primary">
                        </center>
                    </div>
            </form>
            </div>
        </div>
    </div>


@include('includes.footerJs')


@include('includes.footer')
@endsection
