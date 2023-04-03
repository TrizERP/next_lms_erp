@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Requisition Form Approval</h4>
            </div>
        </div>
        <div class="card">
            @if ($message = Session::get('data'))
                @if($message['status_code'] == 1)
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
                @endif
                <button type="button" class="close" data-dismiss="alert">x</button>
                <strong>{{ $message['message'] }}</strong>
                </div>
            @endif
                    <div class="row">
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">

                                @if(isset($data['data']) )
                        <form action="{{ route('requisition_approved.store') }}" method="post">
                            @csrf
                            <table id="student_list" class="table table-striped">
                                <tr>
                                    <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>                               <th>Requisition By</th>
                                    <th>Requisition Date</th>
                                    <th>Requisition No.</th>
                                    <th>Item</th>
                                    <th>Opening Stock</th>
                                    <th>Item Qty</th>
                                    <th>Approved Qty</th>
                                    <th>Expected Delivery Time</th>
                                    <th>Remarks (if any)</th>
                                    <th>Requisition Status</th>
                                    <th>Requisition Approved By</th>
                                    <th>Requisition Approved Remarks (if any)</th>
                                    <th>Items Direct Purchase</th>
                                </tr>
                                @foreach ($data['data'] as $key => $val)
                                    <tr>
                                        @php
                                            if($val->opening_stock == 0)
                                            {
                                                $readonly = ' disabled="disabled" ';
                                            }else{
                                                $readonly = '';
                                            }
                                        @endphp
                                        <td><input id="{{$val->id}}" value="{{$val->id}}" name="requisitions[]"
                                                   type="checkbox" {{$readonly}}></td>
                                        <td>{{$val->requisition_name}}</td>
                                        <td>{{$val->requisition_date}}</td>
                                        <td>{{$val->requisition_no}}</td>
                                        <td>{{$val->item_name}}</td>
                                        <td>{{$val->opening_stock}}</td>
                                        <td>{{$val->item_qty}}</td>
                                        <td>
                                            <input type="number" name="approved_qty[{{$val->id}}]"
                                                   value="{{$val->approved_qty}}" class="form-control" {{$readonly}}>
                                        </td>
                                        <td>{{$val->expected_delivery_time}}</td>
                                        <td>{{$val->remarks}}</td>
                                        @if( isset($data['requisition_status_data']))
                                            <td>
                                                <select name="requisition_status[{{$val->id}}]"
                                                        class="form-control" {{$readonly}}>
                                                    <option value="">Select</option>
                                                    @foreach ($data['requisition_status_data'] as $ekey =>$status)
                                                        <option
                                                            @if($val->requisition_status == $status->requisition_status) selected="selected"
                                                            @endif value="{{$status->requisition_status}}">{{$status->title}}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        @endif
                                        <td>{{$val->requisition_approved_by}}</td>
                                        <td>
                                            <textarea class="form-control" rows="2"
                                                      name="requisition_approved_remarks[{{$val->id}}]" {{$readonly}}>{{$val->requisition_approved_remarks}}</textarea>
                                        </td>
                                        <td style="width: 11%;">
                                            <a href="{{ route('add_item_direct_purchase.create') }}"
                                               class="btn btn-info add-new" target="_blank">Direct Purchase</a>
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="14">
                                        <CENTER>
                                            <input type="submit" name="save" value="Save" class="btn btn-success">
                                        </CENTER>
                                    </td>
                                </tr>
                            </table>
                        </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
        </div>
    </div>


    @include('includes.footerJs')

    <script type="text/javascript">
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

        $(document).ready(function () {
            $('#student_list').DataTable();
        });

    </script>

@include('includes.footer')

