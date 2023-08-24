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
      background-color: rgba(0,0,0,0.5); /* Black background with opacity */
      z-index: 2; /* Specify a stack order in case you're using a different order for other elements */
      cursor: pointer; /* Add a pointer on hover */
    }
</style>

<!-- <style type="text/css">
    @media print {
        table td {
            padding: 8px !important;
        }
        td {
            font-size: 15px !important;
            color: #000 !important;
        }
    }
</style> -->

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Certificate</h4>
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
        <input type="hidden" name="action" id="action" value="{{$data['template']}}">
        <input type="hidden" name="insert_ids" id="insert_ids" value="{{$data['insert_ids']}}">
        <input type="hidden" name="template_name" id="template_name" value="{{$data['template']}}">
        <input type="hidden" name="certificate_reason" id="certificate_reason" value="{{ isset($data['certificate_reason']) ? $data['certificate_reason'] : '' }}">

        <div class="row">            
            <div class="col-md-12 form-group">
                <div id="overlay" style="display:none;">
                    <center>
                        <p style="margin-top: 273px;color:red;font-weight: 700;">Please do not refresh the page, while the process is going on.</p>
                        <img src="https://erp.triz.co.in/admin_dep/images/loader.gif">
                    </center>
                </div>
                <center>
                    <button id="ajax_PDF_Certificate" class="btn btn-success" >Print Certificate</button>
                    {{--onclick="certificate_save_data();"--}}
                </center>
            </div>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>
	function checkAll(ele) {
	     var checkboxes = document.getElementsByTagName('input');
	     if (ele.checked) {
	         for (var i = 0; i < checkboxes.length; i++) {
	             if (checkboxes[i].type == 'checkbox') {
	                 checkboxes[i].checked = true;
	             }
	         }
	     } else {
	         for (var i = 0; i < checkboxes.length; i++) {
	             console.log(i)
	             if (checkboxes[i].type == 'checkbox') {
	                 checkboxes[i].checked = false;
	             }
	         }
	     }
	}
    // function certificate_save_data(){
    //     var insert_ids = $("#insert_ids").val();
    //     var template_name = $("#template_name").val();
    //     var path = "{{ route('ajax_saveData') }}";
    //     $.ajax({
    //             url: path,
    //             data:'insert_student_ids='+insert_ids+'&template='+template_name, 
    //             success: function(result){
    //                 $("#last_inserted_ids").val(result);    
    //             }
    //     });
    // }

    if ( window.history.replaceState )
    {
      window.history.replaceState( null, null, window.location.href );
    }

</script>
@include('includes.footer')
