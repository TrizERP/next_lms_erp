@include('includes.headcss')
<link href="{{ asset('/plugins/bower_components/summernote/dist/summernote.css') }}" rel="stylesheet" />
@include('includes.header')
@include('includes.sideNavigation')
<style>
.popover {
  max-width: 700px;
}
</style>
<div id="page-wrapper">
    <div class="container-fluid">
     <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">                              
                    @if(isset($data))
                    Add Template
                    @else
                    Edit Template
                    @endif                    
                </h4>
            </div>            
        </div>       
            <div class="card">
                <div class="row">
                    @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                    @endif
                       
                    <div class="col-lg-12 col-sm-12 col-xs-12">  
                        <form enctype='multipart/form-data' action="
                          @if (isset($data['template_data']['id']))
                          {{ route('templatemaster.update',$data['template_data']['id']) }}
                          @else
                          {{ route('templatemaster.store') }}
                          @endif" method="post">

                        @if(!isset($data['template_data']['id']))                        
                        {{ method_field("POST") }}
                        @else                        
                        {{ method_field("PUT") }}
                        @endif

                        {{csrf_field()}}     
                            <div class="row">                   
                                <div class="col-md-12 form-group">                   
                                    <a href="{{ route('view_all_tag') }}" target="_blank" style="font-size: 18px;font-weight: 600;color: #25bdea;">Click To View Default Tags</a>                                    
                                </div>

                                <div class="col-md-4 form-group">                   
                                    <label class="control-label">Module Name</label>
                                    <select class="form-control" name="module_name" required>
                                        <option value="">Select</option>
                                        <option value="Fees" @if(isset($data['template_data']['module_name'])) @if($data['template_data']['module_name'] == "Fees") selected @endif @endif >Fees</option>                                        
                                        <option value="Bonafide" @if(isset($data['template_data']['module_name'])) @if($data['template_data']['module_name'] == "Bonafide") selected @endif @endif >Bonafide</option>                                        
                                        <option value="Transfer Certificate" @if(isset($data['template_data']['module_name'])) @if($data['template_data']['module_name'] == "Transfer Certificate") selected @endif @endif >Transfer Certificate</option>   
                                        <option value="Student Fees Certificate" @if(isset($data['template_data']['module_name'])) @if($data['template_data']['module_name'] == "Student Fees Certificate") selected @endif @endif >Student Fees Certificate</option>                                        
                                    </select>
                                </div>
                                
                                <div class="col-md-8 form-group">
                                    <label>Title</label>
                                    <input type="text" id='title' name="title" value="@if(isset($data['template_data']['title'])) {{$data['template_data']['title'] }} @endif" class="form-control" required>                                                                        
                                </div>

                                <div class="col-md-12 form-group">                                                   
                                    <label>HTML Content</label>
                                    <textarea class="summernote" id="html_content" name="html_content" required>
                                        @if(isset($data['template_data']['html_content'])) {{$data['template_data']['html_content'] }} @endif
                                    </textarea>
                                </div>                                                                                                                                        

                                <div class="col-md-12 form-group">
                                    <center>
                                        <input type="submit" name="submit" value="Save" class="btn btn-success">
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
</div>

@include('includes.footerJs')
<script src="{{asset('/plugins/bower_components/summernote/dist/summernote.min.js')}}"></script>

<script>
$( document ).ready(function() { 

    $('.summernote').summernote({
        height: 200, // set editor height
        minHeight: null, // set minimum height of editor
        maxHeight: null, // set maximum height of editor
        focus: false // set focus to editable area after initializing summernote
    });

    $('[data-toggle="popover"]').popover({title: "",html: true});
    
    $('[data-toggle="popover"]').on('click', function (e) {
        $('[data-toggle="popover"]').not(this).popover('hide');
    });

});
</script>





@include('includes.footer')
