<?php
error_reporting(1);
require("config.php");

$ID= $_REQUEST["ID"];
$ch_ID= $_REQUEST["ch_ID"];
$limit= $_REQUEST["limit"];

/*$sql = "
select eq.id as que_id,eq.questiontext as que_list,eqa.id as answer_id ,eqa.answer as answer_list
from mdl_question_answers eqa 
inner join mdl_question eq on eq.id=eqa.question 
inner join mdl_question_categories eqc on eqc.id=eq.category
inner join mdl_course ec on ec.id='".$ID."'
where ec.id='".$ID."' and eqc.id='".$ch_ID."' and eqa.fraction>0 limit 0,$limit
";*/

$sql = "select eq.id as que_id,eq.questiontext as que_list,eqa.id as answer_id ,eqa.answer as answer_list
from mdl_question_answers eqa 
inner join mdl_question eq on eq.id=eqa.question 
inner join mdl_question_categories eqc on eqc.id=eq.category
inner join mdl_course ec on ec.id='".$ID."'
where ec.id='".$ID."' and eqc.id='".$ch_ID."'
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
		$stu["org_que_list"] = $row1['que_list'];
		$stu["que_id"] = $row1['que_id'];
		$stu["answer_id"] = $row1['answer_id'];
		$stu["org_ans_list"] = $row1['answer_list'];
		
		array_push($response["que_detail"], $stu);
	}
	echo str_replace('\/','/',json_encode($response));
}

?>