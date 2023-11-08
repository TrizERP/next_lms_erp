@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Parent Communication</h4>            
            </div>                    
        </div>
                    
                <div class="card">
                    <form action="{{ route('parent_communication.create') }}" enctype="multipart/form-data" method="post">
                         {{ method_field("GET") }}
                        {{csrf_field()}}
                        <div class="row equal">
                        <div class="col-md-4 form-group">
                            <label>From Date</label>
                            <input type="text" name="from_date" class="form-control mydatepicker" autocomplete="off" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>To Date</label>
                            <input type="text" name="to_date" class="form-control mydatepicker"  autocomplete="off" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4 form-group mt-4">                           
                            <center>
                                <input type="submit" name="submit" value="Search" class="btn btn-success triz-btn" >
                            </center>
                        </div>
						</div>
                        <div class="row" style="visibility:hidden !important">
                        {{ App\Helpers\SearchChain('4','single','grade,std,div') }}
                        </div>
                    </form>
                </div>          
        
    </div>
</div>

@include('includes.footerJs')

@include('includes.footer')
