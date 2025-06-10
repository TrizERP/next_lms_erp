<?php
set_time_limit(0);
include("../../excel_upload/db.php");

$cn = mysqli_connect($host, $username, $password) or die("adsad");

mysqli_select_db($cn, $database) or die("database");
// mmis client id 28
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
		$database = $fetClients['db_cms'];
		$sub_institute_id = $fetClients['sub_institute_id'];
		$newJsonData= $names = $failedData = [];
		
		if($host != "" && $username != "" && $password != "" && $database != "" && $sub_institute_id != "")
		{

			$cnOld = mysqli_connect($host, $username, $password) or die("adsad");

			mysqli_select_db($cnOld, $database) or die("database");

			$getOldStudent = mysqli_query($cnOld, 'SELECT a.student_id,a.first_name,a.middle_name,a.last_name,a.mobile,a.enrollment_no,c.doc_type_name,c.doc_type_status,c.id,b.*
			FROM STUDENT_DOCUMENTS b
			INNER JOIN STUDENT_DOCUMENT_TYPE c ON c.id = b.DOC_TYPE_ID
			INNER JOIN mmiserp_solution.tblstudent a ON a.student_id=b.STUDENT_ID');// limit 5 attachment size 0 means salary slips
			while ($fetOldStudent = mysqli_fetch_assoc($getOldStudent)) 
			{
				// $dataArr = [
				// 	"user_name"=>$fetOldStudent['user_name'],
				// 	"staff_id"=>$fetOldStudent['staff_id'],
				// 	"TITLE"=>$fetOldStudent['TITLE'],
				// ];
				// echo "<pre>";print_r($dataArr);
               
            //     $emp_number = $fetOldStudent["emp_number"];
			// 	$first_name = $fetOldStudent["first_name"];
			// 	$last_name = $fetOldStudent["last_name"];
            //     $eattach_id = $fetOldStudent["eattach_id"];
            //     $eattach_desc = $fetOldStudent["eattach_desc"];
            //     $eattach_filename = $fetOldStudent["eattach_filename"];
            //     $eattach_size = $fetOldStudent["eattach_size"];
			// 	$eattach_attachment ='';
			// 	if($eattach_size==0){
					$pdfUrl = 'http://apps.triz.co.in/mmiserp/Products/cms/assets/student_document/' . $fetOldStudent["FILE_NAME"];
					$eattach_attachment = $pdfUrl;
			// 	}else{
					// $eattach_attachment = base64_encode($fetOldStudent["eattach_attachment"]);
			// 	}
            //     $eattach_type = $fetOldStudent["eattach_type"];
            //     $type_id = $fetOldStudent["type_id"];
                $student_id = $fetOldStudent["student_id"];
            //     $docId = $fetOldStudent["docId"];
				$mobile = $fetOldStudent["mobile"];
				$enrollment_no = $fetOldStudent["enrollment_no"];
                $doc_type_name = $fetOldStudent["doc_type_name"];
                $document_title = $fetOldStudent["TITLE"];
                $file_Name = $fetOldStudent["FILE_NAME"];
            //         //  echo "<pre>";print_r($eattach_attachment);exit;
			// 	// get emp id from new db
				$getEmp = mysqli_query($cn,"SELECT id,enrollment_no,first_name,last_name,mobile FROM tblstudent WHERE enrollment_no='".$enrollment_no."' and mobile = '".$mobile."' ");
				$studentId = mysqli_fetch_assoc($getEmp);

                $getDocumentType = mysqli_query($cn,'SELECT id FROM student_document_type WHERE document_type like "'.$document_title.'" and user_type = "student" ');
				$newType = mysqli_fetch_assoc($getDocumentType);
			// 	// echo "<pre>";print_r($newType);exit;

				if( ($studentId && $studentId['id']!=0) && ($newType && $newType['id']!=0)){
					$names[$studentId['id']] = $studentId['first_name'].' '.$studentId['last_name'];
					$currentDate = date("Y-m-d H:i:s");
					$newJsonData[] = [
                        "oldStudentId"=>$student_id,
                        "studentId"=>$studentId['id'],
						"size"=>0,
						"docId"=>$newType['id'],
						"docTitle"=>$document_title,
						"fileName"=>$file_Name,
                        "sub_institute_id"=>47,
                        "created_at"=>$currentDate,
                        "mediumBlob" => $eattach_attachment,
                    ];
				}else{
					$failedData[$fetOldStudent['enrollment_no']]=$fetOldStudent['first_name'].' '.$fetOldStudent['last_name'];
				}
			
			}
			// echo "<pre>";print_r($newJsonData);
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
					$filePath ='converted_json.json';

					// Debug output to verify file path
					// echo "File path: " . $filePath . "<br>";

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