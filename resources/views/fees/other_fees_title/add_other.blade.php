@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Other Fees Title</h4>
            </div>
        </div>
        <div class="card">
          <form enctype='multipart/form-data' action="
              @if (isset($data->display_name))
              {{ route('other_fees_title.update', $data->id) }}
              @else
              {{ route('other_fees_title.store') }}
              @endif" method="post">

                @if(!isset($data->display_name))
                {{ method_field("POST") }}
                @else
                {{ method_field("PUT") }}
                @endif
            
                {{csrf_field()}}
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Display Title</label>
                        <input type="text" id='display_name' required name="display_name" class="form-control" value="@if(isset($data->display_name)){{$data->display_name}}@endif">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Amount</label>
                        <input type="number" id='amount' required name="amount" class="form-control" value="@if(isset($data->amount)){{$data->amount}}@endif">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Sort Order</label>
                        <input type="number" id='sort_order' required name="sort_order" class="form-control" value="@if(isset($data->sort_order)){{$data->sort_order}}@endif">
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="control-label">Include in Imprest</label>
                        <div class="radio-list">
                            <label class="radio-inline p-0">
                                <div class="radio radio-success">
                                    <input type="radio" @if(isset($data->include_imprest) && $data->include_imprest == 'Y') checked @endif name="include_imprest" id="active" value="Y" required>
                                    <label for="Yes">Yes</label>
                                </div>
                            </label>
                            <label class="radio-inline">
                                <div class="radio radio-success">
                                    <input type="radio" @if(isset($data->include_imprest) && $data->include_imprest == 'N') checked @endif name="include_imprest" id="inactive" value="N" required>
                                    <label for="No">No</label>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="control-label">Status</label>
                        <div class="radio-list">
                            <label class="radio-inline p-0">
                                <div class="radio radio-success">
                                    <input type="radio" @if(isset($data->status) && $data->status == '1') checked @endif name="status" id="active" value="1" required>
                                    <label for="active">Active</label>
                                </div>
                            </label>
                            <label class="radio-inline">
                                <div class="radio radio-success">
                                    <input type="radio" @if(isset($data->status) && $data->status == '0') checked @endif name="status" id="inactive" value="0" required>
                                    <label for="inactive">Inactive</label>
                                </div>
                            </label>
                        </div>
                    </div>                    
                    <div class="col-md-4 form-group ml-0">                        
                        <input type="submit" name="submit" value="Submit" class="btn btn-success">
                    </div>
                </div>    
          </form>
        </div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')