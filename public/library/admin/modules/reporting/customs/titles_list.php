<?php
session_start();
// main system configuration
require '../../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
// privileges checking
$can_read = utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('reporting', 'w');

if (!$can_read) 
{
     die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MODULES_BASE_DIR.'reporting/report_dbgrid.inc.php';

$page_title = 'Titles Report';
$reportView = false;
$num_recs_show = 10;
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'reporting/customs/title_list.php class="headerText2">Title List</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
    <!-- filter -->
<!--style="margin-bottom: 3px;" -->
                <br>
     <fieldset>
    <legend style="font-weight: bold">
            <?php echo strtoupper(__('Title List')); ?> - <?php echo __('Report Filter'); ?>
    </legend>
    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
    <div id="filterForm">        
        <table width='70%'>
            <tr>
                <td width="25%">
                    <div class="divRow">
                            <div class="divRowLabel">
                            <?php echo __('Title/ISBN : '); ?>             
                            <?php  echo simbio_form_element::textField('text', 'title', '', 'style="width: 130px"'); ?>
                            </div>
                     </div>
                </td>
                <td width="25%">
                    <div class="divRow">
                        <div class="divRowLabel"><?php echo __('Author : '); ?>
                                <?php echo simbio_form_element::textField('text', 'author', '', 'style="width: 137px"');?>
                        </div>
                    </div>
                </td>
                <td width="25%">
                      <div class="divRow">
                        <div class="divRowLabel"><?php echo __('Classification : '); ?>
                            
                            <?php echo simbio_form_element::textField('text', 'class', '', 'style="width: 140px"'); ?>
                        </div>
                       </div>
                </td>
            </tr>
            <tr>
                <td width="25%">
                    <div class="divRow">
                        <?php //echo __('GMD'); ?>
                            <div class="divRowLabel"><?php echo __('Material Type : '); ?>
                            <?php
                                $gmd_q = $dbs->query('SELECT gmd_id, gmd_name FROM mst_gmd');
                                $gmd_options[] = array('0', __('ALL'));
                                while ($gmd_d = $gmd_q->fetch_row()) 
                                {
                                    $gmd_options[] = array($gmd_d[0], $gmd_d[1]);
                                }
                                echo simbio_form_element::selectList('gmd[]', $gmd_options, '','width=50px;');
                                //,'multiple="multiple" size="5"'
                        ?> 
                            <?php// echo __('Press Ctrl and click to select multiple entries'); ?>
                           </div>
                      </div>
                </td>
                <td width="25%">
                    
                    <div class="divRow">
                        <div class="divRowLabel">
                            <?php echo __('Language :'); ?>
                        <?php
                        $lang_q = $dbs->query('SELECT language_id, language_name FROM mst_language');
                        $lang_options = array();
                        $lang_options[] = array('0', __('ALL'));
                        while ($lang_d = $lang_q->fetch_row()) 
                        {
                            $lang_options[] = array($lang_d[0], $lang_d[1]);
                        }
                        echo simbio_form_element::selectList('language', $lang_options);
                        ?>
                        </div>
                    </div>
                </td>
                <td width="25%">
                    <div class="divRow">
                        <div class="divRowLabel">
                        <?php echo __('Location :'); 
                               echo '<br>';
            
                                $loc_q = $dbs->query('SELECT location_id, location_name FROM mst_location');
                                $loc_options = array();
                                $loc_options[] = array('0', __('ALL'));
                                while ($loc_d = $loc_q->fetch_row()) 
                                {
                                    $loc_options[] = array($loc_d[0], $loc_d[1]);
                                }
                                echo simbio_form_element::selectList('location', $loc_options,'style=width:300px;');
                        ?>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
        
        

        
        
        <!--<div class="divRow">
            <div class="divRowLabel"><?php// echo __('Record each page'); ?></div>
            <div class="divRowContent"><input type="text" name="recsEachPage" size="3" maxlength="3" value="<?php //echo $num_recs_show; ?>" /> <?php //echo __('Set between 20 and 200'); ?></div>
        </div>-->
    </div>
    <div style="padding-top: 10px; clear: both;">
    <input type="submit" name="applyFilter" value="<?php echo __('Search'); ?>" />
    <input type="button" name="moreFilter" value="<?php echo __('Advance Search'); ?>" onclick="showHideTableRows('filterForm', 1, this, '<?php echo __('Show More Search Options'); ?>', '<?php echo __('Hide Search Options'); ?>')" />
<!--added Started by Parth 23/8/2011 -->
    <!--<input type="reset" name="applyReset" value="<?php //echo __('Reset'); ?>" />	-->
<!--added Ended by Parth 23/8/2011 -->
    <input type="hidden" name="reportView" value="true" />
    </div>
    </form>
    </fieldset>
    <script type="text/javascript">hideRows('filterForm', 1);</script>
    <!-- filter end -->
    <div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height: 600px;"></iframe>
<?php
} else {
    ob_start();
    // create datagrid
    $reportgrid = new report_datagrid();
    /*$reportgrid->setSQLColumn('b.biblio_id', 'b.title AS \''.__('Title').'\'', 'COUNT(item_id) AS '.__('Copies').'',
        'b.isbn_issn AS \''.__('ISBN/ISSN').'\'',
        'b.call_number AS \''.__('Call Number').'\'');*/
    $reportgrid->setSQLColumn('b.biblio_id', 
        'b.title AS \''.__('Title').'\'', 
        'i.item_code AS \''.__('Item Code').'\'', 
        'COUNT(item_id) AS '.__('Copies').'',
        'b.isbn_issn AS \''.__('ISBN/ISSN').'\'');
//added and commented started by Parth 23/8/2011
    //$reportgrid->setSQLorder('b.title ASC');
    $reportgrid->setSQLorder('b.biblio_id ASC');
//added and commented ended by Parth 23/8/2011	
    $reportgrid->invisible_fields = array(0);

    // is there any search
    $criteria = 'bsub.biblio_id IS NOT NULL ';
    $outer_criteria = 'b.biblio_id > 0 ';
    if (isset($_GET['title']) AND !empty($_GET['title'])) {
        $keyword = $dbs->escape_string(trim($_GET['title']));
        $words = explode(' ', $keyword);
        if (count($words) > 1) {
            $concat_sql = ' AND (';
            foreach ($words as $word) {
                $concat_sql .= " (bsub.title LIKE '%$word%' OR bsub.isbn_issn LIKE '%$word%') AND";
            }
            // remove the last AND
            $concat_sql = substr_replace($concat_sql, '', -3);
            $concat_sql .= ') ';
            $criteria .= $concat_sql;
        } else {
            $criteria .= ' AND (bsub.title LIKE \'%'.$keyword.'%\' OR bsub.isbn_issn LIKE \'%'.$keyword.'%\')';
        }
    }
    if (isset($_GET['author']) AND !empty($_GET['author'])) 
   {
        $author = $dbs->escape_string($_GET['author']);
        $criteria .= ' AND ma.author_name LIKE \'%'.$author.'%\'';
    }
    if (isset($_GET['class']) AND !empty($_GET['class'])) {
        $class = $dbs->escape_string($_GET['class']);
        $criteria .= ' AND bsub.classification LIKE \''.$class.'%\'';
    }
    if (isset($_GET['gmd']) AND !empty($_GET['gmd'])) {
        $gmd_IDs = '';
        foreach ($_GET['gmd'] as $id) {
            $id = (integer)$id;
            if ($id) {
                $gmd_IDs .= "$id,";
            }
        }
        $gmd_IDs = substr_replace($gmd_IDs, '', -1);
        if ($gmd_IDs) {
            $outer_criteria .= " AND b.gmd_id IN($gmd_IDs)";
        }
    }
    if (isset($_GET['language']) AND !empty($_GET['language'])) 
    {
        $language = $dbs->escape_string(trim($_GET['language']));
        $criteria .= ' AND bsub.language_id=\''.$language.'\'';
    }
    if (isset($_GET['location']) AND !empty($_GET['location'])) 
    {
        $location = $dbs->escape_string(trim($_GET['location']));
        $outer_criteria .= ' AND i.location_id=\''.$location.'\'';
    }
    if (isset($_GET['recsEachPage'])) 
    {
        $recsEachPage = (integer)$_GET['recsEachPage'];
        $num_recs_show = ($recsEachPage >= 20 && $recsEachPage <= 200)?$recsEachPage:$num_recs_show;
    }

    // subquery/view string
    $subquery_str = '(SELECT DISTINCT bsub.biblio_id, bsub.gmd_id, bsub.title, bsub.isbn_issn, bsub.classification, bsub.language_id
        FROM biblio AS bsub
        LEFT JOIN biblio_author AS ba ON bsub.biblio_id = ba.biblio_id
        LEFT JOIN mst_author AS ma ON ba.author_id = ma.author_id
        LEFT JOIN biblio_topic AS bt ON bsub.biblio_id = bt.biblio_id
        LEFT JOIN mst_topic AS mt ON bt.topic_id = mt.topic_id WHERE '.$criteria.')';

    

    
   // table spec
    
    $table_spec = $subquery_str.' AS b
        LEFT JOIN item AS i ON b.biblio_id=i.biblio_id';

    // set group by
    $reportgrid->sql_group_by = 'b.biblio_id';
    $reportgrid->setSQLorder('b.biblio_id');         
    $reportgrid->setSQLCriteria($outer_criteria);

    
    function showTitleAuthors($obj_db, $array_data)
    {
       
      /*  echo 'SELECT b.title, a.author_name FROM biblio AS b
            LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
            LEFT JOIN mst_author AS a ON ba.author_id=a.author_id
            WHERE b.biblio_id='.$array_data[0];die; */
        
        $_biblio_q = $obj_db->query('SELECT b.title, a.author_name FROM biblio AS b
            LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
            LEFT JOIN mst_author AS a ON ba.author_id=a.author_id
            WHERE b.biblio_id='.$array_data[0]);
        
        $_authors = '';
        while ($_biblio_d = $_biblio_q->fetch_row()) 
        {
            $_title = $_biblio_d[0];
            $_authors .= $_biblio_d[1].' - ';
        }
        $_authors = substr_replace($_authors, '', -3);
        $_output = $_title.'<br /><i>'.$_authors.'</i>'."\n";
        return $_output;
    }
    
    $reportgrid->modifyColumnContent(1, 'callback{showTitleAuthors}');
//echo "<pre>";
//print_r($reportgrid);
    // put the result into variables
    echo $reportgrid->createDataGrid($dbs, $table_spec, $num_recs_show);
  //  echo $peging = '<div style="text-align: center;">'.simbio_paging::paging($reportgrid->num_rows, $num_recs_show, 5).'</div>';	
    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'pagingBox\').update(\''.str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set).'\');'."\n";
    echo '</script>';

    $content = ob_get_clean();
    // include the page template
    require SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/printed_page_tpl.php';
}
?>
