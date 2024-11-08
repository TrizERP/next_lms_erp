@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                @if(!isset($data['period_data']))
                Add Petty Cash Master
                @else
                Edit Petty Cash Master
                @endif
                </h4>
                </div>            
        </div>    
        <div class="card">
            <div class="panel-body">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="@if (isset($data['petty_data']))
                          {{ route('pettycashmaster.update', $data['petty_data']['id']) }}
                          @else
                          {{ route('pettycashmaster.store') }}
                          @endif" enctype="multipart/form-data" method="post">
                          @if(!isset($data['petty_data']))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif
                            @csrf

                        <div class="row">
                            <div class="col-md-6 form-group ml-0">
                                <label>Title</label>
                                <input type="text" id='title' value="@if(isset($data['petty_data']['title'])){{$data['petty_data']['title']}}@endif" required name="title" class="form-control">
                            </div>                                                                       
                            <div class="col-md-12 form-group">                        
                                <input type="submit" name="submit" value="Save" class="btn btn-success">                        
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
