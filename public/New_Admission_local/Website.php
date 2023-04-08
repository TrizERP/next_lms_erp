
<noscript><meta http-equiv="refresh" content="1;url=error.html"></noscript>
<?php
session_start();
error_reporting(1);

$host = "150.129.172.214";
$username = "triz_erp";
$password = "Triz@2019$04";
$database = "triz_erp_2";

$cn = mysqli_connect($host, $username, $password) or die("Database Not Connected");
mysqli_select_db($cn, $database) or die("database");

?>
<div id="printthis">
    <?php
    $table = "<CENTER>
        <table width='80%'>
            <tr>
                <td>
                    <img src='MMIS_Logo.png' style='width: 100px;' /><br>
                </td>
                <td>
            <center><span style='font-size: 25px;font-weight: bold;'>Muljibhai Mehta International School</br></span>
                <span>Gokul Township, Agashi Road, Bolinj, Virar (W)</br></span>
                <span>(ONLINE ADMISSION FOR A.Y. 2021-2022)</span></center>
            </td>
            </tr>
        </table>
    </CENTER>";
    
    
    
    $table .= "<center>
        <table width='80%'>
            <tr>
                <td>
                    <br>
                      <center><span style='font-size: 16px;font-weight: bold;border-bottom: 1px solid;'>Age Criteria: as on 31st December 2021</span><br>  </center> <br><br>

                   <table border = '1' style='border-collapse: collapse;' width=80%>  
                        <trstyle='text-align: center;'>
                            <th>Class</th>
                            <th>Age Limit</th>
                            <th>Age Criteria</th>
                            
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Playgroup</td>
                            <td>2 years to 3 years</td>
                            <td>Child born on or between<br>
                            01/01/2019 and 31/12/2019</td>
                    
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Nursery</td>
                            <td>3 years to 4 years</td>
                            <td>Child born on or between <br>
                            01/01/2018 and 31/12/2018</td>
            
                        </tr>
                        					
                    </table> 

        <table width='100%'>
            <tr>
                <td>
                        <span style='font-size: 16px;'>
                        <ul> 
                            <li>
                                Parents need to fill up the Online Enquiry Form 
                                <b>for the General or Sibling quota </b> properly with all required information as follows:<br>
                            </li>
                        </ul>
                        </span>
                        <br>  
                   <table border = '1' style='border-collapse: collapse;' width=50%>  
                        <tr style='text-align: center;'>
                            <td>Student’s Full Name</td>  
                            
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Date of Birth</td>
                            
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Grade </td>
   
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Father’s Name</td>  
                            
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Father’s Aadhar Card No.</td>
                            
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Mother’s Aadhar Card No.</td>
   
                        </tr>
                         <tr style='text-align: center;'>
                            <td>Mobile No. for OTP and to receive the other SMS’s from school.</td>  
                            
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Email _Id</td>
                            
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Residential Address</td>
   
                        </tr>
                         <tr style='text-align: center;'>
                            <td>Select Quota (General or Sibling)</td>  
                            
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Details of Siblings studying in MMIS (GR No.)</td>
                            
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Upload Birth Certificate (Original)</td>
   
                        </tr>
                                 
                                            
                    </table>

      <table width='80%'>
            <tr>
                <td>
                    <br>
                      <span style='font-size: 16px;font-weight: bold;' > <b>Seats Availability: </b><br></span><br>  

                   <center> <table border = '1' style='border-collapse: collapse;' width=100%>  
                        <tr style='text-align: center;'>
                            <th>Class</th> 
                            <th>General Quota</th>
                            <th>Sibling Quota</th> 
                            
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Playgroup</td>
                             <td>40</td>
                              <td>10</td>
                            
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Nursery</td>
                            <td>90</td>
                              <td>10</td>
   
                        </tr>
                        
                                            
                    </table> </center>
                    
                  <br>  <b>NOTE: IN SIBLING QUOTA – </b><br>
                  1. <b>The information provided should be authentic and appropriate.</b><br>
                  2. <b>Application form should be filled for any one quota only (General or Sibling). </b><br>
                  3. <b>Forms for both the categories will not be accepted and Enquiry Form shall be cancelled. </b> <br>
                  4. <b>After the verification of the enquiry form, any discrepancy found will lead to direct cancellation of the Enquiry Form.</b><br>
<br>
<ul style='padding-left: 15px;'>
                  	<li> Clear copy of Original Birth Certificate of the child should be uploaded. </li>
                  <li>After the submission of Online Enquiry Form, Token No. will be generated. Parents need to download and keep a copy of the same in printed format.</li>
					<li>Any discrepancy found in the Online Enquiry Form may lead to the cancellation of Enquiry Form and will not be allowed to participate in the process of Lottery System.</li>
					<li>The process of Lottery System will be conducted Online as per the below mentioned schedule and parents are requested to strictly adhere to same.</li>
                    </ul>
<br>

		<table width='80%'>
            <tr>
 
                   <center> <table border = '1' style='border-collapse: collapse;' width=100%>  
                        <tr style='text-align: center;'>
                            <th>Grade</th> 
                            <th>Day and Date</th>
                            <th>Time</th> 
                            
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Nursery</td>
                             <td>Thursday 28th January, 2021</td>
                              <td>11.00 a.m. to 12.00 noon</td>
                            
                        </tr>
                        <tr style='text-align: center;'>
                            <td>Playgroup</td>
                             <td>Thursday 28th January, 2021</td>
                              <td>12.30 p.m. to 01.30 p.m.</td>
                            
                        </tr>
                        <tr style='text-align: center;'>
                            <td>For Siblings Playgroup</td>
                             <td>Thursday 28th January, 2021</td>
                              <td>02.00 p.m. to 02.15 p.m.</td>
                            
                        </tr>
                         <tr style='text-align: center;'>
                            <td>For SiblingS Nursery </td>
                             <td>Thursday 28th January, 2021</td>
                              <td>02.30 p.m. to 02.45 p.m.</td>
                            
                        </tr>                        
                                            
                    </table> 
                    </center>
          </table>
<BR>               
<ul style='padding-left:15px;'>             
                  <li>Lucky Draw process will be conducted online by YouTube Live. The link will be provided for registered aspirants through their registered Email – ID / Mobile number.</li>
                  <li>Selected aspirants will be provided with the further details of Online Admission Procedure through Email.<br>
                  Right to admission is reserved with the school management.</li>
                  </ul>
                       </table>";
    $table .= "</center>";
    echo $table;

    echo "<center><button style='background:#2E55A9;border: none !important;color:#FFFFFF;font-size: 13px;font-weight: bold;padding: 7px 15px;margin: 10px;'><a style='color: #ffffff !important;' href='index.php' target='_blank'> Proceed </a></button></center>";
   ?> 
</div>