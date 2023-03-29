@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Requisition Form</h4>
            </div>
        </div>
        <div class="card">
                @if ($sessionData = Session::get('data'))
                    @if ($sessionData['status_code'] == 0)
                        <div class="alert alert-danger alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{{ $sessionData['message'] }}</strong>
                        </div>
                    @else
                        <div class="alert alert-success alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{{ $sessionData['message'] }}</strong>
                        </div>
                    @endif
                @endif

            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                     <form action="@if (isset($data)) {{ route('add_requisition.update',$requisition_id) }} @else {{ route('add_requisition.store') }} @endif" method="post">
                      @if(!isset($data))
                        {{ method_field("POST") }}
                      @else
                        {{ method_field("PUT") }}
                      @endif
                      @csrf 

                      @php
                        $disabled = '';
                        if(isset($data->requisition_by))
                        {
                            $disabled = "disabled";
                        }
                      @endphp

                      <div class="row">                          
                            <div class="col-md-6 form-group">
                                <label>Requisition By</label>                               
                                <select class="form-control" name="requisition_by" id="requisition_by" {{$disabled}}>                    
                                    <option value="">Select Requisition By</option>
                                    @if(!empty($menu))  
                                    @foreach($menu as $key => $value)                                        
                                        <option value="{{$value->id}}" @if(isset($data->requisition_by)){{$data->requisition_by == $value->id ? 'selected=selected' : '' }} @endif>{{$value->requisition_name}}</option>
                                    @endforeach
                                    @endif                               
                                </select>
                            </div>                    
                            <div class="col-md-6 form-group" style="display: none;">
                                <label>Department</label>
                                <input type="text" id='department_id' name="department_id" value="@if(isset($data->department_id)){{$data->department_id}}@endif" class="form-control">
                            </div>                                                  
                            <div class="col-md-6 form-group">
                                <label>Requisition Date</label>
                                <input type="text" class="form-control" value="{{date('Y-m-d H:i:s')}}" name="requisition_date" autocomplete="off" readonly="readonly" required="required">                               
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Requisition No.</label>
                                <input type="text" id='requisition_no' required name="requisition_no" value="@if(isset($data->requisition_no)){{$data->requisition_no}}@else{{$REQ_NO}}@endif " class="form-control" readonly="readonly">
                            </div>
                            @if($item_setting_data_value == 'items_with_chain')
                            <div class="col-md-12 form-group">
                                <div class="addButtonCheckbox">     
                                    <div class="row align-items-center">
                                        <div class="col-md-2 my-2">
                                            <div class="form-group mb-0">
                                                <label for="control-label">Item Category</label>
                                                <select class="cust-select form-control mb-0" name="category_id[]" data-new="1" onchange="getCategorywiseSubcategory(this.value,'1');" required>
                                                    <option value="">Select Category</option>
                                                    @if(!empty($category_data))  
                                                    @foreach($category_data as $key => $value)
                                                        <option value="{{ $value['id'] }}" @if(isset($data->category_id)) {{ $data->category_id == $value['id'] ? 'selected' : '' }} @endif> {{ $value['title'] }} </option>
                                                    @endforeach
                                                    @endif  
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2 my-2">
                                            <div class="form-group mb-0">
                                                <label for="control-label">Item Sub Category</label>
                                                <select name="sub_category_id[]" data-new="1" class="cust-select form-control mb-0" required onchange="getSubcategorywiseItems(this.value,'1');">                                    
                                                    @if(empty($sub_category_data))
                                                        <option value="">Select Sub Category</option>
                                                    @endif
                                                    @if(!empty($sub_category_data))  
                                                    @foreach($sub_category_data as $k1 => $v1)
                                                        <option value="{{ $v1['id'] }}" @if(isset($data->sub_category_id)) {{ $data->sub_category_id == $v1['id'] ? 'selected' : '' }} @endif> {{ $v1['title'] }} </option>
                                                    @endforeach
                                                    @endif
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2 my-2">
                                            <div class="form-group mb-0">
                                                <label>Item</label>                               
                                                <select class="cust-select form-control mb-0" name="item_id[]" data-new="1">
                                                    <option value="">Select Item</option>
                                                    @if(!empty($item_data))  
                                                    @foreach($item_data as $key1 => $value1)                                        
                                                        <option value="{{$value1->id}}" @if(isset($data->item_id)){{$data->item_id == $value1->id ? 'selected=selected' : '' }} @endif>{{$value1->title}}</option>
                                                    @endforeach
                                                    @endif                               
                                                </select>
                                            </div>    
                                        </div>
                                        <div class="col-md-1 my-2">
                                            <div class="form-group mb-0">
                                                <label>Qty</label>
                                                <input type="number" required name="item_qty[]" value="@if(isset($data->item_qty)){{$data->item_qty}}@endif" class="form-control mb-0" data-new="1">
                                            </div>    
                                        </div>                        
                                        <div class="col-md-1 my-2">
                                            <div class="form-group mb-0">
                                                <label>Unit</label>
                                                <input type="text" required name="item_unit[]" value="@if(isset($data->item_unit)){{$data->item_unit}}@endif" class="form-control mb-0" data-new="1">
                                            </div>    
                                        </div>                        
                                        <div class="col-md-2 my-2">
                                            <div class="form-group mb-0">
                                                <label>Expected Delivery DateTime</label>
                                                <div class="input-daterange input-group" id="date-range">
                                                    <input type="text" class="form-control mydatepicker mb-0" placeholder="yyyy/mm/dd" value="@if(isset($data->expected_delivery_time)){{$data->expected_delivery_time}}@endif" name="expected_delivery_time[]" autocomplete="off" data-new="1">
                                                    <span class="input-group-addon"><i class="icon-calender"></i></span> 
                                                </div>
                                            </div>    
                                        </div>                          
                                        <div class="col-md-1 my-2">
                                            <div class="form-group mb-0">
                                                <label>Remarks </label>
                                                <input type="text" required name="remarks[]" value="@if(isset($data->remarks)){{$data->remarks}}@endif" class="form-control mb-0" data-new="1">
                                            </div>    
                                        </div>

                                        @if(!isset($data))
                                            <div class="col-md-1 mt-3">
                                                <a href="javascript:void(0);" onclick="addNewRowWithChain();" class="d-inline-block btn btn-success mr-2"><i class="mdi mdi-plus"></i></a>                                            
                                            </div>
                                        @endif                                                                                
                                    </div>
                                </div>
                            </div>    
                            @endif
                            @if($item_setting_data_value == 'items_without_chain')
                            <div class="col-md-12 form-group">
                                <div class="addButtonCheckbox">     
                                    <div class="row align-items-center">                                                                       
                                        <div class="col-md-2 my-2">
                                            <div class="form-group mb-0">
                                                <label>Item</label>                               
                                                <select class="cust-select form-control mb-0" name="item_id[]" data-new = "1">
                                                    <option value="">Select Item</option>
                                                    @if(!empty($menu1))  
                                                    @foreach($menu1 as $key1 => $value1)                                        
                                                        <option value="{{$value1->id}}" @if(isset($data->item_id)){{$data->item_id == $value1->id ? 'selected=selected' : '' }} @endif>{{$value1->title}}</option>
                                                    @endforeach
                                                    @endif                              
                                                </select>
                                            </div>    
                                        </div>
                                        <div class="col-md-1 my-2">
                                            <div class="form-group mb-0">
                                                <label>Qty</label>
                                                <input type="number" id='item_qty' required name="item_qty" value="@if(isset($data->item_qty)){{$data->item_qty}}@endif" class="form-control mb-0">
                                            </div>    
                                        </div>                        
                                        <div class="col-md-1 my-2">
                                            <div class="form-group mb-0">
                                                <label>Unit</label>
                                                <input type="text" id='item_unit' required name="item_unit" value="@if(isset($data->item_unit)){{$data->item_unit}}@endif" class="form-control mb-0">
                                            </div>    
                                        </div>                        
                                        <div class="col-md-2 my-2">
                                            <div class="form-group mb-0">
                                                <label>Expected Delivery DateTime</label>
                                                <div class="input-daterange input-group" id="date-range">
                                                    <input type="text" class="form-control mydatepicker mb-0" placeholder="yyyy/mm/dd" value="@if(isset($data->expected_delivery_time)){{$data->expected_delivery_time}}@endif" name="expected_delivery_time" autocomplete="off">
                                                    <span class="input-group-addon"><i class="icon-calender"></i></span> 
                                                </div>
                                            </div>    
                                        </div>                          
                                        <div class="col-md-1 my-2">
                                            <div class="form-group mb-0">
                                                <label>Remarks </label>
                                                <input type="text" id='remarks' required name="remarks" value="@if(isset($data->remarks)){{$data->remarks}}@endif" class="form-control mb-0">
                                            </div>    
                                        </div>
                                        @if(!isset($data))    
                                            <div class="col-md-1 mt-3">
                                                <a href="javascript:void(0);" onclick="addNewRowWithoutChain();" class="d-inline-block btn btn-success mr-2"><i class="mdi mdi-plus"></i></a>                                           
                                            </div>
                                        @endif    
                                    </div>
                                </div>
                            </div>    
                            @endif                                                                       
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                </center>
                            </div>
                      </div>                  
                    </form>
                </div>
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
<script>

