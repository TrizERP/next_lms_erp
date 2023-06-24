@include('includes.headcss') @include('includes.header') @include('includes.sideNavigation')

<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Add Norm Cluters</h4>
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
					<form action="{{route('norm-clature.create')}}">
						@csrf
						<div class="row">
							<div class="col-md-6 form-group">
								<label>Menu Master</label>
								<select name="menu_title" onchange="menu_title(this.value);" required id="menu_title" class="form-control">
									@if(isset($data['menu_title']) && !empty($data['menu_title'])) @foreach($data['menu_title'] as $key => $value)
									<option value="{{ $value->menu_id }}" @if(isset($data['menu_id']) && $data['menu_id']==$value->menu_id) selected @endif> {{ $value->menu }} </option>
									@endforeach @endif
								</select>
							</div>
						</div>
						<div class="col-md-12 form-group">
							<center>
								<input type="submit" name="submit" value="Search" class="btn btn-success">
							</center>
						</div>
					</form>
				</div>
			</div>
		</div>
		@if(isset($data['table_data']))
		<div class="card">
			@if ($message = Session::get('success'))
			<div class="alert alert-success alert-block">
				<button type="button" class="close" data-dismiss="alert">×</button>
				<strong>{{ $message }}</strong>
			</div>
			@endif
			<div class="row">
				<div class="col-lg-12 col-sm-12 col-xs-12">
				<form action="@if (isset($data['table_data'][0]['sub_institute_id']) && $data['table_data'][0]['sub_institute_id']!=session()->get('sub_institute_id')){{ route('norm-clature.store') }}
                @else{{ route('norm-clature.update',['norm_clature' => 'norm']) }}@endif" enctype="multipart/form-data" method="post">
                        @if(isset($data['table_data'][0]['sub_institute_id']) && $data['table_data'][0]['sub_institute_id']!=session()->get('sub_institute_id'))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif

						@csrf
						<div class="col-lg-12 col-sm-12 col-xs-12">
							<div class="table-responsive">
								<table id="tblLeaves" class="table table-striped table-bordered" style="width:100%">
									<thead>
										<tr class="raw0">
											<th>String</th>
											<th>Value</th>
											<th>value</th>
											<th>Created By</th>
											<th class="text-left">Updated On</th>
										</tr>
									</thead>
									<tbody>
									<input type="hidden" class="form-control" name="menu_id" value="@if(isset($data['table_data'][0]['sub_institute_id'])) {{$data['table_data'][0]['menu_id']}} @endif">
                                    @foreach($data['table_data'] as $key=>$value)
                                    <tr>
                                    <td>{{$value->string}}</td>
									<td>{{ DB::table('app_language')->whereRaw('sub_institute_id = 0 and string = "'.$value->string.'"')->value('value') }}</td>
                                    <td><input type="text" class="form-control" name="value[{{$value->id}}]" value="@if($value->value!='' || $value->value != null){{$value->value}}@endif"></td>                     
									<td>@php $name = DB::table('tbluser')->select('user_name')->where('id',$value->created_by)->first();  @endphp @if(isset($name)){{$name->user_name}}@endif</td> 
                                    <td>{{$value->updated_at}}</td>                                                         
                                    </tr>
                                    @endforeach
									</tbody>
								</table>
							</div>
						</div>
                        <div class="col-md-12 form-group mt-2">
							<center>
								<input type="submit" name="submit" value="Save" class="btn btn-success">
							</center>
						</div>
					</form>
				</div>
			</div>
		</div>
		@endif
		<!-- tbale -->
	</div>
</div>
@include('includes.footerJs') @include('includes.footer')