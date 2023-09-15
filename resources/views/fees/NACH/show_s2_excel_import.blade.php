@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">S2-NACH Excel Import</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <form action="{{ route('NACH_s2excel_import.store') }}" enctype='multipart/form-data' method='post'>
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group ml-0 mr-0">
                        <label>Select File</label>

                        <input type="file" id="s2file" name="s2file" class="form-control" required>
                        <a href="../SAMPLE_NACH_S2_Import.xlsx" download class="text-primary h5">Sample S2 NACH File</a>
                    </div>
                    <div class="col-sm-4 form-group ml-0 mt-4">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
