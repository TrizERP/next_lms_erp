@include('includes.headcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css"> @include('includes.header') @include('includes.sideNavigation')

<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">SMTP Setting</h4>
			</div>
		</div>

		<div class="card">
        <div class="card">
        @if(!empty($data['message']))
                    @if(!empty($data['status_code']) && $data['status_code'] == 1)
                        <div class="alert alert-success alert-block">
                            @else
                                <div class="alert alert-danger alert-block">
                                    @endif
                                    <button type="button" class="close" data-dismiss="alert">×</button>
                                    <strong>{{ $data['message'] }}</strong>
                                </div>
                            @endif
			<div class="panel-body p-0">
				<div class="row">
					<div class="col-lg-3 col-sm-3 col-xs-3 m-30">
						<a href="{{ route('smtp_setting.create') }}" class="btn btn-info add-new">
							<i class="fa fa-plus"></i> Add New</a>
					</div>

					<div class="col-lg-12 col-sm-12 col-xs-12">
						<div class="table-responsive">
							<table id="example" class="table table-striped">
								<thead>
									<tr>
										<th>Sr No</th>
										<th>Email</th>
										<th>Password</th>
										<th>Server Address</th>
										<th>Port</th>
										<th>Action</th>
									</tr>
								</thead>
								<tbody>
									@php $j=1; @endphp @if(isset($data['data'])) @foreach($data['data'] as $key => $datas)
									<tr>
										<td>{{$j}}</td>
										<td>{{$datas->gmail}}</td>
										<td>{{$datas->password}}</td>
										<td>{{$datas->server_address}}</td>
										<td>{{$datas->port}}</td>
										<td>
											<div class="d-inline">
												<a href="{{ route('smtp_setting.edit',$datas->id)}}" class="btn btn-info btn-outline">
													<i class="ti-pencil-alt"></i>
												</a>
											</div>
											<form class="d-inline" action="{{ route('smtp_setting.destroy', $datas->id)}}" method="post">
												@csrf @method('DELETE')
												<button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger">
													<i class="ti-trash"></i>
												</button>
											</form>
										</td>
									</tr>
									@php $j++; @endphp @endforeach @endif


								</tbody>

							</table>
						</div>
					
					</div>
				</div>
                @if(isset($data['data']))
						<div class="email-send mt-4">
							<form action="{{route('check-email')}}" method="post">
                            @csrf
								<div class="col-md-4 form-group">
									<label>Enter Email</label>
									<input type="text" required name="to_email" Placeholder="Enter User Email To Check" class="form-control">
								</div>
								<div class="col-md-12 form-group">
									<center>
										<input type="submit" name="submit" value="Check Email" class="btn btn-success">
									</center>
								</div>
							</form>
						</div>
						@endif
			</div>
		</div>
	</div>

	@include('includes.footerJs')


	<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
	<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
	<script>
		$(document).ready(function() {
			$('#example').DataTable({

			});
		});
	</script>

	@include('includes.footer')