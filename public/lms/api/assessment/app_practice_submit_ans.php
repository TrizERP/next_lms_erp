<?php
error_reporting(1);
require("config.php");

$qid= $_REQUEST["qid"];
$aid= $_REQUEST["aid"];
$uid= $_REQUEST["uid"];
$sub_id= $_REQUEST["sub_id"];
$quiz_id= $_REQUEST["quiz_id"];
$marks= $_REQUEST["marks"];
$time= $_REQUEST["time"];
$oid = $_REQUEST["oid"];
$total = $_REQUEST["total"];
//$qid = "1,2,3,4,5,6";
//$aid = "11,12,13,14,15,16";

$myArray = explode(',', $qid);
$myArray1 = explode(',', $aid);

$i=0;
foreach (array_combine($myArray, $myArray1) as $my_Array => $my_Array1) {
 
$sql = "select id from mdl_question_answers where question='".$my_Array."' and fraction>0";
//echo $sql."<br>";

$res=mysql_query($sql);
	
	$response["result"] = array();
	while($row1=mysql_fetch_array($res)){
	
	$id = $row1['id'];
	//echo $my_Array1."==".$id."<br>";
	
		if($my_Array1==$id){
			$i++;
		}

	}
}

$sql_grade = " select * from mdl_practice_grades where quiz='".$quiz_id."' and userid='".$uid."'";
$res=mysql_query($sql_grade);
   if (mysql_num_rows($res)>0)
	{
	$result1 = "INSERT INTO mdl_practice_grades(quiz,userid,grade,MODIFIED_DATE,QUESTION_LAYOUT,GIVEN_ANS,ORG_ANS,Total_Marks,Exam_Time)VALUES('".$quiz_id."','".$uid."','".$marks."',now(),'".$qid."','".$aid."','".$oid."','".$total."','".$time."')";
		 mysql_query($result1);
		
	}
	else
   {
	$result1 = "INSERT INTO mdl_practice_grades(quiz,userid,grade,MODIFIED_DATE,QUESTION_LAYOUT,GIVEN_ANS,ORG_ANS,Total_Marks,Exam_Time)VALUES('".$quiz_id."','".$uid."','".$marks."',now(),'".$qid."','".$aid."','".$oid."','".$total."','".$time."')";
		 mysql_query($result1);
	}


$sql = "select grade from mdl_practice_grades where quiz='".$quiz_id."' and userid='".$uid."'";
//echo $sql."<br>";

$res=mysql_query($sql);
	
	$response["result"] = array();
	while($row1=mysql_fetch_array($res)){
	
	$grade = $row1['grade'];
	//echo $my_Array1."==".$id."<br>";
	$grades = "select * from app_coins_entry where sub_id='".$sub_id."' and quiz_id='".$quiz_id."' and user_id='".$uid."' ";
	$gd=mysql_query($grades);
	if (mysql_num_rows($gd)>0)
	{
		$result1 = "update app_coins_entry set coins='".$grade."',created_on=now() where quiz_id='".$quiz_id."' and sub_id='".$sub_id."' and user_id='".$uid."' ";
		mysql_query($result1);
	}else{
	   	$result1 = "INSERT INTO app_coins_entry(sub_id,quiz_id,user_id,coins,created_on)VALUES('".$sub_id."','".$quiz_id."','".$uid."','".$grade."',now())";
		mysql_query($result1);
	}
	}
	
	$sql_coin = "
	SELECT IFNULL(SUM(coins),0) as coins FROM app_coins_entry where user_id='".$uid."'
";

$coin=mysql_query($sql_coin);
$stu= array();
if (mysql_num_rows($coin)>0)
{
	
	while($row1=mysql_fetch_array($coin))
	{
		$m_c = $row1['coins'];
		$gift = "select * from app_coins_gifts
		where ($m_c >= from_point) and ($m_c <= to_point )
		";
		$gift_coin=mysql_query($gift);
		if (mysql_num_rows($gift_coin)>0){
		while($row_gift=mysql_fetch_array($gift_coin))
		{
			//$stu["gift"] = $row_gift['gift'];
			//$stu["gift_path"] = $row_gift['gift_path'];
			$stu["gift"] = "No";
			$stu["gift_path"] = "No";
		}
		}else{
			$stu["gift"] = "No";
			$stu["gift_path"] = "No";
		}
	}
	
}


$stu["marks"] = $grade;
	
array_push($response["result"], $stu);
echo str_replace('\/','/',json_encode($response));
	

?>