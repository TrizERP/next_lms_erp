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
                <h1 class="h4 mb-3">Add Leader Board Master</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Add Leader Board Master</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="card border-0">
            <div class="card-body">
                <form action="@if (isset($data['lbmaster_data']))
                {{ route('lb_master.update', $data['lbmaster_data']['id']) }}
                @else
                {{ route('lb_master.store') }}
                @endif " method="post" enctype='multipart/form-data'
                ">
                @if(!isset($data['lbmaster_data']))
                    {{ method_field("POST") }}
                @else
                    {{ method_field("PUT") }}
                @endif
                @csrf

                <div class="row">
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        @if(isset($data['lbmaster_data']))
                        {{ App\Helpers\SearchChain('4','','grade,std',$data['lbmaster_data']['grade_id'],$data['lbmaster_data']['standard_id']) }}
                        @else
                        {{ App\Helpers\SearchChain('4','','grade,std') }}
                        @endif

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="module_name">Module Name</label>
                                <select id="module_name" name="module_name" class="cust-select form-control" required
                                        onchange="show_div(this.value);">
                                    <option value="">Select</option>
                                    <option value="login" @if(isset($data['lbmaster_data']['module_name'])) @if($data['lbmaster_data']['module_name']=="login") selected='selected' @endif @endif>Login</option>
                                    <option value="exampass" @if(isset($data['lbmaster_data']['module_name'])) @if($data['lbmaster_data']['module_name']=="exampass") selected='selected' @endif @endif>Exam Passed</option>
                                    <option value="examfail" @if(isset($data['lbmaster_data']['module_name'])) @if($data['lbmaster_data']['module_name']=="examfail") selected='selected' @endif @endif>Exam Failed</option>
                                    <option value="homework" @if(isset($data['lbmaster_data']['module_name'])) @if($data['lbmaster_data']['module_name']=="homework") selected='selected' @endif @endif>Homework</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4" id="percentage_div">
                            <div class="form-group">
                                <label for="per_value">Percentage</label>
                                <input type="text" class="form-control" id="per_value" name="per_value" value="@if(isset($data['lbmaster_data']['per_value'])){{$data['lbmaster_data']['per_value']}}@endif">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control" id="description" name="description">@if(isset($data['lbmaster_data']['description'])){{$data['lbmaster_data']['description']}}@endif</textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="points">Points</label>
                                <input type="number" class="form-control" id="points" name="points" placeholder="Points" value="@if(isset($data['lbmaster_data']['points'])){{$data['lbmaster_data']['points']}}@endif"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="module_name">Select Icons</label>
                                <div class="font-awesome">
                                    <select id="icon" name="icon" class="form-control fa" required>
                                        <option value="">Select</option>
                                        <option value="xf091" class="fa" style="color:black;font-size:30px;"
                                                @if(isset($data['lbmaster_data']['icon'])) @if($data['lbmaster_data']['icon']=="xf091") selected='selected' @endif @endif>
                                            &#xf091; - Trophy
                                        </option>
                                        <option value="xf005" class="fa" style="color:black;font-size:30px;"
                                                @if(isset($data['lbmaster_data']['icon'])) @if($data['lbmaster_data']['icon']=="xf005") selected='selected' @endif @endif>
                                            &#xf005; - Star
                                        </option>
                                        <option value="xf089" class="fa" style="color:black;font-size:30px;"
                                                @if(isset($data['lbmaster_data']['icon'])) @if($data['lbmaster_data']['icon']=="xf089") selected='selected' @endif @endif>
                                            &#xf089; - Half Star
                                        </option>
                                        <option value="xf118" class="fa" style="color:black;font-size:30px;"
                                                @if(isset($data['lbmaster_data']['icon'])) @if($data['lbmaster_data']['icon']=="xf118") selected='selected' @endif @endif>
                                            &#xf118; - Good
                                        </option>
                                        <option value="xf165" class="fa" style="color:black;font-size:30px;"
                                                @if(isset($data['lbmaster_data']['icon'])) @if($data['lbmaster_data']['icon']=="xf165") selected='selected' @endif @endif>
                                            &#xf165; - Thumbs down
                                        </option>
                                        <option value="xf164" class="fa" style="color:black;font-size:30px;"
                                                @if(isset($data['lbmaster_data']['icon'])) @if($data['lbmaster_data']['icon']=="xf164") selected='selected' @endif @endif>
                                            &#xf164; - Thumbs up
                                        </option>
                                        <option value="xf11a" class="fa" style="color:black;font-size:30px;"
                                                @if(isset($data['lbmaster_data']['icon'])) @if($data['lbmaster_data']['icon']=="xf11a") selected='selected' @endif @endif>
                                            &#xf11a; - Bad
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Show</label><br>
                            <input type="checkbox" checked value="1" id="show_hide" name="show_hide"
                                   @if(isset($data['lbmaster_data']['status'])) @if($data['lbmaster_data']['status'] == 1) checked @endif @endif>
                        </div>
                    </div>

                </div>

                <button class="btn btn-primary" type="submit">Submit</button>
                </form>
            </div>
        </div>
    </div>
</div>
@include('includes.lmsfooterJs')

<script type="text/javascript">

    $(document).ready(function () {
        $("#percentage_div").hide();
    });


    function show_div(a) {
        if (a == "exampass" || a == "examfail") {
            $("#percentage_div").show();
            $("#per_value").attr("required", true);
        } else {
            $("#percentage_div").hide();
            $("#per_value").attr("required", false);
        }
    }

</script>
@include('includes.footer')
@endsection
