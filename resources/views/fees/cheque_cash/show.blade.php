@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Cash Cheque Report</h4>
            </div>
        </div>                 
                <div class="card">
                    <form action="{{ route('cheque_cash.create') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("GET") }}
                        {{csrf_field()}}
                        <div class="row">
                            {{ App\Helpers\SearchChain('4','multiple','grade,std,div') }}
                        
                            <div class="col-md-4 form-group">
                                <label>Fees Heads</label>
                                <select class="form-control" name="fees_heads[]" multiple="" required="required">
                                    <?php foreach ($data['data']['fees_title'] as $id => $val) {?>
                                        <option value="<?php echo $id; ?>"><?php echo $val; ?></option>
                                    <?php }?>
                                </select>
                            </div>
                            
                            <div class="col-md-4 form-group">
                                <label>From Date</label>
                                <input type="text" name="from_date" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            
                            <div class="col-md-4 form-group">
                                <label>To Date</label>
                                <input type="text" name="to_date" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            
                            <div class="col-md-12 form-group">                       
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
                                </center>
                            </div>                        
                        </div>
                    </form>
                </div>            
    </div>
</div>

@include('includes.footerJs')

@include('includes.footer')
