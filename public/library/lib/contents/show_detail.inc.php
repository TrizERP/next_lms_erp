<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com), Hendro Wicaksono (hendrowicaksono@yahoo.com)
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

/* Showing record detail in HTML and XML */

if (isset($_GET['inXML']) AND !empty($_GET['inXML'])) {
    if (!$sysconf['enable_xml_detail']) {
        die('XML Detail is disabled');
    }
    // filter the ID
    $detail_id = intval($_GET['id']);
    // include detail library and template
    include LIB_DIR.'detail.inc.php';
    // create detail object
    $detail = new detail($dbs, $detail_id, 'mods');
    $output = $detail->showDetail();
    // send http header
    header('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="UTF-8" ?>'."\n";
    echo $detail->getPrefix();
    echo $output;
    echo $detail->getSuffix();
    exit();
} else {
    // filter the ID
    $detail_id = intval($_GET['id']);
    // include detail library and template
    include LIB_DIR.'detail.inc.php';
    include $sysconf['template']['dir'].'/'.$sysconf['template']['theme'].'/detail_template.php';
    // create detail object
    $detail = new detail($dbs, $detail_id);
    $detail->setListTemplate($detail_template);
    // set the content for info box
    $info = '<strong>'.strtoupper(__('Record Detail')).'</strong><hr />';
 
    if (!defined('LIGHTWEIGHT_MODE')) {
        $info .= '<a href="javascript: history.back();">'.__('Back To Previous').'</a> &nbsp;';
    }
    if (isset($sysconf['enable_xml_detail']) && $sysconf['enable_xml_detail'] && !defined('LIGHTWEIGHT_MODE')) {
        $info .= '<a href="index.php?p=show_detail&inXML=true&id='.$detail_id.'" class="xmlDetailLink" target="_blank">XML Detail</a>';
    }
    if (!defined('LIGHTWEIGHT_MODE')) {
        // include Prototype javascript library
        echo '<script type="text/javascript" src="'.JS_WEB_ROOT_DIR.'prototype.js"></script>';
    }
    // output the record detail
    echo $detail->showDetail();
    $page_title = $detail->record_title;
    $metadata = $detail->metadata;
    echo '<br />'."\n";
    //added by parth 3/8/2011 for rescentreview
  // $sql_op_view = new simbio_dbop($dbs);
   $count = 0;
    $flag_count = 0;
    $query_view_update = "select count from biblio_view where biblio_id = '".$detail_id."' AND member_id = '".$_SESSION['DUSER_ID']."'";
    $qery_view_update = $dbs->query($query_view_update);
while($row_view_update = $qery_view_update->fetch_assoc())
	{
         $flag_count = 1;
	 $count = $row_view_update['count'];
	//$member_name = $row_update['member_name'];
	}
	//$count = $count+1;
      if($flag_count==1)
		{
		         require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';	
			$sql_op_view = new simbio_dbop($dbs);
			$count = $count + 1;
                        $data2['count'] = $count;
			$data2['last_update'] = date('Y-m-d H:i:s');
			$update1 = $sql_op_view->update('biblio_view', $data2, "biblio_id='".$detail_id."' AND member_id='".$_SESSION['DUSER_ID']."'");
		}
	else
		{
                        require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php'; 
			$sql_op_view = new simbio_dbop($dbs);
			$count = 1;
			$data['member_id'] = $_SESSION['DUSER_ID'];
			$data['biblio_id'] = $detail_id;
                        $data['count'] = $count;
                        $data['last_update'] = date('Y-m-d H:i:s');
			$insert = $sql_op_view->insert('biblio_view', $data);	
		}
	
$flag_count=0;
   //ended by parth 3/8/2011 for rescentreview	
}

?>
