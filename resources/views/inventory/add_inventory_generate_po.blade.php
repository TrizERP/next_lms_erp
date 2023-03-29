@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Generate PO</h4>
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
                      {{ route('add_inventory_generate_po.update', $data->id) }}
                      @else
                      {{ route('add_inventory_generate_po.store') }}
                      @endif" method="post">

                        @if(!isset($data))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif
                        {{csrf_field()}}
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>PO No.</label>
                                <input type="text" id='po_number' required name="po_number" value="@if(isset($data->po_number)){{$data->po_number}}@else{{$PO_NO}}@endif " class="form-control" readonly="readonly">
                            </div>
                            <div class="col-md-6 form-group">
                                @csrf
                                <label>Vendor Name</label>
                                <select class="form-control" required name="vendor_id">
                                @if(!empty($menu))  
                                @foreach($menu as $key => $value)
                                    <option value="{{ $value['id'] }}" @if(isset($data->vendor_id)) {{ $data->vendor_id == $value['id'] ? 'selected' : '' }} @endif> {{ $value['vendor_name'] }} </option>
                                @endforeach
                                @endif
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Delivery DateTime</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy" value="@if(isset($data->delivery_time)){{$data->delivery_time}}@endif" name="delivery_time" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span> 
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Place of Delivery</label>
                                <textarea class="form-control" rows="2" id='po_place_of_delivery' required name="po_place_of_delivery">@if(isset($data->po_place_of_delivery)){{ $data->po_place_of_delivery}}@endif</textarea>  
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Payment Terms</label>
                                <textarea class="form-control" rows="2" id='payment_terms' required name="payment_terms">@if(isset($data->payment_terms)){{ $data->payment_terms}}@endif</textarea>  
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Remarks (if any)</label>
                                <textarea class="form-control" rows="2" id='remarks' required name="remarks">@if(isset($data->remarks)){{ $data->remarks}}@endif</textarea>  
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Transportation Charge</label>
                                <input type="number" id='transportation_charge' required name="transportation_charge" value="@if(isset($data->transportation_charge)){{$data->transportation_charge}}@endif" class="form-control" maxlength="5">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Installation Charge</label>
                                <input type="number" id='installation_charge' required name="installation_charge" value="@if(isset($data->installation_charge)){{$data->installation_charge}}@endif" class="form-control" maxlength="5">
                            </div>
                            <div class="col-md-12 form-group">
                                <table class="table table-striped">
                                    <tr>
                                        <th rowspan=2 style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: center;">
                                            <INPUT class="cls_all_items_chkbx" type=checkbox value=Y name=controller checked>
                                        </th>    
                                        <th rowspan=2 style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Item</th>
                                        <th colspan=3 style="text-align:center;border: 1px solid black;border-collapse: collapse;padding: 5px;">Price</th>
                                        <th colspan=3 style="text-align:center;border: 1px solid black;border-collapse: collapse;padding: 5px;">Discount</th>
                                        <th colspan=2 style="text-align:center;border: 1px solid black;border-collapse: collapse;padding: 5px;">Tax</th>
                                        <th style="border: 1px solid black;border-collapse: collapse;padding: 5px;">Total</th>
                                    </tr>
                                    <tr>
                                        <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;"><b>Rate</b></td>
                                        <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;"><b>Qty</b></td>
                                        <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;"><b>Amount</b></td>
                                        <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;"><b>%</b></td>
                                        <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;"><b>Disc. Amount</b></td>
                                        <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;"><b>Amount</b></td>
                                        <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;"><b>Rate %</b></td>
                                        <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;"><b>Tax Amount</b></td>
                                        <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;"><b>Amount</b></td>
                                    </tr>
                                    @if(!empty($item_data)) 
                                    @foreach($item_data as $k => $v)
                                        <tr>
                                            <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: center;">
                                                <INPUT class="cls_item_chkbx" type="checkbox" name="chkbx_item_id_arr[]" value="{{$v->item_id}}" checked>
                                            </td>
                                            <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">{{$v->item_name}}</td>             
                                            <TD style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">{{$v->price}}
                                                <input class="cls_all_items_prices" type="hidden" name="price[{{$v->item_id}}]" id="price[{{$v->item_id}}]" value='{{$v->price}}' />
                                            </TD>
                                            <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">
                                                <INPUT class="cls_all_items_qty" type="text" name="qty[{{$v->item_id}}]" value="@if(isset($data->qty)){{$data->qty}}@endif" size="5" maxlength="5" onblur="Javascript:update_total_amount_2(this,{{$v->item_id}});
                                    function update_total_amount_2(element,item_id)
                                    {   
                                         all_item_prices = document.getElementsByClassName('cls_all_items_prices');
                                         all_item_qty = document.getElementsByClassName('cls_all_items_qty');
                                         all_items_chkbx = document.getElementsByClassName('cls_item_chkbx');
                                         
                                         all_poac_prices = document.getElementsByClassName('cls_all_poac_prices');
                                         all_poac_chkbx = document.getElementsByClassName('cls_poac_chkbx');

                                         final_total_amount = 0;
                                         final_total_amount_1 = 0;

                                         for(var i = 0; i < all_items_chkbx.length; i++)
                                         {							
                                            if (all_items_chkbx.item(i).checked == true)
                                            {
                    							
                                                if (all_item_qty.item(i).value != '')
                                                {
                                                    item_qty = all_item_qty.item(i).value;
                                                }
                                                else
                                                {
                                                    item_qty = 0;
                                                }											                                
                    							final_total_amount = Number(final_total_amount) + Number(all_item_prices.item(i).value) * Number(item_qty);
                                                final_total_amount_1 = Number(final_total_amount) + Number(all_item_prices.item(i).value) * Number(item_qty);
                    							
                                            }                            
                                         }
                    					 var price = document.getElementById('price['+item_id+']').value;
                    					 //alert(price);
                    					 //alert(element.value);
                    					 document.getElementById('amount['+item_id+']').value = Number(price) * Number(element.value);							
                    					 document.getElementById('TOTAL_PO_AMOUNT['+item_id+']').value = Number(price) * Number(element.value);
                                         for(var j = 0; j < all_poac_chkbx.length; j++)
                                         {
                                            if (all_poac_chkbx.item(j).checked == true)
                                            {
                                                final_total_amount = Number(final_total_amount) + (Number(final_total_amount_1) * Number(all_poac_prices.item(j).value)/100);
                    						
                                            }
                                         }
                                     };" > </td>
                                        <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">
                                            <input type="text" id="amount[{{$v->item_id}}]" name="amount[{{$v->item_id}}]" value="@if(isset($data->amount)){{$data->amount}}@endif" readonly size="8" />
                                        </td>
                                        <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">
                                            <INPUT type="text" name="dis_per[{{$v->item_id}}]" value="@if(isset($data->dis_per)){{$data->dis_per}}@endif" size="5" maxlength="5" class=cls_all_items_qty onblur="Javascript:discount_amount_per(this.value,{{$v->item_id}});
                                function discount_amount_per(element,item_id)
                                 {   //alert(item_id);
                					total_amount = document.getElementById('amount['+item_id+']').value;						
                					percentage_amount = total_amount*element/100;
                					//alert(percentage_amount);
                					tot_amt = Number(total_amount) - Number(percentage_amount);
                					document.getElementById('dis_amount_value['+item_id+']').value = percentage_amount;
                					document.getElementById('after_dis_amount['+item_id+']').value = tot_amt;
                					
                                 };"></td>
                                    <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">
                                        <INPUT type="text" name="dis_amount_value[{{$v->item_id}}]" id="dis_amount_value[{{$v->item_id}}]" value="@if(isset($data->dis_amount_value)){{$data->dis_amount_value}}@endif" size="8" readonly>
                                    </td>
                                    <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">
                                        <INPUT type="text" name="after_dis_amount[{{$v->item_id}}]" size="8" id="after_dis_amount[{{$v->item_id}}]" value="@if(isset($data->dis_amount_value)){{$data->dis_amount_value}}@endif" readonly>
                                    </td>
                                    <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">
                                        <INPUT type="text" name="tax_per[{{$v->item_id}}]" size="8" value="@if(isset($data->tax_per)){{$data->tax_per}}@endif" class=cls_all_items_qty onblur="Javascript:tax_percentage(this.value,{{$v->item_id}});
                                function tax_percentage(element,item_id)
                                 {   
                					total_amount_after_disc = document.getElementById('after_dis_amount['+item_id+']').value;						
                					tax_percentage_amount = total_amount_after_disc*element/100;
                					//alert(tax_percentage_amount);
                					//alert(element);
                					Final_tot_amt = Number(total_amount_after_disc) + Number(tax_percentage_amount);
                					 document.getElementById('tax_amount_value['+item_id+']').value = tax_percentage_amount;
                					 document.getElementById('after_tax_amount['+item_id+']').value = Final_tot_amt;
                					
                                 };"></td>
                                        <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">
                                            <INPUT type="text" name="tax_amount_value[{{$v->item_id}}]" id="tax_amount_value[{{$v->item_id}}]" size="8" value="@if(isset($data->tax_amount_value)){{$data->tax_amount_value}}@endif" readonly>
                                        </td>
                                        <td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">
                                            <INPUT type="text" name="after_tax_amount[{{$v->item_id}}]" id="after_tax_amount[{{$v->item_id}}]" size="8" value="@if(isset($data->after_tax_amount)){{$data->after_tax_amount}}@endif" readonly>
                                        </td>
                                    </tr>
                                    @endforeach
                                    @endif
                                </table>
                            </div>
                        </div>                    
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
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

@include('includes.footerJs')
@include('includes.footer')
