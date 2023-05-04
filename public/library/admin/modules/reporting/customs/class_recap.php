<?php
session_start();
require '../../../../sysconfig.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';

$can_read = utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('reporting', 'w');



if (!$can_read) 
{
    die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MODULES_BASE_DIR.'reporting/report_dbgrid.inc.php';

$page_title = 'Recap Report';
$reportView = false;
if (isset($_GET['reportView'])) 
{
    
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'reporting/customs/class_recap.php class="headerText2">Custom Recapitulation</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
    <!-- filter -->
    <fieldset style="margin-bottom: 3px;">
    <legend style="font-weight: bold"><?php echo strtoupper(gettext('Custom Recapitulations')); ?> - <?php echo gettext('Report Filter'); ?></legend>
    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
    <div id="filterForm">
        <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Recap By'); ?>:</div>
            <div class="divRowContent">
            <?php
            //$recapby_options[] = array('', __('Classification'));
            $recapby_options[] = array('gmd', gettext('Material Type'));
	    $recapby_options[] = array('material_sub_type', gettext('Material Sub Type'));
            $recapby_options[] = array('collType', gettext('Collection Type'));
            $recapby_options[] = array('language', gettext('Language'));
            echo simbio_form_element::selectList('recapBy', $recapby_options);
            ?>
            </div>
        </div>
    </div>
    <div style="padding-top: 10px; clear: both;">
    <input type="submit" name="applyFilter" value="<?php echo gettext('Search'); ?>" />
    <!--
    <input type="button" name="moreFilter" value="<?php echo __('Advance Search'); ?>" onclick="showHideTableRows('filterForm', 1, this, '<?php echo __('Show More Search Options'); ?>', '<?php echo gettext('Hide v Options'); ?>')" />
    -->
    <input type="hidden" name="reportView" value="true" />
    </div>
    </form>
    </fieldset>
    <script type="text/javascript">hideRows('filterForm', 1);</script>
    <!-- filter end -->
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height: 500px;"></iframe>
<?php
} else {
    ob_start();
    $row_class = 'alterCellPrinted';
    $recapby = gettext('Classification');
    $output = '<table align="center" class="border" style="width: 10gettext%;" cellpadding="3" cellspacing="0">';
    // header
    $output .= '<tr><td class="dataListHeaderPrinted">'.$recapby.'</td>
        <td class="dataListHeaderPrinted">'.gettext('Title').'</td>
        <td class="dataListHeaderPrinted">'.gettext('Items').'</td></tr>';
    if (isset($_GET['recapBy']) AND trim($_GET['recapBy']) != '') {
        switch ($_GET['recapBy']) {
            case 'gmd' :
            $recapby = gettext('Material Type');
            /* GMD */
            $gmd_q = $dbs->query("SELECT DISTINCT gmd_id, gmd_name FROM mst_gmd");
            while ($gmd_d = $gmd_q->fetch_row()) {
                $row_class = ($row_class == 'alterCellPrinted')?'alterCellPrinted2':'alterCellPrinted';
                $output .= '<tr><td class="'.$row_class.'"><strong style="font-size: 1.5em;">'.$gmd_d[1].'</strong></td>';
                // count by title
                $bytitle_q = $dbs->query("SELECT COUNT(biblio_id) FROM biblio WHERE gmd_id=".$gmd_d[0]);
                $bytitle_d = $bytitle_q->fetch_row();
                $output .= '<td class="'.$row_class.'"><strong style="font-size: 1.3em;">'.$bytitle_d[0].'</strong></td>';

                // count by item
                $byitem_q = $dbs->query("SELECT COUNT(item_id) FROM item AS i INNER JOIN biblio AS b
                    ON i.biblio_id=b.biblio_id
                    WHERE b.gmd_id=".$gmd_d[0]);
                $byitem_d = $byitem_q->fetch_row();
                $output .= '<td class="'.$row_class.'"><strong style="font-size: 1.3em;">'.$byitem_d[0].'</strong></td>';

                $output .= '</tr>';
            }
            /* GMD END */
            break;
	    case 'material_sub_type' :
            $recapby = gettext('Material Sub Type');
            /* Material Sub Type */
//added by Start By Parth 23/8/2011
            //$gmd_q = $dbs->query("SELECT DISTINCT material_sub_id, material_sub_name FROM mst_material_sub_type");
            $gmd_q = $dbs->query("SELECT DISTINCT material_sub_id, material_sub_name FROM mst_material_sub_type GROUP BY material_sub_name");
//added by Ended By Parth 23/8/2011
            while ($gmd_d = $gmd_q->fetch_row()) {
                $row_class = ($row_class == 'alterCellPrinted')?'alterCellPrinted2':'alterCellPrinted';
                $output .= '<tr><td class="'.$row_class.'"><strong style="font-size: 1.5em;">'.$gmd_d[1].'</strong></td>';
                // count by title
                $bytitle_q = $dbs->query("SELECT COUNT(biblio_id) FROM biblio WHERE material_sub_id=".$gmd_d[0]);
                $bytitle_d = $bytitle_q->fetch_row();
                $output .= '<td class="'.$row_class.'"><strong style="font-size: 1.3em;">'.$bytitle_d[0].'</strong></td>';

                // count by item
                $byitem_q = $dbs->query("SELECT COUNT(item_id) FROM item AS i INNER JOIN biblio AS b
                    ON i.biblio_id=b.biblio_id
                    WHERE b.material_sub_id=".$gmd_d[0]);
                $byitem_d = $byitem_q->fetch_row();
                $output .= '<td class="'.$row_class.'"><strong style="font-size: 1.3em;">'.$byitem_d[0].'</strong></td>';

                $output .= '</tr>';
            }
            /* Material Sub Type END */
            break;	
            case 'language' :
            $recapby = gettext('Language');
            /* LANGUAGE */
//added and commented start by Parth 23/8/2011
        //    $lang_q = $dbs->query("SELECT DISTINCT language_id, language_name FROM mst_language");
	    $lang_q = $dbs->query("SELECT DISTINCT language_id, language_name FROM mst_language GROUP BY language_name"); 	
//added and commented end by Parth 23/8/2011
            while ($lang_d = $lang_q->fetch_row()) {
                $row_class = ($row_class == 'alterCellPrinted')?'alterCellPrinted2':'alterCellPrinted';
                $output .= '<tr><td class="'.$row_class.'"><strong style="font-size: 1.5em;">'.$lang_d[1].'</strong></td>';
                // count by title
                $bytitle_q = $dbs->query("SELECT COUNT(biblio_id) FROM biblio WHERE language_id='".$lang_d[0]."'");
                $bytitle_d = $bytitle_q->fetch_row();
                $output .= '<td class="'.$row_class.'"><strong style="font-size: 1.3em;">'.$bytitle_d[0].'</strong></td>';

                // count by item
                $byitem_q = $dbs->query("SELECT COUNT(item_id) FROM item AS i INNER JOIN biblio AS b
                    ON i.biblio_id=b.biblio_id
                    WHERE b.language_id='".$lang_d[0]."'");
                $byitem_d = $byitem_q->fetch_row();
                $output .= '<td class="'.$row_class.'"><strong style="font-size: 1.3em;">'.$byitem_d[0].'</strong></td>';

                $output .= '</tr>';
            }
            /* LANGUAGE END */
            break;
            case 'collType' :
            $recapby = gettext('Collection Type');
            /* COLLECTION TYPE */
//added and commented started by Parth 23/8/2011
            //$ctype_q = $dbs->query("SELECT DISTINCT coll_type_id, coll_type_name FROM mst_coll_type group by coll_type_name");
             $ctype_q = $dbs->query("select  m.coll_type_name ,count(DISTINCT b.biblio_id) , count(i.item_id) from mst_coll_type m
left join item i  on i.coll_type_id = m.coll_type_id left join biblio b on i.biblio_id=b.biblio_id group by m.coll_type_name");
            while ($ctype_d = $ctype_q->fetch_row()) {
                $row_class = ($row_class == 'alterCellPrinted')?'alterCellPrinted2':'alterCellPrinted';
                //$output .= '<tr><td class="'.$row_class.'"><strong style="font-size: 1.5em;">'.$ctype_d[1].'</strong></td>';
                $output .= '<tr><td class="'.$row_class.'"><strong style="font-size: 1.5em;">'.$ctype_d[0].'</strong></td>'; 
                // count by title
                /*$bytitle_q = $dbs->query("SELECT DISTINCT biblio_id FROM item AS i
                    WHERE i.coll_type_id=".$ctype_d[0]."");*/
                //$output .= '<td class="'.$row_class.'"><strong style="font-size: 1.3em;">'.$bytitle_q->num_rows.'</strong></td>';
 		$output .= '<td class="'.$row_class.'"><strong style="font-size: 1.3em;">'.$ctype_d[1].'</strong></td>';

                // count by item
                /*$byitem_q = $dbs->query("SELECT COUNT(item_id) FROM item AS i
                    WHERE i.coll_type_id=".$ctype_d[0]);
                $byitem_d = $byitem_q->fetch_row();*/
                //$output .= '<td class="'.$row_class.'"><strong style="font-size: 1.3em;">'.$bytitle_q->num_rows.'</strong></td>';
		$output .= '<td class="'.$row_class.'"><strong style="font-size: 1.3em;">'.$ctype_d[2].'</strong></td>';
//added and commented ended by Parth 23/8/2011
                $output .= '</tr>';
            }
            /* COLLECTION TYPE END */
            break;
        }
    } else {
//added by Start By Parth 23/8/2011
$gmd_q = $dbs->query("SELECT DISTINCT gmd_id, gmd_name FROM mst_gmd group by gmd_name");
//$gmd_q = $dbs->query("select  m.gmd_name ,count( distinct b.biblio_id) , count(i.item_id) from mst_gmd m left join biblio b on b.gmd_id = m.gmd_id left join item i  on i.biblio_id=b.biblio_id group by m.gmd_name");
//added by Ended By Parth 23/8/2011
            while ($gmd_d = $gmd_q->fetch_row()) {
                $row_class = ($row_class == 'alterCellPrinted')?'alterCellPrinted2':'alterCellPrinted';
                $output .= '<tr><td class="'.$row_class.'"><strong style="font-size: 1.5em;">'.$gmd_d[1].'</strong></td>';
                // count by title
                $bytitle_q = $dbs->query("SELECT COUNT(biblio_id) FROM biblio WHERE gmd_id=".$gmd_d[0]);
                $bytitle_d = $bytitle_q->fetch_row();
                $output .= '<td class="'.$row_class.'"><strong style="font-size: 1.3em;">'.$bytitle_d[0].'</strong></td>';

                // count by item
                $byitem_q = $dbs->query("SELECT COUNT(item_id) FROM item AS i INNER JOIN biblio AS b
                    ON i.biblio_id=b.biblio_id
                    WHERE b.gmd_id=".$gmd_d[0]);
                $byitem_d = $byitem_q->fetch_row();
                $output .= '<td class="'.$row_class.'"><strong style="font-size: 1.3em;">'.$byitem_d[0].'</strong></td>';

                $output .= '</tr>';
            }
        // recap by classification
        /* DECIMAL CLASSES */
       /* $class_num = 0;
        while ($class_num < 10) {
            $class_num2 = 0;
            $row_class = ($class_num%2 == 0)?'alterCellPrinted':'alterCellPrinted2';
            $output .= '<tr><td class="'.$row_class.'"><strong style="font-size: 1.5em;">'.$class_num.'00</strong></td>';

            // count by title
            $bytitle_q = $dbs->query("SELECT COUNT(biblio_id) FROM biblio WHERE TRIM(classification) LIKE '$class_num%'");
            $bytitle_d = $bytitle_q->fetch_row();
            $output .= '<td class="'.$row_class.'"><strong style="font-size: 1.5em;">'.$bytitle_d[0].'</strong></td>';

            // count by item
            $byitem_q = $dbs->query("SELECT COUNT(item_id) FROM item AS i LEFT JOIN biblio AS b
                ON i.biblio_id=b.biblio_id
                WHERE TRIM(b.classification) LIKE '$class_num%'");
            $byitem_d = $byitem_q->fetch_row();
            $output .= '<td class="'.$row_class.'"><strong style="font-size: 1.5em;">'.$byitem_d[0].'</strong></td>';

            $output .= '</tr>';

            // 2nd subclasses
            while ($class_num2 < 10) {
                $row_class = ($row_class == 'alterCellPrinted')?'alterCellPrinted2':'alterCellPrinted';

                $output .= '<tr><td class="'.$row_class.'"><strong>&nbsp;&nbsp;&nbsp;'.$class_num.$class_num2.'0</strong></td>';
                // count by title
                $bytitle_q = $dbs->query("SELECT COUNT(biblio_id) FROM biblio WHERE TRIM(classification) LIKE '".$class_num.$class_num2."%'");
                $bytitle_d = $bytitle_q->fetch_row();
                $output .= '<td class="'.$row_class.'">'.$bytitle_d[0].'</td>';

                // count by item
                $byitem_q = $dbs->query("SELECT COUNT(item_id) FROM item AS i LEFT JOIN biblio AS b
                    ON i.biblio_id=b.biblio_id
                    WHERE TRIM(b.classification) LIKE '".$class_num.$class_num2."%'");
                $byitem_d = $byitem_q->fetch_row();
                $output .= '<td class="'.$row_class.'">'.$byitem_d[0].'</td>';

                $output .= '</tr>';
                $class_num2++;
            }

            $class_num++;
        }*/

        /* 2X NUMBER CLASSES */
      /*  $row_class = ($row_class == 'alterCellPrinted')?'alterCellPrinted2':'alterCellPrinted';
        $output .= '<tr><td class="'.$row_class.'"><strong style="font-size: 1.5em;">2X</strong> classes</td>';
        // count by title
        $bytitle_q = $dbs->query("SELECT COUNT(biblio_id) FROM biblio WHERE TRIM(classification) LIKE '2X%'");
        $bytitle_d = $bytitle_q->fetch_row();
        $output .= '<td class="'.$row_class.'"><strong style="font-size: 1.5em;">'.$bytitle_d[0].'</strong></td>';

        // count by item
        $byitem_q = $dbs->query("SELECT COUNT(item_id) FROM item AS i INNER JOIN biblio AS b
            ON i.biblio_id=b.biblio_id
            WHERE TRIM(b.classification) LIKE '2X%'");
        $byitem_d = $byitem_q->fetch_row();
        $output .= '<td class="'.$row_class.'"><strong style="font-size: 1.5em;">'.$byitem_d[0].'</strong></td>';

        $output .= '</tr>';*/
        /* 2X NUMBER CLASSES END */


        /* NON-DECIMAL NUMBER CLASSES */
        // get non-decimal class
        /*$_non_decimal_q = $dbs->query("SELECT DISTINCT classification FROM biblio WHERE classification REGEXP '^[^0-9]'");
        if ($_non_decimal_q->num_rows > 0) {
            while ($_non_decimal = $_non_decimal_q->fetch_row()) {
                $row_class = ($row_class == 'alterCellPrinted')?'alterCellPrinted2':'alterCellPrinted';
                $output .= '<tr><td class="'.$row_class.'"><strong style="font-size: 1.5em;">'.$_non_decimal[0].'</strong> classes</td>';
                // count by title
                $bytitle_q = $dbs->query("SELECT COUNT(biblio_id) FROM biblio WHERE classification LIKE '".$_non_decimal[0]."'");
                $bytitle_d = $bytitle_q->fetch_row();
                $output .= '<td class="'.$row_class.'"><strong style="font-size: 1.5em;">'.$bytitle_d[0].'</strong></td>';

                // count by item
                $byitem_q = $dbs->query("SELECT COUNT(item_id) FROM item AS i INNER JOIN biblio AS b
                    ON i.biblio_id=b.biblio_id
                    WHERE classification LIKE '".$_non_decimal[0]."'");
                $byitem_d = $byitem_q->fetch_row();
                $output .= '<td class="'.$row_class.'"><strong style="font-size: 1.5em;">'.$byitem_d[0].'</strong></td>';

                $output .= '</tr>';
            }
        }*/
        /* NON-DECIMAL NUMBER CLASSES END */
    }
    $output .= '</table>';

    // print out
    echo '<div class="printPageInfo">'.gettext('Title and Collection Recap by').' <strong>'.$recapby.'</strong> <a class="printReport" onclick="window.print()" href="#">['.gettext('Print Current Page').']</a></div>'."\n";
    echo $output;

    $content = ob_get_clean();
    // include the page template
    require SENAYAN_BASE_DIR.'/admin/admin_template/printed_page_tpl.php';
}
?>

