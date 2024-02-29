@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">General Setting</h4>
            </div>
        </div>
        <div class="card">
            <div class="row mb-2">     
            </div>  
                @php $field = Session::get('data'); @endphp
                @if ($sessionData = Session::get('data'))
                    @if (isset($sessionData['status_code']))
                        <div class="alert alert-{{ $sessionData['status_code'] == 1 ? 'success' : 'danger' }} alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{!! $sessionData['message'] !!}</strong>
                        </div>
                    @endif
                @endif
                <form action="{{ route('hrms_general_setting.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="table-responsive">
                    <table class="table table-box table-bordered">
                        <tbody>
                            <tr>
                                <th>Are you applying for sandwich leave in your institute?</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-4 form-group" style="margin-left: 0px !important">
                                            <select id='sandwich_leave' name="sandwich_leave" class="form-control" style="margin-left: 50px;">
                                                <option>-- Select --</option>
                                                <option value="Yes" <?php if ($data['get_sandwich_leave_data'] !== null && $data['get_sandwich_leave_data']->fieldvalue === 'Yes') echo "selected"; ?>>Yes</option>
                                                <option value="No" <?php if ($data['get_sandwich_leave_data'] !== null && $data['get_sandwich_leave_data']->fieldvalue === 'No') echo "selected"; ?>>No</option>
                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>How Many days allowed for casual leave at one time?</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-4 form-group" style="margin-left: 0px !important">
                                            <select id='casual_leave_at_one_time' name="casual_leave_at_one_time" class="form-control" style="margin-left: 50px;">
                                                <option value = "0">-- Select --</option>
                                                <option value="0" <?php if ($data['get_casual_leave_data'] !== null && $data['get_casual_leave_data']->fieldvalue === '0') echo "selected"; ?>>0</option>
                                                <option value="1" <?php if ($data['get_casual_leave_data'] !== null && $data['get_casual_leave_data']->fieldvalue === '1') echo "selected"; ?>>1</option>
                                                <option value="2" <?php if ($data['get_casual_leave_data'] !== null && $data['get_casual_leave_data']->fieldvalue === '2') echo "selected"; ?>>2</option>
                                                <option value="3" <?php if ($data['get_casual_leave_data'] !== null && $data['get_casual_leave_data']->fieldvalue === '3') echo "selected"; ?>>3</option>
                                                <option value="4" <?php if ($data['get_casual_leave_data'] !== null && $data['get_casual_leave_data']->fieldvalue === '4') echo "selected"; ?>>4</option>
                                                <option value="5" <?php if ($data['get_casual_leave_data'] !== null && $data['get_casual_leave_data']->fieldvalue === '5') echo "selected"; ?>>5</option>
                                            </select>
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
