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
                                   <tbody id="itemTableBody">
    @include('inventory.partials.vendor_items_rows', ['item_data' => $item_data])
</tbody>


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
<script>
function calculateRow(id) {
    let price = Number($("#price_" + id).val());
    let qty = Number($("#qty_" + id).val());

    let amount = price * qty;
    $("#amount_" + id).val(amount);

    let dis_per = Number($("#dis_per_" + id).val());
    let dis_amt = amount * dis_per / 100;
    $("#dis_amount_value_" + id).val(dis_amt);

    let after_dis = amount - dis_amt;
    $("#after_dis_amount_" + id).val(after_dis);

    let tax_per = Number($("#tax_per_" + id).val());
    let tax_amt = after_dis * tax_per / 100;
    $("#tax_amount_value_" + id).val(tax_amt);

    $("#after_tax_amount_" + id).val(after_dis + tax_amt);
}


// Bind keyup events
function bindEvents() {
    $(".cls_qty, .cls_dis, .cls_tax").off().on("keyup", function () {
        calculateRow($(this).data("id"));
    });
}


$(document).ready(function () {

    bindEvents(); // initial page load

    $('select[name="vendor_id"]').on('change', function () {

        $.ajax({
            url: "{{ url('/inventory/get-vendor-items') }}",
            type: "GET",
            data: { vendor_id: $(this).val() },

            success: function (data) {
                $("#itemTableBody").html(data);
                bindEvents(); // re-apply events after loading new rows
            }
        });

    });
});
</script>


@include('includes.footerJs')
@include('includes.footer')
