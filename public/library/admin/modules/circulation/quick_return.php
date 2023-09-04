<?php
session_start();
/* Item Status Management section */

// echo '<pre>';
// print_r($_REQUEST);

// main system configuration
require '../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('master_file', 'r');
$can_write = utility::havePrivilege('master_file', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
}

// item status rules
$rules_option[] = array(NO_LOAN_TRANSACTION, gettext('No Books'));

/* RECORD OPERATION */
if (isset($_POST['saveData']) AND $can_read AND $can_write) 
{
	//echo 'soni';die;
    exit();
} 
else if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) 
{	
	//echo 'div';die;
    if (!($can_read AND $can_write)) 
    {
        die();
    }
    /* DATA DELETION PROCESS */
    $sql_op = new simbio_dbop($dbs);
    $failed_array = array();
    $error_num = 0;
    if (!is_array($_POST['itemID'])) 
    {
        // make an array
        $_POST['itemID'] = array($dbs->escape_string(trim($_POST['itemID'])));
    }
    // loop array
    foreach ($_POST['itemID'] as $itemID) 
    {
        $itemID = $dbs->escape_string(trim($itemID));
		$item_code = explode('=',$_REQUEST[lastQueryStr]);
		$cur_date = date('Y-m-d');
		//echo 'item '.$itemID;die;
		// echo '<pre>';
		// print_r($_REQUEST);
		// echo "UPDATE loan SET return_date='".$cur_date."' WHERE member_id='".$itemID."' AND item_code = '".$item_code[1]."' AND loan_date is not null AND return_date is null";die;
            $sql_op->custom_update("UPDATE loan SET return_date='".$cur_date."' WHERE member_id='".$itemID."' AND item_code = '".$item_code[1]."' AND loan_date is not null AND return_date is null");
		 // echo '<script type="text/javascript">';       
       // echo "\n".'alert(\''.__('Book Return Successfully!').'\');'."\n";		     
       // echo '</script>';
    }

    // error alerting
    if ($error_num == 0) {
        utility::jsAlert(gettext('Book Return Successfully'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    } else {
        utility::jsAlert(gettext('Some or All Data NOT deleted successfully! Please contact system administrator'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    }
    exit();
}
/* item status update process end */

/* search form */
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'circulation/quick_return.php class="headerText2">Quick Return</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
</table>
<table>
<tr>
	<td class="tab_menu_top">
      <ul class="tabs"> 

<li> 
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>circulation/quick_return.php" class="headerText2"><?php echo gettext('Quick Return'); ?></a> </li>
</ul>
	</td>
</tr>
</table>

<?
if (isset($_POST['detail']) OR (!isset($_GET['action']) AND $_GET['action'] != 'detail'))
{
?>

<fieldset class="menuBox">
<div class="menuBoxInner masterFileIcon">
    <p class="only_border">&nbsp;</p>
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>circulation/quick_return.php" id="search" method="get" style="display: inline;"><?php echo gettext('Item Code'); ?> :
    <input type="text" name="keywords_for_quick_return" size="30" required />
    <input type="submit" id="doSearch" value="<?php echo gettext('Search'); ?>" class="button" />
    </form>
</div>
</fieldset>

<?
}
?>

<?php
$inte_schema = $_SESSION['inte_schema'];
$dyear = $_SESSION['dyear'];

if(isset($_REQUEST['keywords_for_quick_return']))
{
    /* ITEM STATUS LIST */
    // table spec
    $table_spec = "loan AS l
    LEFT JOIN ".$inte_schema.".tblstudent AS m ON l.member_id = m.enrollment_no
    INNER JOIN item AS i ON l.item_code=i.item_code
    INNER JOIN biblio AS b ON i.biblio_id=b.biblio_id
	INNER JOIN ".$inte_schema.".tblstudent_enrollment SE on SE.student_id = m.id and SE.syear=".$dyear." AND m.sub_institute_id = SE.sub_institute_id
	INNER JOIN ".$inte_schema.".standard CS on SE.standard_id = CS.id AND SE.sub_institute_id = CS.sub_institute_id 
	INNER JOIN ".$inte_schema.".division SS on SS.id = SE.section_id AND SS.sub_institute_id = SE.sub_institute_id
	INNER JOIN ".$inte_schema.".academic_section SG on SG.id= SE.grade_id AND SG.sub_institute_id='".$_SESSION['SUB_INSTITUTE_ID']."'
	";

    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
        $datagrid->setSQLColumn(
        'm.enrollment_no AS \''.gettext('Gr.No.').'\'',
        'm.enrollment_no AS \''.gettext('Gr.No.').'\'',
        'concat_ws(" ",m.first_name,m.middle_name,m.last_name) AS \''.gettext('Member Name').'\'',
		'concat_ws ("-",CS.name,SS.name) AS \''.gettext('Std').'\'',
		'l.item_code AS \''.gettext('Item Code').'\'',
		'b.title AS \''.gettext('Book Title').'\'',
        'DATE_FORMAT(l.loan_date,"%d-%m-%Y") AS \''.gettext('Loan Date').'\'',//DATE_FORMAT(l.loan_date,"%d-%m-%Y")
        'DATE_FORMAT(l.due_date,"%d-%m-%Y") AS \''.gettext('Due Date').'\'',                 
        //'l.return_date is not null AS \''.__('Loan Status').'\'',
        'DATE_FORMAT(l.return_date,"%d-%m-%Y") AS \''.gettext('Return Date').'\''
            );
    } else {
        $datagrid->setSQLColumn(
        'm.enrollment_no AS \''.gettext('Gr.No.').'\'',
        'm.enrollment_no AS \''.gettext('Gr.No.').'\'',
        'concat_ws(" ",m.first_name,m.middle_name,m.last_name) AS \''.gettext('Member Name').'\'',    
		'concat_ws ("-",CS.name,SS.name) AS \''.gettext('Std').'\'',
		'l.item_code AS \''.gettext('Item Code').'\'',
		'b.title AS \''.gettext('Book Title').'\'',
        'DATE_FORMAT(l.loan_date,"%d-%m-%Y") AS \''.gettext('Loan Date').'\'',//DATE_FORMAT(l.loan_date,"%d-%m-%Y")
        'DATE_FORMAT(l.due_date,"%d-%m-%Y") AS \''.gettext('Due Date').'\'',                 
        //'l.return_date is not null AS \''.__('Loan Status').'\'',
        'DATE_FORMAT(l.return_date,"%d-%m-%Y") AS \''.gettext('Return Date').'\''
            );
    }
    $datagrid->setSQLorder('l.loan_date DESC');

    // echo '<pre>';
    // print_r($datagrid);
    // die;

	$criteria = ' 1=1 AND l.return_date IS NULL ';
	
    // is there any search
    if (isset($_GET['keywords_for_quick_return']) AND $_GET['keywords_for_quick_return']) {
       $keywords = $dbs->escape_string($_GET['keywords_for_quick_return']);
	   $criteria .= ' AND i.item_code=\''.$keywords.'\'';
       $datagrid->setSQLCriteria("l.item_code LIKE \'%'$keywords'%\'");
    }
	
	 $datagrid->setSQLCriteria($criteria);

    // set table and table header attributes
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];
// echo '<pre>';
// print_r($table_spec);
    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, ($can_read AND $can_write));
	// echo '<pre>';
	// print_r($datagrid_result);
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, gettext('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"</div>';
    }

    echo $datagrid_result;
}
?>
