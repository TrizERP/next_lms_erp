@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                @if(!isset($data['id']))
                Add Bank
                @else
                Edit Bank
                @endif
                </h4>
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
                    <form action="@if (isset($data['id']))
                          {{ route('bank_master.update', $data['id']) }}
                          @else
                          {{ route('bank_master.store') }}
                          @endif" enctype="multipart/form-data" method="post">
                            @if(!isset($data['id']))
                            {{ method_field("POST") }}
                            @else
                            {{ method_field("PUT") }}
                            @endif
                            @csrf
                        <div class="row">                            
                            <div class="col-md-6 form-group ml-0 mr-auto">
                                <label>Bank Name</label>
                                <input type="text" id='bank_name' value="@if(isset($data['bank_name'])){{$data['bank_name']}}@endif" required name="bank_name" class="form-control">
                            </div>
                                                       
                            <div class="col-md-12 form-group">                               
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >                                
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
