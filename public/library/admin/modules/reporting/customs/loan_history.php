<?php
session_start();
require '../../../../sysconfig.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';

$can_read = utility::havePrivilege('circulation', 'r') || utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('circulation', 'w') || utility::havePrivilege('reporting', 'w');

if (!$can_read) 
{
    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MODULES_BASE_DIR.'reporting/report_dbgrid.inc.php';

$page_title = 'Loan History Report';
$reportView = false;
$num_recs_show = 10;
if (isset($_GET['reportView'])) 
{
    $reportView = true;
}

if (!$reportView) 
{
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'reporting/customs/loan_history.php class="headerText2">Loan History</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
    <!-- filter -->
    <fieldset  style="margin-bottom: 3px;">
    <legend style="font-weight: bold"><?php echo strtoupper(__('Loan History')); ?> - <?php echo __('Report Filter'); ?></legend>
    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
    <div id="filterForm">
        <table width='70%'>
            <tr>
                
                <td width='25%'>
                    
                    <div class="divRow">
                            <div class="divRowLabel"><?php echo __('Member ID').''.__(':'); ?>
                           
                            <?php            
                            echo simbio_form_element::textField('text', 'id_name', '', 'style="width:  153px;"');
							?>
                            </div>
                    </div>
                </td>
                <td>
                	Item Code <br />
                    <?php            
                      echo simbio_form_element::textField('text', 'item_code', '', 'style="width:  153px;"');
					?>
                </td>
                <td>Standard <br/>
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
                    
                </td>
                <td>Division <br/>
                	    
                        <?php
						 $str_section = "select id,name from ".$inte_schema.".division where sub_institute_id='".$_SESSION['SUB_INSTITUTE_ID']."'";
                        $section_q = $dbs->query($str_section);
						$section_options = array();
						$section_options[] = array('ALL', __('ALL'));
						while ($section_d = $section_q->fetch_row()) {
							$section_options[] = array($section_d[0], $section_d[1]);
						}
						echo simbio_form_element::selectList('section', $section_options);
                        ?>
                    
                </td>
                <td>Loan From Date <br />
                <?php echo simbio_form_element::dateField('startDate', date('Y-m-d'));?>
                </td>
                <td>Loan To Date <br />
                <?php echo simbio_form_element::dateField('untilDate', date('Y-m-d'));?>
                </td>
            </tr>
        </table>
         </div>
    <div style="padding-top: 10px; clear: both;">
    <input type="submit" name="applyFilter" value="<?php echo __('Generate Report'); ?>" />
    <input type="hidden" name="reportView" value="true" />
    </div>
    </form>
    </fieldset>
    <script type="text/javascript">hideRows('filterForm', 1);</script>
    <!-- filter end -->
   <div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 140%; height: 800px;"></iframe>
<?php
} 
else 
{
    ob_start();
    // table spec
    $table_spec = "loan AS l
    INNER JOIN ".$inte_schema.".tblstudent AS m ON l.member_id = m.enrollment_no
    INNER JOIN item AS i ON l.item_code=i.item_code
    INNER JOIN biblio AS b ON i.biblio_id=b.biblio_id
    INNER JOIN ".$inte_schema.".tblstudent_enrollment SE on SE.student_id = m.id and SE.syear=".$dyear."
    INNER JOIN ".$inte_schema.".standard CS on SE.standard_id = CS.id AND SE.sub_institute_id = CS.sub_institute_id
    INNER JOIN ".$inte_schema.".division SS on SS.id = SE.section_id AND SE.sub_institute_id = SS.sub_institute_id
    INNER JOIN ".$inte_schema.".academic_section SG on SG.ID= SE.grade_id AND SE.sub_institute_id = SG.sub_institute_id AND SG.SUB_INSTITUTE_ID='".$_SESSION['SUB_INSTITUTE_ID']."' ";

     // create datagrid
    $curr_date = date('Y-m-d');

    $reportgrid = new report_datagrid();
    $reportgrid->setSQLColumn(
        'm.enrollment_no AS \''.__('Gr.No.').'\'',
        'concat_ws(" ",m.first_name,m.middle_name,m.last_name) AS \''.__('Member Name').'\'',
		'concat_ws ("-",CS.name,SS.name) AS \''.__('Std').'\'',
		'l.item_code AS \''.__('Item Code').'\'',
		'b.title AS \''.__('Book Title').'\'',
        'DATE_FORMAT(l.loan_date,"%d-%m-%Y") AS \''.__('Loan Date').'\'',//DATE_FORMAT(l.loan_date,"%d-%m-%Y")
        'DATE_FORMAT(l.due_date,"%d-%m-%Y") AS \''.__('Due Date').'\'',                 
        //'l.return_date is not null AS \''.__('Loan Status').'\'',
        'DATE_FORMAT(l.return_date,"%d-%m-%Y") AS \''.__('Return Date').'\''
            );//DATE_FORMAT(l.due_date,"%d-%m-%Y")
    $reportgrid->setSQLorder('l.loan_date DESC');

    $criteria = ' 1=1  ';
	if(isset($_GET["standard"]) AND $_GET["standard"] !='ALL' )
	{
			$std= $_GET["standard"];
			$criteria .= ' AND SE.standard_id='.$std;
	}
	if(isset($_GET["section"]) AND $_GET["section"] !='ALL' )
	{
			$section= $_GET["section"];
			$criteria .= ' AND SE.section_id='.$section;
	}
	if (isset($_GET['id_name']) AND !empty($_GET['id_name'])) 
    {
        $id_name = $dbs->escape_string($_GET['id_name']);
       // echo $id_name;die;
        $criteria .= ' AND (l.member_id LIKE \'%'.$id_name.'%\')';
    }
	if (isset($_GET['item_code']) AND !empty($_GET['item_code'])) 
    {
        $item_code = $dbs->escape_string($_GET['item_code']);
       // echo $id_name;die;
        $criteria .= ' AND (l.item_code LIKE \'%'.$item_code.'%\')';
    }
    if (isset($_GET['title']) AND !empty($_GET['title'])) 
    {
        $keyword = $dbs->escape_string(trim($_GET['title']));
        $words = explode(' ', $keyword);
        if (count($words) > 1) {
            $concat_sql = ' AND (';
            foreach ($words as $word) {
                $concat_sql .= " (b.title LIKE '%$word%') AND";
            }
            // remove the last AND
            $concat_sql = substr_replace($concat_sql, '', -3);
            $concat_sql .= ') ';
            $criteria .= $concat_sql;
        } else {
            $criteria .= ' AND b.title LIKE \'%'.$keyword.'%\'';
        }
    }
    if (isset($_GET['itemCode']) AND !empty($_GET['itemCode'])) {
        $item_code = $dbs->escape_string(trim($_GET['itemCode']));
        $criteria .= ' AND i.item_code=\''.$item_code.'\'';
    }
    // loan date
    if (isset($_GET['startDate']) AND isset($_GET['untilDate'])) 
    {
        $criteria .= ' AND (TO_DAYS(l.loan_date) BETWEEN TO_DAYS(\''.date('Y-m-d',strtotime($_GET['startDate'])).'\') AND
            TO_DAYS(\''.date('Y-m-d',strtotime($_GET['untilDate'])).'\'))';
    }
    else
    {
        $criteria .= ' AND l.loan_date ="'. $curr_date.'" ';
    }
    
    if (isset($_GET['recsEachPage']))
    {
        $recsEachPage = (integer)$_GET['recsEachPage'];
        $num_recs_show = ($recsEachPage >= 20 && $recsEachPage <= 200)?$recsEachPage:$num_recs_show;
    }
    $reportgrid->setSQLCriteria($criteria);
    
    // put the result into variables
    echo $reportgrid->createDataGrid($dbs, $table_spec, 40);

    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'pagingBox\').update(\''.str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set).'\');'."\n";
    echo '</script>';

    $content = ob_get_clean();
    // include the page template
    require SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/printed_page_tpl.php';
}

  
?>
