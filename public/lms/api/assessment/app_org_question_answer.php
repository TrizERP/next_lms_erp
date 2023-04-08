<?php
error_reporting(1);
require("config.php");

$ID= $_REQUEST["ID"];

$sql = "
select quiz.name,quiz.id ,q.questiontext AS org_que_list,q.id as que_id, qa.answer AS org_ans_list,q.defaultgrade as marks,qa.id AS answer_id,quiz.shufflequestions
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
$response["que_detail"] = array();
if (mysql_num_rows($res)>0)
{
	
	while($row1=mysql_fetch_array($res))
	{
		$stu= array();
		$stu["org_que_list"] = $row1['org_que_list'];
		$stu["que_id"] = $row1['que_id'];
		$stu["answer_id"] = $row1['answer_id'];
		$stu["org_ans_list"] = $row1['org_ans_list'];
		$stu["marks"] = $row1['marks'];
		array_push($response["que_detail"], $stu);
	}
	echo str_replace('\/','/',json_encode($response));
}

?>