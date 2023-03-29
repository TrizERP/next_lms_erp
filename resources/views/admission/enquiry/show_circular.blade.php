@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
use DB;
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Circular</h4>
            </div>
        </div>    
        <div id="printPage" class="card">
            <?php
                $file_name = "fees_circular/templates/challan_admission.html";
                $fin = fopen($file_name, 'r') or die("Selected Certificate Template is not in proper format .");
                $string = fread($fin, filesize($file_name));
                fclose($fin);

                $all_inserted_id = '';
                if(isset($data['data'])){
                    $student_data = $data['data'];
                }

                $table = '';
                $table .=  '<tr>
                                <td align="left">&nbsp;School Fees</td>
                                <td>&nbsp;' . $student_data['fees_amount'] . '</td>
                            </tr>';
                $table .=  '<tr>
                                <td align="left">&nbsp; Total</td>
                                <td>&nbsp;' . $student_data['fees_amount'] . '</td>
                            </tr>';

                {{ $amountInWords = App\Helpers\getStringOfAmount($student_data['fees_amount']);}}

                $student_name = $student_data['first_name'].' '.$student_data['middle_name'];

                $fees_remark = '';
                if($student_data['fees_remark'] != '')
                {
                    $fees_remark = '<b><u>('.$student_data['fees_remark'].')</u></b><br>';
                }

                $str = str_replace(htmlspecialchars("<<student_name>>"), $student_name, $string);
                $str = str_replace(htmlspecialchars("<<surname>>"), $student_data['last_name'], $str);
                $str = str_replace(htmlspecialchars("<<standard_name>>"), $data['standard_name'], $str);
                $str = str_replace(htmlspecialchars("<<enquiry_no>>"), $student_data['fees_circular_form_no'], $str);
                $str = str_replace(htmlspecialchars("<<fees_remark>>"), $fees_remark, $str);
                $str = str_replace(htmlspecialchars("<<fees_head>>"), $table, $str);
                $str = str_replace(htmlspecialchars("<<fees_total>>"), $student_data['fees_amount'], $str);
                $str = str_replace(htmlspecialchars("<<fees_amount_in_words>>"), $amountInWords, $str);
                $str = str_replace(htmlspecialchars("<<fees_months>>"), $data['get_term_name'], $str);
               
                if (isset($data['feesCircularMaster']))
                {
                    $str = str_replace(htmlspecialchars("<<bank_name>>"), $data['feesCircularMaster']['bank_name'], $str);
                    $str = str_replace(htmlspecialchars("<<address_line_1>>"), $data['feesCircularMaster']['address_line1'], $str);
                    $str = str_replace(htmlspecialchars("<<address_line_2>>"), $data['feesCircularMaster']['address_line2'], $str);
                    $str = str_replace(htmlspecialchars("<<account_number>>"), $data['feesCircularMaster']['account_no'], $str);
                    $str = str_replace(htmlspecialchars("<<paid_collection>>"), $data['feesCircularMaster']['paid_collection'], $str);
                    $str = str_replace(htmlspecialchars("<<shift>>"), $data['feesCircularMaster']['shift'], $str);                    
                    $str = str_replace(htmlspecialchars("<<branch>>"), $data['feesCircularMaster']['branch'], $str);
                    $str = str_replace(htmlspecialchars("<<current_date>>"), date('d-m-Y'), $str);
                }
            ?>
            <div class="card">
                <?php 
                    echo $str;
                    $new_str = str_replace("'", "", $str);
                    $update_sql = "UPDATE admission_enquiry SET fees_circular_html = '".$new_str."' WHERE id = '".$data['last_inserted_id']."' ";
                    $sql_data = DB::select($update_sql);
                ?>
            </div>
        </div>
        <div class="pagebreak"></div>
        <div class="col-md-12 form-group">
            <center>
                <button class="btn btn-success" onclick="PrintDiv('printPage');">Print</button>
            </center>
        </div>
    </div>
</div>

@include('includes.footerJs')
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
