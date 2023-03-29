@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">       
            <div class="card">
                @if(!empty($data['message']))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('send_sms_report.create') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("GET") }}
                        {{csrf_field()}}
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>From Date</label>
                                <input type="text" name="from_date" class="form-control mydatepicker" autocomplete="off" />
                            </div>
                            <div class="col-md-4 form-group">
                                <label>To Date</label>
                                <input type="text" name="to_date" class="form-control mydatepicker" autocomplete="off" />
                            </div>
                            
                            <div class="col-md-4 form-group">
                                <label>Select</label>
                                <select name="tbl" class="form-control" required>
                                    <option value="">Select</option>
                                    <option value="staff">Staff</option>
                                    <option value="parent">Parents</option>
                                </select>
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
            </div>      
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
