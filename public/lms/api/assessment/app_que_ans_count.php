<?php
error_reporting(1);
require("config.php");

$ID= $_REQUEST["ID"];

$sql = "
select quiz.name,quiz.id,q.questiontext AS que_list,q.id as que_id, qa.answer AS answer_list,qa.id AS answer_id
,count(*) as cnt
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
group by q.id
 
";

mysql_query('SET NAMES \'utf8\'');
	mysql_query ("set character_set_results='utf8'");
	mysql_query("SET character_set_results=utf8");
	mysql_query("SET names=utf8");
	mysql_query("SET character_set_client=utf8");
	mysql_query("SET character_set_connection=utf8");
	mysql_query("SET collation_connection=utf8_general_ci");
/*


*/
$res=mysql_query($sql);
$response["count_detail"] = array();
if (mysql_num_rows($res)>0)
{
	
	while($row1=mysql_fetch_array($res))
	{
		$stu= array();
		$stu["cnt"] = $row1['cnt'];
		
		array_push($response["count_detail"], $stu);
	}
	echo str_replace('\/','/',json_encode($response));
}

?>