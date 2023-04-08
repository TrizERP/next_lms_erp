
<noscript><meta http-equiv="refresh" content="1;url=error.html"></noscript>
<?php
session_start();
error_reporting(1);
include('db.php');

$sub_institute_id = '';
if(isset($_REQUEST['sub_institute_id']) && $_REQUEST['sub_institute_id'] != '')
{
    $sub_institute_id = $_REQUEST['sub_institute_id'];
}else{
    echo "<center><h2><font color=red>Please contact to your administrator for Online Admission.</font></h2></center>";
    exit();
}

$school_sql = "SELECT gd.*,s.Logo 
               FROM school_setup s 
               INNER JOIN general_data gd ON gd.sub_institute_id = s.Id
               WHERE s.Id = '".$sub_institute_id."' AND gd.fieldname like '%admission%'";

$SCHOOL_RET = mysqli_query($cn, $school_sql);
while ($row = mysqli_fetch_all($SCHOOL_RET, MYSQLI_ASSOC))
{
   $school_logo = "/admin_dep/images/".$row[0]['Logo'];
   $school_name = $row[1]['fieldvalue'];
   $school_address = $row[2]['fieldvalue'];
   $admission_year = $row[3]['fieldvalue'];
}


if($_SESSION['token_no'] =='' && $_REQUEST['id'] !='')
{
	$sql_sql = "select *
	from new_admission_inquiry_registration
	where token = '".$_REQUEST['id']."' ";
}
else
{
	$where = "";
	if (isset($_SESSION['token_no'])) 
	{
		$where = " AND token = '" . $_SESSION['token_no'] . "' ";
	}

	$sql_sql = "select *
	from new_admission_inquiry_registration
	where mobile=  '$_SESSION[mobile]' $where ";
}

$ret_data = mysqli_query($cn,$sql_sql);
// echo $sql_sql;
//session_destroy();
$std = "";
$dob = "";
$mobile = "";
$address = "";
$child_name = "";
$father_name = "";
$mail = "";
$father_adhar = "";
$mother_adhar = "";
$sibling_details = "";
$rnum = "";

while ($row = mysqli_fetch_array($ret_data, MYSQLI_ASSOC)) {
    $std = $row["admission_std"];
    $dob = date('d-m-Y', strtotime($row["date_of_birth"]));
    $mobile = $row["mobile"];
    $address = $row["address"];
    $child_name = $row["child_name"];
    $father_name = $row["father_name"];
    $mail = $row["mail"];
    $father_adhar = $row["father_adhar"];
    $mother_adhar = $row["mother_adhar"];
    $sibling_details = $row["sibling_details"];
    $rnum = $row["token"];
}
?>
<div id="printthis">
    <?php


    $table = "<CENTER>
        <table width='80%'>
            <tr>
                <td>
                    <img src=".$school_logo." style='width: 100px;' /><br>
                </td>
                <td>
            <center><span style='font-size: 25px;font-weight: bold;'>".$school_name."</br></span>
                <span>".$school_address."</br></span>
                <span>(".$admission_year.")</span></center>
            </td>
            </tr>
        </table>
    </CENTER>";
    
    $table .= "<center>";
    $table .= "<table border = '1' width='80%' style='border-collapse: collapse;'>";
    $table .= " <tr>";
    $table .= "     <td style='width: 35%;'	>";
    $table .= "         Full Name of the Student";
    $table .= "     </td>";
    $table .= "     <td>";
    $table .= "         $child_name";
    $table .= "     </td>";
    $table .= " </tr>";
    $table .= " <tr>";
    $table .= "     <td>";
    $table .= "         Date Of Birth";
    $table .= "     </td>";
    $table .= "     <td>";
    $table .= "         $dob";
    $table .= "     </td>";
    $table .= " </tr>";
    $table .= " <tr>";
    $table .= "     <td>";
    $table .= "         Grade";
    $table .= "     </td>";
    $table .= "     <td>";
    $table .= "         $std";
    $table .= "     </td>";
    $table .= " </tr>";
    $table .= " <tr>";
    $table .= "     <td>";
    $table .= "         Father’s Name";
    $table .= "     </td>";
    $table .= "     <td>";
    $table .= "         $father_name";
    $table .= "     </td>";
    $table .= " </tr>";
    $table .= " <tr>";
    $table .= "     <td>";
    $table .= "         Father’s Aadhar No.";
    $table .= "     </td>";
    $table .= "     <td>";
    $table .= "         $father_adhar";
    $table .= "     </td>";
    $table .= " </tr>";
    $table .= " <tr>";
    $table .= "     <td>";
    $table .= "         Mother’s Aadhar No.";
    $table .= "     </td>";
    $table .= "     <td>";
    $table .= "         $mother_adhar";
    $table .= "     </td>";
    $table .= " </tr>";
    $table .= " <tr>";
    $table .= "     <td>";
    $table .= "         Mobile No.";
    $table .= "     </td>";
    $table .= "     <td>";
    $table .= "         $mobile";
    $table .= "     </td>";
    $table .= " </tr>";
    $table .= " <tr>";
    $table .= "     <td>";
    $table .= "         E-mail Id";
    $table .= "     </td>";
    $table .= "     <td>";
    $table .= "         $mail";
    $table .= "     </td>";
    $table .= " </tr>";
    $table .= " <tr>";
    $table .= "     <td>";
    $table .= "         Residential Address";
    $table .= "     </td>";
    $table .= "     <td>";
    $table .= "         $address";
    $table .= "     </td>";
    $table .= " </tr>";
