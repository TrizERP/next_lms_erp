@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Bulk Position Upload</h4>
            </div>
        </div>
        <div class="card">
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <form action="{{ route('store_position_data') }}" method="post">
                    {{ method_field("POST") }}
                    {{csrf_field()}}
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Date</label>
                            <input type="text" id="date" name="date" class="form-control mydatepicker"
                                   autocomplete="off" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Upload file</label>
                            <input type="file" class="form-control" name="filename" id="filename" required>
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
