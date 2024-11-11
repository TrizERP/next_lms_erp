@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Item Direct Purchase</h4> </div>
        </div>        
        <div class="card">
            <div class="panel-body">
               @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                    @endif
                   
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="@if (isset($data->id))
                          {{ route('add_item_direct_purchase.update', $data->id) }}
                          @else
                          {{ route('add_item_direct_purchase.store') }}
                          @endif" method="post">
                        @if(!isset($data->id))
                            {{ method_field("POST") }}
                        @else
                            {{ method_field("PUT") }}
                        @endif
                        @csrf
                      
                        <div class="row">
                            <div class="col-md-2 form-group">
                                <label>Vendor Name</label>
                                <select class="form-control" required name="vendor_id">
                                    <option value="">Select Vendor</option>                                    
                                @if(!empty($vendor_data))  
                                @foreach($vendor_data as $key => $value)
                                    <option value="{{ $value['id'] }}" @if(isset($data->vendor_id)) {{ $data->vendor_id == $value['id'] ? 'selected' : '' }} @endif> {{ $value['vendor_name'] }} </option>
                                @endforeach
                                @endif
                                </select>
                            </div>
                            <div class="col-md-2 form-group">
                                <label>Challan No.</label>
                                <input type="text" value="@if(isset($data->challan_no)){{$data->challan_no}}@endif" id='challan_no' required name="challan_no" class="form-control">
                            </div>
                            <div class="col-md-2 form-group">
                                <label>Challan Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input type="text" required class="form-control mydatepicker" value="@if(isset($data->challan_date)){{$data->challan_date}}@endif" name="challan_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span> 
                                </div>
                            </div>
                            <div class="col-md-2 form-group">
                                <label>Bill No.</label>
                                <input type="text" value="@if(isset($data->bill_no)){{$data->bill_no}}@endif" id='bill_no' required name="bill_no" class="form-control">
                            </div>
                            <div class="col-md-2 form-group">
                                <label>Bill Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input type="text" required class="form-control mydatepicker" value="@if(isset($data->bill_date)){{$data->bill_date}}@endif" name="bill_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span> 
                                </div>
                            </div>
                            <div class="col-md-2 form-group">
                                <label>Remarks</label>
                                <textarea class="form-control" required name="remarks">@if(isset($data->remarks)){{$data->remarks}}@endif</textarea>
                            </div>
                            @if($item_setting_data_value == 'items_with_chain')
                            <div class="col-md-12 form-group">
                                <div class="addButtonCheckbox">     
                                    <div class="row align-items-center">
                                        <div class="col-md-2 my-2">
                                            <div class="form-group mb-0">
                                                <label for="control-label">Item Category</label>
                                                <select class="cust-select form-control mb-0" name="category_id[]" data-new="1" onchange="getCategorywiseSubcategory(this.value,'1');">
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
                                                <select name="sub_category_id[]" data-new="1" class="cust-select form-control mb-0" onchange="getSubcategorywiseItems(this.value,'1');">                                    
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
                                                <select class="cust-select form-control mb-0" name="item_id[]" data-new="1" required>
                                                    <option value="">Select Item</option>
                                                    @if(!empty($item_data))  
                                                    @foreach($item_data as $key1 => $value1)                                        
                                                        <option value="{{$value1->id}}" @if(isset($data->item_id)){{$data->item_id == $value1->id ? 'selected=selected' : '' }} @endif>{{$value1->title}}</option>
                                                    @endforeach
                                                    @endif                               
                                                </select>
                                            </div>    
                                        </div>
                                        <div class="col-md-2 my-2">
                                            <div class="form-group mb-0">
                                                <label>Qty</label>
                                                <input type="number" name="item_qty[]" value="@if(isset($data->item_qty)){{$data->item_qty}}@endif" class="form-control mb-0" data-new="1" required>
                                            </div>    
                                        </div>                        
                                        <div class="col-md-2 my-2">
                                            <div class="form-group mb-0">
                                                <label>Price</label>
                                                <input type="number" name="price[]" value="@if(isset($data->price)){{$data->price}}@endif" class="form-control mb-0" data-new="1" onblur="calculateAmount('1');" required>
                                            </div>    
                                        </div>
                                        <div class="col-md-1 my-2">
                                            <div class="form-group mb-0">
                                                <label>Amount</label>
                                                <input type="text" name="amount[]" value="@if(isset($data->amount)){{$data->amount}}@endif" class="form-control mb-0" data-new="1" readonly="readonly">
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
                                                <select class="cust-select form-control mb-0" name="item_id[]" data-new = "1" required>
                                                    <option value="">Select Item</option>
                                                    @if(!empty($menu1))  
                                                    @foreach($menu1 as $key1 => $value1)                                        
                                                        <option value="{{$value1->id}}" @if(isset($data->item_id)){{$data->item_id == $value1->id ? 'selected=selected' : '' }} @endif>{{$value1->title}}</option>
                                                    @endforeach
                                                    @endif                              
                                                </select>
                                            </div>    
                                        </div>
                                        <div class="col-md-2 my-2">
                                            <div class="form-group mb-0">
                                                <label>Qty</label>
                                                <input type="number" required name="item_qty[]" value="@if(isset($data->item_qty)){{$data->item_qty}}@endif" class="form-control mb-0" data-new="1">
                                            </div>    
                                        </div>                        
                                        <div class="col-md-2 my-2">
                                            <div class="form-group mb-0">
                                                <label>Price</label>
                                                <input type="number" required name="price[]" value="@if(isset($data->price)){{$data->price}}@endif" class="form-control mb-0" data-new="1" onblur="calculateAmount('1');">
                                            </div>    
                                        </div>
                                        <div class="col-md-1 my-2">
                                            <div class="form-group mb-0">
                                                <label>Amount</label>
                                                <input type="text" name="amount[]" value="@if(isset($data->amount)){{$data->amount}}@endif" class="form-control mb-0" data-new="1" readonly="readonly">
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
        </div>        
    </div>
