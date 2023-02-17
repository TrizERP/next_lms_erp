@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student I-Card</h4>
            </div>
        </div>
        <div id="printPage">
            @php
            $grade_id = $standard_id = $division_id = '';

            if(isset($data['grade_id'])){
            $grade_id = $data['grade_id'];
            $standard_id = $data['standard_id'];
            $division_id = $data['division_id'];
            }

            @endphp
            <?php
$file_name = "icard/templates/" . $data['template'] . ".html";

$fin = fopen($file_name, 'r') or die("Selected Icard Template is not in proper format .");
$string = fread($fin, filesize($file_name));
fclose($fin);

?>
            @if(isset($data['data']))
            @php
            if(isset($data['data'])){
            $student_data = $data['data'];
            }
            $bootstrapColumn = 12 /$data['column'];
            $pageBreakCount = $data['column'] * $data['row'];
            $j=1;
            @endphp
            <div class="row">
                <div class="white-box">
                    <div class="panel-body">
                        @foreach($student_data as $key => $value)


                        <?php
        // echo ('<pre>');dd($value);exit;
$str = str_replace(htmlspecialchars("<<first_name>>"), $value->first_name, $string);
$str = str_replace(htmlspecialchars("<<last_name>>"), $value->last_name, $str);
$str = str_replace(htmlspecialchars("<<email>>"), $value->email, $str);
$str = str_replace(htmlspecialchars("<<mobile>>"), $value->mobile, $str);
$str = str_replace(htmlspecialchars("<<gender>>"), $value->gender, $str);
$str = str_replace(htmlspecialchars("<<address>>"), $value->address, $str);

?>
                        <div class="col-md-{{$bootstrapColumn}} form-group">
                            <?php echo $str; ?>
                        </div>
                        <?php
if ($j == $pageBreakCount) {
	?>
                        <div class="pagebreak"> </div>
                        <?php
$j = 0;
}
$j++;

?>
                        @endforeach
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