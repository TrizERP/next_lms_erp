@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Inactive Student Bulk Update</h4>
            </div>
        </div>
        <div class="card">
            <div class="row mb-2">     
        </div>  
        @php $field =Session::get('data'); @endphp
        @if ($sessionData = Session::get('data'))
            @if($sessionData['status'] == 1)
                <div class="alert alert-success alert-block">
                    @else
                        <div class="alert alert-danger alert-block">
                            @endif
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{{ $sessionData['message'] }}</strong>
                        </div>
                    @endif

                    <form action="{{ route('student_bulk_update.store') }}" id="submit_student_bulk_update_form" method="post">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-box table-bordered">
                            <thead>
                            <tr>
                                <th>Module Name</th>
                                <th style="text-align:left;">Check for Student Bluk Update Data
                                    <p style="color:red;">(No. of ACTIVE Students {{count($data['get_student_enrollments'])}})</p>
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td>Can all students inactive</td>
                                <td>
                                    <input type="checkbox" id="student_bluk_update" value="1" name="tables">
                                </td>
                            </tr>
                            <tr>
                            <td>Delete Fees Breakoff</td>
                            <td>	
                            <div class="col-md-4 form-group" style="margin-left: 0px !important">
							<select id='bk_month' name="bk_month[]" class="form-control" multiple>
								<option>--Select BK Month--</option>
                                @if(isset($data['bk_month'])) 
                                @foreach($data['bk_month'] as $key => $value)
								<option value="{{$key}}" @if(isset($field['sel_bk_month']) && in_array($key,$field  ['sel_bk_month'])) selected @endif>{{$value}}</option>
								@endforeach 
                                @endif
							</select>
						</div>
                        </td>
                        </tr>

                            </tbody>
                        </table>
                    </div>
                    <div class="col-sm-12 form-group mt-3">
                        <center>
                            <input type="submit" name="submit" value="Submit" class="btn btn-success">
                        </center>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('includes.footerJs')

@include('includes.footer')