function getCategorywiseSubcategory(category_id,data_new)
{         
    var path = "{{ route('ajax_CategorywiseSubcategory') }}";
    $.ajax({
        url:path,
        data:'category_id='+category_id,
        success:function(result){                    
            var e = $('select[name="sub_category_id[]"][data-new='+data_new+']');
            $(e).find('option').remove().end().append('<option value="">Select Sub Category</option>').val('');
            for(var i=0;i < result.length ;i++)
            {
                $(e).append($("<option></option>").val(result[i]['id']).html(result[i]['title']));
            }
        }
    });
}

function getSubcategorywiseItems(sub_category_id,data_new)
{    
    var path = "{{ route('ajax_SubcategoryeiseItems') }}";
    $.ajax({
        url: path,
        data:'sub_category_id='+sub_category_id, 
        success: function(result){
            var e = $('select[name="item_id[]"][data-new='+data_new+']');
            $(e).find('option').remove().end().append('<option value="">Select Item</option>').val('');
            for(var i=0;i < result.length ;i++)
            {
                $(e).append($("<option></option>").val(result[i]['id']).html(result[i]['title']));
            } 
        }
    });
}

function addNewRowWithChain()
{
    $('select[name="category_id[]"]').each(function(){
        data_new =  parseInt($(this).attr('data-new'));
        html = $(this).html();
    });
    data_new = parseInt(data_new) + 1; 

    var category_data = html;
    var htmlcontent = '';    
    htmlcontent += '<div class="clearfix"></div><div class="addButtonCheckbox" style="display: flex; margin-right: -15px; margin-left: -15px; flex-wrap: wrap;">';

    htmlcontent += '<div class="col-md-2 my-2"><div class="form-group mb-0"><select class="form-control cust-select" name="category_id[]" data-new='+data_new+' onchange="getCategorywiseSubcategory(this.value,'+data_new+');">'+category_data+'</select></div></div>';
    htmlcontent += '<div class="col-md-2 my-2"><div class="form-group mb-0"><select class="form-control cust-select" name="sub_category_id[]" data-new='+data_new+' onchange="getSubcategorywiseItems(this.value,'+data_new+');"><option>Select Mapping Value</option></select></div></div>';
    htmlcontent += '<div class="col-md-2 my-2"><div class="form-group mb-0"><select class="form-control cust-select" name="item_id[]" data-new='+data_new+'><option>Select Item</option></select></div></div>';
    htmlcontent += '<div class="col-md-1 my-2"><div class="form-group mb-0"><input type="number" class="form-control" name="item_qty[]" data-new='+data_new+'></div></div>';
    htmlcontent += '<div class="col-md-1 my-2"><div class="form-group mb-0"><input type="text" class="form-control" name="item_unit[]" data-new='+data_new+'></div></div>';
    htmlcontent += '<div class="col-md-2 my-2"><div class="form-group mb-0"><div class="input-daterange input-group" id="date-range"><input type="text" class="form-control mydatepicker" name="expected_delivery_time[]" placeholder="yyyy/mm/dd" data-new='+data_new+'></div></div></div>';
    htmlcontent += '<div class="col-md-1 my-2"><div class="form-group mb-0"><input type="text" class="form-control" name="remarks[]" data-new='+data_new+'></div></div>';
    htmlcontent += '<div class="col-md-1 mt-3"><a href="javascript:void(0);" onclick="removeNewRowWithChain();" class="d-inline btn btn-danger"><i class="mdi mdi-minus"></i></a></div></div>';
                             
    $('.addButtonCheckbox:last').after(htmlcontent);
}
function removeNewRowWithChain()
{     
    $(".addButtonCheckbox:last" ).remove();      
}
function addNewRowWithoutChain()
{
    $('select[name="item_id[]"]').each(function(){
        data_new =  parseInt($(this).attr('data-new'));
        html = $(this).html();
    });
    data_new = parseInt(data_new) + 1; 

    var item_data = html;
    var htmlcontent = '';    
    htmlcontent += '<div class="clearfix"></div><div class="addButtonCheckbox" style="display: flex; margin-right: -15px; margin-left: -15px; flex-wrap: wrap;">';

    htmlcontent += '<div class="col-md-2 my-2"><div class="form-group mb-0"><select class="form-control cust-select" name="item_id[]" data-new='+data_new+'>'+item_data+'</select></div></div>';
    htmlcontent += '<div class="col-md-1 my-2"><div class="form-group mb-0"><input type="number" class="form-control" name="item_qty[]" data-new='+data_new+'></div></div>';
    htmlcontent += '<div class="col-md-1 my-2"><div class="form-group mb-0"><input type="text" class="form-control" name="item_unit[]" data-new='+data_new+'></div></div>';
    htmlcontent += '<div class="col-md-2 my-2"><div class="form-group mb-0"><div class="input-daterange input-group" id="date-range"><input type="text" class="form-control mydatepicker" name="expected_delivery_time[]" placeholder="yyyy/mm/dd" data-new='+data_new+'></div></div></div>';
    htmlcontent += '<div class="col-md-1 my-2"><div class="form-group mb-0"><input type="text" class="form-control" name="remarks[]" data-new='+data_new+'></div></div>';
    htmlcontent += '<div class="col-md-1 mt-3"><a href="javascript:void(0);" onclick="removeNewRowWithoutChain();" class="d-inline btn btn-danger"><i class="mdi mdi-minus"></i></a></div></div>';
                             
    $('.addButtonCheckbox:last').after(htmlcontent);
}
function removeNewRowWithoutChain()
{     
    $(".addButtonCheckbox:last" ).remove();      
}
</script>
@include('includes.footer')
