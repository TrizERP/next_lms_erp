@extends('layout') @section('container')

<div id="page-wrapper">
	<div class="container-fluid">

		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">
					Subject Elective
				</h4>
			</div>
		</div>

		<div class="card">
			<form action="{{ route('subject_elective.store') }}" enctype="multipart/form-data" method="post">
                @csrf
				<div class="col-md-12">
					<div class="form-group">
						<div class="row">
							{{ App\Helpers\SearchChain('4','single','grade,std,div') }}
                            <div class="col-md-4 ">
                                <label for="">Optional Subject</label>
                                <select name="opt_sub" id="opt_sub" class="form-control" required>
                                    
                                </select>
                              </div>
						</div>
					</div>
				</div>
                <div class="col-md-12">
                    <center>
                        <input type="submit" value="Save" name="save" class="btn btn-success">
                    </center>
                </div>
			</form>
		</div>

	</div>
</div>

@include('includes.footerJs') 
<script>
   $(document).ready(function(){
        $('#division').change(function(){
            var standard = $('#standard').val();
            // alert(standard);
            $('#opt_sub').empty();
            $.ajax({
                url: '{{route("optional_subject")}}',
                data : {standard_id : standard},
                success: function (result) {
                    if(result.length > 0){
                        // console.log(result);
                        $('#opt_sub').append('<option value="0">N/A</option>');
                        result.forEach(element => {
                            console.log(element);
                            $('#opt_sub').append('<option value='+element.subject_id+'>'+element.display_name+'</option>');                            
                        });
                    }
                }
            })
        });
      });
</script>
@include('includes.footer') @endsection