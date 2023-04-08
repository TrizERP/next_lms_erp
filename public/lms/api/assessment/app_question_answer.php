<?php
error_reporting(1);
require("config.php");

$ID= $_REQUEST["ID"];
$user_id= $_REQUEST["user_id"];

$sql_attempt="select (ifnull(max(attempt),0) + 1) as attempt,id,uniqueid from mdl_quiz_attempts where quiz='".$ID."' and userid='".$user_id."'";
$res=mysql_query($sql_attempt);

if (mysql_num_rows($res)>0)
{
	while($row1=mysql_fetch_array($res))
	{
		$attempt = $row1['attempt'];
		$uniqueid = $row1['id'];
	}
	$sql_insert="insert into mdl_quiz_attempts(uniqueid,quiz,userid,attempt)VALUES('".$uniqueid."','".$ID."','".$user_id."','".$attempt."')";
	$res2=mysql_query($sql_insert);
}

$sql = "
select quiz.name,quiz.id,q.questiontext AS que_list,q.id as que_id, qa.answer AS answer_list,qa.id AS answer_id
from mdl_quiz as quiz,mdl_question_answers as qa,mdl_question as q
where quiz.id in 
			(select id from mdl_quiz where id='".$ID."') 
			and qa.question=q.id 
			and question in 
					(select id from mdl_question where category=
						(select category from mdl_question where id = 
							(select left(cast(questions as CHAR(10)),5)
							from mdl_quiz where id=quiz.id
							) 
						)
					)
 
";
mysql_query('SET NAMES \'utf8\'');
	mysql_query ("set character_set_results='utf8'");
	mysql_query("SET character_set_results=utf8");
	mysql_query("SET names=utf8");
	mysql_query("SET character_set_client=utf8");
	mysql_query("SET character_set_connection=utf8");
	mysql_query("SET collation_connection=utf8_general_ci");
/*

select quiz.name,quiz.id AS que_id,q.questiontext AS que_list, qa.answer AS answer_list,qa.id AS answer_id
from mdl_quiz as quiz,mdl_question_answers as qa,mdl_question as q
where quiz.id in 
			(select id from mdl_quiz where id='".$ID."') 
			and qa.question=q.id 
			and fraction>0 
			and question in 
					(select id from mdl_question where category=
													(select category from mdl_question where id = 
																							(select left(cast(questions as CHAR(10)),5)
																								from mdl_quiz where id=quiz.id
																							) 
													)
					 )
*/
$res=mysql_query($sql);
$response["que_detail"] = array();
	
shuffle($res);
//print_r($res); 
if (mysql_num_rows($res)>0)
{
	
	while($row1=mysql_fetch_array($res))
	{
		$stu= array();
		$stu["que_id"] = $row1['que_id'];
		$stu["que_list"] = $row1['que_list'];
		$stu["answer_id"] = $row1['answer_id'];
		$stu["answer_list"] = $row1['answer_list'];
		
		
		array_push($response["que_detail"], $stu);
	}
	echo str_replace('\/','/',json_encode($response));
}

?>