/*    $table .= " <tr>";
    $table .= "     <td>";
    $table .= "         Sibling’s Details";
    $table .= "     </td>";
    $table .= "     <td>";
    $table .= "         $sibling_details";
    $table .= "     </td>";
    $table .= " </tr>";
*/    
    $table .= "</table>";
    //echo $table;

    $table .= "<table style='border: 1px solid;margin-top: 15px;font-size: 22px;font-weight: bold;' width='80%'>
			<tr style='text-align: center;'>
				<td>
					Token No. " . $rnum;
    $table .= "			</td>
			</tr>
		</table>";

    $table .= "</center>";
    
    $table .= "<center>
        <table width='80%'>
            <tr>
                <td>
                    <br>
                    <span style='font-size: 16px;font-weight: bold;border-bottom: 1px solid;'>General Terms and Conditions : </span><br><br>

1.  Generation of Token Number only assures your participation in the Online Lucky Draw Process. <br><br>
2.  However, any mistake in the Name / Date of Birth of the Child in the Birth Certificate may lead to cancellation of Enquiry Form and will not be considered in the process of Lottery system. Their Token Number will be cancelled without any intimation.<br><br>
3.  The process of Lottery System will be conducted Online as per the below mentioned schedule and parents are requested to strictly adhere to the same.<br<br><br>
                    <table border = '1' style='border-collapse: collapse;' width=100%>
                            
                        <tr style='text-align: center;'>
                            <th>Grade</th>
                            <th>Day and Date</th>
                            <th>Time</th>
                            <th>Seats</th>
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Nursery</td>
                            <td>Thursday 28th January, 2021</td>
                            <td>11.00 a.m. to 12.00 noon</td>
                            <td>90</td>
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Playgroup</td>
                            <td>Thursday 28th January, 2021</td>
                            <td>12.30 p.m. to 01.30 p.m</td>
                            <td>40</td>
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Siblings (Nursery)</td>
                            <td>Thursday 28th January, 2021</td>
                            <td>02.00 p.m. to 02.15 p.m.</td>
                            <td>10</td>
                        </tr>	
                        <tr style='text-align: center;'>
                            <td>Siblings (Playgroup)</td>
                            <td>Thursday 28th January, 2021</td>
                            <td>02.30 p.m. to 02.45 p.m.</td>
                            <td>10</td>
                        </tr>	

                    </table><br>

                    4.	Lucky Draw process will be conducted online on YouTube Live. The link will be provided for registered aspirants through their registered Email – ID / Mobile number.<br><br>
                    5.  Selected aspirants will be provided with the further details of “Online Admission Procedure” and related link to fill Admission form through Email.<br><br>
                    6.  Right to admission is reserved with the school. <br><br>
        
                    </table>
                    <br>

                </td>
            </tr> 
            <tr>
                <td style='text-align: center;font-size: 20px;font-weight: bold;'>GOOD LUCK !!!</td>
            </tr>

        </table>

        <CENTER>

            
        </CENTER>";


