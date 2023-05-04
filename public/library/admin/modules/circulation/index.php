<?php
session_start();
if (!defined('SENAYAN_BASE_DIR')) 
{
    require '../../../sysconfig.inc.php';
//    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
}

require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
// privileges checking
$can_read = utility::havePrivilege('circulation', 'r');
$can_write = utility::havePrivilege('circulation', 'w');

if (!($can_read AND $can_write))
{
    die('<div class="errorBox">'.gettext('You don\'t have enough privileges to view this section').'</div>');
}
// check if there is transaction running


if (isset($_SESSION['memberID']) AND !empty($_SESSION['memberID'])) 
{
    define('DIRECT_INCLUDE', true);
    include MODULES_BASE_DIR.'circulation/circulation_action.php';
}
else 
{
    
?>
<table  align=center>
<tr>
	<td valign=top>
	<?php
	$bradecum = '';       
        $basedir = basename(dirname(__FILE__));
        $bradecum = "<a href=javascript:void(0); onclick=javascript:new_set_home(); >Home</a>-><a class='' href=javascript:void(0); onclick=javascript:new_set('".$basedir."');>"; 
	$query = "select module_name from mst_module where module_path = '".$basedir."'";
	$set_query = $dbs->query($query);
	while($row=$set_query->fetch_assoc())
	{
                $_formated_module_name = ucwords(str_replace('_', ' ', $row['module_name']));
		$bradecum .= $_formated_module_name;
	}
$bradecum .= '</a>->';
/*if(isset($_REQUEST['action']))
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/index.php?action=detail&physical=physical class="headerText2">Add Hardcopy Book</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/index.php class="headerText2">Book List</a>';
}*/

   $bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'circulation/circulation_action.php class="headerText2">Book Circulation</a>';
   echo $bradecum;


?>	
	</td>
</tr>
</table>

<table>
<tr>
	<td class="tab_menu_top">
                            <ul class="tabs"> 
<!--<li><a href="<?php //echo MODULES_WEB_ROOT_DIR; ?>bibliography/index.php" class="headerText2"><?php //echo __('Library Resource List'); ?></a></li>-->


        

          <li><a href="<?php echo  MODULES_WEB_ROOT_DIR; ?>circulation/index.php"><?php echo gettext('Book Circulation'); ?></a> </li>

                            </ul></td></tr></table>

    <fieldset class="menuBox">
        <div class="menuBoxInner circulationIcon">
            <?php echo gettext('CIRCULATION - Insert GR.No. to start transaction with keyboard or barcode reader'); ?>
           <p class="only_border">&nbsp;</p>
            <form id="startCirc" class="notAJAX" method="post" action="#" style="display: inline;" target="blindSubmit" onsubmit="$('start').click();">
            <?php echo gettext('GR.No.'); ?> :
            <?php
            // create AJAX drop down
            $ajaxDD = new simbio_fe_AJAX_select();
            $ajaxDD->element_name = 'memberID';
            $ajaxDD->element_css_class = 'ajaxInputField';
            $ajaxDD->handler_URL = MODULES_WEB_ROOT_DIR.'membership/member_AJAX_response.php';
            $ajaxDD->additional_params_new='return checkspecialcharacterdynamic(this.name);';
            $ajaxDD->additional_params_new2='return maxlength(this.name,20);';
            
            echo $ajaxDD->out();
            ?>
            <input type="button" value="<?php echo gettext('Start Transaction'); ?>" id="start" class="button" onclick="setContent('mainContent', '<?php echo MODULES_WEB_ROOT_DIR; ?>circulation/circulation_action.php', 'post', $('startCirc').serialize(), true)" />
           </form> 
        </div>
    </fieldset>

<?php


    if (isset($_POST['finishID'])) 
    {
        $msg = str_ireplace('{enrollment_no}', $_POST['finishID'], gettext('Transaction with member {enrollment_no} is completed'));
        echo '<div class="infoBox">'.$msg.'</div>';
	$select=$dbs->query("select issue,checkin,reserve from ".$inte_schema.".tblstudent where SUB_INSTITUTE_ID='$_SESSION[SUB_INSTITUTE_ID]' AND enrollment_no=".$_SESSION['memberID']."");

		$issue='';
		$return='';
        while($row=$select->fetch_assoc())
		{
			$issue=$row['issue'];
			$return=$row['checkin'];
		}
        $count_issue=count($_SESSION['receipt_record']['loan']);
		$count_return= count($_SESSION['receipt_record']['return']);	
		if($issue=='1')
		{
			if(($count_issue) > '0')
				{
				require_once SENAYAN_BASE_DIR.'admin/modules/membership/member_book_issue_notification_mail.php';
				echo "<br>";
				}
		}
		if($return=='1')
		{
			if(($count_return) > '0')
			{
			  require_once SENAYAN_BASE_DIR.'admin/modules/membership/member_book_return_notification_mail.php';
			}
		}
    }
}
?>