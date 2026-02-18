@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Fees Circular</h4>
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
                    <form action="{{ route('fees_circular_master.store') }}" method="post">
                        {{ method_field("POST") }}                      
                        @csrf
                        <div class="row">
                        	{{ App\Helpers\SearchChain('4','single','grade,std') }}	                   
	                        <div class="col-md-4 form-group">
	                            <label>Bank Name </label>
	                            <input type="text" id='bank_name' required name="bank_name" class="form-control">
	                        </div>
	                        <div class="col-md-4 form-group">
	                            <label>Address Line 1 </label>
	                            <input type="text" id='address_line1' required name="address_line1" class="form-control">
	                        </div>
	                        <div class="col-md-4 form-group">
	                            <label>Address Line 2 </label>
	                            <input type="text" id='address_line2' required name="address_line2" class="form-control">
	                        </div>
	                        <div class="col-md-4 form-group">
	                            <label>Account No </label>
	                            <input type="text" id='account_no' required name="account_no" class="form-control">
	                        </div>
	                        <div class="col-md-4 form-group">
	                            <label>Paid Collection </label>
	                            <input type="text" id='paid_collection' required name="paid_collection" class="form-control">
	                        </div>
	                        <div class="col-md-4 form-group">
	                            <label>Shift </label>
	                            <input type="text" id='shift' required name="shift" class="form-control">
	                        </div>
	                        <div class="col-md-4 form-group">
	                            <label>Form No </label>
	                            <input type="text" id='form_no' required name="form_no" class="form-control">
	                        </div>
	                        <div class="col-md-4 form-group">
	                            <label>Branch </label>
	                            <input type="text" id='branch' required name="branch" class="form-control">
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
        </div>
    </div>
</div>

@include('includes.footerJs')
<script type="text/javascript">
	$(document).ready(function () {
    	$('#grade').attr('required', 'required');
		$('#standard').attr('required', 'required');
	});
</script>
@include('includes.footer')
