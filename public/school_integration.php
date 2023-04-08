<?php
set_time_limit(0);
$host = '150.129.172.214';
$username = 'triz_erp';
$password = 'Triz@2019$04';
$database = 'triz_erp_2';
$syear = "2023";

$cn = mysqli_connect($host, $username, $password) or die("adsad");

mysqli_select_db($cn, $database) or die("database");

$client_id = 81;

$getClients = mysqli_query($cn, "SELECT * FROM tblclient  where id = '" . $client_id . "'");
while ($fetClients = mysqli_fetch_assoc($getClients)) {

	$host = $fetClients['db_host'];
	$username = $fetClients['db_user'];
	$password = $fetClients['db_password'];
	$database = $fetClients['db_cms'];

	$cnOld = mysqli_connect($host, $username, $password) or die("adsad");

	mysqli_select_db($cnOld, $database) or die("database");

	$getSchoolsOld = mysqli_query($cnOld, "SELECT * FROM " . $fetClients['db_solution'] . ".tblcollege");

	while ($fetSchoolsOld = mysqli_fetch_assoc($getSchoolsOld)) {
		$checkSchools = mysqli_query($cn, "SELECT * FROM school_setup where SchoolName = '" . $fetSchoolsOld['NAME'] . "' and client_id = '" . $client_id . "'");

		if (mysqli_num_rows($checkSchools) == 0) {
			$insertSchool = "INSERT INTO school_setup (SchoolName,ShortCode,ContactPerson,Mobile,Email,ReceiptHeader,ReceiptAddress,FeeEmail,ReceiptContact,SortOrder,Logo,created_at,updated_at,client_id) VALUES ('" . $fetSchoolsOld['NAME'] . "','" . $fetSchoolsOld['TITLE'] . "','" . $fetSchoolsOld['CONTACT_PERSON'] . "','" . $fetSchoolsOld['MOBILE'] . "','" . $fetSchoolsOld['EMAIL'] . "','" . $fetSchoolsOld['FEES_HEADER'] . "','" . $fetSchoolsOld['FEES_ADDRESS'] . "','" . $fetSchoolsOld['FEES_EMAIL_ADDRESS'] . "','" . $fetSchoolsOld['FEES_CONTACT_NO'] . "','" . $fetSchoolsOld['SORT_ORDER'] . "','" . $fetSchoolsOld['FEES_LOGO'] . "','now()','now()','" . $client_id . "')";

			mysqli_query($cn, $insertSchool) or die(mysqli_error($cn));
		}
	}

}
?>