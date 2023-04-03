@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style>
    br {
        display: block;
    }

    .table-bordered {
        border: 1px solid #dee2e6;
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">S4-NACH Excel Import</h4>
            </div>
        </div>
        <div class="card">

            <!-- <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
            </div> -->

            <form action="{{ route('NACH_s4excel_import.store') }}" enctype='multipart/form-data' method='post'>
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group ml-0 mr-0">
                        <label>Select File</label>

                        <input type="file" id="s4file" name="s4file" class="form-control" required>
                        <a href="../SAMPLE_NACH_S4_Import.xlsx" download class="text-primary h5">Sample S4 NACH File</a>
                    </div>
                    <div class="col-sm-4 form-group ml-0 mt-4">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>
                </div>
            </form>
            @if (isset($data['message']))
                <div class="border rounded mb-3 mb-md-4 mt-3 p-4 main_option_div border-dark"
                     style="overflow: auto !important;">
                    <h2>
                        <center>Fees Import Result</center>
                    </h2>

                    <strong>{!! $data['message'] !!}</strong>

                </div>
            @endif
        </div>
    </div>
</div>

@include('includes.footerJs')
<script type="text/javascript">
    function printreport_title(DIV_ID, TITLE) {
        // document.getElementById("fee_print").style.display = "none";
        // document.getElementById("fee_print2").style.display = "none";
        // var window_content;
        // window_content = jQuery('#' + DIV_ID).html();

        // var windowUrl = 'Report';

        // var windowName = 'Print ' + windowUrl;
        // params = 'width=' + screen.width;
        // params += ', height=' + screen.height;
        // params += ', scrollbars=yes';
        // params += ', fullscreen=yes';
        // var printWindow = window.open('', '', params);

        // printWindow.document.write('<link href="../admin_dep/css/printreport.css" type="text/css" rel="stylesheet"/>');

        // printWindow.document.write(window_content);
        // document.getElementById("fee_print").style.display = "";
        // document.getElementById("fee_print2").style.display = "";
        // printWindow.focus();
        // printWindow.print();


        //return false;

        var divToPrint = document.getElementById(DIV_ID);
        var popupWin = window.open('', '_blank', 'width=300,height=300');
        popupWin.document.open();
        popupWin.document.write('<html>');
        var mainCss = "page {background: white;display: block;margin: 0cm;margin-bottom: 0cm;}page[size='A4'] {width: 21cm;height: 29.7cm;}page[size='A4'][layout='landscape'] {width: 29.7cm;height: 21cm;}page[size='A3'] {width: 29.7cm;height: 42cm;}page[size='A3'][layout='landscape'] {width: 42cm;height: 29.7cm;}page[size='A5'] {width: 14.8cm;height: 21cm;}page[size='A5'][layout='landscape'] {width: 21cm;height: 14.8cm;}media print {body,page {margin: 0;box-shadow: 0;}}";

        popupWin.document.write('<link href="../admin_dep/css/printreport.css" type="text/css" rel="stylesheet"/>');

        popupWin.document.write('<body onload="window.print()">' + divToPrint.innerHTML + '</html>');
        popupWin.document.close();
    }
</script>
@include('includes.footer')
