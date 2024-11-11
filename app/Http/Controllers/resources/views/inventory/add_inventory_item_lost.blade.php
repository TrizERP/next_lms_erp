@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Item Lost</h4>
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
                      {{ route('add_inventory_item_lost.update', $data->ID) }}
                      @else
                      {{ route('add_inventory_item_lost.store') }}
                      @endif" method="post">
                        @if(!isset($data))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif
                        {{csrf_field()}}
                        <div class="row">                            
                            <div class="col-md-4 form-group">
                                    @csrf
                                <label for="item">Item:</label>
                                <select name="item_id" id="item_id" class="form-control">
                                    <option value="">Select item</option>
                                    @if(!empty($edit_item_data))  
                                    @foreach($edit_item_data as $key => $value)
                                        <option value="{{ $value['id'] }}" @if(isset($data->ITEM_ID)) {{ $data->ITEM_ID == $value['id'] ? 'selected' : '' }} @endif> {{ $value['title'] }} </option>
                                    @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="requisition_id">Requisition By:</label>
                                <select name="requisition_id" id="requisition_id" class="form-control">
                                    <option value="">Select Requisition By</option>
                                    @if(!empty($edit_user_data))  
                                    @foreach($edit_user_data as $k => $v)
                                        <option value="{{ $v['id'] }}" @if(isset($data->REQUISITION_BY)) {{ $data->REQUISITION_BY == $v['id'] ? 'selected' : '' }} @endif> {{ $v['first_name'] }} {{ $v['middle_name'] }} {{ $v['last_name'] }}</option>
                                    @endforeach
                                    @endif
                                </select>
                            </div>                    
                            <div class="col-md-4 form-group">
                                <label>Remarks </label>
                                <textarea class="form-control" rows="2" id='remarks' required name="remarks">@if(isset($data->REMARKS)) {{$data->REMARKS}} @endif</textarea>  
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Lost Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy" value="@if(isset($data->LOST_DATE)){{$data->LOST_DATE}}@endif" name="lost_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span> 
                                </div>
                            </div>                    
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
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
