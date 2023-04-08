<?php

include("auth/auth.php");

$host = "192.168.0.2";//"150.129.172.214";
$username = "root";//"triz_erp";
$password = "Tr!zLMS@123#";//"Triz@2019$04";
$database = "triz_erp_2";//"triz_erp_2";

$cn = mysqli_connect($host, $username, $password) or die("Database Not Connected");
mysqli_select_db($cn, $database) or die("database");

$type = $_REQUEST['type'];
$teacher_id = $_REQUEST['teacher_id'];
$sub_institute_id = $_REQUEST['sub_institute_id'];
$syear = $_REQUEST['syear'];
$action = $_REQUEST['action'];

if($syear != "" && $sub_institute_id != "" && $teacher_id != "" && $action != "") 
{
	$username_sql = "select user_name from tbluser where sub_institute_id = '".$sub_institute_id."' and id = '".$teacher_id."'"; 
	$username_data = mysqli_query($cn,$username_sql);
	$username_data = mysqli_fetch_assoc($username_data);
	
	$sql = "select *,if(db_library is null,0,1) as rights,
				if(db_library is null,0,1) as library_rights
				from school_setup s
				inner join tblclient c on c.id = s.client_id
				where s.Id = ".$sub_institute_id; 
	$client_data = mysqli_query($cn,$sql);
	$client_data = mysqli_fetch_assoc($client_data);

	// $host1 = $client_data['db_host'];
	// $username1 = $client_data['db_user'];
	// $password1 = $client_data['db_password'];
	$library_db = $client_data['db_library'];
	$library_host = '192.168.0.2';
    $library_user = 'root';
    $library_password = 'Tr!zLMS@123#';
	 
	$cn1 = mysqli_connect($library_host, $library_user, $library_password) or die("College Database Not Connected");	
	mysqli_select_db($cn1, $library_db) or die("College database not found");
	
	$server = "https://".$_SERVER['HTTP_HOST'];

	//Teacher Search Book API
	if($action == "search_book")
	{
		$item_code = $_REQUEST['item_code'];

		if($item_code != "")
		{
			$sql = "SELECT item.item_id, item.item_code AS 'Item Code', item.biblio_id,biblio.title,
			CONCAT('$server/library/images/docs/',biblio.image) as book_image,
	ct.coll_type_name AS 'Collection Type', loc.location_name AS 'Location', 
	biblio.classification AS 'Classification', item.last_update AS 'Last Updated'
	FROM item
	LEFT JOIN biblio ON item.biblio_id=biblio.biblio_id
	LEFT JOIN mst_location AS loc ON item.location_id=loc.location_id
	LEFT JOIN mst_coll_type AS ct ON item.coll_type_id=ct.coll_type_id
	WHERE item.item_code = '".$item_code."'"; 
			$search_book = mysqli_query($cn1,$sql);
			if(mysqli_num_rows($search_book) > 0)
			{
				while($search_book_data = mysqli_fetch_assoc($search_book))
				{
					$data[] = $search_book_data;			
				}
				$res['status_code'] = 1;
				$res['message'] = "Success";
				$res['data'] = $data;
			}
			else{
				$res['status_code'] = 0;
				$res['message'] = "Item Not Found";			
			}
		}
		else{
			$res['status_code'] = 0;
			$res['message'] = "Parameter Missing";	
		}	
	}
	
	//Teacher My Book API
	if($action == "my_book")
	{
		$sql = "SELECT l.item_code AS 'Item Code', b.title AS 'Title', l.loan_date AS 'Loan Date', 
IF(return_date IS NULL, 'Not Returned Yet', return_date) AS 'Return Date'
FROM loan AS l
LEFT JOIN item AS i ON l.item_code collate utf8_general_ci =i.item_code collate utf8_general_ci
LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
WHERE l.member_id = '".$username_data['user_name']."'
				ORDER BY l.loan_date DESC"; 
		$my_book = mysqli_query($cn1,$sql);
		if(mysqli_num_rows($my_book) > 0)
		{
			while($my_book_data = mysqli_fetch_assoc($my_book))
			{
				$data[] = $my_book_data;			
			}
			$res['status_code'] = 1;
			$res['message'] = "Success";
			$res['data'] = $data;
		}
		else{
			$res['status_code'] = 0;
			$res['message'] = "Book history not found";			
		}
	}
	
	// All book list API
	if($action == "all_book")
	{
		$sql = "SELECT item.item_id, item.item_code, item.biblio_id, biblio.title,
			CONCAT('$server/library/images/docs/',biblio.image) as book_image,
ct.coll_type_name, loc.location_name, 
biblio.classification, item.last_update
FROM item
LEFT JOIN biblio ON item.biblio_id=biblio.biblio_id
LEFT JOIN mst_location AS loc ON item.location_id=loc.location_id
LEFT JOIN mst_coll_type AS ct ON item.coll_type_id=ct.coll_type_id "; 
		$allbook = mysqli_query($cn1,$sql);
		if(mysqli_num_rows($allbook) > 0)
		{
			while($allbook_data = mysqli_fetch_assoc($allbook))
			{
				$data[] = $allbook_data;			
			}
			$res['status_code'] = 1;
			$res['message'] = "Success";
			$res['data'] = $data;
		}
		else{
			$res['status_code'] = 0;
			$res['message'] = "Book Not Found";			
		}
	}

	//Teacher Reserve Book API
	if($action == "reserve_book")
	{
		$item_code = $_REQUEST['item_code'];
		$biblio_id = $_REQUEST['biblio_id'];
		
		if($item_code != "" && $biblio_id != "")
		{
			$ins_sql = "INSERT INTO reserve(member_id,biblio_id,item_code,reserve_date) 
				VALUES('".$teacher_id."','".$biblio_id."','".$item_code."',now())";
			mysqli_query($cn1,$ins_sql);

			$res['status_code'] = 1;
			$res['message'] = "Successfully Reserve Book";
		}else{
			$res['status_code'] = 0;
			$res['message'] = "Parameter Missing";	
		}
	}
}
else
{
	$res['status_code'] = 0;
	$res['message'] = "Parameter Missing";	
}
print_r(json_encode($res));
?>