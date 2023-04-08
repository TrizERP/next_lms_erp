<?php
require '../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';

// page title
$page_title = 'Subject List';
// check for biblioID in url
$biblioID = 0;

if (isset($_GET['biblioID']) AND $_GET['biblioID']) 
{
    $biblioID = (integer)$_GET['biblioID'];
}

// utility function to check subject/topic
function checkSubject($str_subject,$type1)
{        
    global $dbs;    
    $_q = $dbs->query('SELECT subject_type_id FROM mst_subject_type WHERE subject_type_name=\''.$str_subject.'\' and topic_id='.$type1);
    
    if ($_q->num_rows > 0) 
    {
        $_d = $_q->fetch_row();                
        return $_d[0];
    }
    else
    {
        return false;
    }
}
// start the output buffer
ob_start();
// biblio topic save proccess
if (isset($_POST['save']) AND (isset($_POST['subjectID']) OR trim($_POST['search_str']))) 
{
    
    $subject = trim($dbs->escape_string(strip_tags($_POST['search_str'])));
    $type1=(integer)$_POST['type'];
    // create new sql op object
    $sql_op = new simbio_dbop($dbs);
    // check if biblioID POST var exists
    if (isset($_POST['biblioID']) AND !empty($_POST['biblioID'])) 
    {
        
        $data['biblio_id'] = (integer)$_POST['biblioID'];
        // check if the topic select list is empty or not
        if (!empty($_POST['subjectID']))
        {   
            $data['subject_type_id'] = $_POST['subjectID'];
        }
        else if (empty($_POST['subjectID']))         
        {        
            
                                  
            // check subject
            $subject_id = checkSubject($subject,$type1);
            /*utility::jsAlert($subject_id);
            exit();            */
            if ($subject_id != false) 
            {                                                        
                $data['subject_type_id'] = $subject_id;
            }
            else
            {                           
                // adding new topic
                $topic_data['topic_id']=intval($_POST['type']);
                $topic_data['subject_type_name'] = $subject;
                $topic_data['input_date'] = date('Y-m-d');
                $topic_data['last_update'] = date('Y-m-d');
                                
                // insert new topic to topic master table
                $sql_op->insert('mst_subject_type', $topic_data);
                // put last inserted ID
                $data['subject_type_id'] = $sql_op->insert_id;
            }
        }
        
        
        //$data['topic_id'] = intval($_POST['type']);                
        $data['topic_id'] = intval($_POST['type']);
        //utility::jsAlert("TOPIC".$data['topic_id']);
        $data['level'] = intval($_POST['level']);
        //utility::jsAlert("Level".$data['level']);
        //exit();
        
        if ($sql_op->insert('biblio_topic', $data))
        {
            echo '<script type="text/javascript">';
            echo 'alert(\'Topic succesfully updated!\');';
            echo 'parent.setIframeContent(\'topicIframe\', \''.MODULES_WEB_ROOT_DIR.'bibliography/iframe_topic.php?biblioID='.$data['biblio_id'].'\');';
            echo '</script>';
        }
        else 
        {
            utility::jsAlert(__('Duplicate Entery Same Subject and Topic is alreadt Exist'));
         //   $sql_op->update('biblio_topic',$data,"biblio_id=$data[biblio_id] and topic_id=$data[topic_id]");
            //utility::jsAlert(__('Duplicate Entery Same Subject and Topic is alreadt Exist')."\n".$sql_op->error);
           // utility::jsAlert(__('Record has been Already There!')."\n".$sql_op->error);
        }

    } else {
        if (!empty($_POST['subjectID'])) {
            // add to current session
            $_SESSION['biblioTopic'][$_POST['subjectID']] = array($_POST['subjectID'],intval($_POST['type']));
	   
        } else if ($subject AND empty($_POST['subjectID'])) {
            // check subject
            $subject_id = checkSubject($subject);
            if ($subject_id !== false) {
                $last_id = $subject_id;
            } else {
                // adding new topic
		
                $topic_data['subject_type_name'] = $subject;
                $topic_data['topic_id'] = $_POST['type'];
                $topic_data['input_date'] = date('Y-m-d');
                $topic_data['last_update'] = date('Y-m-d');
                // insert new topic to topic master table
                $sql_op->insert('mst_subject_type', $topic_data);
		$last_id = $sql_op->insert_id;
            }
	 //  $_SESSION['biblioTopic'][$_POST['subjectID']] = array($_POST['subjectID'],intval($_POST['type']));
           $_SESSION['biblioTopic'][$last_id] = array($last_id,intval($_POST['type']));
	  // print_r($_SESSION['biblioTopic'][$last_id]);
		//return;
	   
        }
       
	
        echo '<script type="text/javascript">';
        echo 'alert(\''.__('Subject added!').'\');';
        echo 'parent.setIframeContent(\'topicIframe\', \''.MODULES_WEB_ROOT_DIR.'bibliography/iframe_topic.php\');';
        echo '</script>';
    }
}