</div>

@include('includes.footerJs')
<script type="text/javascript">
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

function calculateAmount(data_new)
{   
    var qty = $('input[name="item_qty[]"][data-new='+data_new+']').val(); 
    var price = $('input[name="price[]"][data-new='+data_new+']').val(); 
    var amount = (qty * price);
    $('input[name="amount[]"][data-new='+data_new+']').val(amount);
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
    htmlcontent += '<div class="col-md-2 my-2"><div class="form-group mb-0"><input type="number" class="form-control" name="item_qty[]" data-new='+data_new+'></div></div>';
    htmlcontent += '<div class="col-md-2 my-2"><div class="form-group mb-0"><input type="number" class="form-control" name="price[]" data-new='+data_new+' onblur="calculateAmount('+data_new+');"></div></div>';
    htmlcontent += '<div class="col-md-1 my-2"><div class="form-group mb-0"><input type="text" class="form-control" readonly="readonly" name="amount[]" data-new='+data_new+'></div></div>';
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
    htmlcontent += '<div class="col-md-2 my-2"><div class="form-group mb-0"><input type="number" class="form-control" name="item_qty[]" data-new='+data_new+'></div></div>';
    htmlcontent += '<div class="col-md-2 my-2"><div class="form-group mb-0"><input type="number" class="form-control" name="price[]" data-new='+data_new+'></div></div>';
    htmlcontent += '<div class="col-md-1 my-2"><div class="form-group mb-0"><input type="text" class="form-control" readonly="readonly" name="amount[]" data-new='+data_new+' onblur="calculateAmount('+data_new+');"></div></div>';
    htmlcontent += '<div class="col-md-1 mt-3"><a href="javascript:void(0);" onclick="removeNewRowWithoutChain();" class="d-inline btn btn-danger"><i class="mdi mdi-minus"></i></a></div></div>';
                             
    $('.addButtonCheckbox:last').after(htmlcontent);
}

function removeNewRowWithoutChain()
{     
    $(".addButtonCheckbox:last" ).remove();      
}
</script>
@include('includes.footer')
