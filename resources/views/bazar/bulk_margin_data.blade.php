@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Bulk Margin Upload</h4>
            </div>
        </div>
        <div class="card">
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <form action="{{ route('store_margin_data') }}" method="POST" enctype="multipart/form-data">
                    {{csrf_field()}}
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Date</label>
                            <input type="text" id="upload_date" name="upload_date" class="form-control mydatepicker"
                                   autocomplete="off" value="{{date('Y-m-d'); }}" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Upload file</label>
                            <input type="file" class="form-control" name="attachment" id="attachment" required>
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Upload" class="btn btn-success">
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
