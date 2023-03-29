@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Tax Master</h4> 
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
                      {{ route('add_inventory_item_tax_master.update', $data->id) }}
                      @else
                      {{ route('add_inventory_item_tax_master.store') }}
                      @endif
                      " method="post">
                        @if(!isset($data))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif

                        {{csrf_field()}}
                        <div class="row">
                            <div class="col-md-4 form-group">
                                @csrf
                                <label>Title</label>
                                <input type="text" id='title' required name="title" value="@if(isset($data->title)){{$data->title}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Amount Percentage(%)</label>
                                <input type="number" id='title' required name="amount_percentage" value="@if(isset($data->amount_percentage)){{$data->amount_percentage}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Description</label>
                                <textarea class="form-control" rows="2" id='description_1' required name="description_1">@if(isset($data->description_1)){{ $data->description_1 }}@endif</textarea>  
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Sort Order</label>
                                <input type="number" id='sort_order' required name="sort_order" value="@if(isset($data->sort_order)){{$data->sort_order}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">                           
                                <label>Status</label>
                                    <div class="radio-list">
                                        <label class="radio-inline">
                                            <input type="radio" name="status" value="Yes" @if(isset($data->status)) {{ $data->status == 'Yes' ? 'checked' : '' }} @endif> Yes </label>
                                        <label class="radio-inline">
                                            <input type="radio" name="status" value="No" @if(isset($data->status)) {{ $data->status == 'No' ? 'checked' : '' }} @endif> No </label>
                                    </div>                            
                            </div>
                            <div class="col-md-4 form-group">
                                
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                
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
