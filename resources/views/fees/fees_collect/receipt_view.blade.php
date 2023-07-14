@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">

    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Receipt</h4>
            </div>
        </div>
        <div id="printableArea" class="card">
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <?php
                    //dd($data);
                    //die();
                        $page = "";
                        if($data['paper'] == "A5"){    
                            $page = '<page size="A5" layout="landscape">'; 
                                echo $data['data'];
                        }
                        else if($data['paper'] == "A5DB")
                        {    
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
                        else  if($data['paper'] == "A4")
                        {    
                            $page = '<page size="A4" layout="landscape">'; 
                            echo $data['data']; 
                        }
                        else  if($data['paper'] == "A4DB")
                        {    
                            $page = '<page size="A4">'; 
                            echo $data['data']; 
                            echo $data['data']; 
                        }
                    ?>
                    <input type="hidden" name="action" id="action" value="fees_collect_receipt">
                    <input type="hidden" name="student_id" id="student_id" value="{{$data['student_id']}}">
                    <input type="hidden" name="receipt_id_html" id="receipt_id_html" value="{{$data['receipt_id_html']}}">
                    <input type="hidden" name="paper_size" id="paper_size" value="{{$data['paper']}}">
                </div>
            </div>
        </div>
    </div>
    <div id="overlay" style="display:none;"><center><p style="margin-top: 273px;color:red;font-weight: 700;">Please do not refresh the page, while the process is going on.</p><img src="https://erp.triz.co.in/admin_dep/images/loader.gif"></center></div>
    <center> <input type="button" value="Print Receipt" class="btn btn-success mb-2" id="ajax_PDF"/> {{--onclick="PrintDiv('printableArea')"--}}
    @php 
    $send_email =  DB::table('fees_config_master')->where(['sub_institute_id'=>session()->get('sub_institute_id'),'syear'=>session()->get('syear')])->pluck('send_email');    
    @endphp
    @if($send_email[0] == 1)
    <input type="button" value="Send Email" class="btn btn-success mb-2" id="ajax_sendEmail"/>
    @endif
    </center>
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

@include('includes.footerJs')
<script>

 function send_mail(){
     
 }

    if ( window.history.replaceState ) {
      window.history.replaceState( null, null, window.location.href );
    }

</script>
@include('includes.footer')