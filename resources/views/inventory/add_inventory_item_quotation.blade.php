@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Item Quotation</h4>
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
                      {{ route('add_inventory_item_quotation.update', $data->id) }}
                      @else
                      {{ route('add_inventory_item_quotation.store') }}
                      @endif" method="post">

                        @if(!isset($data))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif

                        {{csrf_field()}}
                        <div class="row">                        
                            <div class="col-md-6 form-group">
                                @csrf
                                <label>Vendor Name</label>
                                <select class="form-control" required name="vendor_id">
                                    <option>Select Vendor</option>
                                @if(!empty($menu))  
                                @foreach($menu as $key => $value)
                                    <option value="{{ $value['id'] }}" @if(isset($data->vendor_id)) {{ $data->vendor_id == $value['id'] ? 'selected' : '' }} @endif> {{ $value['vendor_name'] }} </option>
                                @endforeach
                                @endif
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Remarks (if any)</label>
                                <textarea class="form-control" rows="2" id='description_1' required name="remarks">@if(isset($data->remarks)){{ $data->remarks}}@endif</textarea>  
                            </div>
                            <div class="col-md-12 form-group">
                                <div class="row" id="addparts">                                   
                                    <div class="row">
                                        <div class="col-md-3 form-group">
                                            <label>Item</label>
                                            <select class="form-control" required name="item[]" required>                                             
                                            @if(!empty($item_data))  
                                            @foreach($item_data as $k => $v)
                                                <option value="{{$v['id']}}" @if(isset($data->item_id)) {{ $data->item_id == $v['id'] ? 'selected' : '' }} @endif> {{$v['title']}} </option>
                                            @endforeach
                                            @endif
                                            </select>
                                        </div>
                                        <div class="col-md-2 form-group">
                                            <label>Qty</label>
                                            <input type="number" required name="qty[]" value="@if(isset($data->qty)){{ $data->qty}}@endif" class="form-control" required>
                                        </div>
                                        <div class="col-md-2 form-group">
                                            <label>Unit</label>
                                            <input type="text" required name="unit[]" value="@if(isset($data->unit)){{ $data->unit}}@endif" class="form-control" required>
                                        </div>
                                        <div class="col-md-2 form-group">
                                            <label>Price/Piece</label>
                                            <input type="text" required name="price[]" value="@if(isset($data->price)){{ $data->price}}@endif" class="form-control" required>
                                        </div>
                                        <div class="col-md-2 form-group">
                                            <label>Tax</label>
                                            <input type="text" required name="tax[]" value="@if(isset($data->tax)){{ $data->tax}}@endif" class="form-control" required>
                                        </div>
                                    </div>  
                                </div>                                         
                            </div>                                                              
                            <div class="col-md-12 form-group" id="div_button">
                                <center>
                                    <div class="row" id="div_button">
                                        <input type="button" name="addmore" class="btn btn-info" value="Add More"
                                            id="addMore">
                                        <input type="button" name="minmore" class="btn btn-danger" value="Remove Last"
                                            id="minMore">
                                    </div>
                                    <div class="row" id="div_button">
                                         <div class="col-md-6 form-group">
                                            <label>Transportation Charge</label>
                                            <input type="number" id='transportation_charge' required name="transportation_charge" value="@if(isset($data->transportation_charge)){{$data->transportation_charge}}@endif" class="form-control">
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label>Installation Charge</label>
                                            <input type="number" id='installation_charge' required name="installation_charge" value="@if(isset($data->installation_charge)){{$data->installation_charge}}@endif" class="form-control">
                                        </div>
                                    </div>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success m-t-40">
                                </center>
                            </div>
                            <!-- <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                </center>
                            </div> -->
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
    $(document).ready(function() 
    {
        var id = 1;
        // get item
        var item = $("#addparts");
        var before = $('#div_button');

        // initalize event click
        $('#addMore').on('click', function() {
            // clone addparts
            var clone = item.clone(true);
                // remove id
                clone.attr('id', '');
                // add class duplicate
                clone.attr('class', 'duplicate');
            // insert duplicate before button div
            before.before(clone);
        });
        $('#minMore').on('click', function() {
            $('.duplicate').children().last().remove();
        });
    });
</script>

@include('includes.footer')
