@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Bazar Report</h4>
            </div>
        </div>
        <div class="card">
            
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <form action="{{ route('show_bazar_report') }}" method="post">
                    {{ method_field("POST") }}
                    {{csrf_field()}}

                    <div class="row">

                        <div class="col-md-4 form-group">
                            <label for="report_of">Select Report</label>
                            <select name="report_of" id="report_of" class="form-control" required>
                                <option value="">Select Report</option>
                                <option value="position_report">Position Report</option>
                                <option value="margin_report">Margin Report</option>
                                <option value="pnl_report">PNL Report</option>
                            </select>
                        </div>

                        <div class="col-md-4 form-group" id="for_from_date">
                            <label>From Date</label>
                            <input type="text" id="from_date" name="from_date" class="form-control mydatepicker"
                                   autocomplete="off" value="{{date('Y-m-d'); }}">
                        </div>

                        <div class="col-md-4 form-group" id="for_to_date">
                            <label>To Date</label>
                            <input type="text" id="to_date" name="to_date" class="form-control mydatepicker"
                                   autocomplete="off" value="{{date('Y-m-d'); }}">
                        </div>

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Search" class="btn btn-success">
                            </center>
                        </div>

                    </div>
                </form>
            </div>
            @if (count($errors) > 0)
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> There were some problems with your input.<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
