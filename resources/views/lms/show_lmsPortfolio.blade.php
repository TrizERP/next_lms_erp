{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<div class="content-main flex-fill">

	<div class="container-fluid mb-5">
		<div class="card border-0">
			<div class="card-body">
				<div class="dt-buttons mt-5">
					<button type="button" class="btn btn-danger mr-2"><i class="mdi mdi-delete-alert-outline"></i> Delete</button>
					<button type="button" class="btn btn-success mr-2"><i class="mdi mdi-eye-check-outline"></i> Show All</a>
					<button type="button" class="btn btn-warning mr-2"><i class="mdi mdi-pencil-box-outline"></i> Edit Pending</a>
				</div>
				<div class="table-responsive">
					<div class="dataTables_wrapper">
						<table id="example23" class="display nowrap table table-hover table-striped table-bordered dataTable" cellspacing="0" width="100%" role="grid" aria-describedby="example23_info" style="width: 100% !important;">
							<thead>
								<tr role="row">
									<th tabindex="0" aria-controls="example23" rowspan="1" colspan="1" aria-sort="ascending" aria-label="Name: activate to sort column descending">
										<div class="custom-control custom-checkbox">
											<input type="checkbox" id="lorem" class="custom-control-input">
											<label for="lorem" class="custom-control-label"></label>
										</div>
									</th>
									<th class="sorting_asc" tabindex="0" aria-controls="example23" rowspan="1" colspan="1" aria-sort="ascending" aria-label="Name: activate to sort column descending">Name</th>
									<th class="sorting" tabindex="0" aria-controls="example23" rowspan="1" colspan="1" aria-label="Position: activate to sort column ascending">Position</th>
									<th class="sorting" tabindex="0" aria-controls="example23" rowspan="1" colspan="1" aria-label="Office: activate to sort column ascending">Office</th>
									<th class="sorting" tabindex="0" aria-controls="example23" rowspan="1" colspan="1" aria-label="Age: activate to sort column ascending">Age</th>
									<th class="sorting" tabindex="0" aria-controls="example23" rowspan="1" colspan="1" aria-label="Start date: activate to sort column ascending">Start date</th>
									<th class="sorting" tabindex="0" aria-controls="example23" rowspan="1" colspan="1" aria-label="Salary: activate to sort column ascending">Salary</th>
								</tr>
							</thead>
							<tfoot>
								<tr>
									<th rowspan="1" colspan="1">
										<div class="custom-control custom-checkbox">
											<input type="checkbox" id="lorem" class="custom-control-input">
											<label for="lorem" class="custom-control-label"></label>
										</div>
									</th>
									<th rowspan="1" colspan="1">Name</th>
									<th rowspan="1" colspan="1">Position</th>
									<th rowspan="1" colspan="1">Office</th>
									<th rowspan="1" colspan="1">Age</th>
									<th rowspan="1" colspan="1">Start date</th>
									<th rowspan="1" colspan="1">Salary</th></tr>
							</tfoot>
							<tbody>
								<tr role="row">
									<td>
										<div class="custom-control custom-checkbox">
											<input type="checkbox" id="lorem" class="custom-control-input">
											<label for="lorem" class="custom-control-label"></label>
										</div>
									</td>
									<td class="sorting_1">Airi Satou</td>
									<td>Accountant</td>
									<td>Tokyo</td>
									<td>33</td>
									<td>2008/11/28</td>
									<td>$162,700</td>
								</tr>
								<tr role="row">
									<td>
										<div class="custom-control custom-checkbox">
											<input type="checkbox" id="lorem" class="custom-control-input">
											<label for="lorem" class="custom-control-label"></label>
										</div>
									</td>
									<td class="sorting_1">Angelica Ramos</td>
									<td>Chief Executive Officer (CEO)</td>
									<td>London</td>
									<td>47</td>
									<td>2009/10/09</td>
									<td>$1,200,000</td>
								</tr>
								<tr role="row">
									<td>
										<div class="custom-control custom-checkbox">
											<input type="checkbox" id="lorem" class="custom-control-input">
											<label for="lorem" class="custom-control-label"></label>
										</div>
									</td>
									<td class="sorting_1">Ashton Cox</td>
									<td>Junior Technical Author</td>
									<td>San Francisco</td>
									<td>66</td>
									<td>2009/01/12</td>
									<td>$86,000</td>
								</tr>
								<tr role="row">
									<td>
										<div class="custom-control custom-checkbox">
											<input type="checkbox" id="lorem" class="custom-control-input">
											<label for="lorem" class="custom-control-label"></label>
										</div>
									</td>
									<td class="sorting_1">Bradley Greer</td>
									<td>Software Engineer</td>
									<td>London</td>
									<td>41</td>
									<td>2012/10/13</td>
									<td>$132,000</td>
								</tr>
								<tr role="row">
									<td>
										<div class="custom-control custom-checkbox">
											<input type="checkbox" id="lorem" class="custom-control-input">
											<label for="lorem" class="custom-control-label"></label>
										</div>
									</td>
									<td class="sorting_1">Brenden Wagner</td>
									<td>Software Engineer</td>
									<td>San Francisco</td>
									<td>28</td>
									<td>2011/06/07</td>
									<td>$206,850</td>
								</tr>
								<tr role="row">
									<td>
										<div class="custom-control custom-checkbox">
											<input type="checkbox" id="lorem" class="custom-control-input">
											<label for="lorem" class="custom-control-label"></label>
										</div>
									</td>
									<td class="sorting_1">Brielle Williamson</td>
									<td>Integration Specialist</td>
									<td>New York</td>
									<td>61</td>
									<td>2012/12/02</td>
									<td>$372,000</td>
								</tr>
								<tr role="row">
									<td>
										<div class="custom-control custom-checkbox">
											<input type="checkbox" id="lorem" class="custom-control-input">
											<label for="lorem" class="custom-control-label"></label>
										</div>
									</td>
									<td class="sorting_1">Bruno Nash</td>
									<td>Software Engineer</td>
									<td>London</td>
									<td>38</td>
									<td>2011/05/03</td>
									<td>$163,500</td>
								</tr>
								<tr role="row">
									<td>
										<div class="custom-control custom-checkbox">
											<input type="checkbox" id="lorem" class="custom-control-input">
											<label for="lorem" class="custom-control-label"></label>
										</div>
									</td>
									<td class="sorting_1">Caesar Vance</td>
									<td>Pre-Sales Support</td>
									<td>New York</td>
									<td>21</td>
									<td>2011/12/12</td>
									<td>$106,450</td>
								</tr>
								<tr role="row">
									<td>
										<div class="custom-control custom-checkbox">
											<input type="checkbox" id="lorem" class="custom-control-input">
											<label for="lorem" class="custom-control-label"></label>
										</div>
									</td>
									<td class="sorting_1">Cara Stevens</td>
									<td>Sales Assistant</td>
									<td>New York</td>
									<td>46</td>
									<td>2011/12/06</td>
									<td>$145,600</td>
								</tr>
								<tr role="row">
									<td>
										<div class="custom-control custom-checkbox">
											<input type="checkbox" id="lorem" class="custom-control-input">
											<label for="lorem" class="custom-control-label"></label>
										</div>
									</td>
									<td class="sorting_1">Cedric Kelly</td>
									<td>Senior Javascript Developer</td>
									<td>Edinburgh</td>
									<td>22</td>
									<td>2012/03/29</td>
									<td>$433,060</td>
								</tr>
							</tbody>
						</table>
						<div class="d-md-flex align-items-center justify-content-between mt-3">
							<div class="dataTables_info" id="example23_info" role="status" aria-live="polite">Showing 1 to 10 of 57 entries</div>
							<div class="dataTables_paginate paging_simple_numbers" id="example23_paginate">
								<a class="paginate_button previous disabled" aria-controls="example23" data-dt-idx="0" tabindex="0" id="example23_previous">Previous</a>
								<span>
									<a class="paginate_button current" aria-controls="example23" data-dt-idx="1" tabindex="0">1</a>
									<a class="paginate_button " aria-controls="example23" data-dt-idx="2" tabindex="0">2</a>
									<a class="paginate_button " aria-controls="example23" data-dt-idx="3" tabindex="0">3</a>
									<a class="paginate_button " aria-controls="example23" data-dt-idx="4" tabindex="0">4</a>
									<a class="paginate_button " aria-controls="example23" data-dt-idx="5" tabindex="0">5</a>
									<a class="paginate_button " aria-controls="example23" data-dt-idx="6" tabindex="0">6</a>
								</span>
								<a class="paginate_button next" aria-controls="example23" data-dt-idx="7" tabindex="0" id="example23_next">Next</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
    </div>

</div>

@include('includes.lmsfooterJs')
@include('includes.footer')
@endsection
