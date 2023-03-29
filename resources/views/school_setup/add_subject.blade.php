@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                @if(!isset($data))
                Add Subject
                @else
                Edit Subject
                @endif
                </h4>
            </div>            
        </div>
        <div class="card">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="@if (isset($data))
                          {{ route('subject_master.update', $data['id']) }}
                          @else
                          {{ route('subject_master.store') }}
                          @endif" enctype="multipart/form-data" method="post">
                            @if(!isset($data))
                            {{ method_field("POST") }}
                            @else
                            {{ method_field("PUT") }}
                            @endif
                        @csrf
                        <div class="row">                            
                            <div class="col-md-3 form-group">
                                <label>Subject Name</label>
                                <input type="text" id='subject_name' value="@if(isset($data['subject_name'])){{$data['subject_name']}}@endif" required name="subject_name" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Subject Code</label>
                                <input type="text" id='subject_code' value="@if(isset($data['subject_code'])){{$data['subject_code']}}@endif" required name="subject_code" class="form-control">
                            </div>                                                
                            <div class="col-md-3 form-group">
                                <label>Short Name</label>
                                <input type="text" id='short_name' value="@if(isset($data['short_name'])){{$data['short_name']}}@endif" required name="short_name" class="form-control">
                            </div>
                            <div class="col-md-2 form-group checkbox checkbox-info checkbox-circle mt-4">      
                                @php
                                $checked = "";
                                if(isset($data['subject_type']) && $data['subject_type'] != "")
                                    $checked = "checked";
                                @endphp                                                                    
                                <br><input type="checkbox" id="subject_type" name="subject_type" {{$checked}} value="Major">
                                <label for="subject_type"><b>Major</b></label> 
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
