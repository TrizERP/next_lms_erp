<style>
    body {
        background: #ffffff;
    }

    page {
        background: white;
        display: block;
        margin: 0 auto;
        margin-bottom: 0.5cm;
        /* box-shadow: 0 0 0.5cm rgba(0, 0, 0, 0.5); */
    }

    page[size="A4"] {
        width: 21cm;
        height: 29.7cm;
    }

    page[size="A4"][layout="landscape"] {
        width: 29.7cm;
        height: 21cm;
    }

    page[size="A3"] {
        width: 29.7cm;
        height: 42cm;
    }

    page[size="A3"][layout="landscape"] {
        width: 42cm;
        height: 29.7cm;
    }

    page[size="A5"] {
        width: 14.8cm;
        height: 21cm;
    }

    page[size="A5"][layout="landscape"] {
        width: 21cm;
        height: 14.8cm;
    }

    @media print {

        body,
        page {
            margin: 0;
            box-shadow: 0;
        }
    }
</style>
<style>
    table.fees-receipt {
        border-collapse: collapse
    }

    .fees-receipt {
        border: 1px solid #888;
        height: 510px;
        overflow: hidden
    }

    .particulars {
        border-collapse: collapse
    }

    .particulars td {
        border: 1px solid #888;
        border-collapse: collapse
    }

    .fees-receipt td {
        font-family: Arial, Helvetica, sans-serif !important;
        padding: 6px 8px;
        font-size: 13px
    }

    .fees-receipt img.logo {
        width: 100px;
        height: 90px;
        margin: 0
    }

    .double-border {
        border-bottom: 1px double #000;
        border-width: 3px;
    }

    .particulars {        
        overflow: hidden;
        display: block;
        vertical-align: top
    }

    .particulars td {
        width: 100%;
        height: 20px;
        font-size: 12px
    }

    .mg-top {
        top: 10px;
        position: relative
    }

    .mg-top label {
        border-radius: 3px;
        font-weight: 700;
        font-size: 14px;
        top: 5px;
        position: relative
    }

    .receipt-hd {
        border: 1px solid #000;
        padding: 5px 15px;
        margin-top: 15px
    }

    .sc-hd {
        font-size: 26px;
        font-weight: 700;
        font-family: Arial, Helvetica, sans-serif !important
    }

    .ma-hd {
        font-size: 18px;
        font-weight: 700;
        font-family: Arial, Helvetica, sans-serif !important
    }

    .rg-hd {
        font-size: 14px;
        font-weight: 600;
        font-family: Arial, Helvetica, sans-serif !important
    }

    .padding {
        padding-bottom: 20px !important
    }

    .logo-width {
        width: 165px;
        text-align: center
    }
    br {
        display: block;
    }   
</style>

<div id="page-wrapper">

    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h3 class="page-title text-center">Fees Receipt</h3>
            </div>
        </div>
        <div id="printableArea" class="card">
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <?php
                        $page = "";
                            if($data['paper'] == "A5"){    
                                $page = '<page size="A5" layout="landscape">'; 
                                    echo $data['data'];
                            }
                           else if($data['paper'] == "A5DB"){    
                                $page = '<page size="A5" layout="landscape">'; 
                                    ?>
                    <table width="100%">
                        <tr>
                            <td style="width:50%">
                                <?php echo $data['data']; ?>
                            </td>
                            <td style="width:50%;">
                                <?php echo $data['data']; ?>
                            </td>
                        </tr>
                    </table>
                    <?php
                        }
                      else  if($data['paper'] == "A4"){    
                            $page = '<page size="A4" layout="landscape">'; 
                                echo $data['data']; 
                        }
                      else  if($data['paper'] == "A4DB"){    
                            $page = '<page size="A4">'; 
                            echo $data['data']; 
                            echo $data['data']; 
                        }
                    ?>
                    
                </div>
            </div>
        </div>
    </div>
    <center> <input type="button" onclick="PrintDiv('printableArea')" value="Print Receipt" class="btn btn-success"/></center>
</div>

{{-- <div id="printableArea" class="col-md-12"> --}}
{{-- <page size="A4"> --}}




{{-- </page> --}}
{{-- </div> --}}
<!-- <center> <input type="button" onclick="PrintDiv('printableArea')" value="Print Receipt" /></center> -->
{{-- <page size="A4"></page>
<page size="A4" layout="landscape"></page>
<page size="A5"></page>
<page size="A5" layout="landscape"></page>
<page size="A3"></page>
<page size="A3" layout="landscape"></page> --}}
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
        var mainCss = "page {background: white;display: block;margin: 0cm;margin-bottom: 0cm;}page[size='A4'] {width: 21cm;height: 29.7cm;}page[size='A4'][layout='landscape'] {width: 29.7cm;height: 21cm;}page[size='A3'] {width: 29.7cm;height: 42cm;}page[size='A3'][layout='landscape'] {width: 42cm;height: 29.7cm;}page[size='A5'] {width: 14.8cm;height: 21cm;}page[size='A5'][layout='landscape'] {width: 21cm;height: 14.8cm;}media print {body,page {margin: 0;box-shadow: 0;}}";
        var css = "{{$data['css']}}";
        var finalCss = mainCss +  css;
        // popupWin.document.write("<style>" + css + "</style>");
        popupWin.document.write("<style>" + finalCss + "</style>");
        popupWin.document.write('<body onload="window.print()"><?php echo $page; ?>' + divToPrint.innerHTML + '</page></html>');
        popupWin.document.close();
    }
</script>