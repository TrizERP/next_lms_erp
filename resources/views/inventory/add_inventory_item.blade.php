@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Inventory Item</h4> 
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
                          {{ route('add_inventory_item.update', $data->id) }}
                          @else
                          {{ route('add_inventory_item.store') }}
                          @endif" enctype="multipart/form-data" method="post">
                            @if(!isset($data))
                            {{ method_field("POST") }}
                            @else
                            {{ method_field("PUT") }}
                            @endif
                            @csrf
                        <div class="row">                            
                            <div class="col-md-4 form-group">                   
                                <label class="control-label">Item Category</label>
                                <select class="form-control" required name="category_id" onchange="getCategorywiseSubcategory(this.value);">
                                    <option value="">--Select Category--</option>
                                @if(!empty($menu))  
                                @foreach($menu as $key => $value)
                                    <option value="{{ $value['id'] }}" @if(isset($data->category_id)) {{ $data->category_id == $value['id'] ? 'selected' : '' }} @endif> {{ $value['title'] }} </option>
                                @endforeach
                                @endif
                                </select>
                            </div>                                                        
                            <div class="col-md-4 form-group">                   
                                <label class="control-label">Item Sub Category</label>
                                <select class="form-control" required name="sub_category_id" id="sub_category_id">
                                   @if(empty($menu1))
                                    <option value="">--Select Sub Category--</option>
                                    @endif
                                @if(!empty($menu1))  
                                @foreach($menu1 as $k1 => $v1)
                                    <option value="{{ $v1['id'] }}" @if(isset($data->sub_category_id)) {{ $data->sub_category_id == $v1['id'] ? 'selected' : '' }} @endif> {{ $v1['title'] }} </option>
                                @endforeach
                                @endif
                                </select>
                            </div>                                
                            <div class="col-md-4 form-group">
                                <label>Title </label>
                                <input type="text" value="@if(isset($data->title)){{$data->title}}@endif" id='title' required name="title" class="form-control">
                            </div>                             
                            <div class="col-md-4 form-group">
                                <label>Description</label>
                                <textarea class="form-control" required name="description">@if(isset($data->description)){{$data->description}}@endif</textarea>
                            </div>                                    
                            <div class="col-md-4 form-group">
                                <label>Minimum Stock Level </label>
                                <input type="number" id='minimum_stock' value="@if(isset($data->minimum_stock)){{$data->minimum_stock}}@endif" required name="minimum_stock" class="form-control">
                            </div>                                
                            <div class="col-md-4 form-group">
                                <label>Opening Stock </label>
                                <input type="number" id='opening_stock' value="@if(isset($data->opening_stock)){{$data->opening_stock}}@endif" required name="opening_stock" class="form-control">
                            </div>                                
                             <div class="col-sm-4 form-group">                         
                                <label for="input-file-now">Item Image</label>
                                <input type="file" accept="image/*" name="item_attachment" @if(isset($data->item_attachment))data-default-file="/storage/inventory_item/{{ $data->item_attachment }}" @else  @endif id="input-file-now" class="dropify" /> 
                            </div>                                    
                            <div class="col-md-4 form-group">                   
                                <label class="control-label">Item Type</label>
                                <select class="form-control" required name="item_type_id">
                                    <option value="">--Select Item type--</option>                              
                                @if(!empty($menu2))                            
                                @foreach($menu2 as $key => $value)                       
                                    <option value="{{ $value['id'] }}" @if(isset($data->item_type_id)) {{ $data->item_type_id == $value['id'] ? 'selected' : '' }} @endif> {{ $value['title'] }} </option>
                                @endforeach
                                @endif
                                </select>
                            </div>                                
                            <div class="col-md-4 form-group">                           
                                <label>Status</label>
                                    <div class="radio-list">
                                        <label class="radio-inline">
                                            <input type="radio" name="item_status" value="Active" @if(isset($data->item_status)) {{ $data->item_status == 'Active' ? 'checked' : '' }} @endif> Active </label>
                                        <label class="radio-inline">
                                            <input type="radio" name="item_status" value="Inactive" @if(isset($data->item_status)) {{ $data->item_status == 'Inactive' ? 'checked' : '' }} @endif> Inactive  </label>
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
<script src="../../../plugins/bower_components/dropify/dist/js/dropify.min.js"></script>
    <script>
    $(document).ready(function() {
        // Basic
        $('.dropify').dropify();
        // Translated
        $('.dropify-fr').dropify({
            messages: {
                default: 'Glissez-déposez un fichier ici ou cliquez',
                replace: 'Glissez-déposez un fichier ou cliquez pour remplacer',
                remove: 'Supprimer',
                error: 'Désolé, le fichier trop volumineux'
            }
        });
        // Used events
        var drEvent = $('#input-file-events').dropify();
        drEvent.on('dropify.beforeClear', function(event, element) {
            return confirm("Do you really want to delete \"" + element.file.name + "\" ?");
        });
        drEvent.on('dropify.afterClear', function(event, element) {
            alert('File deleted');
        });
        drEvent.on('dropify.errors', function(event, element) {
            console.log('Has Errors');
        });
        var drDestroy = $('#input-file-to-destroy').dropify();
        drDestroy = drDestroy.data('dropify')
        $('#toggleDropify').on('click', function(e) {
            e.preventDefault();
            if (drDestroy.isDropified()) {
                drDestroy.destroy();
            } else {
                drDestroy.init();
            }
        })
    });
    </script>
<script>
function getCategorywiseSubcategory(category_id){    
    var path = "{{ route('ajax_CategorywiseSubcategory') }}";
    //alert('dk');
    $('#sub_category_id').find('option').remove().end().append('<option value="">Select Sub Category</option>').val('');
    $.ajax({url: path,data:'category_id='+category_id, success: function(result){  
          
        for(var i=0;i < result.length;i++){  
             //alert(result[i]['id']);
             //alert(result[i]['title']);
            $("#sub_category_id").append($("<option></option>").val(result[i]['id']).html(result[i]['title']));  
        } 
    }
    });
}
</script>
@include('includes.footer')
