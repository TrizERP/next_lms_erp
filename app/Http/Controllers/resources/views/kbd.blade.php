@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Knowledge Base Detail</h4> </div>
            </div>
        <div class="row" style=" margin-top: 25px;">
            <div class="white-box">
            <div class="panel-body">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">

                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped responsive-utilities" id="example">
                                        <thead>
                                            <tr>
                                                <th style="text-align: center;"> Knowledge Base </th>
                                                <th style="text-align: center;"> <i class="mdi mdi-youtube-play fa-fw"></i>Youtube Link </th>
                                                <th style="text-align: center;"> <i class="mdi mdi-file-pdf fa-fw"></i>Pdf Link </th>
                                                
                                            </tr>
                                        </thead>
                                        <tbody>
                                          @foreach($data['data'] as $key => $value)
                                            <tr>
                                                <td><b>{{ $value['kname'] }}</b></td>
                                                <td style="text-align: center;">
                                                        <label> <a href="{{ $value['youtube_url'] }}" target="_blank"><i style="color: #ff0000;" class="mdi mdi-youtube-play fa-fw"></i></a> </label>
                                                </td>
                                                <td style="text-align: center;">
                                                        <label> <a href="{{ $value['pdf_url'] }}" target="_blank"><i style="color: #ff0000;" class="mdi mdi-file-pdf fa-fw"></i></a> </label>
                                                </td>
                                            </tr>

                                          @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                </div>
            </div>
            </div>
        </div>
    </div>
</div>
@include('includes.footerJs')
<script>
$(document).ready(function () {
    $('#example').DataTable();
});

</script>
@include('includes.footer')
