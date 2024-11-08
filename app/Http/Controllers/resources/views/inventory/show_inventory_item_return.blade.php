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
                <h4 class="page-title">Item Return</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <form action="{{ route('show_inventory_item_return.create') }}">
                @csrf
                <div class="row">
                    <div class="col-md-3 form-group ml-0 mr-0">
                        <label for="requisition_by">Requisition By:</label>
                        <select name="requisition_by" id="requisition_by" class="form-control">
                            <option value="">Select Requisition By</option>
                            @foreach($data['users'] as $key => $value)
                                <option value="{{$value['id']}}"
                                        @if(isset($data['requisition_by']))
                                        @if($data['requisition_by'] == $value['id'])
                                        selected='selected'
                                    @endif
                                @endif
                                >{{$value['requisition_by_name']}}</option>
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
                <form method="POST" action="{{ route('show_inventory_item_return.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <table id="example" class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th></th>
                                        <th>Requisition Date</th>
                                        <th>Requisition By</th>
                                        <th>Requisition Remark</th>
                                        <th>Item</th>
                                        <th>Qty</th>
                                        <th>Return Qty</th>
                                        <th>Remarks</th>
                                        <th>Received By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                        @php
                                        $j=1;
                                        @endphp
                                    @foreach($student_data as $key => $data)
                                    <tr>
                                        <td><input id="{{$data->ITEM_ID}}" value="{{$data->ITEM_ID}}" name="items[]" type="checkbox" checked="checked" readonly></td>
                                        <td>{{$data->REQUISITION_DATE}}</td>
                                        <td>
                                            {{$data->REQUISITION_BY_NAME}}
                                            <input type="hidden" name="requisition_by[{{$data->ITEM_ID}}]" value="{{$data->requisition_by}}" class="form-control">
                                            <input type="hidden" name="requisition_details_id[{{$data->ITEM_ID}}]" value="{{$data->requisition_details_id}}" class="form-control">
                                        </td>
                                        <td>{{$data->REQUISITION_REMARK}}</td>
                                        <td>{{$data->ITEM_NAME}}</td>
                                        <td>{{$data->TOTAL_QTY}}</td>
                                        <td>
                                            <input type="number" name="return_qty[{{$data->ITEM_ID}}]" value="@if(isset($data->RETURN_QTY)){{$data->RETURN_QTY}}@endif" class="form-control">
                                        </td>
                                        <td>
                                            <textarea class="form-control" rows="2" name="remarks[{{$data->ITEM_ID}}]">@if(isset($data->REMARKS)){{$data->REMARKS}}@endif</textarea>
                                        </td>
                                        <td></td>
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
                            <!-- <input type="hidden" name="requisition_by" @if(isset($finalData['requisition_by'])) value="{{$finalData['requisition_by']}}" @endif>   -->
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
