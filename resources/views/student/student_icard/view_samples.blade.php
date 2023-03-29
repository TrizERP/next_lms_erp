@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student I-Card Samples</h4> 
            </div>
        </div>
        <div id="printPage" class="card">
        <?php
            $file_name = "icard/templates/template_1.html";
            $fin = fopen($file_name, 'r') or die("Selected Icard Template is not in proper format .");
            $string = fread($fin, filesize($file_name));
            fclose($fin);
        ?>
            <div class="row">
                <div class="col-md-12 form-group">
                    <p>Template 1</p>
                    <?php echo $string; ?>
                </div>
                <div class="pagebreak"></div>
        <?php
            $file_name = "icard/templates/template_2.html";
            $fin = fopen($file_name, 'r') or die("Selected Icard Template is not in proper format .");
            $string = fread($fin, filesize($file_name));
            fclose($fin);
        ?>
                <div class="col-md-12 form-group">
                    <p>Template 2</p>                
                    <?php echo $string; ?>
                </div>
                <div class="pagebreak"></div>
        <?php
            $file_name = "icard/templates/template_3.html";
            $fin = fopen($file_name, 'r') or die("Selected Icard Template is not in proper format .");
            $string = fread($fin, filesize($file_name));
            fclose($fin);
        ?>

                <div class="col-md-12 form-group">
                    <p>Template 3</p>
                    <?php echo $string; ?>
                </div>
                <div class="pagebreak"> </div>
        <?php
            $file_name = "icard/templates/template_4.html";
            $fin = fopen($file_name, 'r') or die("Selected Icard Template is not in proper format .");
            $string = fread($fin, filesize($file_name));
            fclose($fin);
        ?>

                <div class="col-md-12 form-group">
                    <p>Template 4</p>
                    <?php echo $string; ?>
                </div>
                <div class="pagebreak"> </div>                    
            </div>    
        </div>
        <div class="pagebreak"></div>
        <div class="row">            
            <div class="col-md-12 form-group">
                <center>
                    <button class="btn btn-success" onclick="printdiv('printPage');">Print</button>
                </center>
            </div>
        </div>        
    </div>
</div>

@include('includes.footerJs')
<script type="text/javascript">
    window.onafterprint = function(){
      window.location.reload(true);
    }
</script>
@include('includes.footer')
