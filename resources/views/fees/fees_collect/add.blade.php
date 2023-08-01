@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<style>
    @font-face
    {
        font-family:Raleway;
        src: url(../../styles/fonts/Raleway-Regular.ttf);

    }
    table.fees-receipt
    {
        border-collapse: collapse;

    }
    .fees-receipt
    {
        border:1px solid #888;
        height:510px;
        overflow:hidden;
    }
    .particulars
    {
        border-collapse: collapse;
    }
    .particulars td
    {
        border:1px solid #888;
        border-collapse: collapse;
    }
    .fees-receipt td
    {
        font-family:'Arial', Helvetica, sans-serif !important;
        padding:0px 8px;
        font-size:13px;

    }

    .fees-receipt img.logo
    {
        width:100px;
        height:90px;
        margin:0px;
    }
    .double-border
    {
        border-bottom:1px double #000;
        border-width:5px;
    }
    .particulars
    {	
        height:180px;
        overflow:hidden;
        display:block;
        vertical-align:top;
    }
    .particulars td
    {
        width:100%;
        height:20px;
        font-size:12px;
    }
    .mg-top
    {
        top:10px;
        position:relative;

    }
    .mg-top label
    {
        border-radius: 3px;
        font-weight: 700;
        font-size: 14px;
        top:5px;
        position:relative;
    }
    .receipt-hd
    {
        border:1px solid #000;
        padding:5px 15px;
        margin-top:15px;
    }
    .sc-hd
    {
        font-size:26px;
        font-weight:bold;
        font-family:'Arial', Helvetica, sans-serif !important;
    }
    .ma-hd
    {
        font-size:18px;
        font-weight:bold;
        font-family:'Arial', Helvetica, sans-serif !important;
    }
    .rg-hd
    {
        font-size:14px;
        font-weight:600;
        font-family:'Arial', Helvetica, sans-serif !important;
    }
    .padding
    {
        padding-bottom:20px !important;		
    }
    .logo-width
    {
        width:165px;
        text-align:center;
    }

 
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Fees Receipt</h4>            
            </div>                    
        </div>
        <div id="printableArea" class="row" style=" margin-top: 25px;">
            <div class="panel-body white-box">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    {{ $data['data'] }}
                </div>
            </div>
        </div>
    </div>
    <center> <input type="button" onclick="PrintDiv('printableArea')" value="Print Receipt" /></center>
</div>


@include('includes.footerJs')
<script>
//    function printDiv(divName) {
//        var printContents = document.getElementById(divName).innerHTML;
//        var originalContents = document.body.innerHTML;
//
//        document.body.innerHTML = printContents;
//
//        window.print();
//
//        document.body.innerHTML = originalContents;
//    }
//    document.getElementsByTagName('button')[0].addEventListener('click', function () {
//        
//    });
    function PrintDiv(divName) {
        var divToPrint = document.getElementById(divName);
        var popupWin = window.open('', '_blank', 'width=300,height=300');
        popupWin.document.open();
        popupWin.document.write('<html>');
        var css = "table.fees-receipt{border-collapse:collapse}.fees-receipt{border:1px solid #888;height:510px;overflow:hidden}.particulars{border-collapse:collapse}.particulars td{border:1px solid #888;border-collapse:collapse}.fees-receipt td{font-family:Arial,Helvetica,sans-serif!important;padding:0 8px;font-size:13px}.fees-receipt img.logo{width:100px;height:90px;margin:0}.double-border{border-bottom:1px double #000;border-width:5px}.particulars{height:180px;overflow:hidden;display:block;vertical-align:top}.particulars td{width:100%;height:20px;font-size:12px}.mg-top{top:10px;position:relative}.mg-top label{border-radius:3px;font-weight:700;font-size:14px;top:5px;position:relative}.receipt-hd{border:1px solid #000;padding:5px 15px;margin-top:15px}.sc-hd{font-size:26px;font-weight:700;font-family:Arial,Helvetica,sans-serif!important}.ma-hd{font-size:18px;font-weight:700;font-family:Arial,Helvetica,sans-serif!important}.rg-hd{font-size:14px;font-weight:600;font-family:Arial,Helvetica,sans-serif!important}.padding{padding-bottom:20px!important}.logo-width{width:165px;text-align:center}";
        popupWin.document.write("<style>" + css + "</style>");
        popupWin.document.write('<body onload="window.print()">' + divToPrint.innerHTML + '</html>');
        popupWin.document.close();
    }
</script>
@include('includes.footer')
