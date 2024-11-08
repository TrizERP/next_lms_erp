@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
         <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Item Defective</h4>
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
                    <form action="
                      @if (isset($data))
                    {{ route('add_inventory_item_defective.update', $data['ID']) }}
                    @else
                    {{ route('add_inventory_item_defective.store') }}
                    @endif" method="post">

                        @if(!isset($data))
                            {{ method_field("POST") }}
                        @else
                            {{ method_field("PUT") }}
                        @endif

                        @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Item</label>
                                <select class="selectpicker form-control" name="item_id" id="item_id"
                                        required="required">
                                    <option value="">Select Item</option>
                                    @if(!empty($menu))
                                        @foreach($menu as $key => $value)
                                            <option
                                                value="{{$value['ITEM_ID']}}" @if(isset($data['ITEM_ID'])){{$data['ITEM_ID'] == $value['ITEM_ID'] ? 'selected=selected' : '' }} @endif>{{$value['item_name']}}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Warranty Start Date</label>
                                <input type="text" class="form-control mydatepicker"
                                       value="@if(isset($data['WARRANTY_START_DATE'])){{$data['WARRANTY_START_DATE']}}@endif"
                                       name="warranty_start_date" autocomplete="off">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Warranty End Date</label>
                                <input type="text" class="form-control mydatepicker"
                                       value="@if(isset($data['WARRANTY_END_DATE'])){{$data['WARRANTY_END_DATE']}}@endif"
                                       name="warranty_end_date" autocomplete="off">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Defect Remarks</label>
                                <textarea class="form-control" rows="2" id='defect_remarks'
                                          name="defect_remarks">@if(isset($data['DEFECT_REMARKS'])){{$data['DEFECT_REMARKS']}}@endif</textarea>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Item Given To</label>
                                <textarea class="form-control" rows="2" id='item_given_to'
                                          name="item_given_to">@if(isset($data['ITEM_GIVEN_TO'])){{$data['ITEM_GIVEN_TO']}}@endif</textarea>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Estimated Received Date</label>
                                <input type="text" class="form-control mydatepicker"
                                       value="@if(isset($data['ESTIMATED_RECEIVED_DATE'])){{$data['ESTIMATED_RECEIVED_DATE']}}@endif"
                                       name="estimated_received_date" autocomplete="off">
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success">
                                </center>
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
