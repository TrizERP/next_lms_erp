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
            <form action="{{ route('show_inventory_item_allocation.create') }}">
                @csrf
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label for="Item">Item :</label>
                        <select name="item_id" id="item_id" class="form-control">
                            <option value="">Select Item</option>
                            @foreach($data['item'] as $key => $value)
                                <option value="{{$value['item_id']}}"
                                        @if(isset($data['item_id']))
                                        @if($data['item_id'] == $value['item_id'])
                                        selected='selected'
                                    @endif
                                @endif
                                >{{$value['item_title']}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="requisition_by">Requisition By :</label>
                        <select name="requisition_by" id="requisition_by" class="form-control">
                            <option value="">Select Requisition By</option>
                            @foreach($data['user'] as $key => $value)
                                <option value="{{$value['requisition_by']}}"
                                @if(isset($data['requisition_by']))
                                    @if($data['requisition_by'] == $value['requisition_by'])
                                    selected='selected'
                                    @endif
                                @endif
                                >{{$value['requisition_by_name']}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>From Date </label>
                        <input type="text" id='from_date' required name="from_date" @if(isset($data['from_date'])) value="{{$data['from_date']}}" @endif class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>To Date </label>
                        <input type="text" id='to_date' required name="to_date" @if(isset($data['to_date'])) value="{{$data['to_date']}}" @endif class="form-control mydatepicker"
                               autocomplete="off">
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>
                </div>
            </form>
        </div>
        @if(isset($data['requisition_RET']))
            @php
                if(isset($data['requisition_RET'])){
                    $requisition_data = $data['requisition_RET'];
                    $finalData = $data;
                }
            @endphp
            <div class="card">
                <form method="POST" action="{{ route('show_inventory_item_allocation.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <table id="example" class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>
                                        <th>Sr.No.</th>
                                        <th>Requisition By</th>
                                        <th>Requisition Date</th>
                                        <th>Requisition No.</th>
                                        <th>Item</th>
                                        <th>Item Qty</th>
                                        <th>Item Type</th>
                                        <th>Expected Delivery Time</th>
                                        <th>Location of Material</th>
                                        <th>Person of Responsible</th>
                                        <th>Approval Status</th>
                                        <th>Approval Remarks</th>
                                        <th>Approved Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                        @php
                                        $j=1;
                                        @endphp
                                    @foreach($requisition_data as $key => $data)
                                    <tr>
                                        <td><input id="{{$data->ITEM_ID}}" value="{{$data->ITEM_ID}}" name="items[]" type="checkbox"></td>
                                        <td>{{$j}}</td>
                                        <td>
                                            {{$data->REQUISITION_BY_NAME}}
                                            <input type="hidden" value="{{$data->REQUISITION_DETAILS_ID}}" name="requisition_details_id[{{$data->ITEM_ID}}]">

                                            <input type="hidden" value="{{$data->REQUISITION_BY}}" name="requisition_by[{{$data->ITEM_ID}}]">
                                        </td>
                                        <td>{{$data->REQUISITION_DATE}}</td>
                                        <td>{{$data->REQUISITION_NO}}</td>
                                        <td>{{$data->ITEM_NAME}}</td>
                                        <td>{{$data->ITEM_QTY}}</td>
                                        <td>{{$data->ITEM_TYPE}}</td>
                                        <td>{{date('d-m-Y H:i:s',strtotime($data->EXPECTED_DELIVERY_TIME))}}</td>
                                        <td>
                                            <input type="text" class="form-control" value="@if(isset($data->LOCATION_OF_MATERIAL)){{$data->LOCATION_OF_MATERIAL}}@endif" name="location_of_material[{{$data->ITEM_ID}}]" autocomplete="off">
                                        </td>

                                        <td>
                                            <input type="text" class="form-control" value="@if(isset($data->PERSON_RESPONSIBLE)){{$data->PERSON_RESPONSIBLE}}@endif" name="person_responsible[{{$data->ITEM_ID}}]" autocomplete="off">
                                        </td>
                                        <td>{{$data->APPROVED_status}}</td>
                                        <td>{{$data->REMARKS}}</td>
                                        <td>{{$data->APPROVED_QTY}}</td>
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
                            <!-- <input type="hidden" name="po_number" @if(isset($finalData['po_number'])) value="{{$finalData['po_number']}}" @endif>                           -->
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
