@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit Head Type</h4>
            </div>
        </div>
        <div class="card">
            <!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
            @if ($sessionData = Session::get('data'))
                @if($sessionData['status_code'] == 1)
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
                @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <div class="row">            
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('fees_head_type_master.update', $data['id']) }}" enctype="multipart/form-data" method="POST">
                        {{ method_field("PUT") }}                    
                        @csrf 
                        <div class="row">                        
                            <div class="col-md-3 form-group">
                                <label>Code </label>
                                <input type="text" id='code' value="@if(isset($data['code'])){{ $data['code'] }}@endif" required name="code" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Head Title</label>
                                <input type="text" id='head_title' value="@if(isset($data['head_title'])){{ $data['head_title'] }}@endif" required name="head_title" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Description </label>
                                <input type="text" id='description' value="@if(isset($data['description'])){{ $data['description'] }}@endif" required name="description" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Mandetory </label>
                                <div class="checkbox checkbox-info">
                                    <input id="mandatory" name="mandatory"  @if(isset($data['mandatory']))@if("1" == $data['mandatory']) checked @endif @endif value="1" type="checkbox">
                                    <label for="mandatory"> Mandatory </label>
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
