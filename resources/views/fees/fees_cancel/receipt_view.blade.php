@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style type="text/css">
    #overlay {
        position: fixed; /* Sit on top of the page content */
        display: none; /* Hidden by default */
        width: 100%; /* Full width (cover the whole page) */
        height: 100%; /* Full height (cover the whole page) */
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5); /* Black background with opacity */
        z-index: 2; /* Specify a stack order in case you're using a different order for other elements */
        cursor: pointer; /* Add a pointer on hover */
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Refund Receipt</h4>
            </div>
        </div>
        <div id="printPage" class="card">


            @if(isset($data['str']))
                @php
                    if(isset($data['str'])){
                        $str = $data['str'];
                    }
                @endphp

                {!!$str!!}
        </div>
        <div class="pagebreak"></div>
        <div class="row">
            <div class="col-md-12 form-group">
                <center>
                    <div id="overlay" style="display:none;">
                        <center><p style="margin-top: 273px;color:red;font-weight: 700;">Please do not refresh the page,
                                while the process is going on.</p><img
                                src="http://dev.triz.co.in/admin_dep/images/loader.gif"></center>
                    </div>
                    <button class="btn btn-success" id="ajax_PDF">Print Receipt</button>
                    <input type="hidden" name="action" id="action" value="fees_refund_receipt">
                    <input type="hidden" name="student_id" id="student_id" value="{{$data['student_id']}}">
                    <input type="hidden" name="receipt_id_html" id="receipt_id_html"
                           value="{{$data['receipt_id_html']}}">
                    <input type="hidden" name="paper_size" id="paper_size" value="{{$data['paper']}}">
                    <input type="button" value="Send Email" class="btn btn-success" id="ajax_sendEmail"/>
                </center>
            </div>
        </div>


        @endif
    </div>
</div>

@include('includes.footerJs')
<script type="text/javascript">
    window.onafterprint = function () {
        window.location.reload(true);
    }
</script>
@include('includes.footer')
