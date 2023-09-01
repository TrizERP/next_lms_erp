@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')
<style>
		
    .cls_tr_frm_branch_field_line{
        line-height:9px;
    }
    .cls_td_date_boxes{
        line-height:9px;
    }
    .cls_space_input_boxes{
        display:inline;float:left;height:22px;width:10px;text-align:center;
    }
    .cls_qft_input_boxes{
        border: 1px solid;display:inline;float:left;height:22px;width:20px;text-align:center;
    }
    .cls_qft_input_boxes b{
        display:block;padding-top:25%;
    }
    .cls_in_input_boxes{
        border: 1px solid;display:inline;float:left;height:22px;width:13px;text-align:center;
    }
    .cls_in_input_boxes b{
        display:block;padding-top:40%;
    }
    .cls_lbl_in_boxes{
        display:inline;float:left;width:98px;margin-top:6px;font-size:10px;
    }
    .cls_atbc_input_boxes{
        border: 1px solid;display:inline;float:left;height:22px;width:13px;text-align:center;
    }
    .cls_atbc_input_boxes b{
        display:block;padding-top:40%;
    }
    .cls_lbl_atbc_boxes{
        display:inline;float:left;width:130px;margin-top:6px;font-size:10px;
    }
    .cls_stamp_lbl{
        text-align:center;
    }
    .cls_frm_amount_in_word_blank_field_line{
        border-bottom:1px solid;width:98%;
    }
    .cls_frm_amount_in_word_field_line{
        border-bottom:1px solid;width:74%;margin-left:24%;margin-top:-3%;text-align:center;
    }    
    .cls_frm_drawn_on_bank_field_line{
        border-bottom:1px solid;width:74%;margin-left:24%;margin-top:-3%;
    }    
    .cls_frm_cheque_dd_no_field_line{
        border-bottom:1px solid;width:74%;margin-left:24%;margin-top:-3%;
    }
    .cls_fee_tbl_heading1{
        text-align:center;
    }
    .cls_frm_father_name_field_line{
        border-bottom:1px solid;width:73%;margin-left:24%;margin-top:-3%;text-align:center;font-size:13px;
    }
    .cls_frm_class_div_field_line{
        border-bottom:1px solid;width:57%;margin-left:36%;margin-top:-7%;text-align:center;font-size:13px;
    }    
    .cls_frm_gr_no_field_line{
        border-bottom:1px solid;width:70%;margin-left:29%;margin-top:-6%;text-align:center;font-size:13px;
    }
    .cls_frm_stud_name_field_line{
        border-bottom:1px solid;width:72%;margin-left:25%;margin-top:-3%;text-align:center;font-size:13px;
    }
    .cls_frm_branch_field_line{
        border-bottom:1px solid;width:85%;margin-left:12%;margin-top:-3%;
    }
    .cls_lbl_date_boxes{
        display:inline;float:left;width:30px;margin-top:6px;font-size:10px;
    }
    .cls_input_boxes{
        border: 1px solid;display:inline;float:left;height:22px;width:13px;
    }
    .cls_lbl_pan_no{
        border:1px solid;float:left;margin-left:3%;margin-top:4px;
    }
    .cls_td_break{
        border-top: 1px solid;line-height:0px;padding-top:0px !important;
    }
    .cls_rec_head_lbl{
        text-align:center;
        font-size:12px;
    }
    .cls_rec_logo
    {
        margin-top: 1%;
    }
	.head_tit{
		font-size:12px;
		letter-space:1px;
		padding-right:10px;
		/*padding-top:12px;*/			
                    text-align: center !important;
                    width: 50%;
                    padding-left: 10px;
                    font-family:monospace Grande,monospace !important;
	}
            .head_tit b{
		font-size:12px;
		letter-space:1px;
		padding-right:10px;			
		font-weight:bolder;
                    text-align: center !important;
                    width: 50%;
                    padding-left: 10px;
                    font-family:monospace Grande,monospace !important;
	}
	.head_tit span{
		font-size:12px;
		letter-space:1px;
                    font-family:monospace Grande,monospace !important;
	}
	.head_tit2{
		font-size:11px !important;
		text-align:center;
		font-weight:normal;
		padding:0px !important;
	}
	
	.head_tit3{
		font-size:12px;
	}
	.fee_reci h3{
		border:2px solid #000;
		-moz-border-radius: 5px;
		-webkit-border-radius: 5px;
		border-radius: 5px;
		padding:4px;
		margin:0px auto;
		width:200px;
		font-size:18px;
		}
    #fee_tbl{
            background:#fff;
            border:2px solid #000;
            font-family:monospace Grande,monospace !important;
			margin:0px auto;
    }
    #fee_tbl td{
            padding:5px;
            background:#fff;
            font-size:10px;
    }
    #fee_tbl2{
            background:#000;

    }
    #fee_tbl2 td{
            padding:0px 5px;
            background:#fff;
            /*font-size:11px !important;*/
            font-size:12px !important;
            border-bottom:0.5px solid #000;
            border-right:0.5px solid #000;
            border-left:0.5px solid #000;
            border-top:0.5px solid #000;
    }
	#fee_tbl3 td{
            padding:0px 5px;
            background:#fff;
			font-size:11px !important;
    }
	.aslam1{
		float:left;
		width:50% !important;
		margin:0px auto 0px auto;
	}
            .aslam1.cls_imprest_aslam1{
		float:left;
		/*width:30% !important;*/
                    width:23% !important;
		margin:0px auto 0px auto;
                    /*margin-left:2%;*/
                    margin-left:4%;
                    /*margin-right:1%;*/
                    /*margin-right:0%;*/
                    margin-right:1%;
	}
            .aslam1.cls_imprest_aslam1.last{
                    margin-left:10%;
	}
            .aslam1.cls_imprest_aslam1.cls_imprest_collection_aslam1{
                /*margin-right:1%;*/
                margin-right:7%;
                /*margin-left:2%;*/
                margin-left:0%;
            }                
            .cls_ledger_main_div {margin-top:1%;}
            @media print {
                    /*.cls_ledger_main_div {page-break-after: always;}*/
                    div+.cls_ledger_main_div {page-break-before: always;}
            }        
    td b, td, p, div{
            font-family:Arial, Helvetica, sans-serif !important;
    }        

</style>

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
                    @php
                        $page = "";
                        if($data['paper'] == "A5"){    
                            $page = '<page size="A5" layout="landscape">'; 
                                echo $data['data'];
                        }
                        else if($data['paper'] == "A5DB")
                        {    
                            $page = '<page size="A5" layout="landscape">'; 
                    @endphp
                            <table width="100%">
                                <tr>
                                    <td style="width:50%">
                                        {{$data['data']}}
                                    </td>
                                    <td style="width:50%;">
                                      {{ $data['data']}}
                                    </td>
                                </tr>
                            </table>
                @php
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
                @endphp
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