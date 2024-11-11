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
        @if (isset($data['status_code']))
                @if($data['status_code'] == 1)
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
                @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
            @endif
			<form action="{{ route('subject_elective.update',$data['subject_elective']->id) }}" enctype="multipart/form-data" method="post">
            @method('PUT') 
                @csrf
				<div class="col-md-12">
					<div class="form-group">
						<div class="row">
                            <div class="col-md-4">
                                <label for="">Standard</label>
                                <input type="text" name="standard" id="standard" class="form-control" value="{{$data['subject_elective']->std_name}}" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="">Division</label>
                                <input type="text" name="division" id="division" class="form-control" value="{{$data['subject_elective']->div_name}}" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="">Optional Subject</label>
                                <select name="opt_sub" id="opt_sub" class="form-control">
                                    <option value="0" @if($data['subject_elective']->subject_id==0) selected @endif>N/A</option>
                                    @foreach($data['optional_subject'] as $key=>$value)
                                    <option value="{{$value->subject_id}}" @if($data['subject_elective']->subject_id==$value->subject_id) selected @endif>{{$value->display_name}}</option>                                    
                                    @endforeach
                                </select>
                            </div>
						</div>
					</div>
				</div>
                <div class="col-md-12">
                    <center>
                        <input type="submit" value="Update" name="Update" class="btn btn-success">
                    </center>
                </div>
			</form>
		</div>

	</div>
</div>

@include('includes.footerJs') 
<script>
   
</script>
@include('includes.footer') @endsection