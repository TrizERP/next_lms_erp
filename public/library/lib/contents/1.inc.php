<?php
if (isset($_GET['gmd_search']) && !empty($_GET['gmd_search']))//added by iresh on 17-3-2011 For alphabetical searching
{
echo "<pre>";
print_r($_REQUEST);
     $sql="select gmd_id from mst_gmd where gmd_name='".$_GET['gmd_search']."'";
     $sql=$dbs->query($sql);
     $s_gmd='';
     while($row=$sql->fetch_assoc())
     {

	echo $s_gmd.=$row['gmd_id'];

     }
     $sql="select biblio_id from biblio where gmd_id='$s_gmd'";
     $sql=$dbs->query($sql);
     $s_biblio='';
     while($row=$sql->fetch_assoc())
     {
	 $s_biblio.=$row['biblio_id'].',';
    
     }

     $s_biblio=substr($s_biblio,0,-1);
     $sql="select topic_id from biblio_topic where biblio_id IN($s_biblio)";
     $sql=$dbs->query($sql);
     $s_topic='';
     while($row=$sql->fetch_assoc())
     {
	
	 $s_topic.=$row['topic_id'].',';
     }
     $s_topic=substr($s_topic,0,-1);
    $sql="select topic from mst_topic where topic_id IN($s_topic)";
    $sql=$dbs->query($sql);
    $s_topic_name='';
    while($row=$sql->fetch_assoc())
    {

	$s_topic_name.=$row['topic'].',';

    }
    $s_topic_name=substr($s_topic_name,0,-1);
echo '<div>'.$s_topic_name.'</div>';
    // default vars
  /*  $is_adv = false;
    $keywords = '';
    $criteria = '';
    // simple search
    if (isset($_GET['gmd_search'])) {
        $keywords = trim(strip_tags(urldecode($_GET['gmd_search'])));
    }
    if ($keywords && !preg_match('@[a-z0-9_.]+=[^=]+\s+@i', $keywords.' ')) {
     $criteria =' gmd='.$keywords;
       $biblio_list->setSQLcriteria($criteria);
	
    } else {
        $biblio_list->setSQLcriteria($keywords);
    }*/

}

