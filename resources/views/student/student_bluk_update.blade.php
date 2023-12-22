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
                @php $field = Session::get('data'); @endphp
                @if ($sessionData = Session::get('data'))
                    @if (isset($sessionData['status']))
                        <div class="alert alert-{{ $sessionData['status'] == 1 ? 'success' : 'danger' }} alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{!! $sessionData['message'] !!}</strong>
                        </div>
                    @endif
                @endif

                <form action="{{ route('student_bulk_update.store') }}" id="submit_student_bulk_update_form" method="post" enctype="multipart/form-data">
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
                            <tr>
                                <th>Students active/inactive excel</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-4" style="margin-left: 0px !important">
                                            <select id='student_active_inactive_excel' name="student_active_inactive_excel" class="form-control">
                                                <option>-- Select --</option>
                                                <option value="Active" name="active">Active</option>
                                                <option value="Inactive" name="inactive">Inactive</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4" style="margin-left: 0px !important">
                                            <div class="custom-file">
                                                <input type="file" class="form-control" name="attachment" id="attachment" required>
                                            </div>
                                        </div>
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
