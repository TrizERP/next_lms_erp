<?php
set_time_limit(0);
$host = '128.199.17.97';
$username = 'root';
$password = 'Triz@R@jesh';
$database = 'triz_erp_2';
$syear = "2023";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$cn = mysqli_connect($host,$username,$password,$database);

// Check connection
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  exit();
}

$getClients = mysqli_query($cn, "SELECT * FROM tblclient where id = 81");
while ($fetClients = mysqli_fetch_assoc($getClients)) {

	$host = $fetClients['db_host'];
	$username = $fetClients['db_user'];
	$password = $fetClients['db_password'];
	$database = $fetClients['db_cms'];

	$cnOld = mysqli_connect($host, $username, $password) or die("adsad");

	mysqli_select_db($cnOld, $database) or die("database");

	$getSchools = mysqli_query($cn, "select * from school_setup where client_id = '" . $fetClients['id'] . "'");

	while ($fetSchools = mysqli_fetch_assoc($getSchools)) {

		$getProfiles = mysqli_query($cnOld, "SELECT * FROM " . $fetClients['db_solution'] . ".tbluser_groups");
		while ($fetProfiles = mysqli_fetch_assoc($getProfiles)) {

			$checkProfile = mysqli_query($cn, "SELECT * FROM tbluserprofilemaster where name = '" . $fetProfiles['user_group_name'] . "' and sub_institute_id = '" . $fetSchools['Id'] . "'");

			if (mysqli_num_rows($checkProfile) == 0) {

				$insertUserProfiles = "INSERT INTO tbluserprofilemaster (parent_id,name,description,sort_order,status,sub_institute_id) VALUES ('','" . $fetProfiles['user_group_name'] . "','" . $fetProfiles['user_description'] . "','" . $fetProfiles['sort_order'] . "','1','" . $fetSchools['Id'] . "')";

				$iqup = mysqli_query($cn, $insertUserProfiles);

			}

		}

		$profiles = array();

		$getProfilesNew = mysqli_query($cn, "SELECT * FROM tbluserprofilemaster where sub_institute_id = '" . $fetSchools['Id'] . "'");

		while ($fetProfilesNew = mysqli_fetch_assoc($getProfilesNew)) {
			$profiles[$fetProfilesNew['name']] = $fetProfilesNew['id'];
		}

		$cities = array();

		$getCity = mysqli_query($cnOld, "SELECT * FROM " . $fetClients['db_solution'] . ".tblcity");

		while ($fetCity = mysqli_fetch_assoc($getCity)) {
			$cities[$fetCity['city_id']] = $fetCity['city_name'];
		}

		$states = array();

		$getState = mysqli_query($cnOld, "SELECT * FROM " . $fetClients['db_solution'] . ".tblstate");

		while ($fetState = mysqli_fetch_assoc($getState)) {
			$states[$fetState['state_id']] = $fetState['state_name'];
		}

		$getAdmins = mysqli_query($cnOld, "SELECT a.*,u.user_group_name FROM " . $fetClients['db_solution'] . ".tbladmin a INNER JOIN " . $fetClients['db_solution'] . ".tbluser_groups u ON a.user_group_id = u.user_group_id");
		while ($fetAdmins = mysqli_fetch_assoc($getAdmins)) {

			$checkAdmins = mysqli_query($cn, "SELECT * FROM tbluser where user_name = '" . $fetAdmins['user_name'] . "' and sub_institute_id = '" . $fetSchools['Id'] . "'");

			if (mysqli_num_rows($checkAdmins) == 0) {

				$insertUser = "INSERT INTO tbluser (user_name,password,name_suffix,first_name,middle_name,last_name,email,mobile,gender,birthdate,address,city,state,pincode,user_profile_id,join_year,image,plain_password,sub_institute_id,status,last_login) VALUES ('" . $fetAdmins['user_name'] . "','" . $fetAdmins['plainpassword'] . "','" . $fetAdmins['name_suffix'] . "','" . $fetAdmins['first_name'] . "','" . $fetAdmins['middle_name'] . "','" . $fetAdmins['last_name'] . "','" . $fetAdmins['email'] . "','" . $fetAdmins['mobile'] . "','" . $fetAdmins['gender'] . "','" . $fetAdmins['birthdate'] . "','" . $fetAdmins['address'] . "','" . $cities[$fetAdmins['city_id']] . "','" . $states[$fetAdmins['state_id']] . "','India','" . $profiles[$fetAdmins['user_group_name']] . "','" . $fetAdmins['year'] . "','','" . $fetAdmins['plainpassword'] . "','" . $fetSchools['Id'] . "','1','')";

				$iqu = mysqli_query($cn, $insertUser) or die(mysqli_error($cn));

			}

		}

		$getStaffs = mysqli_query($cnOld, "SELECT a.*,u.user_group_name FROM " . $fetClients['db_solution'] . ".tblstaff a INNER JOIN " . $fetClients['db_solution'] . ".tbluser_groups u ON a.user_group_id = u.user_group_id");
		while ($fetStaffs = mysqli_fetch_assoc($getStaffs)) {

			$checkStaffs = mysqli_query($cn, "SELECT * FROM tbluser where user_name = '" . $fetStaffs['user_name'] . "' and sub_institute_id = '" . $fetSchools['Id'] . "'");

			if (mysqli_num_rows($checkStaffs) == 0) {

				$insertUser = "INSERT INTO tbluser (user_name,password,name_suffix,first_name,middle_name,last_name,email,mobile,gender,birthdate,address,city,state,pincode,user_profile_id,join_year,image,plain_password,sub_institute_id,status,last_login) VALUES ('" . $fetStaffs['user_name'] . "','" . $fetStaffs['plainpassword'] . "','" . $fetStaffs['name_suffix'] . "','" . $fetStaffs['first_name'] . "','" . $fetStaffs['middle_name'] . "','" . $fetStaffs['last_name'] . "','" . $fetStaffs['email'] . "','" . $fetStaffs['mobile'] . "','" . $fetStaffs['gender'] . "','" . $fetStaffs['birthdate'] . "','" . $fetStaffs['address'] . "','" . $cities[$fetStaffs['city_id']] . "','" . $states[$fetStaffs['state_id']] . "','India','" . $profiles[$fetStaffs['user_group_name']] . "','" . $fetStaffs['year'] . "','','" . $fetStaffs['plainpassword'] . "','" . $fetSchools['Id'] . "','1','')";

				$iqu = mysqli_query($cn, $insertUser) or die(mysqli_error($cn));
				
			}

		}

	}
}
?>