//<input type="button" name="print" onclick="printthis();" value="Print">
        
    
    $src = making_pdf_receipt($table,$rnum);
   
    //$table .= "<input type='button' name='print' onclick='window.print();' value='Print' style='background:#2E55A9;border: none !important;color:#FFFFFF;font-size: 13px;font-weight: bold;padding: 7px 15px;margin: 10px;'>
    $table .= "</center>";
     echo $table;

    $sqlTwins = "select *
        from new_admission_inquiry_registration
        where mobile=  '".$_SESSION[mobile]."' AND token = '".$_SESSION['token_no']."' AND admission_for_child_twins = 'Twins' ";
    $Twinsdata = mysqli_num_rows(mysqli_query($cn,$sqlTwins));

     echo "<center><button style='background:#2E55A9;border: none !important;color:#FFFFFF;font-size: 13px;font-weight: bold;padding: 7px 15px;margin: 10px;'><a style='color: #ffffff !important;' href='".$src."' download>Download PDF</a></button></center>";

    if($Twinsdata > 0){
        echo "<center><button style='background:#2E55A9;border: none !important;color:#FFFFFF;font-size: 13px;font-weight: bold;padding: 7px 15px;margin: 10px;'><a style='color: #ffffff !important;' href='index.php'>Fill Form for Twins</a></button></center>";
    } 
     
   

   ?> 
</div>

<script>
    function printthis() {
        var printContents = document.getElementById('printthis').innerHTML;
        var originalContents = document.body.innerHTML;

        document.body.innerHTML = printContents;

        window.print();

        document.body.innerHTML = originalContents;
    }
</script>
<?php 
function making_pdf_receipt($final_html,$rnum)
{
    $dom = '<!DOCTYPE html>
    <html>
        <head>
            <title></title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body>
        <div>
            ##HTML_SEC##
        </div>
    </body>
</html>';

    //echo $final_html;
    //echo '<pre>';
   // print_r($_SERVER);
    $cms_to_pdf_folder = $_SERVER['DOCUMENT_ROOT'] . '/New_Admission/new_admission_receipt';
    $save_path = $cms_to_pdf_folder ;//. $pdf_folder
    
        $html_filename = $rnum.".html";
        $pdf_filename = $rnum.".pdf";

        $html = '';
        $html .= $final_html;
        
        $path = "src=http://" . $_SERVER['HTTP_HOST'] . "/New_Admission/new_admission_receipt";
        $html = str_replace('src="', $path, $html);
        $html = str_replace('" alt=', ' alt=', $html);
        
        $html = str_replace('##HTML_SEC##', $html, $dom);
        
        $html_file_path = $save_path . '/' . $html_filename;
        $pdf_file_path = $save_path . '/' . $pdf_filename;
        file_put_contents($html_file_path, $html);
        htmlToPDF($html_file_path, $pdf_file_path);
        // unlink($html_file_path);
    
    $src= 'http://' . $_SERVER['HTTP_HOST'] . '/New_Admission/new_admission_receipt/'.$pdf_filename;
    //$responce = stripslashes(json_encode(array('Count' => count($data),'src' => $src)));//'Path' => $save_path
    $responce = stripslashes($src);
    return $responce;
    //exit(0);

}  
function htmlToPDF($htmlPath, $pdfPath) 
{
    $command = '/usr/local/bin/wkhtmltopdf '; // --page-height 297mm //-L 0 -R 0 -B 0 -T 0 -s A4 
    $command .= " $htmlPath ";
    $command .= " $pdfPath ";
   //die;
   return exec($command);
}
?>