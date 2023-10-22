{{--@include('includes.lmsheadcss')--}}
@extends('lmslayout')
@section('container')
<style>
.tooltip-inner {
    max-width: 1100px !important;
}

</style>
{{--@include('includes.header')
@include('includes.sideNavigation')--}}
<!-- Content main Section -->
<div id="page-wrapper">
    <div class="container-fluid mb-5">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Add Content Category</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Add Content Category</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="card border-0">
            <div class="card-body">
                <form action="@if (isset($data['cc_data'])){{ route('lms_content_category.update', $data['cc_data']['id']) }}@else{{ route('lms_content_category.store') }}@endif" method="post" enctype='multipart/form-data'>
                    @if(!isset($data['cc_data']))
                    {{ method_field("POST") }}
                    @else
                    {{ method_field("PUT") }}
                    @endif
                    @csrf

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="per_value">Content Category Name</label>
                                <input type="text" class="form-control" id="category_name" name="category_name" value="@if(isset($data['cc_data']['category_name'])){{$data['cc_data']['category_name']}}@endif">
                            </div>
                        </div>

                        <div class="col-md-4 mt-4">
                            <div class="form-group">
                                <button class="btn btn-primary" type="submit">Submit</button>
                            </div>
                        </div>
                    </div>


                </form>
            </div>
        </div>
    </div>
</div>

@include('includes.lmsfooterJs')
@include('includes.footer')
@endsection
