<?php
require("config.php");
error_reporting(1);

$quiz_id= $_REQUEST["quiz_id"];
$uid= $_REQUEST["uid"];

$sql = "select QUESTION_LAYOUT,GIVEN_ANS,ORG_ANS from mdl_quiz_grades where quiz='".$quiz_id."' and userid='".$uid."'";
mysql_query('SET NAMES \'utf8\'');
	mysql_query ("set character_set_results='utf8'");
	mysql_query("SET character_set_results=utf8");
	mysql_query("SET names=utf8");
	mysql_query("SET character_set_client=utf8");
	mysql_query("SET character_set_connection=utf8");
	mysql_query("SET collation_connection=utf8_general_ci");
$res=mysql_query($sql);
	if (mysql_num_rows($res)>0)
	{
		while($row1=mysql_fetch_array($res))
		{
			$QUESTION_LAYOUT=explode(',', $row1[QUESTION_LAYOUT]);
			$GIVEN_ANS=explode(',', $row1[GIVEN_ANS]);
			$ORG_ANS=explode(',', $row1[ORG_ANS]);
		}
	}

$mi = new MultipleIterator();
$mi->attachIterator(new ArrayIterator($QUESTION_LAYOUT));
$mi->attachIterator(new ArrayIterator($GIVEN_ANS));
$mi->attachIterator(new ArrayIterator($ORG_ANS));

$respons["given"] = array();
$stu= array();
foreach ( $mi as $value ) {
    list($QUESTIONLAYOUT, $GIVENANS, $ORGANS) = $value;
	$sql="	select q.questiontext, qa.answer as org_ans,qb.answer as given_ans from mdl_question as q 
			inner join mdl_question_answers as qa on qa.id='".$ORGANS."'
			inner join mdl_question_answers as qb on qb.id='".$GIVENANS."'
			where q.id='".$QUESTIONLAYOUT."'
		";
		mysql_query('SET NAMES \'utf8\'');
	mysql_query ("set character_set_results='utf8'");
	mysql_query("SET character_set_results=utf8");
	mysql_query("SET names=utf8");
	mysql_query("SET character_set_client=utf8");
	mysql_query("SET character_set_connection=utf8");
	mysql_query("SET collation_connection=utf8_general_ci");
	$res=mysql_query($sql);
	
	if (mysql_num_rows($res)>0)
	{
		while($row1=mysql_fetch_array($res))
		{
			$stu["questiontext"] = $row1['questiontext'];
			$stu["org_ans"] = $row1['org_ans'];
			$stu["given_ans"] = $row1['given_ans'];
				
			array_push($respons["given"], $stu);
		}
	}
}
echo str_replace('\/','/',json_encode($respons));

?>