@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style>
br{
    display:block !important;
}
</style>
<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Daily Voucher</h4> </div>
            </div>
        @php
            $to_date = "";

            if(isset($data['to_date']))
            {
                $to_date = $data['to_date'];                
            }
        @endphp
        <div class="card">
            @if ($sessionData = Session::get('data'))
                @if($sessionData['status_code'] == 1)
                <div class="alert alert-success alert-block">
                    @else
                    <div class="alert alert-danger alert-block">
                @endif
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
                <form action="{{ route('daily_voucher.create') }}" enctype="multipart/form-data" class="row">                
                    @csrf
                     <div class="col-md-4 form-group ml-0 mr-0">
                        <label>Date</label>
                        <input type="text" id="to_date" name="to_date" value="{{$to_date}}" class="form-control mydatepicker" autocomplete="off" required>
                    </div>
                    
                    <div class="col-md-4 form-group mt-4">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>
                </form>
            </div>
        @if(isset($data['fees_data']))
        @php
            if(isset($data['fees_data'])){
                $fees_data = $data['fees_data'];
            }
             if(isset($data['receipt_data'])){
                $receipt_data = $data['receipt_data'];
            }
            $total = 0;
        @endphp
        <div class="card">
            <div id="daily_voucher_table" class="table-responsive">
                <center>
                    <table class="table w-50">
                        <tbody>
                            <tr>
                                <td style="text-align: center;">
                                    <img style="width: 100px;height: 90px;margin: 0;" src="../../storage/fees/{{$receipt_data['receipt_logo']}}" alt="SCHOOL LOGO">
                                </td>
                                <td colspan="2" style="text-align: center;">
                                    <span class="sc-hd">{{$receipt_data['receipt_line_1']}}</span><br>   
                                    <span class="ma-hd">{{$receipt_data['receipt_line_2']}}</span><br>  
                                    <span class="rg-hd">{{$receipt_data['receipt_line_3']}}</span><br> 
                                    <span class="rg-hd">Phone No.:{{$receipt_data['receipt_line_4']}}</span><br>                           
                                </td>

                            </tr>
                            <tr>
                                <td style='font-size:14px !important;padding-top: 50px;'><b>VOUCHER NO: _________</b></td>                                
                                <td style='font-size:14px !important;padding-top: 50px;' align=right><b>DATE: {{date('d-m-Y',strtotime($to_date))}}</b></td>                                
                            </tr>
                            <tr>
                                <td colspan="3" style="border-bottom:1px solid;border-top:1px solid;text-align: left !important;"><b>Fees collection, Cheque/Cash deposited as per Pay slip attached as mentioned below:</b></td>
                            </tr>
                          
                        </tbody>
                    </table>
                    <br><br>
                    <table width="25%" border="1px" cellspacing="0" cellpadding="5" align="center" style="border-collapse: collapse;">
                        <thead>
                            <tr>
                                <td style='font-size:15px;font-weight: bold;padding: 6px;'><SPAN style='font-size:14px !important;'><b>PARTICULARS</b></SPAN></td>
                                <td align=right style='font-size:15px;font-weight: bold;padding: 6px;'><SPAN style='font-size:14px !important;'><b>AMOUNT</b></SPAN></td>
                            </tr>
                        </thead>
                        <tbody>                                                   
                        @if(isset($data['fees_data']))
                            @foreach($fees_data as $key => $fees_value)                  
                            <tr>                            
                                <td style="padding: 6px;">{{$fees_value['FEES_TYPE']}}</td>                           
                                <td style="padding: 6px;" align="right">{{$fees_value['AMOUNT']}}</td>                           
                                @php
                                $total += $fees_value['AMOUNT'];
                                @endphp
                            </tr>                        
                            @endforeach
                            <tr>
                                <td align="right" style="padding: 6px;"><b>GRAND TOTAL</b></td>
                                <td style="padding: 6px;"  align="right"><b>{{$total}}</b></td>                            
                            </tr>                                               
                        @endif
                        </tbody>
                    </table>
                    <table width="70%" cellspacing="0" cellpadding="5" align="center">
                        <tr>
                        <td style='font-size:14px !important;padding-top: 130px;'><b>ACCOUNTANT</b></td>
                        <td style='font-size:14px !important;padding-top: 130px;' align=right><b>PRINCIPAL</b></td>
                        </tr>
                        <tr>
                        <td colspan="2" align="right" style='font-size:10px !important;'>{{date('d-m-Y h:i:sa')}}</td>
                        </tr>
                    </table>
                </center>

            </div>
            <center>
                <button id="btnPrint" type="button" class="btn btn-primary" onclick="PrintDiv('daily_voucher_table');">Print</button>
            </center> 
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script type="text/javascript">
function PrintDiv(divName) 
{
    var divToPrint = document.getElementById(divName);
    var popupWin = window.open('', '_blank', 'width=300,height=300');
    popupWin.document.open();
    popupWin.document.write('<html>');
    popupWin.document.write('<body onload="window.print()">' + divToPrint.innerHTML + '</html>');
    popupWin.document.close();
}
</script>
@include('includes.footer')
