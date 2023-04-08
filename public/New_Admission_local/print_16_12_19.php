<?php
session_start();
error_reporting(1);

// if (!isset($_SESSION['check'])) {
// header("Location: index.php");
// }

$host = "150.129.172.214";
$user_name = "triz_surat";
$passwd = "Triz@2017$123";
$dbname = "mmiserp_cms";
// Create connection
$conn = mysql_connect($host, $user_name, $passwd);
// Check connection
if (!$conn) {
    die("Connection failed: " . mysql_error());
} else {
    mysql_select_db($dbname);
}

/*
$where = "";
if (isset($_SESSION['token_no'])) {
    $where = " AND token = '" . $_SESSION['token_no'] . "' ";
}

$sql_sql = "select *
from new_admission_inquiry_registration
where mobile=  '$_SESSION[mobile]' $where ";
*/

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

$ret_data = mysql_query($sql_sql);
//echo $sql_sql;
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

while ($row = mysql_fetch_array($ret_data, MYSQL_ASSOC)) {
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
    <CENTER>
        <table width="80%">
            <tr>
                <td>
                    <img src='MMIS_Logo.png' style="width: 100px;" /><br>
                </td>
                <td>
            <center><span style="font-size: 25px;font-weight: bold;">Muljibhai Mehta International School</br></span>
                <span>Gokul Township, Agashi Road, Bolinj, Virar (W)</br></span>
                <span>(ONLINE ADMISSION FOR A.Y. 2020-2021)</span></center>
            </td>
            </tr>
        </table>
    </CENTER>
    <?php
    echo "<center>";
    $table = "<table border = '1' width='80%' style='border-collapse: collapse;'>";
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
    $table .= "         Mobile No. for OTP";
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
    $table .= " <tr>";
    $table .= "     <td>";
    $table .= "         Sibling’s Details";
    $table .= "     </td>";
    $table .= "     <td>";
    $table .= "         $sibling_details";
    $table .= "     </td>";
    $table .= " </tr>";
    $table .= "</table>";
    echo $table;

    echo "<table style='border: 1px solid;margin-top: 15px;font-size: 22px;font-weight: bold;' width='80%'>
			<tr style='text-align: center;'>
				<td>
					Token No. " . $rnum;
    echo "			</td>
			</tr>
		</table>";

    echo "</center>";
    ?>
    <center>
        <table width="80%">
            <tr>
                <td>
                    <br>
                    <span style="font-size: 16px;font-weight: bold;border-bottom: 1px solid;">General Terms and Conditions : </span><br>
1.  Only single parent (Father / Mother) with original ID proof will be allowed for the process of Lottery System.<br>
2.  Parents will have to carry the print out of the Online Student Enquiry Form without which the parents will not be allowed to participate in the process of Lottery System.<br>
3.  Parents will have to carry the Original / Attested copy of Student’s Birth Certificate for verification.<br>
4.  Any mistake in the Name / Date of Birth of the Child in the Birth Certificate may lead to cancellation of Enquiry Form and will not be allowed to participate in the process of Lottery system.<br>
5.  The process of Lottery system will be conducted in the school premises as per the below mentioned schedule and parents are requested to strictly adhere to the same.<br>
&nbsp;&nbsp;&nbsp;NOTE: No entry will be accepted after 4:00 p.m.<br>

                    <table border = '1' style='border-collapse: collapse;'>  
                        <tr>
                            <th>Grade</th>
                            <th>Date of Lottery System</th>
                            <th>Verification of Documents</th>
                            <th>Process of Lottery System</th>
                        </tr>
                        <tr>
                            <td>NURSERY</td>
                            <td>06/01/2020</td>
                            <td>3pm – 4pm</td>
                            <td>4:15 pm onwards</td>
                        </tr>
                        <tr>
                            <td>PLAYGROUP</td>
                            <td>07/01/2020</td>
                            <td>3pm – 4pm</td>
                            <td>4:15 pm onwards</td>
                        </tr>					
                    </table>
                    6.	Right to admission is reserved with the school.<br><br>
                    <!--Documents Verified By: _____________________			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;	Parent’s Sign: ____________<br>-->
                    <table width="100%">
                        <tr>
                            <td align="left">
                    Documents Verified By: _____________________            
                            </td>
                     
                            <td align="right">
                    Parent’s Sign: ____________
                            </td>
                        </tr>
                    </table>
                    <br>

                </td>
            </tr>
            <tr>
                <td style="text-align: center;font-size: 20px;font-weight: bold;">GOOD LUCK !!!</td>
            </tr>
            <tr>
                <td style="text-align: center;"><img src="ct.png" style="width: 100%;height: 40px;" src="ct.png"/></td>
            </tr>
        </table>

        <CENTER>
            <table width="80%">
                <tr>
                    <td>
                        <img src='MMIS_Logo.png' style="width: 100px;" /><br>
                    </td>
                    <td>
                <center><span style="font-size: 25px;font-weight: bold;">Muljibhai Mehta International School</br></span>
                    <span>(ONLINE ADMISSION FOR A.Y. 2020-2021)</br></span>
                    </td>
                    </tr>
            </table>
            <table style='border: 1px solid;margin-top: 15px;font-size: 20px;font-weight: bold;' width='80%'>
                <tr style='text-align: center;'>
                    <td>
                        Token No. <?php echo $rnum; ?>
                    </td>
                </tr>
            </table><br><br>
            <table style="width: 80%;">
                <tr>
                    <td>
                        <b>Full Name of the Student:</b>&nbsp;&nbsp; <?php echo $child_name; ?>	</td>
                    <td style="float: right;"><b>Father’s Name:</b>&nbsp; <?php echo $father_name; ?>
                    </td>
                </tr>
            </table>
        </CENTER>


<!--<input type="button" name="print" onclick="printthis();" value="Print">-->
        <input type="button" name="print" onclick="this.style.display = 'none';window.print();" value="Print">
    </center>
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