?>

<div style="padding: 5px; background: #CCCCCC;">
<form name="mainForm" action="pop_topic.php?biblioID=<?php echo $biblioID; ?>" method="post">
<div>
    <strong><?php echo __('Add Subject'); ?></strong>
    <hr />
    <form name="searchTopic" method="post" style="display: inline;">
<?php
 //$ajax_exp1 = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/ajax_check_subsubject_id.php', 'mst_topic', 'topic_id:topic', 'type', $('search_str').getValue())";
//$ajax_exp1 = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/ajax_check_subsubject_id.php', 'mst_subject_type', 'subject_type_id:subject_type_name', 'type', $('search_str').getValue())";
        // string element
  ///      if ($topic_data['topic']) {
            //$topic_options[] = array($topic_data['topic_id'], $topic_data['topic']);
     //   }
       // $topic_options[] = array('0', __('subject type'));
       // $str_input = simbio_form_element::selectList('type', $topic_options, '', 'style="width: 50%;"');
?>
    <?php
    $ajax_exp = "ajaxFillSelect('../../AJAX_lookup_handler.php', 'mst_subject_type', 'subject_type_id:subject_type_name', 'subjectID', this.value)";
   //$ajax_exp = "ajaxFillSelect('../../AJAX_lookup_handler.php', 'mst_subject_type', 'subject_type_id:subject_type_name', 'subjectID', $('search_str').getValue())";
      ?>
    <?php echo __('Keyword'); ?> : <input type="text" name="search_str" id="search_str" style="width: 30%;" onkeyup="<?php echo $ajax_exp;?>" onblur="return charactercheck('search_str');" />
      <!--  <?php echo __('Keyword'); ?> : <input type="text" name="search_str" id="search_str" style="width: 30%;" onkeyup="<?php //echo $ajax_exp;?>" onclick="<?php //echo $ajax_exp1;?>" />                          -->
<?php
   
   
	//echo $str_input;
?>

<?php
 $sub_subject_visible=0;
$sub_subject_visible ='<div id="subsubject_visible_id"></div>';
if($sub_subject_visible != 1 )
{ echo '<td id="subsubject">';
?>  
 <select name="type" style="width: 20%;"><?php
$subject_type=$dbs->query('select topic_id,topic from mst_topic order by topic');
while($row=$subject_type->fetch_assoc())
{	
	echo '<option value='.$row['topic_id'].'>'.$row['topic'].'</option>';
	
}
  
?></select>
    <select name="level" style="width: 20%;">
    <?php
    echo '<option value="1">Primary</option>';
    echo '<option value="2">Additional</option>';
    ?>
    </select>
    
    
<?php echo '</td>';}?>
     <?php echo '<td><span id="ajax_output_sub_subject_4subject"></span></td>'; ?>

</div>
<div style="margin-top: 5px;">
<select name="subjectID" id="subjectID" size="5" style="width: 100%;"><option value="0"><?php echo __('Type to search for existing topics or to add a new one'); ?></option></select>
<?php if ($biblioID) { echo '<input type="hidden" name="biblioID" value="'.$biblioID.'" />'; } ?>
<input type="submit" name="save" value="<?php echo __('Insert To Bibliography'); ?>" style="margin-top: 5px;" />
</div>
</form>
</div>

<?php
/* main content end */
$content = ob_get_clean();
// include the page template
require SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';
?>
