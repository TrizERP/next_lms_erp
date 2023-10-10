@include('includes.headcss')
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">               
                    Bazar Bulk Uploading
                </h4>
            </div>            
        </div>
        <div class="card">
            @if(session('message'))
                @if(session('status_code') == 1)
                    <div class="alert alert-success alert-block">
                @else
                    <div class="alert alert-danger alert-block">
                @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ session('message') }}</strong>
                </div>
            @endif
            <div class="row">                                   
                <div class="col-lg-12 col-sm-12 col-xs-12">                                      
                    <div class="col-md-12 form-group">
                        <a href="{{route('bulk_position_data')}}" class="btn btn-info add-new">Bulk Upoad Position</a>
                        
                        <a href="{{ route('bulk_margin_data') }}" target="_blank" class="btn btn-info add-new">Bulk Upload Margin</a>
                        
                        <a href="{{ route('bulk_pnl_data') }}" target="_blank" class="btn btn-info add-new">Bulk Upload PNL</a>
                    </div>			
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
