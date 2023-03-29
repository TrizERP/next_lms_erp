@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Student I-Card Samples</h4> </div>
            </div>
            <div id="printPage">
        <?php
            $file_name = "icard/templates/template_1.html";

            $fin = fopen($file_name, 'r') or die("Selected Icard Template is not in proper format .");
            $string = fread($fin, filesize($file_name));
            fclose($fin);
        ?>
        <div class="row">
                <div class="white-box">
                    <div class="panel-body">
       
            <div class="col-md-12 form-group">
                <p>Template 1</p>
                <br>
                <br>
                    	<?php echo $string; ?>
            </div>
            
                    <div class="pagebreak"> </div>
        <?php
            $file_name = "icard/templates/template_2.html";

            $fin = fopen($file_name, 'r') or die("Selected Icard Template is not in proper format .");
            $string = fread($fin, filesize($file_name));
            fclose($fin);
        ?>
            <div class="col-md-12 form-group">
                <p>Template 2</p>
                <br>
                <br>
                        <?php echo $string; ?>
            </div>
            
                    <div class="pagebreak"> </div>

        <?php
            $file_name = "icard/templates/template_3.html";

            $fin = fopen($file_name, 'r') or die("Selected Icard Template is not in proper format .");
            $string = fread($fin, filesize($file_name));
            fclose($fin);
        ?>

            <div class="col-md-12 form-group">
                <p>Template 3</p>
                <br>
                <br>
                        <?php echo $string; ?>
            </div>
            
                    <div class="pagebreak"> </div>
        
            </div>
            </div>
            </div>
        </div>
            <div class="pagebreak"> </div>
        <div class="col-md-12 form-group">
            <center>
                <button class="btn btn-success" onclick="printdiv('printPage');">Print</button>
            </center>
        </div>
        
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
</script>
<script type="text/javascript">
    window.onafterprint = function(){
      window.location.reload(true);
    }
</script>
<!-- <script>
$(document).ready(function () {
    $('#example').DataTable();
});

</script> -->
@include('includes.footer')
