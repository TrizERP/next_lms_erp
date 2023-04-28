@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
use DB;
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
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Circular</h4> </div>
        </div>
        <div id="printPage" class="card">
        @php
        $grade_id = $standard_id = $division_id = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }

        @endphp
        <?php
            $file_name = "fees_circular/templates/hills_challan.html";
            $fin = fopen($file_name, 'r') or die("Selected Certificate Template is not in proper format .");
            $string = fread($fin, filesize($file_name));
            fclose($fin);
        ?>
        @if(isset($data['data']))
        @php
            $all_inserted_id = '';
            if(isset($data['data'])){
                $student_data = $data['data'];
            }
            
        @endphp
        @foreach($student_data as $key => $value)

        <?php
$table = '';
$total = 0;

$sub_institute_id = session()->get('sub_institute_id');



if (isset($data['breakoff'][$value['id']]))
{
    foreach ($data['breakoff'][$value['id']] as $dhead => $damount) {
        $table .= '<tr>
              <td align="left">&nbsp;' . $dhead . '</td>
              <td>&nbsp;' . $damount . '</td>
           </tr>';
        $total += $damount;
    }

   
}
 if(isset($data['fees_circular_amount'][$value['id']]) && $data['fees_circular_amount'][$value['id']] != ' ' ) 
    {
        $total = $data['fees_circular_amount'][$value['id']];
    }

    $table .= '<tr>
              <td align="left">&nbsp; Total</td>
              <td>&nbsp;' . $total . '</td>
           </tr>';

{{ $amountInWords = App\Helpers\getStringOfAmount($total); }}
$str = str_replace(htmlspecialchars("<<student_name>>"), $value['student_name'], $string);
$str = str_replace(htmlspecialchars("<<surname>>"), $value['surname'], $str);
$str = str_replace(htmlspecialchars("<<enrollment_no>>"), $value['enrollment_no'], $str);
$str = str_replace(htmlspecialchars("<<standard_name>>"), $value['standard_name'], $str);
$str = str_replace(htmlspecialchars("<<division_name>>"), $value['division_name'], $str);
$str = str_replace(htmlspecialchars("<<father_name>>"), $value['father_name'], $str);
$str = str_replace(htmlspecialchars("<<fees_circular_form_no>>"), $value['fees_circular_form_no'], $str);
$str = str_replace(htmlspecialchars("<<fees_head>>"), $table, $str);
$str = str_replace(htmlspecialchars("<<fees_total>>"), $total, $str);
$str = str_replace(htmlspecialchars("<<fees_remarks>>"),$data['fees_circular_remarks'][$value['id']], $str);
$str = str_replace(htmlspecialchars("<<fees_amount_in_words>>"), $amountInWords, $str);
$str = str_replace(htmlspecialchars("<<fees_months>>"), $data['display_month_name'], $str);
if (isset($data['feesCircularMaster']))
{
    $str = str_replace(htmlspecialchars("<<bank_name>>"), $data['feesCircularMaster']['bank_name'], $str);
    $str = str_replace(htmlspecialchars("<<address_line_1>>"), $data['feesCircularMaster']['address_line1'], $str);
    $str = str_replace(htmlspecialchars("<<address_line_2>>"), $data['feesCircularMaster']['address_line2'], $str);
    $str = str_replace(htmlspecialchars("<<account_number>>"), $data['feesCircularMaster']['account_no'], $str);
    $str = str_replace(htmlspecialchars("<<paid_collection>>"), $data['feesCircularMaster']['paid_collection'], $str);
    $str = str_replace(htmlspecialchars("<<shift>>"), $data['feesCircularMaster']['shift'], $str);
    // $str = str_replace(htmlspecialchars("<<form_no>>"), $data['feesCircularMaster']['form_no'], $str);
    $str = str_replace(htmlspecialchars("<<branch>>"), $data['feesCircularMaster']['branch'], $str);
    $str = str_replace(htmlspecialchars("<<current_date>>"), date('d-m-Y'), $str);
}
?>
            
                <div class="card">
                    <?php 
                        echo $str; 

                        $inserted_ids_arr = explode(',',$data['last_inserted_ids']); 

                        foreach ($inserted_ids_arr as $k => $v) 
                        {   
                            $new_str = str_replace("'", "", $str);
                            $update_sql = "UPDATE fees_circular_log SET FEES_CIRCULAR_HTML = '".$new_str."' WHERE STUDENT_ID = '".$value['id']."'   AND id = '".$v."' ";
                            $sql_data = DB::select($update_sql);
                        }
                    ?>

                </div>
            
        @endforeach
        @php
            $inserted_ids = rtrim($all_inserted_id,',');
        @endphp
        </div>
        <div class="pagebreak"> </div>
        <div class="col-md-12 form-group">
            <center>
                <div id="overlay" style="display:none;"><center><p style="margin-top: 273px;color:red;font-weight: 700;">Please do not refresh the page, while the process is going on.</p><img src="http://dev.triz.co.in/admin_dep/images/loader.gif"></center></div>
                <button class="btn btn-success" onclick="PrintDiv('printPage');">Print</button>
                <input type="hidden" name="action" id="action" value="fees_circular">
                <input type="hidden" name="last_inserted_ids" id="last_inserted_ids" value="{{$data['last_inserted_ids']}}">
                <input type="button" value="Send Email" class="btn btn-success" id="ajax_sendBulkEmail" />
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
<script>
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
