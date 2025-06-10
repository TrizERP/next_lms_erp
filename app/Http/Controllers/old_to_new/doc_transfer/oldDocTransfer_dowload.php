<?php
set_time_limit(0);
include("../../excel_upload/db.php");

$cn = mysqli_connect($host, $username, $password) or die("adsad");

mysqli_select_db($cn, $database) or die("database");
// mmis client id 2
$client_id = $_REQUEST['client_id'];
$sub_institute_id = $_REQUEST['sub_institute_id'];
$syear = $_REQUEST['syear'];
$message = 'client is - '.$client_id;
if($client_id != "")
{
	$getClients = mysqli_query($cn, "SELECT *,s.Id as sub_institute_id  
					FROM tblclient c 
					INNER JOIN school_setup s on c.id = s.client_id
					WHERE c.id = '" . $client_id . "'"
					);

	$final_result = array();

	while ($fetClients = mysqli_fetch_assoc($getClients)) 
	{

		$host = $fetClients['db_host'];
		$username = $fetClients['db_user'];
		$password = $fetClients['db_password'];
		$database = $fetClients['db_hrms'];
		$sub_institute_id = $fetClients['sub_institute_id'];
		$newJsonData= $names = $failedData = [];
		
		if($host != "" && $username != "" && $password != "" && $database != "" && $sub_institute_id != "")
		{

			$cnOld = mysqli_connect($host, $username, $password) or die("adsad");

			mysqli_select_db($cnOld, $database) or die("database");

			$getOldStudent = mysqli_query($cnOld, 'SELECT a.*,b.employee_id,c.id as docId,c.`type` AS document_title,b.emp_firstname as first_name,b.emp_lastname as last_name
             FROM hs_hr_emp_attachment a
            INNER JOIN hs_hr_employee b ON b.emp_number = a.emp_number
            INNER JOIN hs_hr_emp_attachment_type c ON c.id = a.type_id
             WHERE a.eattach_size=0 limit 2');// limit 5 attachment size 0 means salary slips
			while ($fetOldStudent = mysqli_fetch_assoc($getOldStudent)) 
			{
				// echo "<pre>";print_r($fetOldStudent['emp_code']);
               
                $emp_number = $fetOldStudent["emp_number"];
				$first_name = $fetOldStudent["first_name"];
				$last_name = $fetOldStudent["last_name"];
                $eattach_id = $fetOldStudent["eattach_id"];
                $eattach_desc = $fetOldStudent["eattach_desc"];
                $eattach_filename = $fetOldStudent["eattach_filename"];
                $eattach_size = $fetOldStudent["eattach_size"];
				$eattach_attachment ='';
				if($eattach_size==0){
					$pdfUrl = 'http://apps.triz.co.in/mmiserp/Products/hrms/member_payroll_pdf/' . $fetOldStudent["eattach_filename"];
					$eattach_attachment = $pdfUrl;
				}else{
					$eattach_attachment = base64_encode($fetOldStudent["eattach_attachment"]);
				}
                $eattach_type = $fetOldStudent["eattach_type"];
                $type_id = $fetOldStudent["type_id"];
                $employee_id = $fetOldStudent["employee_id"];
                $docId = $fetOldStudent["docId"];
                $document_title = $fetOldStudent["document_title"];
                    //  echo "<pre>";print_r($eattach_attachment);exit;
				// get emp id from new db
				$getEmp = mysqli_query($cn,"SELECT id,first_name,last_name,employee_no FROM tbluser WHERE employee_no like '%".$employee_id."%' and first_name = '".$first_name."' and status = 1 ");
				$empId = mysqli_fetch_assoc($getEmp);

                $getDocumentType = mysqli_query($cn,"SELECT id FROM student_document_type WHERE document_type like '".$document_title."' and user_type = 'staff' ");
				$newType = mysqli_fetch_assoc($getDocumentType);
				// echo "<pre>";print_r($newType);exit;

				if( ($empId && $empId['id']!=0) && ($newType && $newType['id']!=0)){
					$names[$empId['employee_no']] = $empId['first_name'].' '.$empId['last_name'];
					$currentDate = date("Y-m-d H:i:s");
					$newJsonData[] = [
                        "oldEmpId"=>$employee_id,
                        "empId"=>$empId['id'],
                        "contentType"=>$eattach_type,
                        "fileName"=>$eattach_filename,
						'size'=>$eattach_size,
                        "oldDocId"=>$docId,
                        "docId"=> $newType['id'],
                        "docTitle"=>$document_title,
                        "sub_institute_id"=>47,
                        "created_at"=>$currentDate,
                        "mediumBlob" => $eattach_attachment,
                    ];
				}else{
					$failedData[$emp_number]=$first_name.' '.$last_name;
				}
			
			}
			// echo "<pre>";print_r($names);
			// convert into json data and store
			// Function to sanitize data
				function sanitizeData($data) {
					if (is_array($data)) {
						return array_map('sanitizeData', $data);
					} elseif (is_string($data)) {
						return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
					} else {
						return $data;
					}
				}

				if (!empty($newJsonData)) {
					// Sanitize data
					$sanitizedData = sanitizeData($newJsonData);

					// Convert the data to JSON format
					$jsonData = json_encode($sanitizedData, JSON_PRETTY_PRINT);

					// Debug output to verify JSON encoding
					if ($jsonData === false) {
						echo "JSON encoding error: " . json_last_error_msg();
						exit;
					}

					// Define the file path where the JSON file will be stored
					$filePath = $_SERVER['DOCUMENT_ROOT'].'/converted_json.json';

					// Debug output to verify file path
					echo "File path: " . $filePath . "<br>";

					// Ensure the directory exists
					$directoryPath = dirname($filePath);
					if (!is_dir($directoryPath)) {
						if (mkdir($directoryPath, 0755, true)) {
							echo "Directory created successfully.<br>";
						} else {
							echo "Failed to create directory.<br>";
							exit;
						}
					}

					// Save the JSON data to the file
					if (file_put_contents($filePath, $jsonData) === false) {
						echo "Failed to write JSON data to file.<br>";
						exit;
					}

					// Output the file path for confirmation
					echo "JSON data has been saved to: " . $filePath;
				} else {
					echo "Failed to get JSON data.<br>";
				}
		}
		else
		{
			echo "<font color='red'>School Setup is missing</font>";
		}
	}
}
else
{
	$message = "No Client Id Found";
}

echo '<br>'.count($newJsonData).'-counted<br>';
echo "Failed users <pre>";print_r($failedData);echo "</pre>";
echo "Found users ".count($names)." <pre>";print_r($names);echo "</pre>";
echo "<pre>";print_r($final_result);echo "</pre>";
?>