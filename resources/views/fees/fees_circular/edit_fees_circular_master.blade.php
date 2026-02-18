@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit Fees Circular</h4>
            </div>
        </div>
        <div class="card">
			<!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="row">            	
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('fees_circular_master.update', $data['id']) }}" method="post">
                        {{ method_field("PUT") }}
                        @csrf
                        <div class="row">     
                        	{{ App\Helpers\SearchChain('4','single','grade,std',$data['grade_id'],$data['standard_id']) }}	                   		                        
	                        <div class="col-md-4 form-group">
	                            <label>Bank Name </label>
	                            <input type="text" id='bank_name' value="@if(isset($data['bank_name'])){{ $data['bank_name'] }}@endif" required name="bank_name" class="form-control">
	                        </div>
	                        <div class="col-md-4 form-group">
	                            <label>Address Line 1 </label>
	                            <input type="text" id='address_line1' value="@if(isset($data['address_line1'])){{ $data['address_line1'] }}@endif" required name="address_line1" class="form-control">
	                        </div>
	                        <div class="col-md-4 form-group">
	                            <label>Address Line 2 </label>
	                            <input type="text" id='address_line2' value="@if(isset($data['address_line2'])){{ $data['address_line2'] }}@endif" required name="address_line2" class="form-control">
	                        </div>
	                        <div class="col-md-4 form-group">
	                            <label>Account No </label>
	                            <input type="text" id='account_no' value="@if(isset($data['account_no'])){{ $data['account_no'] }}@endif" required name="account_no" class="form-control">
	                        </div>
	                        <div class="col-md-4 form-group">
	                            <label>Paid Collection </label>
	                            <input type="text" id='account_no' value="@if(isset($data['paid_collection'])){{ $data['paid_collection'] }}@endif" required name="paid_collection" class="form-control">
	                        </div>
	                        <div class="col-md-4 form-group">
	                            <label>Shift </label>
	                            <input type="text" id='shift' value="@if(isset($data['shift'])){{ $data['shift'] }}@endif" required name="shift" class="form-control">
	                        </div>
	                        <div class="col-md-4 form-group">
	                            <label>Form No </label>
	                            <input type="text" id='form_no' value="@if(isset($data['form_no'])){{ $data['form_no'] }}@endif" required name="form_no" class="form-control">
	                        </div>
	                        <div class="col-md-4 form-group">
	                            <label>Branch </label>
	                            <input type="text" id='branch' value="@if(isset($data['branch'])){{ $data['branch'] }}@endif" required name="branch" class="form-control">
	                        </div>
	                        <div class="col-md-12 form-group">
	                        	<center>	                        		
	                                <input type="submit" name="submit" value="Update" class="btn btn-success" >
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
