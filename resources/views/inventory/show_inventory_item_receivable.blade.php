@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style type="text/css">
    input[type="checkbox"][readonly] {
        pointer-events: none;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Item Receivable</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <form action="{{ route('show_inventory_item_receivable.create') }}">
                @csrf
                <div class="row">
                    <div class="col-md-3 form-group ml-0 mr-0">
                        <label for="subject">Po Number:</label>
                        <select name="po_number" id="po_number" class="form-control" required="required">
                            <option value="">Select Po Number</option>
                            @foreach($data['po_numbers'] as $key => $value)
                                <option value="{{$value['po_number']}}"
                                        @if(isset($data['po_number']))
                                        @if($data['po_number'] == $value['po_number'])
                                        selected='selected'
                                    @endif
                                @endif
                                >{{$value['po_number']}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group ml-0 mr-0 mt-4">
                        <input type="submit" name="submit" value="Search" class="btn btn-success">
                    </div>
                </div>
            </form>
        </div>
        @if(isset($data['student_data']))
            @php
                if(isset($data['student_data'])){
                    $student_data = $data['student_data'];
                    $finalData = $data;
                }
            @endphp
            <div class="card">
                <form method="POST" action="{{ route('show_inventory_item_receivable.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <table id="example" class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th></th>
                                        <th>Po Number</th>
                                        <th>Item</th>
                                        <th>Order Qty</th>
                                        <th>Previous Received Qty</th>
                                        <th>Actual Received Qty</th>
                                        <th>Pending Qty</th>
                                        <th>Remarks</th>
                                        <th>Warranty Start Date</th>
                                        <th>Warranty End Date</th>
                                        <th>Challan No</th>
                                        <th>Challan Date</th>
                                        <th>Bill No</th>
                                        <th>Bill Date</th>
                                        <th>Received By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                        @php
                                        $j=1;
                                        @endphp
                                    @foreach($student_data as $key => $data)
                                    <tr>
                                        <td><input id="{{$data->item_id}}" value="{{$data->item_id}}" name="items[]" type="checkbox" checked="checked" readonly></td>
                                        <td>{{$data->po_number}}</td>
                                        <td>{{$data->item_name}}</td>
                                        <td>
                                            {{$data->qty}}
                                            <input type="hidden" name="qty[{{$data->item_id}}]" value="{{$data->qty}}" class="form-control">
                                        </td>

                                        <td>
                                            {{$data->previous_receive_qty}}
                                            <input type="hidden" name="previous_receive_qty[{{$data->item_id}}]" value="{{$data->previous_receive_qty}}" class="form-control">
                                        </td>
                                        <td>
                                            <input type="number" name="actual_received_qty[{{$data->item_id}}]" value="@if(isset($data->ACTUAL_RECEIVED_QTY)){{$data->ACTUAL_RECEIVED_QTY}}@endif" class="form-control">
                                        </td>
                                        <td>
                                            {{$data->pending_qty}}
                                            <input type="hidden" name="pending_qty[{{$data->item_id}}]"  value="{{$data->pending_qty}}" class="form-control">
                                        </td>
                                        <td>
                                            <textarea class="form-control" rows="2" name="remarks[{{$data->item_id}}]">@if(isset($data->REMARKS)){{$data->REMARKS}}@endif</textarea>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control mydatepicker" value="@if(isset($data->WARRANTY_START_DATE)){{$data->WARRANTY_START_DATE}}@endif" name="warranty_start_date[{{$data->item_id}}]" autocomplete="off">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control mydatepicker" value="@if(isset($data->WARRANTY_END_DATE)){{$data->WARRANTY_END_DATE}}@endif" name="warranty_end_date[{{$data->item_id}}]" autocomplete="off">
                                        </td>
                                        <td>
                                            <input type="text" name="challan_no[{{$data->item_id}}]" value="@if(isset($data->CHALLAN_NO)){{$data->CHALLAN_NO}}@endif" class="form-control">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control mydatepicker" value="@if(isset($data->CHALLAN_DATE)){{$data->CHALLAN_DATE}}@endif" name="challan_date[{{$data->item_id}}]" autocomplete="off">
                                        </td>
                                        <td>
                                            <input type="text" name="bill_no[{{$data->item_id}}]" value="@if(isset($data->BILL_NO)){{$data->BILL_NO}}@endif" class="form-control">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control mydatepicker" value="@if(isset($data->BILL_DATE)){{$data->BILL_DATE}}@endif" name="bill_date[{{$data->item_id}}]" autocomplete="off">
                                        </td>
                                        <td>{{$data->received_by}}</td>
                                    </tr>
                                    @php
                                        $j++;
                                    @endphp
                                    @endforeach
                                </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="hidden" name="po_number"
                                       @if(isset($finalData['po_number'])) value="{{$finalData['po_number']}}" @endif>
                                <input type="submit" name="submit" value="Submit" class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>
    function checkAll(ele) {
         var checkboxes = document.getElementsByTagName('input');
         if (ele.checked) {
             for (var i = 0; i < checkboxes.length; i++) {
                 if (checkboxes[i].type == 'checkbox') {
                     checkboxes[i].checked = true;
                 }
             }
         } else {
             for (var i = 0; i < checkboxes.length; i++) {
                 console.log(i)
                 if (checkboxes[i].type == 'checkbox') {
                     checkboxes[i].checked = false;
                 }
             }
         }
    }
</script>
<script>
$(document).ready(function () {
    $('#example').DataTable();
});
</script>
@include('includes.footer')
