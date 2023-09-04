<?php
/**
 *
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/* Reserve List */

// main system configuration
require '../../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
// privileges checking
$can_read = utility::havePrivilege('circulation', 'r');
$can_write = utility::havePrivilege('circulation', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MODULES_BASE_DIR.'reporting/report_dbgrid.inc.php';

$page_title = 'Reservation List Report';
$reportView = false;
if (isset($_GET['reportView'])) {
    $reportView = true;
}

if (!$reportView) {
?>
    <!-- filter -->
    <fieldset style="margin-bottom: 3px;">
    <legend style="font-weight: bold"><?php echo strtoupper(gettext('Reservation')); ?> - <?php echo gettext('Report Filter'); ?></legend>
    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
    <div id="filterForm">
        <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Member ID').'/'.gettext('Member Name'); ?></div>
            <div class="divRowContent">
            <?php    
            //comment by iresh on 25-1-2011 echo simbio_form_element::textField('text', 'member', '', 'style="width: 50%"');
             /*added by iresh on 25-1-2011 */echo simbio_form_element::textField('text', 'member', '', 'style="width: 140px"');
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Title/ISBN'); ?></div>
            <div class="divRowContent">
            <?php 
            //comment by iresh on 25-1-2011 echo simbio_form_element::textField('text', 'title', '', 'style="width: 50%"');
             /*added by iresh on 25-1-2011 */echo simbio_form_element::textField('text', 'title', '', 'style="width: 140px"');
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Item Code'); ?></div>
            <div class="divRowContent">
            <?php 
              //comment by iresh on 25-1-2011echo simbio_form_element::textField('text', 'itemCode', '', 'style="width: 50%"');
	     /*added by iresh on 25-1-2011 */ echo simbio_form_element::textField('text', 'itemCode', '', 'style="width: 140px"');
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Reserve Date From'); ?></div>
            <div class="divRowContent">
            <?php
            echo simbio_form_element::dateField('startDate', '2000-01-01');
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Reserve Date Until'); ?></div>
            <div class="divRowContent">
            <?php
            echo simbio_form_element::dateField('untilDate', date('Y-m-d'));
            ?>
            </div>
        </div>
    </div>
    <div style="padding-top: 10px; clear: both;">
    <input type="submit" name="applyFilter" value="<?php echo gettext('Apply Filter'); ?>" />
    <input type="button" name="moreFilter" value="<?php echo gettext('Show More Filter Options'); ?>" onclick="showHideTableRows('filterForm', 1, this, '<?php echo gettext('Show More Filter Options'); ?>', '<?php echo gettext('Hide Filter Options'); ?>')" />
    <input type="hidden" name="reportView" value="true" />
    </div>
    </form>
    </fieldset>
    <script type="text/javascript">hideRows('filterForm', 1);</script>
    <!-- filter end --><!--
    <div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>-->
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; min-height:200px;"></iframe>
<?php
} else {
    ob_start();
    // table spec
    $table_spec = 'reserve AS r
        LEFT JOIN biblio AS b ON r.biblio_id=b.biblio_id
        LEFT JOIN member AS m ON r.member_id=m.member_id';

    // create datagrid
    $reportgrid = new report_datagrid();
    $reportgrid->setSQLColumn('r.item_code AS \''.gettext('Item Code').'\'',
        'b.title AS \''.gettext('Title').'\'',
        'm.member_name AS \''.gettext('Member Name').'\'',
        'r.reserve_date AS \''.gettext('Reserve Date').'\'');
    $reportgrid->setSQLorder('r.reserve_date DESC');

    // is there any search
    $criteria = 'r.reserve_id IS NOT NULL ';
    if (isset($_GET['title']) AND !empty($_GET['title'])) {
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
            $criteria .= ' AND (b.title LIKE \'%'.$keyword.'%\')';
        }
    }
    if (isset($_GET['itemCode']) AND !empty($_GET['itemCode'])) {
        $item_code = $dbs->escape_string(trim($_GET['itemCode']));
        $criteria .= ' AND r.item_code LIKE \'%'.$item_code.'%\'';
    }
    if (isset($_GET['member']) AND !empty($_GET['member'])) {
        $member = $dbs->escape_string($_GET['member']);
        $criteria .= ' AND (m.member_name LIKE \'%'.$member.'%\' OR m.member_id LIKE \'%'.$member.'%\')';
    }
    if (isset($_GET['startDate']) AND isset($_GET['untilDate'])) {
        $criteria .= ' AND (TO_DAYS(r.reserve_date) BETWEEN TO_DAYS(\''.$_GET['startDate'].'\') AND
            TO_DAYS(\''.$_GET['untilDate'].'\'))';
    }

    $reportgrid->setSQLCriteria($criteria);

    // put the result into variables
    echo $reportgrid->createDataGrid($dbs, $table_spec, 20);

    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'pagingBox\').update(\''.str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set).'\');'."\n";
    echo '</script>';

    $content = ob_get_clean();
    // include the page template
    require SENAYAN_BASE_DIR.'/admin/admin_template/printed_page_tpl.php';
}
?>
