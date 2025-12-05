@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Negotiate PO</h4>
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

                    {{-- FORM START --}}
                    <form action="@if (isset($data))
                                    {{ route('add_inventory_negotiate_po.update', $data->id) }}
                                  @else
                                    {{ route('add_inventory_negotiate_po.store') }}
                                  @endif"
                        method="post">

                        @csrf
                        @if (!isset($data))
                            {{ method_field('POST') }}
                        @else
                            {{ method_field('PUT') }}
                        @endif

                        {{-- -------------------- GENERATED PO DETAILS -------------------- --}}
                        <h4 class="page-title text-center">Generated Purchase Order Details</h4>

                        <div class="border border-dark mt-2 mb-5">
                            <div class="row mt-2 ml-2">
                                <div class="col-md-4 form-group">
                                    <label>PO No.: </label>
                                    <label class="text-primary">{{ $generate_po_data[0]['po_number'] }}</label>
                                </div>

                                <div class="col-md-4 form-group">
                                    <label>Vendor Name: </label>
                                    <label class="text-primary">{{ $generate_po_data[0]['vendor_name'] }}</label>
                                </div>

                                <div class="col-md-4 form-group">
                                    <label>Delivery DateTime: </label>
                                    <label class="text-primary">{{ $generate_po_data[0]['delivery_time'] }}</label>
                                </div>

                                <div class="col-md-4 form-group">
                                    <label>Place of Delivery: </label>
                                    <label class="text-primary">{{ $generate_po_data[0]['po_place_of_delivery'] }}</label>
                                </div>

                                <div class="col-md-4 form-group">
                                    <label>Payment Terms: </label>
                                    <label class="text-primary">{{ $generate_po_data[0]['payment_terms'] }}</label>
                                </div>

                                <div class="col-md-4 form-group">
                                    <label>Remarks: </label>
                                    <label class="text-primary">{{ $generate_po_data[0]['remarks'] }}</label>
                                </div>

                                <div class="col-md-4 form-group">
                                    <label>Transportation Charge: </label>
                                    <label class="text-primary">{{ $generate_po_data[0]['transportation_charge'] }}</label>
                                </div>

                                <div class="col-md-4 form-group">
                                    <label>Installation Charge: </label>
                                    <label class="text-primary">{{ $generate_po_data[0]['installation_charge'] }}</label>
                                </div>

                            </div>

                            {{-- ITEMS TABLE --}}
                            <div class="table-responsive">
                                <center>
                                    <table class="table table-box table-bordered col-md-8 mb-3">
                                        <tr>
                                            <th rowspan="2">Item</th>
                                            <th colspan="3" style="text-align:center">Price</th>
                                            <th colspan="3" style="text-align:center">Discount</th>
                                            <th colspan="2" style="text-align:center">Tax</th>
                                            <th>Total</th>
                                        </tr>
                                        <tr>
                                            <th>Rate</th>
                                            <th>Qty</th>
                                            <th>Amount</th>
                                            <th>%</th>
                                            <th>Disc. Amt</th>
                                            <th>Amount</th>
                                            <th>Rate%</th>
                                            <th>Tax Amt</th>
                                            <th>Amount</th>
                                        </tr>

                                        @foreach ($generate_po_data as $k => $val)
                                            <tr>
                                                <td>{{ $val['item_name'] }}</td>
                                                <td>{{ $val['price'] }}</td>
                                                <td>{{ $val['qty'] }}</td>
                                                <td>{{ $val['amount'] }}</td>
                                                <td>{{ $val['dis_per'] }}</td>
                                                <td>{{ $val['dis_amount_value'] }}</td>
                                                <td>{{ $val['after_dis_amount'] }}</td>
                                                <td>{{ $val['tax_per'] }}</td>
                                                <td>{{ $val['tax_amount_value'] }}</td>
                                                <td>{{ $val['after_tax_amount'] }}</td>
                                            </tr>
                                        @endforeach

                                    </table>
                                </center>
                            </div>
                        </div>

                        {{-- -------------------- NEGOTIATE PO SECTION -------------------- --}}
                        <h4 class="page-title text-center">Negotiate Purchase Order</h4>

                        <div class="row">

                            {{-- PO NUMBER --}}
                            <div class="col-md-6 form-group">
                                <label>PO No.</label>
                                <input type="text" name="po_number"
                                    value="{{ $generate_po_data[0]['po_number'] }}"
                                    class="form-control" readonly>
                            </div>

                            {{-- VENDOR NAME --}}
                            <div class="col-md-6 form-group">
                                <label>Vendor Name</label>
                                <input type="text" name="vendor_name"
                                    value="{{ $generate_po_data[0]['vendor_name'] }}"
                                    class="form-control" readonly>
                                <input type="hidden" name="vendor_id"
                                    value="{{ $generate_po_data[0]['vendor_id'] }}">
                            </div>

                            {{-- DELIVERY DATETIME --}}
                            <div class="col-md-6 form-group">
                                <label>Delivery DateTime</label>
                                <input type="text" class="form-control mydatepicker"
                                    name="delivery_time"
                                    value="@if(isset($data->delivery_time)){{ date('d-m-Y', strtotime($data->delivery_time)) }}@else{{ $generate_po_data[0]['delivery_time'] }}@endif">
                            </div>

                            {{-- PLACE OF DELIVERY --}}
                            <div class="col-md-6 form-group">
                                <label>Place of Delivery</label>
                                <textarea class="form-control" name="po_place_of_delivery">@if(isset($data->po_place_of_delivery)){{ $data->po_place_of_delivery }}@else{{ $generate_po_data[0]['po_place_of_delivery'] }}@endif</textarea>
                            </div>

                            {{-- PAYMENT TERMS --}}
                            <div class="col-md-6 form-group">
                                <label>Payment Terms</label>
                                <textarea class="form-control" name="payment_terms">@if(isset($data->payment_terms)){{ $data->payment_terms }}@else{{ $generate_po_data[0]['payment_terms'] }}@endif</textarea>
                            </div>

                            {{-- REMARKS --}}
                            <div class="col-md-6 form-group">
                                <label>Remarks</label>
                                <textarea class="form-control" name="remarks">@if(isset($data->remarks)){{ $data->remarks }}@else{{ $generate_po_data[0]['remarks'] }}@endif</textarea>
                            </div>

                            {{-- TRANSPORT CHARGE --}}
                            <div class="col-md-6 form-group">
                                <label>Transportation Charge</label>
                                <input type="number" name="transportation_charge" class="form-control"
                                    value="@if(isset($data->transportation_charge)){{ $data->transportation_charge }}@else{{ $generate_po_data[0]['transportation_charge'] }}@endif">
                            </div>

                            {{-- INSTALLATION CHARGE --}}
                            <div class="col-md-6 form-group">
                                <label>Installation Charge</label>
                                <input type="number" name="installation_charge" class="form-control"
                                    value="@if(isset($data->installation_charge)){{ $data->installation_charge }}@else{{ $generate_po_data[0]['installation_charge'] }}@endif">
                            </div>
