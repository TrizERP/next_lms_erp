<?php
require '../sysconfig.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';

if(isset($_GET['like']) ||$_REQUEST['addcomment']== 'set' )
{

    $sql_op_like = new simbio_dbop($dbs);
    $data['biblio_id'] = $_GET['subid'];
    $data['member_id'] = $_GET['memberid'];
    $insert = $sql_op_like->insert('biblio_like', $data);
    if($insert>0)
    {
        $count = 0;
        $query_update = "select like_count from biblio where biblio_id = '$_GET[subid]'";
        $qery_update = $dbs->query($query_update);
            while($row_update = $qery_update->fetch_assoc())
            {
                $count = $row_update['like_count']+1;	
            }
            //$count = $count+1;
        $data1['like_count'] = $count;
    	$update = $sql_op_like->update('biblio', $data1, 'biblio_id='.$_GET['subid']);
    }
}
if(isset($_GET['unlike']))
{
$sql_op_unlike = new simbio_dbop($dbs);
$data['biblio_id'] = $_GET['subid'];
$data['member_id'] = $_GET['memberid'];
//$data['member_name'] = $member_name;
//$data['comment'] = $_POST['comment'];
$sql_op_unlike->delete('biblio_like', 'biblio_id='.$data['biblio_id'].' AND member_id='.$data['member_id']);
$query_delete_update = "select like_count from biblio where biblio_id = '$_GET[subid]'";
$qery_delete_update = $dbs->query($query_delete_update);
while($row_delete_update = $qery_delete_update->fetch_assoc())
	{
           if($row_delete_update['like_count']!=0)
	 $count = $row_delete_update['like_count']-1;
	//$member_name = $row_update['member_name'];
	}
	//$count = $count+1;
        $data2['like_count'] = $count;
	$update1 = $sql_op_unlike->update('biblio', $data2, 'biblio_id='.$_GET['subid']);
}

if(isset($_GET['addcomment']) && isset($_POST['comment']))
{
$query = "select first_name from ".$inte_schema.".tblstudent where enrollment_no = '$_GET[memberid]'";
$qery = $dbs->query($query);
while($row = $qery->fetch_assoc())
	{
	$member_name = $row['first_name'];
	}
 $sql_op = new simbio_dbop($dbs);
$data['biblio_id'] = $_GET['subid'];
$data['member_id'] = $_GET['memberid'];
$data['member_name'] = $member_name;
$data['comment'] = $_POST['comment'];
$insert = $sql_op->insert('biblio_comments', $data);
}
$query_comment = "select * from biblio_comments where biblio_id = '$_GET[subid]'";
$qery_comment = $dbs->query($query_comment);
while($row_comment = $qery_comment->fetch_assoc())
	{
	$comment_name = $row_comment['comment'];
	echo '<div style="font-size:10px;">Comment :- <table><tr><td bgcolor="white" valign="top" style="font-size:10px;border:1px solid #0000CC;-moz-border-radius:5px;">'.$comment_name.'</td></td></tr></table></div></br>';
	}
$query_like_count = "select like_count from biblio where biblio_id = '$_GET[subid]'";
$qery_like_count = $dbs->query($query_like_count);
while($row_like_count = $qery_like_count->fetch_assoc())
	{
		echo "Rating :- ".$row_like_count['like_count']."<br/>";		
	}
if(isset($_GET['setflag']))
{
echo '<form name=likepage action=like.php?subid='.$_GET['subid'].'&addcomment=set&memberid='.$_GET['memberid'].' method=post>';
	echo '<input type=text name=comment />' ;
echo '<input type=submit value=Add />';
echo '</form>';
}
$checkflag = 0;
$query_like = "select * from biblio_like where member_id = '$_GET[memberid]' AND biblio_id = '$_GET[subid]'";
$qery_like = $dbs->query($query_like);
while($row_like = $qery_like->fetch_assoc())
	{
		$checkflag=1;		
	}
if($_GET['memberid']!='')
{
	if($checkflag==1)
	{
	echo '<a href=like.php?subid='.$_GET['subid'].'&memberid='.$_GET['memberid'].'&unlike=1 style="text-decoration:none;" ><img src="unlike.png" height=20px width=20px border=0 alt="not available"
title="Unlike"></a> ';
	}
	else	
	{
	echo '<a href=like.php?subid='.$_GET['subid'].'&memberid='.$_GET['memberid'].'&like=1 style="text-decoration:none;" ><img src="like.png" border=0 height=20px width=20px alt="not available"
title="Like"></a> ';
	}

	echo '<a href=like.php?subid='.$_GET['subid'].'&memberid='.$_GET['memberid'].'&setflag=1 style=text-decoration:none; >Comment</a>';
}
?>
