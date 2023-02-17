@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add House</h4> </div>
        </div>        
        <div class="card">
                    <form action="@if (isset($data->id))
                          {{ route('add_house.update', $data->id) }}
                          @else
                          {{ route('add_house.store') }}
                          @endif" method="post">
                          @if(!isset($data->id))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif
                            @csrf
                      
                        <div class="row">                       
                            <div class="col-md-4 form-group">
                                <label>House Name</label>
                                <input type="text" id='house_name' value="@if(isset($data->house_name)){{$data->house_name}}@endif" required name="house_name" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Sort Order</label>
                                <input type="number" id='sort_order' value="@if(isset($data->sort_order)){{$data->sort_order}}@endif" required name="sort_order" class="form-control">
                            </div>
                            <div class="col-md-4 form-group mt-4">                               
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </div>
                        </div>
                    </form>

        </div>        
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
