<?php

// main system configuration
session_start();
require '../../../../sysconfig.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
// privileges checking
$can_read = utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('reporting', 'w');

// echo "<pre>";
//print_r($_REQUEST);
//echo "<pre>";

if (!$can_read) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO_BASE_DIR.'simbio_GUI/template_parser/simbio_template_parser.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MODULES_BASE_DIR.'reporting/report_dbgrid.inc.php';

$page_title = 'Members Report';
$reportView = false;
$num_recs_show = 20;
if (isset($_GET['reportView'])) {
    $reportView = true;
}

if (!$reportView) {
?>
    
<table  align=center>
<tr>
	<td valign=top>
	<?php
	$bradecum = '';       
        $basedir = basename(dirname(__FILE__));
      $bradecum = "<a href=javascript:void(0); onclick=javascript:new_set_home(); >Home</a>-><a class='' href=javascript:void(0); onclick=javascript:new_set('reporting');>"; 
	$query = "select module_name from mst_module where module_path = 'reporting'";
	$set_query = $dbs->query($query);
	while($row=$set_query->fetch_assoc())
	{
                $_formated_module_name = ucwords(str_replace('_', ' ', $row['module_name']));
		$bradecum .= $_formated_module_name;
	}
$bradecum .= '</a>->';
/*if(isset($_REQUEST['action']))
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php?action=detail class="headerText2">Add New Member</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php class="headerText2">View Member List</a>';
}*/
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'reporting/customs/member_list.php class="headerText2">Member List</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
<!-- filter -->
    <fieldset style="margin-bottom: 3px;">
    <legend style="font-weight: bold"><?php echo strtoupper(__('Member List')); ?> - <?php echo __('Report Filter'); ?></legend>
    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
    <div id="filterForm">
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Membership Type'); ?></div>
            <div class="divRowContent">
            <?php
            $mtype_options = array();
            $mtype_options[] = array(' ', __('--Select Membership Type--'));
             $mtype_options[] = array('0', __('Admin'));
             $mtype_options[] = array('1', __('Staff'));
             $mtype_options[] = array('2', __('Student'));
             //$mtype_options[] = array('3', __('Parent'));
             //$mtype_options[] = array('4', __('Employee'));
            echo simbio_form_element::selectList('member_type', $mtype_options);
            ?>
            </div>
            
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Member ID').'/'.__('Member Name'); ?></div>
            <div class="divRowContent">
            <?php 
            //comment by iresh on 25-1-2011 echo simbio_form_element::textField('text', 'id_name', '', 'style="width: 50%"');
           /*added by iresh on 25-1-2011 */ echo simbio_form_element::textField('text', 'id_name', '', 'style="width: 140px"');
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Standard'); ?></div>
            <div class="divRowContent">
            <?php
            
			$str = "SELECT cs.id AS subject_id,cs.name AS title
                        FROM ".$inte_schema.".standard cs
                        INNER JOIN ".$inte_schema.".academic_section sg ON sg.id = cs.grade_id AND cs.sub_institute_id = sg.sub_institute_id
                        WHERE sg.sub_institute_id='".$_SESSION['SUB_INSTITUTE_ID']."' ";
			$std_q = $dbs->query($str);
			$std_options = array();
			$std_options[] = array('ALL', __('ALL'));
			while ($std_d = $std_q->fetch_row()) {
				$std_options[] = array($std_d[0], $std_d[1]);
			}
			echo simbio_form_element::selectList('standard', $std_options);		
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Gender'); ?></div>
            <div class="divRowContent">
            <?php
            $gender_chbox[0] = array('ALL', __('ALL'));
            $gender_chbox[1] = array('1', __('Male'));
            $gender_chbox[2] = array('0', __('Female'));
            echo simbio_form_element::radioButton('gender', $gender_chbox, 'ALL');
            ?>
            </div>
        </div>
        <div class="divRow">
           <!-- <div class="divRowLabel"><?php //echo __('Address'); ?></div>
            <div class="divRowContent">
            <?php
           //comment by iresh on 25-1-2011 echo simbio_form_element::textField('text', 'address', '', 'style="width: 50%"');
           /*added by iresh on 25-1-2011 *///echo simbio_form_element::textField('text', 'address', '', 'style="width: 140px"');
            ?>
            </div>-->
        </div>
<!--        <div class="divRow">-->
<!--            <div class="divRowLabel">-->
<table><tr>
        <td>
            <?php //echo __('Register Date From'); ?>
        </td>
        <td>
            <?php //echo __('Register Date Until'); ?>
        </td>
        <td>
            <?php echo __('Record each page'); ?>
        </td>
    </tr>
    <tr>
        <td>
            <?php //echo simbio_form_element::dateField('startDate', date('Y-m-d')); ?> 
        </td>
        <td>
             <?php //echo simbio_form_element::dateField('untilDate', date('Y-m-d')); ?>
        </td>
        <td>
            <input type="text" name="recsEachPage" size="3" maxlength="3" value="<?php echo $num_recs_show; ?>" /> <?php echo __('Set between 20 and 200'); ?>
        </td>
    </tr>
</table>
    <div style="padding-top: 10px; clear: both;">
    <input type="submit" name="applyFilter" value="<?php echo __('Apply Filter'); ?>" />
    <input type="button" name="moreFilter" value="<?php echo __('Show More Filter Options'); ?>" onclick="showHideTableRows('filterForm', 1, this, '<?php echo __('Show More Filter Options'); ?>', '<?php echo __('Hide Filter Options'); ?>')" />
    <input type="hidden" name="reportView" value="true" />
    </div>
    </form>
    </fieldset>
    <script type="text/javascript">hideRows('filterForm', 1);</script>
    <!-- filter end -->
    <div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height: 500px;"></iframe>
<?php
} 
else 
{
    ob_start();
    // table spec
//    $table_spec = 'member AS m
//        LEFT JOIN mst_member_type AS mt ON m.member_type_id=mt.member_type_id';
     if($_GET['member_type']==' ' OR !isset($_GET['member_type']))
     {       
         echo " ";
     } 
     else if ($_GET['member_type']=='0')
     {
        $table_spec = " ".$inte_schema.".tbluser AS m
        inner JOIN ".$inte_schema.".tbluserprofilemaster AS mt ON m.user_profile_id = mt.id AND mt.name != 'Teacher' AND mt.name != 'Student' ";

        // create datagrid
        $reportgrid = new report_datagrid();
        $reportgrid->setSQLColumn('m.user_name AS \''.__('Member ID').'\'',
            'concat_ws(" ",m.first_name,m.middle_name,m.last_name) AS \''.__('Member Name').'\'',
            'mt.name AS \''.__('Membership Type').'\'');
        $reportgrid->setSQLorder('concat(m.user_name) ASC');
        $criteria = 'm.id IS NOT NULL AND m.sub_institute_id = '.$_SESSION['SUB_INSTITUTE_ID'].' ';
     
        if (isset($_GET['member_type']) AND !empty($_GET['member_type']))
        {
            $mtype = intval($_GET['member_type']);
            $mtype = $mtype+1;
             $criteria .= ' AND m.user_profile_id='.$mtype;
        }
        if (isset($_GET['id_name']) AND !empty($_GET['id_name']))
        {
            $id_name = $dbs->escape_string($_GET['id_name']);
            $criteria .= ' AND (m.id LIKE \'%'.$id_name.'%\' OR concat(m.user_name) LIKE \'%'.$id_name.'%\')';
        }
        if (isset($_GET['gender']) AND $_GET['gender'] != 'ALL') 
        {
            $gender = $_GET['gender'];
            $criteria .= ' AND m.gender='.$gender;
        }
        // register date
        if (isset($_GET['startDate']) AND isset($_GET['untilDate'])) {
            $criteria .= ' AND (TO_DAYS(m.created_on) BETWEEN TO_DAYS(\''.$_GET['startDate'].'\') AND
                TO_DAYS(\''.$_GET['untilDate'].'\'))';
        }
        if (isset($_GET['recsEachPage'])) {
            $recsEachPage = (integer)$_GET['recsEachPage'];
            $num_recs_show = ($recsEachPage >= 20 && $recsEachPage <= 200)?$recsEachPage:$num_recs_show;
        }
        $reportgrid->setSQLCriteria($criteria);

        // put the result into variables
        echo $reportgrid->createDataGrid($dbs, $table_spec, $num_recs_show);
    }
    else if ($_GET['member_type']=='1')
    {
        $table_spec = " ".$inte_schema.".tbluser AS m
                    inner JOIN ".$inte_schema.".tbluserprofilemaster AS mt ON m.user_profile_id = mt.id AND mt.name = 'Teacher' ";                            
        // create datagrid
        $reportgrid = new report_datagrid();
        $reportgrid->setSQLColumn('m.user_name AS \''.__('Member ID').'\'',
            'concat_ws(" ",m.first_name,m.middle_name,m.last_name) AS \''.__('Member Name').'\'',
            'mt.name AS \''.__('Membership Type').'\'');
        $reportgrid->setSQLorder('m.user_name ASC');            
        $criteria = 'm.id IS NOT NULL AND m.sub_institute_id = '.$_SESSION['SUB_INSTITUTE_ID'].' ';
        if (isset($_GET['member_type']) AND !empty($_GET['member_type']))
        {
            $mtype = intval($_GET['member_type']);
            $mtype=$mtype+1;
            $criteria .= ' AND m.user_profile_id='.$mtype;
        }
        if (isset($_GET['id_name']) AND !empty($_GET['id_name'])) 
        {
            $id_name = $dbs->escape_string($_GET['id_name']);
            $criteria .= ' AND (m.staff_id LIKE \'%'.$id_name.'%\' OR m.user_name LIKE \'%'.$id_name.'%\')';
        }
        if (isset($_GET['gender']) AND $_GET['gender'] != 'ALL') 
        {
            $gender = $_GET['gender'];
            $criteria .= ' AND m.gender='.$gender;
        }
        // register date
        if (isset($_GET['startDate']) AND isset($_GET['untilDate'])) 
        {
            $criteria .= ' AND (TO_DAYS(m.created_on) BETWEEN TO_DAYS(\''.$_GET['startDate'].'\') AND
                TO_DAYS(\''.$_GET['untilDate'].'\'))';
        }
        if (isset($_GET['recsEachPage'])) {
            $recsEachPage = (integer)$_GET['recsEachPage'];
            $num_recs_show = ($recsEachPage >= 20 && $recsEachPage <= 200)?$recsEachPage:$num_recs_show;
        }
        $reportgrid->setSQLCriteria($criteria);
        echo $reportgrid->createDataGrid($dbs, $table_spec, $num_recs_show);
    }   
    else if ($_GET['member_type']=='2')
    {
        $table_spec = " ".$inte_schema.".tblstudent AS m
                    inner JOIN ".$inte_schema.".tbluserprofilemaster AS mt ON m.user_profile_id = mt.id AND mt.name = 'Student'
            		inner join ".$inte_schema.".tblstudent_enrollment SE on SE.student_id = m.id and SE.syear=".$dyear."
            		inner join ".$inte_schema.".standard CS on SE.standard_id = CS.id AND SE.sub_institute_id = CS.sub_institute_id
            		inner join ".$inte_schema.".division SS on SS.id = SE.section_id AND SE.sub_institute_id = SS.sub_institute_id
            		inner join ".$inte_schema.".academic_section SG on SG.ID= SE.grade_id AND SE.sub_institute_id = SG.sub_institute_id ";
        // create datagrid
        $reportgrid = new report_datagrid();
        $reportgrid->setSQLColumn('m.enrollment_no AS \''.__('Member ID').'\'',
            'concat_ws(" ",m.first_name,m.middle_name,m.last_name) AS \''.__('Member Name').'\'',
            'mt.name AS \''.__('Membership Type').'\'',
    		' CS.name AS \''.__('Std').'\'',
    		' SS.name AS \''.__('Div').'\''
    		
    		);
        $reportgrid->setSQLorder('CS.name,SS.name,concat(m.first_name) ASC');
        $criteria = 'm.enrollment_no IS NOT NULL AND m.sub_institute_id = '.$_SESSION['SUB_INSTITUTE_ID'].'';
        if (isset($_GET['id_name']) AND !empty($_GET['id_name'])) 
        {
            $id_name = $dbs->escape_string($_GET['id_name']);
            $criteria .= ' AND (m.enrollment_no LIKE \'%'.$id_name.'%\' OR m.first_name LIKE \'%'.$id_name.'%\')';
        }
        if (isset($_GET['gender']) AND $_GET['gender'] != 'ALL')
        {
            $gender = $_GET['gender'];
            $criteria .= ' AND m.gender='.$gender;
        }
    	if(isset($_GET["standard"]) AND $_GET["standard"] !='ALL' )
    	{
			$std= $_GET["standard"];
			$criteria .= ' AND SE.standard_id='.$std;
    	}
        if (isset($_GET['recsEachPage']))
        {
            $recsEachPage = (integer)$_GET['recsEachPage'];
            $num_recs_show = ($recsEachPage >= 20 && $recsEachPage <= 200)?$recsEachPage:$num_recs_show;
        }

        $reportgrid->setSQLCriteria($criteria);
        echo $reportgrid->createDataGrid($dbs, $table_spec, $num_recs_show);
    }
     
    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'pagingBox\').update(\''.str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set).'\');'."\n";
    echo '</script>';

    $content = ob_get_clean();
    // include the page template
    require SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/printed_page_tpl.php';
}
?>
