@include('../includes.headcss')
<link href="{{ asset('/plugins/bower_components/summernote/dist/summernote.css') }}" rel="stylesheet" />
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="card">
			<form enctype='multipart/form-data' action="
              @if (isset($data->type))
              {{ route('schooldetail.update', $data->id) }}
              @else
              {{ route('schooldetail.store') }}
              @endif" method="post">

                @if(!isset($data->type))
                {{ method_field("POST") }}
                @else
                {{ method_field("PUT") }}
                @endif
            
                {{csrf_field()}}
                <div class="row">
                    <div class="col-md-6 form-group ml-0 mr-0">
                        <label>Type</label>
						@php
						$disabled = "";
						if(isset($data->type))
						{
							$disabled = "disabled";
						}								
						@endphp
                        <select name="type" id="type" class="form-control" required {{$disabled}}>
                            <option value="">Select</option>
                            
							@if(isset($data->type_arr))										
							@foreach($data->type_arr as $key => $val)
								<option value="{{$key}}">{{$val}}</option>
							@endforeach
							@endif
							@if(isset($data->type))																			
								<option value="{{$data->type}}" selected>{{$data->type}}</option>									
							@endif									
                        </select>
                    </div>
                </div>
                <div class="row">    
                    <div class="col-md-6 form-group ml-0 mr-0">
                        <label>Description</label>
                        <!-- <textarea name="message" class="form-control">@if(isset($data->title)) {{$data->title}} @endif</textarea> -->
                        <textarea class="summernote" id="message" name="message">@if(isset($data->title)) {{$data->title}} @endif
                        </textarea>
                    </div>
                    <div class="col-md-12 form-group">                        
                        <center>
                            <input type="submit" name="submit" value="Submit" class="btn btn-success">
                        </center>
                    </div>
                </div>    
            </form>
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