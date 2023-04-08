<?php
error_reporting(1);
require("config.php");

$ID= $_REQUEST["ID"];

/*$sql = "select count(*) from mdl_question where FIND_IN_SET(id, (select questions from mdl_quiz where course='".$ID."'))";
$res=mysql_query($sql);
   if (mysql_num_rows($res)>0)
 {*/
/*$sql = "
select * from mdl_quiz where course='".$ID."' and questions!=''
and questions in
(
select ID from mdl_question where ID in (select questions from mdl_quiz where course='".$ID."' )
) 
";*/
/*$sql = "select eq.* ,
case 
	when ace.quiz_id is null THEN 'False'
	when ace.quiz_id !='' THEN 'True'
ELSE 'N/A'
END AS Lock_Flage
from mdl_quiz eq 
left join app_coins_entry ace on ace.quiz_id=eq.id
where eq.course='".$ID."' and eq.questions!=''
and eq.questions in
(
select ID from mdl_question where ID in (select questions from mdl_quiz where course='".$ID."' )
) ";*/

$sql="
select case 
		when ace.quiz_id is null THEN 'False'
		when ace.quiz_id !='' THEN 'True'
			ELSE 'N/A'
		END AS Lock_Flage,eq.*
		
		from mdl_quiz eq 
		left join app_coins_entry ace on ace.quiz_id=eq.id
		where eq.course='".$ID."' and eq.questions!=''
		and eq.questions in
			(
				select ID from mdl_question where ID in (select questions from mdl_quiz where course='".$ID."' )
			) 
		order by Lock_Flage desc ,eq.id
";
mysql_query('SET NAMES \'utf8\'');
	mysql_query ("set character_set_results='utf8'");
	mysql_query("SET character_set_results=utf8");
	mysql_query("SET names=utf8");
	mysql_query("SET character_set_client=utf8");
	mysql_query("SET character_set_connection=utf8");
	mysql_query("SET collation_connection=utf8_general_ci");
$res=mysql_query($sql);
$response["quiz_detail"] = array();
if (mysql_num_rows($res)>0)
{
	
	while($row1=mysql_fetch_array($res))
	{
		$stu= array();
		$stu["id"] = $row1['id'];
		$stu["name"] = $row1['name'];
		$stu["questions"] = $row1['sumgrades'];
		$stu["status"] = $row1['Lock_Flage'];
		
		array_push($response["quiz_detail"], $stu);
	}
	echo str_replace('\/','/',json_encode($response));
	}
/*}*/

?>