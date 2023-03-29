@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Inventory Master Setup</h4> 
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
                    <form enctype="multipart/form-data" action="
                      @if (isset($data))
                      {{ route('add_inventory_master_setup.update', $data->ID) }}
                      @else
                      {{ route('add_inventory_master_setup.store') }}
                      @endif
                      " method="post">
                        
                        @if(!isset($data))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif

                        {{csrf_field()}}
                        <div class="row">                            
                            <div class="col-md-4 form-group">
                                <label>GST Registration No. </label>
                                <input type="text" id='GST_REGISTRATION_NO' required name="GST_REGISTRATION_NO" value="@if(isset($data->GST_REGISTRATION_NO)) {{ $data->GST_REGISTRATION_NO }} @endif" class="form-control">
                            </div>                  
                            <div class="col-md-4 form-group">
                                <label>GST Registration Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input type="text" class="form-control mydatepicker" value="@if(isset($data->GST_REGISTRATION_DATE)){{$data->GST_REGISTRATION_DATE}}@endif" name="GST_REGISTRATION_DATE" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span> 
                                </div>
                            </div>                  
                            <div class="col-md-4 form-group">
                                <label>CST Registration No. </label>
                                <input type="text" id='CST_REGISTRATION_NO' required name="CST_REGISTRATION_NO" value="@if(isset($data->CST_REGISTRATION_NO)) {{ $data->CST_REGISTRATION_NO }} @endif" class="form-control">
                            </div>                    
                            <div class="col-md-4 form-group">
                                <label>CST Registration Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input type="text" class="form-control mydatepicker" value="@if(isset($data->CST_REGISTRATION_DATE)){{$data->CST_REGISTRATION_DATE}}@endif" name="CST_REGISTRATION_DATE" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span> 
                                </div>
                            </div>                          
                            <div class="col-md-4 form-group">
                                <label>LOGO </label>
                                <input type="file" id='LOGO' name="LOGO" class="form-control">
    							@php
    							if(isset($data->LOGO) && $data->LOGO !="")
    							{
    								echo "<img src='/storage/inventory_master/$data->LOGO' height='80' width='80'>";
    								echo "<input type='hidden' name='hid_logo' value='/$data->LOGO'>";										
    							}								
    							@endphp
                            </div>                    
                            <div class="col-md-4 form-group">
                                <label>PO No Prefix</label>
                                <input type="text" id='PO_NO_PREFIX' required name="PO_NO_PREFIX" value="@if(isset($data->PO_NO_PREFIX)) {{ $data->PO_NO_PREFIX }} @endif" class="form-control">
                            </div>
                             <div class="col-md-4 form-group ml-0 mr-0">
                                <label>Item Setting for Requisition Form</label>
                                <select name="ITEM_SETTING_FOR_REQUISITION" id="ITEM_SETTING_FOR_REQUISITION" class="form-control" disabled>                                    
                                    <option value="items_with_chain" @if(isset($data->ITEM_SETTING_FOR_REQUISITION) && $data->ITEM_SETTING_FOR_REQUISITION == 'items_with_chain') selected @endif>Items With Category/Sub-category</option>
                                    <option value="items_without_chain" @if(isset($data->ITEM_SETTING_FOR_REQUISITION) && $data->ITEM_SETTING_FOR_REQUISITION == 'items_without_chain') selected @endif>Items Without Category/Sub-category</option>
                                                                        
                                </select>
                            </div> 
                            <div class="col-md-4 form-group ml-0 mr-0 mt-4">                                
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >                                
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
@include('includes.footer')