<div class="col-md-12 form-group">
                                <table class="col-md-12 form-group">
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
                                           <td>
        {{ $v->price }}
        <input type="hidden" id="price[{{ $v->item_id }}]" name="price[{{ $v->item_id }}]" value="{{ $v->price }}">
    </td>
                                           <td>
        <input type="text"
               name="qty[{{ $v->item_id }}]"
               id="qty[{{ $v->item_id }}]"
               value="{{ $v->qty }}"
               onkeyup="update_total_amount_2(this, '{{ $v->item_id }}')"
               style="width:70px;">
    </td>
                                            <td>
        <input type="text"
               name="amount[{{ $v->item_id }}]"
               id="amount[{{ $v->item_id }}]"
               value="{{ $v->amount }}"
               readonly
               style="width:90px;">
    </td>

                                            <td>
        <input type="text"
               name="dis_per[{{ $v->item_id }}]"
               id="dis_per[{{ $v->item_id }}]"
               value="{{ $v->dis_per }}"
               onkeyup="discount_amount_per(this, '{{ $v->item_id }}')"
               style="width:70px;">
    </td>
                                           <td>
        <input type="text"
               name="dis_amount_value[{{ $v->item_id }}]"
               id="dis_amount_value[{{ $v->item_id }}]"
               value="{{ $v->dis_amount_value }}"
               readonly
               style="width:90px;">
    </td>
    
                                            <td>
        <input type="text"
               name="after_dis_amount[{{ $v->item_id }}]"
               id="after_dis_amount[{{ $v->item_id }}]"
               value="{{ $v->after_dis_amount }}"
               readonly
               style="width:90px;">
    </td>

                                           <td>
        <input type="text"
               name="tax_per[{{ $v->item_id }}]"
               id="tax_per[{{ $v->item_id }}]"
               value="{{ $v->tax_per }}"
               onkeyup="tax_percentage(this, '{{ $v->item_id }}')"
               style="width:70px;">
    </td>

                                            <td>
        <input type="text"
               name="tax_amount_value[{{ $v->item_id }}]"
               id="tax_amount_value[{{ $v->item_id }}]"
               value="{{ $v->tax_amount_value }}"
               readonly
               style="width:90px;">
    </td>
                                            <td>
        <input type="text"
               name="after_tax_amount[{{ $v->item_id }}]"
               id="after_tax_amount[{{ $v->item_id }}]"
               value="{{ $v->after_tax_amount }}"
               readonly
               style="width:90px;">
    </td>
                                        </tr>
                                    @endforeach
                                    @endif
                                </table>
                            {{-- ---------------- APPROVAL STATUS ---------------- --}}
                            <div class="row">
                            <div class="col-md-6 form-group">
                                <label>PO Approval Status</label>
                                <select class="form-control" name="po_approval_status" required>
                                    @foreach($status_data as $s)
                                        <option value="{{ $s['id'] }}"
                                            @if(isset($data->po_approval_status) && $data->po_approval_status == $s['id']) selected @endif>
                                            {{ $s['title'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- APPROVAL REMARKS --}}
                            <div class="col-md-6 form-group">
                                <label>PO Approval Remarks</label>
                                <textarea class="form-control" name="po_approval_remark">@if(isset($data->po_approval_remark)){{ $data->po_approval_remark }}@endif</textarea>
                            </div>

                            {{-- SAVE BUTTON --}}
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" value="Save" class="btn btn-success">
                                </center>
                            </div>

                        </div>
                    </form>
                    {{-- FORM END --}}

                </div>
            </div>

        </div>
    </div>
</div>
<script>
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
                     }

                     function discount_amount_per(element,item_id)
                     {   //alert(item_id);
    					total_amount = document.getElementById('amount['+item_id+']').value;						
    					percentage_amount = total_amount*element/100;
    					//alert(percentage_amount);
    					tot_amt = Number(total_amount) - Number(percentage_amount);
    					document.getElementById('dis_amount_value['+item_id+']').value = percentage_amount;
    					document.getElementById('after_dis_amount['+item_id+']').value = tot_amt;
    					
                     }

function tax_percentage(element,item_id)
                     {   
    					total_amount_after_disc = document.getElementById('after_dis_amount['+item_id+']').value;						
    					tax_percentage_amount = total_amount_after_disc*element/100;
    					//alert(tax_percentage_amount);
    					//alert(element);
    					Final_tot_amt = Number(total_amount_after_disc) + Number(tax_percentage_amount);
    					 document.getElementById('tax_amount_value['+item_id+']').value = tax_percentage_amount;
    					 document.getElementById('after_tax_amount['+item_id+']').value = Final_tot_amt;
    					
                     }

</script>
@include('includes.footerJs')
@include('includes.footer')
