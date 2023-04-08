<?php
error_reporting(1);
require("config.php");

$ID= $_REQUEST["ID"];
$ch_ID= $_REQUEST["ch_ID"];
$limit= $_REQUEST["limit"];

/*$sql = "
select eq.id as que_id,eq.questiontext as org_que_list,count(eq.id) as cnt
from mdl_question_answers eqa 
inner join mdl_question eq on eq.id=eqa.question 
inner join mdl_question_categories eqc on eqc.id=eq.category
inner join mdl_context ect on ect.id=eqc.id
inner join mdl_course_modules ecm on ecm.instance=ect.instanceid
inner join mdl_course ec on ec.id=ecm.course
where ec.id='".$ID."' and eqc.id='".$ch_ID."'
group by eq.questiontext limit 0,$limit
 
";*/
$sql = "
select eq.id as que_id,eq.questiontext as que_list,eqa.id as answer_id ,eqa.answer as answer_list,count(eq.id) as cnt
from mdl_question_answers eqa 
inner join mdl_question eq on eq.id=eqa.question 
inner join mdl_question_categories eqc on eqc.id=eq.category
inner join mdl_course ec on ec.id='".$ID."'
where ec.id='".$ID."' and eqc.id='".$ch_ID."'
group by eq.questiontext limit 0,$limit
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